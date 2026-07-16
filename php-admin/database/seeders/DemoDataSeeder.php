<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\GameResult;
use App\Models\GameSession;
use App\Models\Keyboard;
use App\Models\PlayMode;
use App\Models\Question;
use App\Models\QuestionBankItem;
use App\Models\Quiz;
use App\Models\Role;
use App\Models\SessionAnswer;
use App\Models\SiteFeedback;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\Data\Grade10Questions;
use Database\Seeders\Data\Grade11Questions;
use Database\Seeders\Data\Grade12Questions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Bộ dữ liệu demo lớn: 2 game, 12 quiz (mỗi khối 4 đề × 20 câu),
 * ngân hàng 300 câu hỏi, 12 phòng chơi kèm kết quả để xem báo cáo, và góp ý mẫu.
 *
 * Chạy: php artisan db:seed --class=DemoDataSeeder
 */
class DemoDataSeeder extends Seeder
{
    private const STUDENT_NAMES = [
        'Gia An', 'Bảo Anh', 'Đức Bình', 'Minh Châu', 'Thành Đạt', 'Hải Đăng', 'Thu Hà',
        'Gia Hân', 'Minh Khang', 'Anh Khoa', 'Bảo Lâm', 'Ngọc Linh', 'Nhật Minh', 'Trà My',
        'Thanh Ngân', 'Bảo Ngọc', 'Minh Nhật', 'Hồng Phúc', 'Minh Quân', 'Anh Thư',
    ];

    public function run(): void
    {
        mt_srand(20260712);

        $teacher = $this->seedUsers();
        [$inorganicKeyboard, $organicKeyboard] = $this->keyboards();

        $kahootMode = PlayMode::query()->where('slug', 'kahoot_sync')->firstOrFail();
        $duckMode = PlayMode::query()->where('slug', 'duck_race')->firstOrFail();

        $quizGame = Game::updateOrCreate(
            ['name' => 'Ôn tập Hóa học THPT'],
            [
                'description' => 'Quiz đồng bộ kiểu Kahoot: cả phòng cùng câu hỏi, chấm điểm theo tốc độ.',
                'created_by' => $teacher->id,
                'play_mode_id' => $kahootMode->id,
            ]
        );

        $duckGame = Game::updateOrCreate(
            ['name' => 'Đua vịt Hóa học'],
            [
                'description' => 'Trả lời đúng vịt tiến 3 bước, sai lùi 5 bước. Chạm đích trước để thắng!',
                'created_by' => $teacher->id,
                'play_mode_id' => $duckMode->id,
            ]
        );

        $pools = [
            '10' => Grade10Questions::all(),
            '11' => Grade11Questions::all(),
            '12' => Grade12Questions::all(),
        ];

        $bankByGrade = $this->seedQuestionBank($pools);
        $quizzes = $this->seedQuizzes($pools, $bankByGrade, $quizGame, $duckGame, $inorganicKeyboard, $organicKeyboard);
        $this->seedSessions($quizzes, $teacher);
        $this->seedFeedback();
    }

    private function seedUsers(): User
    {
        $adminRoleId = Role::query()->where('slug', 'admin')->value('id');
        $teacherRoleId = Role::query()->where('slug', 'teacher')->value('id');

        User::updateOrCreate(
            ['email' => 'admin@hoadat.local'],
            ['name' => 'Quản trị viên', 'password' => Hash::make('password123'), 'role_id' => $adminRoleId]
        );

        $teacher = User::updateOrCreate(
            ['email' => 'teacher@hoadat.local'],
            ['name' => 'Giáo viên Demo', 'password' => Hash::make('password123'), 'role_id' => $teacherRoleId]
        );

        User::updateOrCreate(
            ['email' => 'lan@hoadat.local'],
            ['name' => 'Cô Lan', 'password' => Hash::make('password123'), 'role_id' => $teacherRoleId]
        );

        User::updateOrCreate(
            ['email' => 'minh@hoadat.local'],
            ['name' => 'Thầy Minh', 'password' => Hash::make('password123'), 'role_id' => $teacherRoleId]
        );

        return $teacher;
    }

