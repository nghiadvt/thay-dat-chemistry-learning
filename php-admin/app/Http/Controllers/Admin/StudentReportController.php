<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\GameResult;
use App\Models\PracticeAttempt;
use App\Models\Question;
use App\Models\SessionAnswer;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Thống kê bài làm của từng học sinh cho giáo viên.
 *
 * Hai nguồn dữ liệu:
 *  - Phòng live của giáo viên: game_results + session_answers (gắn student_id từ Phase 3)
 *  - Lượt tự luyện: practice_attempts + practice_attempt_answers
 */
class StudentReportController extends Controller
{
    /** Ngưỡng xếp loại theo % câu đúng — dùng cho màu viền record. */
    private const GRADE_GOOD = 80;

    private const GRADE_FAIR = 65;

    /** Danh sách record cho modal (JSON). */
    public function index(Request $request, Student $student): JsonResponse
    {
        $this->assertOwned($request, $student);

        $records = $this->roomRecords($student)
            ->concat($this->soloRecords($student))
            ->sortByDesc('played_at')
            ->values()
            // Đánh "thứ tự" sau khi đã trộn và sắp xếp cả hai nguồn.
            ->map(function (array $record, int $index) {
                $record['index'] = $index + 1;

                return $record;
            });

        return $this->jsonSuccess([
            'student' => [
                'display_name' => $student->display_name,
                'username' => $student->username,
                'class_name' => $student->studentClass?->name,
            ],
            'records' => $records,
        ]);
    }

