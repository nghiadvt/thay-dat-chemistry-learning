<?php

namespace Database\Seeders;

use App\Models\PracticeAttempt;
use App\Models\PracticeAttemptAnswer;
use App\Models\Student;
use App\Models\StudentClass;
use App\Models\User;
use App\Services\StudentCredentials;
use App\Services\StudentPasswordService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dữ liệu mẫu cho demo: 1 lớp 20 học sinh, học sinh đầu tiên có 20 lượt tự
 * luyện để xem thử màn thống kê.
 *
 * Chạy: php artisan db:seed --class=DemoClassStatsSeeder
 */
class DemoClassStatsSeeder extends Seeder
{
    private const STUDENTS_COUNT = 20;

    private const ATTEMPTS_COUNT = 20;

    private const CLASS_NAME = 'Lớp Demo Thống Kê';

    private const SURNAMES = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Huỳnh', 'Phan', 'Vũ', 'Võ', 'Đặng', 'Bùi', 'Đỗ', 'Hồ', 'Ngô', 'Dương'];

    private const MIDDLE_MALE = ['Văn', 'Hữu', 'Minh', 'Quốc', 'Đức', 'Anh', 'Thành', 'Công'];

    private const MIDDLE_FEMALE = ['Thị', 'Ngọc', 'Thu', 'Kim', 'Thanh', 'Hồng', 'Bảo', 'Diễm'];

    private const FIRST_MALE = ['An', 'Bình', 'Cường', 'Dũng', 'Đạt', 'Hải', 'Huy', 'Khang', 'Long', 'Minh', 'Nam', 'Phát', 'Quân', 'Sơn', 'Tuấn', 'Việt', 'Vinh'];

    private const FIRST_FEMALE = ['Anh', 'Chi', 'Duyên', 'Giang', 'Hà', 'Hân', 'Linh', 'Mai', 'My', 'Nga', 'Ngân', 'Nhi', 'Phương', 'Thảo', 'Trang', 'Vy'];

    private const TOPICS = [
        ['label' => 'Bảng tuần hoàn nguyên tố', 'topic_slug' => 'bang-tuan-hoan', 'grade_slug' => '10'],
        ['label' => 'Phản ứng oxi hóa - khử', 'topic_slug' => 'oxi-hoa-khu', 'grade_slug' => '10'],
        ['label' => 'Liên kết hóa học', 'topic_slug' => 'lien-ket-hoa-hoc', 'grade_slug' => '10'],
        ['label' => 'Tốc độ phản ứng', 'topic_slug' => 'toc-do-phan-ung', 'grade_slug' => '10'],
    ];

    public function run(): void
    {
        $teacher = User::query()->where('email', 'teacher@hoadat.local')->first()
            ?? User::query()->first();

        if (! $teacher) {
            $this->command?->warn('Không tìm thấy user nào để gán làm giáo viên — bỏ qua DemoClassStatsSeeder.');

            return;
        }

        $credentials = app(StudentCredentials::class);
        $passwords = app(StudentPasswordService::class);

        $class = StudentClass::query()->updateOrCreate(
            ['teacher_id' => $teacher->id, 'name' => self::CLASS_NAME],
            ['grade' => '10', 'description' => 'Lớp dữ liệu mẫu dùng để xem thử thống kê học sinh.', 'is_active' => true]
        );

        $existing = Student::withTrashed()->where('class_id', $class->id)->count();

        if ($existing >= self::STUDENTS_COUNT) {
            $this->command?->info('Lớp demo đã có đủ học sinh, bỏ qua.');

            return;
        }

        $prefix = Str::slug(self::CLASS_NAME);
        $firstStudent = null;

        DB::transaction(function () use ($class, $teacher, $credentials, $passwords, $prefix, $existing, &$firstStudent) {
            for ($i = $existing + 1; $i <= self::STUDENTS_COUNT; $i++) {
                $student = Student::create([
                    'teacher_id' => $teacher->id,
                    'class_id' => $class->id,
                    'student_code' => $credentials->generateStudentCode(),
                    'username' => $credentials->generateUsername($prefix, $i),
                    'display_name' => $this->randomDisplayName(),
                    'description' => 'Học sinh mẫu số '.$i,
                    'password' => Str::random(32),
                ]);

                $passwords->apply($student, $credentials->generatePassword(), $teacher, 'apply', null);

                $firstStudent ??= $student;
            }
        });

        if ($firstStudent) {
            $this->seedPracticeAttempts($firstStudent);
        }

        $this->command?->info('Đã tạo lớp demo '.self::STUDENTS_COUNT.' học sinh và '.self::ATTEMPTS_COUNT.' lượt thống kê mẫu.');
    }

    private function seedPracticeAttempts(Student $student): void
    {
        for ($i = 0; $i < self::ATTEMPTS_COUNT; $i++) {
            $topic = self::TOPICS[$i % count(self::TOPICS)];
            $total = mt_rand(8, 15);
            $correct = mt_rand((int) ($total * 0.4), $total);
            $durationMs = mt_rand(60_000, 600_000);
            $startedAt = now()->subDays(self::ATTEMPTS_COUNT - $i)->subMinutes(mt_rand(0, 120));
            $finishedAt = (clone $startedAt)->addMilliseconds($durationMs);

            $attempt = PracticeAttempt::create([
                'student_id' => $student->id,
                'feature_key' => 'practice-quiz',
                'label' => $topic['label'],
                'topic_slug' => $topic['topic_slug'],
                'grade_slug' => $topic['grade_slug'],
                'total_questions' => $total,
                'correct_count' => $correct,
                'score' => $correct * 10,
                'duration_ms' => $durationMs,
                'started_at' => $startedAt,
                'finished_at' => $finishedAt,
            ]);

            $wrongPositions = collect(range(1, $total))->shuffle()->take($total - $correct);

            for ($position = 1; $position <= $total; $position++) {
                $isCorrect = ! $wrongPositions->contains($position);

                PracticeAttemptAnswer::create([
                    'attempt_id' => $attempt->id,
                    'position' => $position,
                    'answer_index' => mt_rand(0, 3),
                    'is_correct' => $isCorrect,
                    'answered_at' => $startedAt->clone()->addSeconds($position * 10),
                ]);
            }
        }
    }

    private function randomDisplayName(): string
    {
        $isFemale = mt_rand(0, 1) === 1;

        $surname = self::SURNAMES[array_rand(self::SURNAMES)];
        $middle = $isFemale ? self::MIDDLE_FEMALE[array_rand(self::MIDDLE_FEMALE)] : self::MIDDLE_MALE[array_rand(self::MIDDLE_MALE)];
        $first = $isFemale ? self::FIRST_FEMALE[array_rand(self::FIRST_FEMALE)] : self::FIRST_MALE[array_rand(self::FIRST_MALE)];

        return "$surname $middle $first";
    }
}