    /**
     * @return array{0: Keyboard, 1: Keyboard}
     */
    private function keyboards(): array
    {
        $inorganic = Keyboard::query()->where('name', 'Bàn phím Hóa vô cơ')->first();
        $organic = Keyboard::query()->where('name', 'Bàn phím Hóa hữu cơ')->first();

        if (! $inorganic || ! $organic) {
            $this->call(SampleDataSeeder::class);
            $inorganic = Keyboard::query()->where('name', 'Bàn phím Hóa vô cơ')->firstOrFail();
            $organic = Keyboard::query()->where('name', 'Bàn phím Hóa hữu cơ')->firstOrFail();
        }

        return [$inorganic, $organic];
    }

    /**
     * Đưa 300 câu vào ngân hàng câu hỏi kèm tag "Khối X" + tag chủ đề.
     *
     * @param  array<string, list<array<string, mixed>>>  $pools
     * @return array<string, list<QuestionBankItem>>
     */
    private function seedQuestionBank(array $pools): array
    {
        $bankByGrade = [];

        foreach ($pools as $grade => $pool) {
            $gradeTag = Tag::findOrCreateFromName('Khối '.$grade);

            foreach ($pool as $data) {
                $topic = $data['topic'];
                unset($data['topic']);

                $item = QuestionBankItem::updateOrCreate(
                    ['content' => $data['content']],
                    $data + ['is_active' => true]
                );

                $topicTag = Tag::findOrCreateFromName($topic);
                $item->tags()->syncWithoutDetaching([$gradeTag->id, $topicTag->id]);

                $bankByGrade[$grade][] = $item;
            }
        }

        return $bankByGrade;
    }

    /**
     * 12 quiz: mỗi khối 4 đề × 20 câu, lấy xen kẽ từ 80 câu đầu của pool
     * để mỗi đề trộn đủ chủ đề. Đề 1-2 thuộc game quiz, đề 3-4 thuộc game đua vịt.
     *
     * @param  array<string, list<array<string, mixed>>>  $pools
     * @param  array<string, list<QuestionBankItem>>  $bankByGrade
     * @return list<Quiz>
     */
    private function seedQuizzes(
        array $pools,
        array $bankByGrade,
        Game $quizGame,
        Game $duckGame,
        Keyboard $inorganicKeyboard,
        Keyboard $organicKeyboard,
    ): array {
        $quizzes = [];
        $sortOrder = 1;

        foreach (array_keys($pools) as $grade) {
            $keyboard = $grade === '10' ? $inorganicKeyboard : $organicKeyboard;

            for ($n = 0; $n < 4; $n++) {
                $game = $n < 2 ? $quizGame : $duckGame;

                $quiz = Quiz::updateOrCreate(
                    ['game_id' => $game->id, 'name' => 'Ôn tập Hóa '.$grade.' – Đề '.($n + 1)],
                    [
                        'keyboard_id' => $keyboard->id,
                        'subject' => 'chemistry',
                        'grade' => $grade,
                        'sort_order' => $sortOrder++,
                        'is_active' => true,
                        'show_explanation' => true,
                        'shuffle_options' => false,
                    ]
                );

                $gradeTag = Tag::findOrCreateFromName('Khối '.$grade);
                $quiz->tags()->syncWithoutDetaching([$gradeTag->id]);

                // Câu thứ k của đề n là phần tử n + 4k trong pool (k = 0..19).
                for ($k = 0; $k < 20; $k++) {
                    $bankItem = $bankByGrade[$grade][$n + 4 * $k];

                    Question::updateOrCreate(
                        ['quiz_id' => $quiz->id, 'sort_order' => $k + 1],
                        [
                            'source_bank_question_id' => $bankItem->id,
                            'content' => $bankItem->content,
                            'explanation' => $bankItem->explanation,
                            'answer_type' => $bankItem->answer_type,
                            'options' => $bankItem->options,
                            'correct_index' => $bankItem->correct_index,
                            'correct_answer_normalized' => $bankItem->correct_answer_normalized,
                            'time_limit_seconds' => $bankItem->time_limit_seconds,
                            'points' => $bankItem->points,
                            'is_active' => true,
                        ]
                    );
                }

                $quizzes[] = $quiz;
            }
        }

        return $quizzes;
    }

