<?php

namespace Database\Seeders;

use App\Models\PracticeGrade;
use App\Models\PracticeQuiz;
use App\Models\PracticeTopic;
use App\Models\QuestionBankItem;
use App\Models\StudentClass;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Dữ liệu mẫu cho "Ôn trắc nghiệm": 6 lớp mới (10A/10B/11A/11B/12A/12B),
 * 3 khối, 6 chủ đề (2 mỗi khối), 8 bài trắc nghiệm với câu hỏi thật lấy theo
 * tag sẵn có trong ngân hàng câu hỏi. Mỗi khối có 1 bài mẫu bật "Yêu cầu Pro".
 *
 * Chạy: php artisan db:seed --class=PracticeSampleSeeder
 */
class PracticeSampleSeeder extends Seeder
{
    public function run(): void
    {
        $teacher = User::where('email', 'teacher@hoadat.local')->firstOrFail();

        $classes = [];
        foreach (['10A', '10B', '11A', '11B', '12A', '12B'] as $name) {
            $classes[$name] = StudentClass::updateOrCreate(
                ['teacher_id' => $teacher->id, 'name' => $name],
                ['grade' => substr($name, 0, 2)]
            );
        }

        $plan = [
            '10' => [
                'grade_name' => 'Khối 10',
                'classes' => ['10A', '10B'],
                'topics' => [
                    'Nguyên tử - Bảng tuần hoàn' => [
                        'Bài 1: Cấu tạo nguyên tử' => ['nguyen-tu', 15, false],
                        'Bài 2: Bảng tuần hoàn các nguyên tố' => ['bang-tuan-hoan', 15, false],
                    ],
                    'Ôn tập giữa kỳ 10' => [
                        'Đề ôn giữa kỳ' => ['khoi-luong-mol', 15, true],
                    ],
                ],
            ],
            '11' => [
                'grade_name' => 'Khối 11',
                'classes' => ['11A', '11B'],
                'topics' => [
                    'Hiđrocacbon' => [
                        'Bài 1: Hiđrocacbon' => ['hidrocacbon', 15, false],
                    ],
                    'Ôn tập giữa kỳ 11' => [
                        'Đề ôn giữa kỳ' => ['dong-phan-dong-dang', 12, true],
                    ],
                ],
            ],
            '12' => [
                'grade_name' => 'Khối 12',
                'classes' => ['12A', '12B'],
                'topics' => [
                    'Este – Lipit & Polime' => [
                        'Bài 1: Este – Lipit' => ['este-lipit', 8, false],
                        'Bài 2: Polime' => ['polime', 14, false],
                    ],
                    'Ôn tập giữa kỳ 12' => [
                        'Đề ôn giữa kỳ' => ['dai-cuong-kim-loai', 12, true],
                    ],
                ],
            ],
        ];

        $gradeSort = 0;
        foreach ($plan as $gradeKey => $spec) {
            $grade = PracticeGrade::updateOrCreate(
                ['slug' => Str::slug($spec['grade_name'])],
                ['name' => $spec['grade_name'], 'sort_order' => $gradeSort++]
            );

            $topicSort = 0;
            foreach ($spec['topics'] as $topicName => $quizzes) {
                $topic = PracticeTopic::updateOrCreate(
                    ['practice_grade_id' => $grade->id, 'slug' => Str::slug($topicName)],
                    ['name' => $topicName, 'sort_order' => $topicSort++]
                );

                $quizSort = 0;
                foreach ($quizzes as $quizName => [$topicTagSlug, $limit, $requiresPro]) {
                    $quiz = PracticeQuiz::updateOrCreate(
                        ['practice_topic_id' => $topic->id, 'name' => $quizName],
                        ['sort_order' => $quizSort++, 'is_active' => true, 'requires_pro' => $requiresPro]
                    );

                    $questionIds = QuestionBankItem::query()
                        ->where('is_active', true)
                        ->where('answer_type', 'mc')
                        ->whereNotNull('correct_index')
                        ->whereHas('tags', fn ($q) => $q->where('slug', "khoi-{$gradeKey}"))
                        ->whereHas('tags', fn ($q) => $q->where('slug', $topicTagSlug))
                        ->orderBy('id')
                        ->limit($limit)
                        ->pluck('id');

                    $sync = [];
                    foreach ($questionIds->values() as $i => $id) {
                        $sync[$id] = ['sort_order' => $i + 1];
                    }
                    $quiz->questionBankItems()->sync($sync);

                    $classIds = collect($spec['classes'])->map(fn ($n) => $classes[$n]->id)->all();
                    $quiz->studentClasses()->sync($classIds);
                }
            }
        }

        $this->command?->info('Đã tạo dữ liệu mẫu Ôn trắc nghiệm: 3 khối, 6 chủ đề, 8 bài, 6 lớp.');
    }
}
