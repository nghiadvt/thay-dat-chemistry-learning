<?php

namespace Tests\Feature\Admin;

use App\Models\Student;
use App\Models\StudentClass;
use App\Models\StudentEntitlement;
use App\Services\EntitlementResolver;
use App\Support\FeatureRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntitlementResolverTest extends TestCase
{
    use RefreshDatabase;

    private EntitlementResolver $resolver;

    private StudentClass $class;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = app(EntitlementResolver::class);
        $this->class = StudentClass::factory()->create();
        $this->student = Student::factory()->create([
            'teacher_id' => $this->class->teacher_id,
            'class_id' => $this->class->id,
        ]);
    }

    private function grant(array $attributes = []): StudentEntitlement
    {
        return StudentEntitlement::create(array_merge([
            'student_id' => $this->student->id,
            'feature_key' => 'elements',
            'access_level' => FeatureRegistry::ACCESS_FULL,
        ], $attributes));
    }

    public function test_student_without_grants_gets_the_free_tier(): void
    {
        $result = $this->resolver->for($this->student, 'elements');

        $this->assertSame(FeatureRegistry::ACCESS_FREE, $result['access_level']);
        $this->assertSame(FeatureRegistry::freeScope('elements'), $result['scope']);
        $this->assertSame('default', $result['source']);
        $this->assertNull($result['expires_at']);
    }

    public function test_all_returns_every_registered_feature(): void
    {
        $all = $this->resolver->all($this->student);

        $this->assertSame(FeatureRegistry::keys(), array_keys($all));
    }

    public function test_full_grant_unlocks_the_full_scope(): void
    {
        $this->grant();

        $result = $this->resolver->for($this->student, 'elements');

        $this->assertSame(FeatureRegistry::ACCESS_FULL, $result['access_level']);
        $this->assertSame(FeatureRegistry::fullScope('elements'), $result['scope']);
        $this->assertSame('student', $result['source']);
        $this->assertTrue($this->resolver->hasFullAccess($this->student, 'elements'));
    }

    public function test_class_grant_applies_to_students_in_that_class(): void
    {
        StudentEntitlement::create([
            'class_id' => $this->class->id,
            'feature_key' => 'balance',
            'access_level' => FeatureRegistry::ACCESS_FULL,
        ]);

        $result = $this->resolver->for($this->student, 'balance');

        $this->assertSame(FeatureRegistry::ACCESS_FULL, $result['access_level']);
        $this->assertSame('class', $result['source']);
    }

    public function test_student_grant_overrides_class_grant(): void
    {
        StudentEntitlement::create([
            'class_id' => $this->class->id,
            'feature_key' => 'elements',
            'access_level' => FeatureRegistry::ACCESS_FULL,
        ]);
        $this->grant(['access_level' => FeatureRegistry::ACCESS_NONE]);

        $result = $this->resolver->for($this->student, 'elements');

        $this->assertSame(FeatureRegistry::ACCESS_NONE, $result['access_level']);
        $this->assertSame('student', $result['source']);
        $this->assertFalse($this->resolver->allows($this->student, 'elements'));
    }

    public function test_class_grant_does_not_leak_to_students_of_other_classes(): void
    {
        $otherClass = StudentClass::factory()->create();
        StudentEntitlement::create([
            'class_id' => $otherClass->id,
            'feature_key' => 'elements',
            'access_level' => FeatureRegistry::ACCESS_FULL,
        ]);

        $this->assertSame(
            FeatureRegistry::ACCESS_FREE,
            $this->resolver->for($this->student, 'elements')['access_level']
        );
    }

    public function test_expired_grant_falls_back_to_the_free_tier(): void
    {
        $this->grant(['expires_at' => now()->subMinute()]);

        $result = $this->resolver->for($this->student, 'elements');

        $this->assertSame(FeatureRegistry::ACCESS_FREE, $result['access_level']);
        $this->assertSame('default', $result['source']);
    }

    public function test_grant_that_has_not_started_yet_is_ignored(): void
    {
        $this->grant(['starts_at' => now()->addDay()]);

        $this->assertSame(
            FeatureRegistry::ACCESS_FREE,
            $this->resolver->for($this->student, 'elements')['access_level']
        );
    }

    public function test_revoked_grant_is_ignored(): void
    {
        $this->grant(['revoked_at' => now()]);

        $this->assertSame(
            FeatureRegistry::ACCESS_FREE,
            $this->resolver->for($this->student, 'elements')['access_level']
        );
    }

    public function test_newest_grant_wins_at_the_same_level(): void
    {
        $this->grant(['access_level' => FeatureRegistry::ACCESS_FULL, 'expires_at' => now()->addDays(3)]);
        $this->grant(['access_level' => FeatureRegistry::ACCESS_FULL, 'expires_at' => null]);

        $result = $this->resolver->for($this->student, 'elements');

        $this->assertNull($result['expires_at'], 'Grant vĩnh viễn cấp sau phải thắng grant 3 ngày.');
    }

    public function test_scope_override_merges_onto_the_level_scope(): void
    {
        $this->grant([
            'access_level' => FeatureRegistry::ACCESS_FREE,
            'scope' => ['unlocked' => 30],
        ]);

        $result = $this->resolver->for($this->student, 'elements');

        $this->assertSame(FeatureRegistry::ACCESS_FREE, $result['access_level']);
        $this->assertSame(30, $result['scope']['unlocked']);
    }

    public function test_days_remaining_is_reported_for_time_limited_grants(): void
    {
        $this->grant(['expires_at' => now()->addDays(3)]);

        $result = $this->resolver->for($this->student, 'elements');

        $this->assertSame(3, $result['days_remaining']);
        $this->assertNotNull($result['expires_at']);
    }

    public function test_permanent_grant_has_no_days_remaining(): void
    {
        $this->grant(['expires_at' => null]);

        $this->assertNull($this->resolver->for($this->student, 'elements')['days_remaining']);
    }

    public function test_soonest_expiry_picks_the_nearest_full_grant(): void
    {
        $this->grant(['feature_key' => 'elements', 'expires_at' => now()->addDays(7)]);
        $this->grant(['feature_key' => 'balance', 'expires_at' => now()->addDays(2)]);
        $this->grant(['feature_key' => 'quiz', 'expires_at' => null]);

        $soonest = $this->resolver->soonestExpiry($this->student);

        $this->assertSame('balance', $soonest['feature']);
        $this->assertSame(2, $soonest['days']);
    }

    public function test_soonest_expiry_is_null_when_nothing_is_time_limited(): void
    {
        $this->grant(['expires_at' => null]);

        $this->assertNull($this->resolver->soonestExpiry($this->student));
    }

    public function test_student_without_a_class_is_unaffected_by_class_grants(): void
    {
        $loner = Student::factory()->create(['class_id' => null]);
        StudentEntitlement::create([
            'class_id' => $this->class->id,
            'feature_key' => 'elements',
            'access_level' => FeatureRegistry::ACCESS_FULL,
        ]);

        $this->assertSame(
            FeatureRegistry::ACCESS_FREE,
            $this->resolver->for($loner, 'elements')['access_level']
        );
    }

    public function test_unknown_feature_key_resolves_to_no_access(): void
    {
        $result = $this->resolver->for($this->student, 'khong-ton-tai');

        $this->assertSame(FeatureRegistry::ACCESS_NONE, $result['access_level']);
    }
}
