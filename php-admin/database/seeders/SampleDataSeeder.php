<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\GameResult;
use App\Models\GameSession;
use App\Models\Keyboard;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\SessionAnswer;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SampleDataSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = User::updateOrCreate(
            ['email' => 'teacher@hoadat.local'],
            [
                'name' => 'Giáo viên Demo',
                'password' => Hash::make('password123'),
                'role' => 'teacher',
            ]
        );

        $inorganicKeyboard = Keyboard::updateOrCreate(
            ['name' => 'Bàn phím Hóa vô cơ'],
            [
                'subject' => 'chemistry',
                'config' => $this->inorganicKeyboardConfig(),
            ]
        );

        $organicKeyboard = Keyboard::updateOrCreate(
            ['name' => 'Bàn phím Hóa hữu cơ'],
            [
                'subject' => 'chemistry',
                'config' => $this->organicKeyboardConfig(),
            ]
        );

        $game = Game::updateOrCreate(
            ['name' => 'Ôn tập học kỳ 1'],
            [
                'description' => 'Bộ câu hỏi mẫu cho demo local deployment.',
                'created_by' => $teacher->id,
            ]
        );

        $quizInorganic = Quiz::updateOrCreate(
            ['game_id' => $game->id, 'name' => 'Hóa vô cơ cơ bản'],
            [
                'keyboard_id' => $inorganicKeyboard->id,
                'subject' => 'chemistry',
                'grade' => '10',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $quizOrganic = Quiz::updateOrCreate(
            ['game_id' => $game->id, 'name' => 'Hóa hữu cơ nhập môn'],
            [
                'keyboard_id' => $organicKeyboard->id,
                'subject' => 'chemistry',
                'grade' => '11',
                'sort_order' => 2,
                'is_active' => true,
            ]
        );

        $quizMixed = Quiz::updateOrCreate(
            ['game_id' => $game->id, 'name' => 'Tổng hợp nhanh'],
            [
                'keyboard_id' => $inorganicKeyboard->id,
                'subject' => 'chemistry',
                'grade' => '10',
                'sort_order' => 3,
                'is_active' => true,
            ]
        );

        $this->seedInorganicQuestions($quizInorganic);
        $this->seedOrganicQuestions($quizOrganic);
        $this->seedMixedQuestions($quizMixed);
        $this->seedSampleSession($teacher, $game, $quizInorganic);
    }

    private function seedInorganicQuestions(Quiz $quiz): void
    {
        $questions = [
            [
                'content' => '<p>Nguyên tố nào có ký hiệu <strong>Na</strong>?</p>',
                'answer_type' => 'mc',
                'options' => ['Natri', 'Niken', 'Neon', 'Nitơ'],
                'correct_index' => 0,
                'sort_order' => 1,
            ],
            [
                'content' => '<p>Công thức hóa học của nước là gì?</p>',
                'answer_type' => 'essay',
                'correct_answer_normalized' => 'H2O',
                'sort_order' => 2,
            ],
            [
                'content' => '<p>Viết phương trình cân bằng: H₂ + O₂ → H₂O</p>',
                'answer_type' => 'essay',
                'correct_answer_normalized' => '2H2 + O2 -> 2H2O',
                'sort_order' => 3,
            ],
            [
                'content' => '<p>Kim loại nào là kiềm mạnh nhất trong nhóm I?</p>',
                'answer_type' => 'mc',
                'options' => ['Li', 'Na', 'K', 'Cs'],
                'correct_index' => 3,
                'sort_order' => 4,
            ],
            [
                'content' => '<p>Công thức của axit clohidric loãng?</p>',
                'answer_type' => 'essay',
                'correct_answer_normalized' => 'HCl',
                'sort_order' => 5,
            ],
        ];

        foreach ($questions as $data) {
            Question::updateOrCreate(
                ['quiz_id' => $quiz->id, 'sort_order' => $data['sort_order']],
                $data
            );
        }
    }

    private function seedOrganicQuestions(Quiz $quiz): void
    {
        $questions = [
            [
                'content' => '<p>Hợp chất nào là ankan đơn giản nhất?</p>',
                'answer_type' => 'mc',
                'options' => ['CH4', 'C2H6', 'C2H4', 'C2H2'],
                'correct_index' => 0,
                'sort_order' => 1,
            ],
            [
                'content' => '<p>Công thức của etanol?</p>',
                'answer_type' => 'essay',
                'correct_answer_normalized' => 'C2H5OH',
                'sort_order' => 2,
            ],
            [
                'content' => '<p>Nhóm chức của axit axetic là gì?</p>',
                'answer_type' => 'mc',
                'options' => ['-OH', '-COOH', '-CHO', '-NH2'],
                'correct_index' => 1,
                'sort_order' => 3,
            ],
            [
                'content' => '<p>Công thức metan?</p>',
                'answer_type' => 'essay',
                'correct_answer_normalized' => 'CH4',
                'sort_order' => 4,
            ],
            [
                'content' => '<p>Phản ứng ester hóa tạo ra ester và:</p>',
                'answer_type' => 'mc',
                'options' => ['H2O', 'CO2', 'H2', 'O2'],
                'correct_index' => 0,
                'sort_order' => 5,
            ],
        ];

        foreach ($questions as $data) {
            Question::updateOrCreate(
                ['quiz_id' => $quiz->id, 'sort_order' => $data['sort_order']],
                $data
            );
        }
    }

    private function seedMixedQuestions(Quiz $quiz): void
    {
        $questions = [
            [
                'content' => '<p>pH của dung dịch axit mạnh thường:</p>',
                'answer_type' => 'mc',
                'options' => ['< 7', '= 7', '> 7', '≥ 14'],
                'correct_index' => 0,
                'sort_order' => 1,
            ],
            [
                'content' => '<p>Công thức muối ăn?</p>',
                'answer_type' => 'essay',
                'correct_answer_normalized' => 'NaCl',
                'sort_order' => 2,
            ],
            [
                'content' => '<p>Khí CO₂ có tính chất gì trong nước?</p>',
                'answer_type' => 'mc',
                'options' => ['Axit yếu', 'Bazơ mạnh', 'Trung tính', 'Oxi hóa mạnh'],
                'correct_index' => 0,
                'sort_order' => 3,
            ],
        ];

        foreach ($questions as $data) {
            Question::updateOrCreate(
                ['quiz_id' => $quiz->id, 'sort_order' => $data['sort_order']],
                $data
            );
        }
    }

    private function seedSampleSession(User $teacher, Game $game, Quiz $quiz): void
    {
        $session = GameSession::updateOrCreate(
            ['pin' => '123456'],
            [
                'name' => 'Phòng mẫu — Hóa vô cơ',
                'host_id' => $teacher->id,
                'game_id' => $game->id,
                'quiz_id' => $quiz->id,
                'status' => 'ended',
                'is_active' => false,
                'started_at' => now()->subHour(),
                'ended_at' => now()->subMinutes(30),
            ]
        );

        $students = [
            ['name' => 'An', 'score' => 2850, 'rank' => 1],
            ['name' => 'Bình', 'score' => 2400, 'rank' => 2],
            ['name' => 'Chi', 'score' => 2100, 'rank' => 3],
        ];

        foreach ($students as $student) {
            GameResult::updateOrCreate(
                ['session_id' => $session->id, 'student_name' => $student['name']],
                ['score' => $student['score'], 'rank' => $student['rank']]
            );
        }

        $firstQuestion = $quiz->questions()->orderBy('sort_order')->first();
        if ($firstQuestion) {
            SessionAnswer::updateOrCreate(
                [
                    'session_id' => $session->id,
                    'question_id' => $firstQuestion->id,
                    'student_name' => 'An',
                ],
                [
                    'answer_submitted' => ['index' => 0],
                    'is_correct' => true,
                    'score_earned' => 950,
                    'answered_at' => now()->subMinutes(55),
                ]
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function inorganicKeyboardConfig(): array
    {
        return [
            'schema_version' => 1,
            'defaults' => [
                'keySize' => 'M',
                'fontSize' => 'M',
                'textColor' => '#000000',
                'background' => '#FFFFFF',
                'border' => '#D0D0D0',
            ],
            'rows' => [
                $this->row('Numbers', ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9']),
                $this->row('Symbols', ['(', ')', '+', '-', '=', '°C', '→'], delete: true),
                $this->row('Elements 1', ['H', 'O', 'C', 'N', 'Cl', 'Ca', 'Na', 'K']),
                $this->row('Elements 2', ['He', 'Li', 'Be', 'B', 'F', 'Ne', 'Mg', 'Al']),
                $this->spaceRow(),
            ],
            'smart_context' => [
                'after_element' => 'subscript',
                'after_plus' => 'coefficient',
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function organicKeyboardConfig(): array
    {
        return [
            'schema_version' => 1,
            'defaults' => [
                'keySize' => 'M',
                'fontSize' => 'M',
                'textColor' => '#000000',
                'background' => '#FFFFFF',
                'border' => '#D0D0D0',
            ],
            'rows' => [
                $this->row('Numbers', ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9']),
                $this->row('Symbols', ['(', ')', '+', '-', '=', '→'], delete: true),
                $this->row('Organic', ['C', 'H', 'O', 'N', 'Br', 'Cl', 'OH', 'COOH']),
                $this->row('Bonds', ['-', '=', '#', '[', ']', 'R', 'Ar']),
                $this->spaceRow(),
            ],
            'smart_context' => [
                'after_element' => 'subscript',
                'after_plus' => 'coefficient',
            ],
        ];
    }

    /**
     * @param  list<string>  $tokens
     * @return array<string, mixed>
     */
    private function row(string $name, array $tokens, bool $delete = false): array
    {
        $keys = array_map(fn (string $token) => $this->key($token), $tokens);

        if ($delete) {
            $keys[] = [
                'id' => 'key-delete',
                'text' => 'Delete',
                'value' => '⌫',
                'width' => 2,
                'type' => 'delete',
                'background' => '#FFFFFF',
                'color' => '#000000',
                'border' => '#D0D0D0',
                'radius' => 6,
                'fontSize' => 'M',
                'keySize' => 'M',
                'tooltip' => '',
                'disabled' => false,
            ];
        }

        return [
            'id' => 'row-'.strtolower(str_replace(' ', '-', $name)),
            'name' => $name,
            'height' => 'M',
            'padding' => 2,
            'spacing' => 4,
            'background' => '#F5F5F5',
            'border' => '#E0E0E0',
            'alignment' => 'center',
            'hidden' => false,
            'locked' => false,
            'isSpaceRow' => false,
            'keys' => $keys,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function key(string $text): array
    {
        return [
            'id' => 'key-'.md5($text),
            'text' => $text,
            'value' => $text,
            'width' => 1,
            'type' => 'normal',
            'background' => '#FFFFFF',
            'color' => '#000000',
            'border' => '#D0D0D0',
            'radius' => 6,
            'fontSize' => 'M',
            'keySize' => 'M',
            'tooltip' => '',
            'disabled' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function spaceRow(): array
    {
        return [
            'id' => 'row-space',
            'name' => 'Space & Send',
            'height' => 'M',
            'padding' => 2,
            'spacing' => 4,
            'background' => '#F5F5F5',
            'border' => '#E0E0E0',
            'alignment' => 'center',
            'hidden' => false,
            'locked' => true,
            'isSpaceRow' => true,
            'keys' => [
                [
                    'id' => 'key-globe',
                    'text' => '🌐',
                    'value' => '',
                    'width' => 1,
                    'type' => 'globe',
                    'background' => '#FFFFFF',
                    'color' => '#000000',
                    'border' => '#D0D0D0',
                    'radius' => 6,
                    'fontSize' => 'M',
                    'keySize' => 'M',
                    'tooltip' => '',
                    'disabled' => false,
                ],
                [
                    'id' => 'key-empty',
                    'text' => '',
                    'value' => '',
                    'width' => 2,
                    'type' => 'empty',
                    'background' => '#FFFFFF',
                    'color' => '#000000',
                    'border' => '#D0D0D0',
                    'radius' => 6,
                    'fontSize' => 'M',
                    'keySize' => 'M',
                    'tooltip' => '',
                    'disabled' => false,
                ],
                [
                    'id' => 'key-space',
                    'text' => 'Space',
                    'value' => ' ',
                    'width' => 4,
                    'type' => 'space',
                    'background' => '#FFFFFF',
                    'color' => '#000000',
                    'border' => '#D0D0D0',
                    'radius' => 6,
                    'fontSize' => 'M',
                    'keySize' => 'M',
                    'tooltip' => '',
                    'disabled' => false,
                ],
                [
                    'id' => 'key-send',
                    'text' => 'Send',
                    'value' => "\n",
                    'width' => 3,
                    'type' => 'send',
                    'background' => '#2D46D6',
                    'color' => '#FFFFFF',
                    'border' => '#2D46D6',
                    'radius' => 6,
                    'fontSize' => 'M',
                    'keySize' => 'M',
                    'tooltip' => '',
                    'disabled' => false,
                ],
            ],
        ];
    }
}