    /** Chi tiết một lượt chơi trong phòng live. */
    public function showRoomRecord(Request $request, Student $student, GameResult $result): View
    {
        $this->assertOwned($request, $student);
        abort_unless($result->student_id === $student->id, 404);

        $result->load('session.quiz');
        $session = $result->session;

        $questions = Question::query()
            ->where('quiz_id', $session?->quiz_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $answers = SessionAnswer::query()
            ->where('session_id', $result->session_id)
            ->where('student_id', $student->id)
            ->get()
            ->keyBy('question_id');

        $rows = $questions->map(function (Question $question, int $position) use ($answers) {
            $answer = $answers->get($question->id);

            return [
                'position' => $position + 1,
                'question' => $question,
                'answer' => $answer,
                'status' => $answer === null ? 'chua-lam' : ($answer->is_correct ? 'dung' : 'sai'),
                'student_answer_text' => $this->answerText($question, $answer?->answer_submitted),
                'correct_answer_text' => $this->correctAnswerText($question),
            ];
        });

        $correct = $rows->where('status', 'dung')->count();

        $summary = [
            'title' => $session?->quiz?->name ?? ($session?->name ?? 'Lượt chơi'),
            'played_at' => $result->finished_at ?? $result->created_at,
            'duration' => $this->formatDuration($this->roomDurationSeconds($result)),
            'score' => $result->score,
            'rank' => $result->rank,
            'correct' => $correct,
            'total' => $rows->count(),
            'percent' => $rows->count() > 0 ? (int) round($correct / $rows->count() * 100) : 0,
        ];
        $summary['grade'] = $this->gradeFromPercent($summary['percent']);

        return view('admin.students.report-detail', compact('student', 'rows', 'summary'));
    }

    /** Chi tiết một lượt tự luyện. */
    public function showSoloRecord(Request $request, Student $student, PracticeAttempt $attempt): View
    {
        $this->assertOwned($request, $student);
        abort_unless($attempt->student_id === $student->id, 404);

        $attempt->load('answers.question');

        $rows = $attempt->answers->map(fn ($answer, $index) => [
            'position' => $index + 1,
            'question' => $answer->question,
            'answer' => $answer,
            'status' => $answer->status(),
            'student_answer_text' => $this->bankAnswerText($answer->question, $answer->answer_index),
            'correct_answer_text' => $this->bankAnswerText($answer->question, $answer->question?->correct_index),
        ]);

        $summary = [
            'title' => $attempt->label ?? 'Tự luyện',
            'played_at' => $attempt->finished_at ?? $attempt->created_at,
            'duration' => $this->formatDuration((int) round($attempt->duration_ms / 1000)),
            'score' => $attempt->score,
            'rank' => null,
            'correct' => $attempt->correct_count,
            'total' => $attempt->total_questions,
            'percent' => $attempt->accuracyPercent(),
            'grade' => $attempt->grade(),
        ];

        return view('admin.students.report-detail', compact('student', 'rows', 'summary'));
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function roomRecords(Student $student)
    {
        $results = GameResult::query()
            ->where('student_id', $student->id)
            ->with(['session.quiz:id,name', 'session:id,quiz_id,name,started_at,ended_at'])
            ->get();

        // Gộp số câu đúng theo phiên bằng một truy vấn, tránh N+1.
        $stats = SessionAnswer::query()
            ->where('student_id', $student->id)
            ->selectRaw('session_id, COUNT(*) as answered, SUM(is_correct) as correct')
            ->groupBy('session_id')
            ->get()
            ->keyBy('session_id');

        $questionCounts = Question::query()
            ->whereIn('quiz_id', $results->pluck('session.quiz_id')->filter()->unique())
            ->selectRaw('quiz_id, COUNT(*) as total')
            ->groupBy('quiz_id')
            ->pluck('total', 'quiz_id');

        return $results->map(function (GameResult $result) use ($stats, $student, $questionCounts) {
            $stat = $stats->get($result->session_id);
            $quizId = $result->session?->quiz_id;
            $total = (int) ($questionCounts[$quizId] ?? ($stat->answered ?? 0));
            $correct = (int) ($stat->correct ?? 0);
            $percent = $total > 0 ? (int) round($correct / $total * 100) : 0;

            return [
                'type' => 'room',
                'label' => $result->session?->quiz?->name ?? ($result->session?->name ?? 'Phòng chơi'),
                'correct' => $correct,
                'total' => $total,
                'percent' => $percent,
                'grade' => $this->gradeFromPercent($percent),
                'score' => $result->score,
                'duration' => $this->formatDuration($this->roomDurationSeconds($result)),
                'played_at' => $result->finished_at ?? $result->created_at,
                'played_at_text' => ($result->finished_at ?? $result->created_at)?->format('d/m/Y H:i'),
                'detail_url' => route('admin.students.reports.room', [$student, $result]),
            ];
        });
    }

    /**
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function soloRecords(Student $student)
    {
        return PracticeAttempt::query()
            ->where('student_id', $student->id)
            ->whereNotNull('finished_at')
            ->get()
            ->map(fn (PracticeAttempt $attempt) => [
                'type' => 'solo',
                'label' => $attempt->label ?? 'Tự luyện',
                'correct' => $attempt->correct_count,
                'total' => $attempt->total_questions,
                'percent' => $attempt->accuracyPercent(),
                'grade' => $attempt->grade(),
                'score' => $attempt->score,
                'duration' => $this->formatDuration((int) round($attempt->duration_ms / 1000)),
                'played_at' => $attempt->finished_at,
                'played_at_text' => $attempt->finished_at?->format('d/m/Y H:i'),
                'detail_url' => route('admin.students.reports.solo', [$student, $attempt]),
            ]);
    }

    private function roomDurationSeconds(GameResult $result): ?int
    {
        $start = $result->session?->started_at;
        $end = $result->finished_at ?? $result->session?->ended_at;

        if ($start === null || $end === null || $end->lt($start)) {
            return null;
        }

        return (int) $start->diffInSeconds($end);
    }

    private function formatDuration(?int $seconds): string
    {
        if ($seconds === null || $seconds <= 0) {
            return '—';
        }

        $minutes = intdiv($seconds, 60);
        $rest = $seconds % 60;

        return $minutes > 0 ? "{$minutes} phút {$rest} giây" : "{$rest} giây";
    }

    private function gradeFromPercent(int $percent): string
    {
        return match (true) {
            $percent >= self::GRADE_GOOD => 'gioi',
            $percent >= self::GRADE_FAIR => 'kha',
            default => 'duoi-kha',
        };
    }

    /** Đáp án HS chọn trong phòng live (answer_submitted là JSON tự do). */
    private function answerText(Question $question, mixed $submitted): string
    {
        if ($submitted === null) {
            return '—';
        }

        $value = is_array($submitted) ? ($submitted['index'] ?? $submitted['answer'] ?? $submitted[0] ?? null) : $submitted;

        if (is_int($value) || (is_string($value) && ctype_digit($value))) {
            $options = $question->options ?? [];
            $index = (int) $value;

            if (array_key_exists($index, $options)) {
                return $this->optionLetter($index).'. '.strip_tags((string) $options[$index]);
            }
        }

        return is_scalar($value) ? (string) $value : json_encode($submitted, JSON_UNESCAPED_UNICODE);
    }

    private function correctAnswerText(Question $question): string
    {
        $options = $question->options ?? [];
        $index = $question->correct_index;

        if ($index !== null && array_key_exists((int) $index, $options)) {
            return $this->optionLetter((int) $index).'. '.strip_tags((string) $options[(int) $index]);
        }

        return (string) ($question->correct_answer_normalized ?? '—');
    }

    private function bankAnswerText(mixed $question, ?int $index): string
    {
        if ($question === null || $index === null) {
            return '—';
        }

        $options = $question->options ?? [];

        return array_key_exists($index, $options)
            ? $this->optionLetter($index).'. '.strip_tags((string) $options[$index])
            : '—';
    }

    private function optionLetter(int $index): string
    {
        return chr(65 + max(0, min(25, $index)));
    }

    private function assertOwned(Request $request, Student $student): void
    {
        abort_unless(
            $request->user()->isAdmin() || $student->teacher_id === $request->user()->id,
            403,
            'Bạn không quản lý học sinh này.'
        );
    }
}
