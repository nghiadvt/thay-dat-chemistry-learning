<?php

namespace Tests\Feature\Admin;

use App\Models\Game;
use App\Models\GameResult;
use App\Models\GameSession;
use App\Models\Keyboard;
use App\Models\PracticeAttempt;
use App\Models\PracticeAttemptAnswer;
use App\Models\Question;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\SessionAnswer;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentReportTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Student $student;

    private Quiz $quiz;

    private GameSession $session;

    /** @var \Illuminate\Support\Collection<int, Question> */
    private $questions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->teacher = User::factory()->teacher()->create();
        $this->student = Student::factory()->create(['teacher_id' => $this->teacher->id]);

        $game = Game::create(['name' => 'Đua vịt', 'created_by' => $this->teacher->id]);
        $this->quiz = Quiz::create([
            'game_id' => $game->id,
            'keyboard_id' => Keyboard::factory()->create()->id,
            'name' => 'Ôn chương 1',
        ]);

        $this->questions = collect(range(0, 4))->map(fn ($i) => Question::create([
            'quiz_id' => $this->quiz->id,
            'content' => 'Câu hỏi '.$i,
            'answer_type' => 'mc',
            'options' => ['A', 'B', 'C', 'D'],
            'correct_index' => 1,
            'sort_order' => $i,
        ]));

        $this->session = GameSession::create([
            'pin' => '111222',
            'host_id' => $this->teacher->id,
            'game_id' => $game->id,
            'quiz_id' => $this->quiz->id,
            'status' => 'ended',
            'started_at' => now()->subMinutes(10),
            'ended_at' => now()->subMinutes(5),
        ]);
    }

    /** 3 đúng, 1 sai, 1 chưa làm. */
    private function seedRoomPlay(): GameResult
    {
        foreach ([0, 1, 2] as $i) {
            SessionAnswer::create([
                'session_id' => $this->session->id,
                'student_id' => $this->student->id,
                'question_id' => $this->questions[$i]->id,
                'student_name' => $this->student->display_name,
                'answer_submitted' => ['index' => 1],
                'is_correct' => true,
                'score_earned' => 100,
                'answered_at' => now()->subMinutes(8),
            ]);
        }

        SessionAnswer::create([
            'session_id' => $this->session->id,
            'student_id' => $this->student->id,
            'question_id' => $this->questions[3]->id,
            'student_name' => $this->student->display_name,
            'answer_submitted' => ['index' => 3],
            'is_correct' => false,
            'score_earned' => 0,
            'answered_at' => now()->subMinutes(7),
        ]);

        return GameResult::create([
            'session_id' => $this->session->id,
            'student_id' => $this->student->id,
            'student_name' => $this->student->display_name,
            'score' => 300,
            'rank' => 2,
            'finished_at' => now()->subMinutes(5),
        ]);
    }

    public function test_records_list_reports_correct_counts_and_duration(): void
    {
        $this->seedRoomPlay();

        $response = $this->actingAs($this->teacher)
            ->getJson('/admin/students/'.$this->student->id.'/reports');

        $response->assertOk()
            ->assertJsonPath('data.student.display_name', $this->student->display_name)
            ->assertJsonPath('data.records.0.label', 'Ôn chương 1')
            ->assertJsonPath('data.records.0.correct', 3)
            ->assertJsonPath('data.records.0.total', 5)
            ->assertJsonPath('data.records.0.type', 'room')
            ->assertJsonPath('data.records.0.index', 1);

        // 10 phút trước -> 5 phút trước = 5 phút
        $this->assertSame('5 phút 0 giây', $response->json('data.records.0.duration'));
    }

    public function test_grade_band_reflects_the_accuracy(): void
    {
        $this->seedRoomPlay(); // 3/5 = 60% -> dưới khá

        $this->actingAs($this->teacher)
            ->getJson('/admin/students/'.$this->student->id.'/reports')
            ->assertJsonPath('data.records.0.percent', 60)
            ->assertJsonPath('data.records.0.grade', 'duoi-kha');
    }

    public function test_high_accuracy_is_graded_as_good(): void
    {
        // 5/5 đúng
        foreach ($this->questions as $question) {
            SessionAnswer::create([
                'session_id' => $this->session->id,
                'student_id' => $this->student->id,
                'question_id' => $question->id,
                'student_name' => $this->student->display_name,
                'answer_submitted' => ['index' => 1],
                'is_correct' => true,
                'score_earned' => 100,
                'answered_at' => now(),
            ]);
        }
        GameResult::create([
            'session_id' => $this->session->id,
            'student_id' => $this->student->id,
            'student_name' => $this->student->display_name,
            'score' => 500,
            'rank' => 1,
        ]);

        $this->actingAs($this->teacher)
            ->getJson('/admin/students/'.$this->student->id.'/reports')
            ->assertJsonPath('data.records.0.percent', 100)
            ->assertJsonPath('data.records.0.grade', 'gioi');
    }

    public function test_records_exclude_other_students_plays(): void
    {
        $this->seedRoomPlay();

        $other = Student::factory()->create(['teacher_id' => $this->teacher->id]);
        GameResult::create([
            'session_id' => $this->session->id,
            'student_id' => $other->id,
            'student_name' => $other->display_name,
            'score' => 999,
            'rank' => 1,
        ]);

        $this->actingAs($this->teacher)
            ->getJson('/admin/students/'.$this->student->id.'/reports')
            ->assertJsonCount(1, 'data.records');
    }

    public function test_anonymous_plays_are_not_attributed_to_any_student(): void
    {
        GameResult::create([
            'session_id' => $this->session->id,
            'student_name' => 'Khách vãng lai',
            'score' => 100,
            'rank' => 1,
        ]);

        $this->actingAs($this->teacher)
            ->getJson('/admin/students/'.$this->student->id.'/reports')
            ->assertJsonCount(0, 'data.records');
    }

    public function test_solo_attempts_appear_in_the_same_list(): void
    {
        $this->seedRoomPlay();

        PracticeAttempt::create([
            'student_id' => $this->student->id,
            'feature_key' => 'quiz',
            'label' => 'Tự luyện chương 2',
            'total_questions' => 10,
            'correct_count' => 9,
            'score' => 9,
            'duration_ms' => 90000,
            'finished_at' => now(),
        ]);

        $response = $this->actingAs($this->teacher)
            ->getJson('/admin/students/'.$this->student->id.'/reports');

        $response->assertJsonCount(2, 'data.records');
        // Mới nhất đứng đầu và được đánh số 1.
        $response->assertJsonPath('data.records.0.type', 'solo')
            ->assertJsonPath('data.records.0.index', 1)
            ->assertJsonPath('data.records.0.grade', 'gioi')
            ->assertJsonPath('data.records.1.index', 2);
    }

    public function test_unfinished_solo_attempts_are_hidden(): void
    {
        PracticeAttempt::create([
            'student_id' => $this->student->id,
            'feature_key' => 'quiz',
            'total_questions' => 10,
            'finished_at' => null,
        ]);

        $this->actingAs($this->teacher)
            ->getJson('/admin/students/'.$this->student->id.'/reports')
            ->assertJsonCount(0, 'data.records');
    }

    public function test_room_detail_marks_each_question_correct_wrong_or_unanswered(): void
    {
        $result = $this->seedRoomPlay();

        $response = $this->actingAs($this->teacher)
            ->get('/admin/students/'.$this->student->id.'/reports/room/'.$result->id);

        $response->assertOk()
            ->assertSee('Ôn chương 1')
            ->assertSee('Câu hỏi 0')
            ->assertSee('Bản đồ câu trả lời');

        $rows = $response->viewData('rows');
        $this->assertSame(
            ['dung', 'dung', 'dung', 'sai', 'chua-lam'],
            $rows->pluck('status')->all()
        );

        $summary = $response->viewData('summary');
        $this->assertSame(3, $summary['correct']);
        $this->assertSame(5, $summary['total']);
        $this->assertSame(2, $summary['rank']);
    }

    public function test_room_detail_shows_option_letters_for_answers(): void
    {
        $result = $this->seedRoomPlay();

        $rows = $this->actingAs($this->teacher)
            ->get('/admin/students/'.$this->student->id.'/reports/room/'.$result->id)
            ->viewData('rows');

        $this->assertSame('B. B', $rows[0]['student_answer_text']);
        $this->assertSame('D. D', $rows[3]['student_answer_text']);
        $this->assertSame('—', $rows[4]['student_answer_text']);
        $this->assertSame('B. B', $rows[0]['correct_answer_text']);
    }

    public function test_solo_detail_renders_the_answer_grid(): void
    {
        $bankItem = QuestionBankItem::create([
            'content' => 'Câu ngân hàng',
            'answer_type' => 'mc',
            'options' => ['A', 'B', 'C', 'D'],
            'correct_index' => 2,
            'is_active' => true,
        ]);

        $attempt = PracticeAttempt::create([
            'student_id' => $this->student->id,
            'feature_key' => 'quiz',
            'label' => 'Tự luyện',
            'total_questions' => 2,
            'correct_count' => 1,
            'score' => 1,
            'duration_ms' => 30000,
            'finished_at' => now(),
        ]);
        PracticeAttemptAnswer::create([
            'attempt_id' => $attempt->id, 'question_bank_item_id' => $bankItem->id,
            'position' => 0, 'answer_index' => 2, 'is_correct' => true, 'answered_at' => now(),
        ]);
        PracticeAttemptAnswer::create([
            'attempt_id' => $attempt->id, 'question_bank_item_id' => $bankItem->id,
            'position' => 1, 'answer_index' => null,
        ]);

        $response = $this->actingAs($this->teacher)
            ->get('/admin/students/'.$this->student->id.'/reports/solo/'.$attempt->id);

        $response->assertOk();
        $this->assertSame(['dung', 'chua-lam'], $response->viewData('rows')->pluck('status')->all());
        $this->assertSame('C. C', $response->viewData('rows')[0]['student_answer_text']);
        $this->assertSame('30 giây', $response->viewData('summary')['duration']);
    }

    public function test_teacher_cannot_read_another_teachers_student_reports(): void
    {
        $result = $this->seedRoomPlay();
        $intruder = User::factory()->teacher()->create();

        $this->actingAs($intruder)
            ->getJson('/admin/students/'.$this->student->id.'/reports')
            ->assertForbidden();

        $this->actingAs($intruder)
            ->get('/admin/students/'.$this->student->id.'/reports/room/'.$result->id)
            ->assertForbidden();
    }

    public function test_detail_404s_when_the_record_belongs_to_another_student(): void
    {
        $other = Student::factory()->create(['teacher_id' => $this->teacher->id]);
        $foreign = GameResult::create([
            'session_id' => $this->session->id,
            'student_id' => $other->id,
            'student_name' => $other->display_name,
            'score' => 10,
            'rank' => 1,
        ]);

        $this->actingAs($this->teacher)
            ->get('/admin/students/'.$this->student->id.'/reports/room/'.$foreign->id)
            ->assertNotFound();
    }
}