    /**
     * 12 phòng chơi: 8 phòng đã kết thúc (kèm kết quả + câu trả lời cho báo cáo),
     * 2 phòng đang chơi, 2 phòng đang chờ.
     *
     * @param  list<Quiz>  $quizzes
     */
    private function seedSessions(array $quizzes, User $teacher): void
    {
        foreach ($quizzes as $index => $quiz) {
            $game = $quiz->game;
            $playModeSlug = $game->playMode?->slug ?? 'kahoot_sync';
            $status = $index < 8 ? 'ended' : ($index < 10 ? 'playing' : 'waiting');
            $startedAt = now()->subDays(11 - $index)->setTime(19, 0);

            $session = GameSession::updateOrCreate(
                ['pin' => sprintf('9000%02d', $index + 1)],
                [
                    'name' => 'Phòng '.$quiz->name,
                    'host_id' => $teacher->id,
                    'game_id' => $game->id,
                    'quiz_id' => $quiz->id,
                    'play_mode_slug' => $playModeSlug,
                    'status' => $status,
                    'is_active' => $status !== 'ended',
                    'started_at' => $status === 'waiting' ? null : $startedAt,
                    'ended_at' => $status === 'ended' ? $startedAt->copy()->addMinutes(25) : null,
                ]
            );

            if ($status === 'ended') {
                $this->seedSessionResults($session, $quiz, $playModeSlug, $startedAt);
            }
        }
    }

