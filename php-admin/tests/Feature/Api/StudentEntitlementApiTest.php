<?php

namespace Tests\Feature\Api;

use App\Models\QuestionBankItem;
use App\Models\Student;
use App\Models\StudentEntitlement;
use App\Services\StudentPasswordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentEntitlementApiTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = Student::factory()->create();
        app(StudentPasswordService::class)->apply($this->student, 'Hoc@2026');
        $this->student->refresh();
    }

    private function seedQuestions(int $count = 40): void
    {
        for ($i = 0; $i < $count; $i++) {
            QuestionBankItem::create([
                'content' => 'Câu hỏi số '.$i,
                'answer_type' => 'mc',
                'options' => ['A', 'B', 'C', 'D'],
                'correct_index' => 0,
                'is_active' => true,
            ]);
        }
    }

    public function test_entitlements_endpoint_returns_every_feature(): void
    {
        $response = $this->actingAs($this->student, 'student')->getJson('/api/student/entitlements');

        $response->assertOk()
            ->assertJsonPath('data.features.elements.access_level', 'free')
            ->assertJsonPath('data.features.elements.is_pro', false)
            ->assertJsonPath('data.pro_banner', null);
    }

    public function test_pro_banner_reports_the_nearest_expiry(): void
    {
        StudentEntitlement::create([
            'student_id' => $this->student->id,
            'feature_key' => 'elements',
            'access_level' => 'full',
            'expires_at' => now()->addDays(3),
        ]);

        $this->actingAs($this->student, 'student')
            ->getJson('/api/student/entitlements')
            ->assertOk()
            ->assertJsonPath('data.features.elements.is_pro', true)
            ->assertJsonPath('data.features.elements.days_remaining', 3)
            ->assertJsonPath('data.pro_banner.days', 3)
            ->assertJsonPath('data.pro_banner.feature', 'elements');
    }

    public function test_guest_cannot_read_entitlements(): void
    {
        $this->getJson('/api/student/entitlements')->assertUnauthorized();
    }

    public function test_guest_practice_is_capped_at_the_free_tier(): void
    {
        $this->seedQuestions();

        $response = $this->getJson('/api/practice/questions?count=30');

        $response->assertOk();
        $this->assertCount(10, $response->json('data.questions'));
    }

    public function test_free_student_practice_is_capped_at_the_free_tier(): void
    {
        $this->seedQuestions();

        $response = $this->actingAs($this->student, 'student')
            ->getJson('/api/practice/questions?count=30');

        $response->assertOk();
        $this->assertCount(10, $response->json('data.questions'));
    }

    /** Chặn thật nằm ở server: client gửi count=30 vẫn phải bị cắt. */
    public function test_full_access_student_gets_the_full_amount(): void
    {
        $this->seedQuestions();
        StudentEntitlement::create([
            'student_id' => $this->student->id,
            'feature_key' => 'quiz',
            'access_level' => 'full',
        ]);

        $response = $this->actingAs($this->student, 'student')
            ->getJson('/api/practice/questions?count=30');

        $response->assertOk();
        $this->assertCount(30, $response->json('data.questions'));
    }

    public function test_scope_override_raises_the_cap_without_full_access(): void
    {
        $this->seedQuestions();
        StudentEntitlement::create([
            'student_id' => $this->student->id,
            'feature_key' => 'quiz',
            'access_level' => 'free',
            'scope' => ['max_questions' => 20],
        ]);

        $response = $this->actingAs($this->student, 'student')
            ->getJson('/api/practice/questions?count=30');

        $this->assertCount(20, $response->json('data.questions'));
    }

    public function test_blocked_student_cannot_load_practice_questions(): void
    {
        $this->seedQuestions();
        StudentEntitlement::create([
            'student_id' => $this->student->id,
            'feature_key' => 'quiz',
            'access_level' => 'none',
        ]);

        $this->actingAs($this->student, 'student')
            ->getJson('/api/practice/questions?count=10')
            ->assertForbidden();
    }

    public function test_expired_grant_returns_the_student_to_the_free_cap(): void
    {
        $this->seedQuestions();
        StudentEntitlement::create([
            'student_id' => $this->student->id,
            'feature_key' => 'quiz',
            'access_level' => 'full',
            'expires_at' => now()->subMinute(),
        ]);

        $response = $this->actingAs($this->student, 'student')
            ->getJson('/api/practice/questions?count=30');

        $this->assertCount(10, $response->json('data.questions'));
    }
}
