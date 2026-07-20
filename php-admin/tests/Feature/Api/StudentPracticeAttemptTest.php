<?php

namespace Tests\Feature\Api;

use App\Models\GameResult;
use App\Models\PracticeAttempt;
use App\Models\QuestionBankItem;
use App\Models\Student;
use App\Services\StudentPlayToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\TestCase;

class StudentPracticeAttemptTest extends TestCase
{
    use RefreshDatabase;

    private Student $student;

    /** @var array<int, int> */
    private array $questionIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->student = Student::factory()->create();

        // correct_index = 1 cho mọi câu, để test dễ suy luận.
        for ($i = 0; $i < 5; $i++) {
            $this->questionIds[] = QuestionBankItem::create([
                'content' => 'Câu '.$i,
                'answer_type' => 'mc',
                'options' => ['A', 'B', 'C', 'D'],
                'correct_index' => 1,
                'is_active' => true,
            ])->id;
        }
    }

    private function startAttempt(): int
    {
        $response = $this->actingAs($this->student, 'student')
            ->postJson('/api/student/practice-attempts', [
                'feature_key' => 'quiz',
                'label' => 'Ôn tập chương 1',
                'question_ids' => $this->questionIds,
            ]);

        $response->assertCreated();

        return $response->json('data.attempt_id');
    }

    public function test_starting_an_attempt_records_one_row_per_question_as_unanswered(): void
    {
        $attemptId = $this->startAttempt();
        $attempt = PracticeAttempt::with('answers')->findOrFail($attemptId);

        $this->assertSame($this->student->id, $attempt->student_id);
        $this->assertSame(5, $attempt->total_questions);
        $this->assertCount(5, $attempt->answers);
        $this->assertSame([0, 1, 2, 3, 4], $attempt->answers->pluck('position')->all());

        foreach ($attempt->answers as $answer) {
            $this->assertNull($answer->answer_index);
            $this->assertSame('chua-lam', $answer->status());
        }
    }

    public function test_server_grades_the_attempt_itself(): void
    {
        $attemptId = $this->startAttempt();

        $response = $this->actingAs($this->student, 'student')
            ->postJson('/api/student/practice-attempts/'.$attemptId.'/finish', [
                'duration_ms' => 45000,
                'answers' => [
                    ['position' => 0, 'answer_index' => 1], // đúng
                    ['position' => 1, 'answer_index' => 1], // đúng
                    ['position' => 2, 'answer_index' => 3], // sai
                    ['position' => 3, 'answer_index' => null], // chưa làm
                    // vị trí 4 không gửi -> chưa làm
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.correct_count', 2)
            ->assertJsonPath('data.total_questions', 5);

        $attempt = PracticeAttempt::with('answers')->findOrFail($attemptId);
        $statuses = $attempt->answers->map(fn ($a) => $a->status())->all();

        $this->assertSame(['dung', 'dung', 'sai', 'chua-lam', 'chua-lam'], $statuses);
        $this->assertSame(45000, $attempt->duration_ms);
        $this->assertNotNull($attempt->finished_at);
    }

    /** Client gửi kèm is_correct/score cũng không được tin. */
    public function test_client_supplied_correctness_is_ignored(): void
    {
        $attemptId = $this->startAttempt();

        $this->actingAs($this->student, 'student')
            ->postJson('/api/student/practice-attempts/'.$attemptId.'/finish', [
                'answers' => [
                    ['position' => 0, 'answer_index' => 3, 'is_correct' => true],
                    ['position' => 1, 'answer_index' => 2, 'is_correct' => true],
                ],
                'score' => 999,
            ])
            ->assertOk()
            ->assertJsonPath('data.correct_count', 0);

        $this->assertSame(0, PracticeAttempt::findOrFail($attemptId)->score);
    }

    public function test_accuracy_and_grade_bands(): void
    {
        $attempt = PracticeAttempt::create([
            'student_id' => $this->student->id,
            'feature_key' => 'quiz',
            'total_questions' => 10,
            'correct_count' => 9,
        ]);
        $this->assertSame(90, $attempt->accuracyPercent());
        $this->assertSame('gioi', $attempt->grade());

        $attempt->correct_count = 7;
        $this->assertSame('kha', $attempt->grade());

        $attempt->correct_count = 3;
        $this->assertSame('duoi-kha', $attempt->grade());
    }

    public function test_cannot_finish_someone_elses_attempt(): void
    {
        $attemptId = $this->startAttempt();
        $intruder = Student::factory()->create();

        $this->actingAs($intruder, 'student')
            ->postJson('/api/student/practice-attempts/'.$attemptId.'/finish', ['answers' => []])
            ->assertForbidden();

        $this->assertNull(PracticeAttempt::findOrFail($attemptId)->finished_at);
    }

    public function test_cannot_finish_the_same_attempt_twice(): void
    {
        $attemptId = $this->startAttempt();
        $payload = ['answers' => [['position' => 0, 'answer_index' => 1]]];

        $this->actingAs($this->student, 'student')
            ->postJson('/api/student/practice-attempts/'.$attemptId.'/finish', $payload)
            ->assertOk();

        $this->actingAs($this->student, 'student')
            ->postJson('/api/student/practice-attempts/'.$attemptId.'/finish', $payload)
            ->assertStatus(409);
    }

    public function test_unknown_question_ids_are_dropped(): void
    {
        $response = $this->actingAs($this->student, 'student')
            ->postJson('/api/student/practice-attempts', [
                'feature_key' => 'quiz',
                'question_ids' => [$this->questionIds[0], 999999],
            ]);

        $response->assertCreated()->assertJsonPath('data.total_questions', 1);
    }

    public function test_attempt_with_only_unknown_questions_is_rejected(): void
    {
        $this->actingAs($this->student, 'student')
            ->postJson('/api/student/practice-attempts', [
                'feature_key' => 'quiz',
                'question_ids' => [999998, 999999],
            ])
            ->assertStatus(422);
    }

    public function test_guest_cannot_create_an_attempt(): void
    {
        $this->postJson('/api/student/practice-attempts', [
            'feature_key' => 'quiz',
            'question_ids' => $this->questionIds,
        ])->assertUnauthorized();
    }

    public function test_game_results_can_be_linked_to_a_student_and_stay_optional(): void
    {
        $session = \App\Models\GameSession::create([
            'pin' => '123456',
            'host_id' => \App\Models\User::factory()->teacher()->create()->id,
            'game_id' => \App\Models\Game::create([
                'name' => 'Đua vịt',
                'created_by' => \App\Models\User::factory()->teacher()->create()->id,
            ])->id,
            'status' => 'ended',
        ]);

        $linked = GameResult::create([
            'session_id' => $session->id,
            'student_id' => $this->student->id,
            'student_name' => $this->student->display_name,
            'score' => 100,
            'rank' => 1,
        ]);
        $anonymous = GameResult::create([
            'session_id' => $session->id,
            'student_name' => 'Khách vãng lai',
            'score' => 50,
            'rank' => 2,
        ]);

        $this->assertTrue($linked->student->is($this->student));
        $this->assertNull($anonymous->student_id);
        $this->assertNull($anonymous->student);
    }

    public function test_play_token_is_written_to_the_shared_redis_key(): void
    {
        $connection = Mockery::mock();
        $connection->shouldReceive('setex')
            ->once()
            ->withArgs(function ($key, $ttl, $value) {
                return str_starts_with($key, StudentPlayToken::KEY_PREFIX)
                    && $ttl === StudentPlayToken::TTL_SECONDS
                    && $value === (string) $this->student->id;
            });

        Redis::shouldReceive('connection')->with('rooms')->andReturn($connection);

        $response = $this->actingAs($this->student, 'student')
            ->postJson('/api/student/play-token');

        $response->assertOk()
            ->assertJsonPath('data.expires_in', StudentPlayToken::TTL_SECONDS);
        $this->assertNotEmpty($response->json('data.play_token'));
    }

    public function test_guest_cannot_request_a_play_token(): void
    {
        $this->postJson('/api/student/play-token')->assertUnauthorized();
    }
}