    /**
     * Sinh câu trả lời từng câu cho từng học sinh rồi tổng hợp thành bảng xếp hạng.
     */
    private function seedSessionResults(GameSession $session, Quiz $quiz, string $playModeSlug, \Illuminate\Support\Carbon $startedAt): void
    {
        $questions = $quiz->questions()->orderBy('sort_order')->get();
        $isDuckRace = $playModeSlug === 'duck_race';

        $studentCount = mt_rand(8, 14);
        $offset = mt_rand(0, count(self::STUDENT_NAMES) - 1);
        $students = [];
        for ($s = 0; $s < $studentCount; $s++) {
            $students[] = self::STUDENT_NAMES[($offset + $s) % count(self::STUDENT_NAMES)];
        }

        SessionAnswer::query()->where('session_id', $session->id)->delete();
        GameResult::query()->where('session_id', $session->id)->delete();

        $answerRows = [];
        $totals = [];

        foreach ($students as $studentName) {
            $skill = mt_rand(45, 92) / 100;
            $total = 0;

            foreach ($questions as $qIndex => $question) {
                $isCorrect = (mt_rand(1, 100) / 100) <= $skill;

                if ($question->answer_type === 'mc') {
                    $optionCount = max(2, count($question->options ?? []));
                    $wrongIndex = ($question->correct_index + mt_rand(1, $optionCount - 1)) % $optionCount;
                    $submitted = ['index' => $isCorrect ? $question->correct_index : $wrongIndex];
                } else {
                    $submitted = ['text' => $isCorrect ? $question->correct_answer_normalized : $question->correct_answer_normalized.'2'];
                }

                $score = $isDuckRace
                    ? ($isCorrect ? 3 : -5)
                    : ($isCorrect ? mt_rand(500, 1000) : 0);
                $total += $score;

                $answerRows[] = [
                    'session_id' => $session->id,
                    'question_id' => $question->id,
                    'student_name' => $studentName,
                    'answer_submitted' => json_encode($submitted, JSON_UNESCAPED_UNICODE),
                    'is_correct' => $isCorrect,
                    'score_earned' => $score,
                    'answered_at' => $startedAt->copy()->addSeconds(60 * $qIndex + mt_rand(5, 55)),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            $totals[$studentName] = $isDuckRace ? max(0, $total) : $total;
        }

        foreach (array_chunk($answerRows, 200) as $chunk) {
            DB::table('session_answers')->insert($chunk);
        }

        arsort($totals);
        $rank = 1;
        $resultRows = [];
        foreach ($totals as $studentName => $score) {
            $resultRows[] = [
                'session_id' => $session->id,
                'student_name' => $studentName,
                'player_token' => substr(md5($session->pin.$studentName), 0, 32),
                'score' => $score,
                'rank' => $rank,
                'finish_rank' => $isDuckRace && $rank <= 3 ? $rank : null,
                'finished_at' => $isDuckRace && $rank <= 3
                    ? $startedAt->copy()->addMinutes(15 + $rank * 2)
                    : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];
            $rank++;
        }

        DB::table('game_results')->insert($resultRows);
    }

    private function seedFeedback(): void
    {
        if (SiteFeedback::query()->exists()) {
            return;
        }

        $userIds = User::query()
            ->whereIn('email', ['teacher@hoadat.local', 'lan@hoadat.local', 'minh@hoadat.local'])
            ->pluck('id')
            ->all();

        $items = [
            ['Trang ngân hàng câu hỏi load hơi chậm khi mở danh sách 300 câu.', '/admin/question-bank', 'Ngân hàng câu hỏi', 'high', 'new'],
            ['Nên có nút nhân bản quiz để tạo đề tương tự nhanh hơn.', '/admin/games', 'Quản lý game', 'medium', 'new'],
            ['Font công thức hóa học trên điện thoại hơi nhỏ, khó đọc chỉ số dưới.', '/play', 'Trang học sinh', 'medium', 'read'],
            ['Đề nghị thêm bộ lọc theo khối lớp ở ngân hàng câu hỏi.', '/admin/question-bank', 'Ngân hàng câu hỏi', 'low', 'done'],
            ['Mã PIN phòng nên hiển thị to hơn khi trình chiếu cho cả lớp.', '/admin/sessions', 'Phòng chơi', 'high', 'done'],
            ['Xuất CSV bị lỗi font tiếng Việt khi mở bằng Excel.', '/admin/question-bank', 'Ngân hàng câu hỏi', 'high', 'read'],
            ['Muốn có thống kê điểm trung bình theo từng lớp trong báo cáo.', '/admin/sessions', 'Báo cáo phiên chơi', 'medium', 'new'],
            ['Âm thanh game đua vịt hơi to, cần thêm nút tắt tiếng cho học sinh.', '/play', 'Đua vịt hóa học', 'low', 'read'],
            ['Thêm chế độ xem trước bàn phím ngay khi soạn câu hỏi tự luận.', '/admin/questions', 'Soạn câu hỏi', 'low', 'new'],
            ['Sau khi kết thúc phòng nên tự chuyển sang trang báo cáo kết quả.', '/admin/sessions', 'Phòng chơi', 'medium', 'done'],
        ];

        $rows = [];
        foreach ($items as $k => [$body, $url, $title, $priority, $status]) {
            $rows[] = [
                'user_id' => $userIds[$k % count($userIds)],
                'page_url' => $url,
                'page_title' => $title,
                'body' => $body,
                'priority' => $priority,
                'status' => $status,
                'created_at' => now()->subDays(9 - ($k % 10)),
                'updated_at' => now()->subDays(9 - ($k % 10)),
            ];
        }

        DB::table('site_feedback')->insert($rows);
    }
}
