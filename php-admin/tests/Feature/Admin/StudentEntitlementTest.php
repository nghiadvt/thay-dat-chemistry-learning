<?php

namespace Tests\Feature\Admin;

use App\Models\Student;
use App\Models\StudentClass;
use App\Models\StudentEntitlement;
use App\Models\User;
use App\Services\EntitlementResolver;
use App\Support\FeatureRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentEntitlementTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private StudentClass $class;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->teacher()->create();
        $this->class = StudentClass::factory()->create(['teacher_id' => $this->teacher->id]);
        $this->student = Student::factory()->create([
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->class->id,
        ]);
    }

    public function test_entitlements_page_lists_every_registered_feature(): void
    {
        $response = $this->actingAs($this->teacher)
            ->get('/admin/students/'.$this->student->id.'/entitlements');

        $response->assertOk();
        foreach (FeatureRegistry::all() as $feature) {
            $response->assertSee($feature['name']);
        }
    }

    public function test_teacher_can_grant_permanent_full_access(): void
    {
        $this->actingAs($this->teacher)
            ->post('/admin/students/'.$this->student->id.'/entitlements', [
                'feature_key' => 'elements',
                'access_level' => 'full',
                'duration' => 'permanent',
            ])
            ->assertRedirect();

        $grant = StudentEntitlement::query()->firstOrFail();

        $this->assertSame($this->student->id, $grant->student_id);
        $this->assertSame('full', $grant->access_level);
        $this->assertNull($grant->expires_at);
        $this->assertSame($this->teacher->id, $grant->granted_by);
    }

    /** Kịch bản của đề bài: cấp full "Đọc nguyên tố" cho 1 học sinh trong 3 ngày. */
    public function test_teacher_can_grant_full_access_for_three_days(): void
    {
        $this->actingAs($this->teacher)
            ->post('/admin/students/'.$this->student->id.'/entitlements', [
                'feature_key' => 'elements',
                'access_level' => 'full',
                'duration' => 'days',
                'days' => 3,
            ])
            ->assertRedirect();

        $resolved = app(EntitlementResolver::class)->for($this->student->refresh(), 'elements');

        $this->assertSame('full', $resolved['access_level']);
        $this->assertSame(3, $resolved['days_remaining']);
    }

    public function test_teacher_can_grant_to_the_whole_class_in_one_action(): void
    {
        $classmate = Student::factory()->create([
            'teacher_id' => $this->teacher->id,
            'class_id' => $this->class->id,
        ]);

        $this->actingAs($this->teacher)
            ->post('/admin/students/classes/'.$this->class->id.'/entitlements', [
                'feature_key' => 'balance',
                'access_level' => 'full',
                'duration' => 'permanent',
            ])
            ->assertRedirect();

        $resolver = app(EntitlementResolver::class);

        $this->assertSame('full', $resolver->for($this->student, 'balance')['access_level']);
        $this->assertSame('full', $resolver->for($classmate, 'balance')['access_level']);
        $this->assertSame(1, StudentEntitlement::count(), 'Cấp cho lớp chỉ nên tạo 1 dòng.');
    }

    public function test_scope_override_is_saved_and_applied(): void
    {
        $this->actingAs($this->teacher)
            ->post('/admin/students/'.$this->student->id.'/entitlements', [
                'feature_key' => 'elements',
                'access_level' => 'free',
                'duration' => 'permanent',
                'scope' => ['unlocked' => '25'],
            ])
            ->assertRedirect();

        $resolved = app(EntitlementResolver::class)->for($this->student, 'elements');

        $this->assertSame(25, $resolved['scope']['unlocked']);
    }

    public function test_blank_scope_fields_do_not_override_the_defaults(): void
    {
        $this->actingAs($this->teacher)
            ->post('/admin/students/'.$this->student->id.'/entitlements', [
                'feature_key' => 'elements',
                'access_level' => 'full',
                'duration' => 'permanent',
                'scope' => ['unlocked' => ''],
            ])
            ->assertRedirect();

        $this->assertNull(StudentEntitlement::query()->firstOrFail()->scope);
    }

    public function test_revoking_a_grant_drops_the_student_back_to_free(): void
    {
        $grant = StudentEntitlement::create([
            'student_id' => $this->student->id,
            'feature_key' => 'elements',
            'access_level' => 'full',
        ]);

        $this->actingAs($this->teacher)
            ->post('/admin/student-entitlements/'.$grant->id.'/revoke')
            ->assertRedirect();

        $this->assertNotNull($grant->refresh()->revoked_at);
        $this->assertSame(
            'free',
            app(EntitlementResolver::class)->for($this->student, 'elements')['access_level']
        );
    }

    public function test_unknown_feature_key_is_rejected(): void
    {
        $this->actingAs($this->teacher)
            ->post('/admin/students/'.$this->student->id.'/entitlements', [
                'feature_key' => 'game-khong-ton-tai',
                'access_level' => 'full',
                'duration' => 'permanent',
            ])
            ->assertSessionHasErrors('feature_key');
    }

    public function test_days_is_required_when_duration_is_days(): void
    {
        $this->actingAs($this->teacher)
            ->post('/admin/students/'.$this->student->id.'/entitlements', [
                'feature_key' => 'elements',
                'access_level' => 'full',
                'duration' => 'days',
            ])
            ->assertSessionHasErrors('days');
    }

    public function test_teacher_cannot_grant_to_another_teachers_student(): void
    {
        $intruder = User::factory()->teacher()->create();

        $this->actingAs($intruder)
            ->post('/admin/students/'.$this->student->id.'/entitlements', [
                'feature_key' => 'elements',
                'access_level' => 'full',
                'duration' => 'permanent',
            ])
            ->assertForbidden();

        $this->assertSame(0, StudentEntitlement::count());
    }

    public function test_teacher_cannot_revoke_another_teachers_grant(): void
    {
        $intruder = User::factory()->teacher()->create();
        $grant = StudentEntitlement::create([
            'student_id' => $this->student->id,
            'feature_key' => 'elements',
            'access_level' => 'full',
        ]);

        $this->actingAs($intruder)
            ->post('/admin/student-entitlements/'.$grant->id.'/revoke')
            ->assertForbidden();

        $this->assertNull($grant->refresh()->revoked_at);
    }

    public function test_expire_command_revokes_only_overdue_grants(): void
    {
        $expired = StudentEntitlement::create([
            'student_id' => $this->student->id,
            'feature_key' => 'elements',
            'access_level' => 'full',
            'expires_at' => now()->subDay(),
        ]);
        $live = StudentEntitlement::create([
            'student_id' => $this->student->id,
            'feature_key' => 'balance',
            'access_level' => 'full',
            'expires_at' => now()->addDay(),
        ]);
        $permanent = StudentEntitlement::create([
            'student_id' => $this->student->id,
            'feature_key' => 'quiz',
            'access_level' => 'full',
        ]);

        $this->artisan('students:expire-entitlements')->assertSuccessful();

        $this->assertNotNull($expired->refresh()->revoked_at);
        $this->assertNull($live->refresh()->revoked_at);
        $this->assertNull($permanent->refresh()->revoked_at);
    }

    public function test_expire_command_dry_run_changes_nothing(): void
    {
        $expired = StudentEntitlement::create([
            'student_id' => $this->student->id,
            'feature_key' => 'elements',
            'access_level' => 'full',
            'expires_at' => now()->subDay(),
        ]);

        $this->artisan('students:expire-entitlements --dry-run')->assertSuccessful();

        $this->assertNull($expired->refresh()->revoked_at);
    }
}
