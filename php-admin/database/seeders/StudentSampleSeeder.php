<?php

namespace Database\Seeders;

use App\Models\Student;
use App\Models\StudentClass;
use App\Models\User;
use App\Services\StudentCredentials;
use App\Services\StudentPasswordService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Dữ liệu mẫu: 3 giáo viên demo x 3 lớp x 100 học sinh.
 *
 * Chạy: php artisan db:seed --class=StudentSampleSeeder
 */
class StudentSampleSeeder extends Seeder
{
    private const STUDENTS_PER_CLASS = 100;

    private const SURNAMES = ['Nguyễn', 'Trần', 'Lê', 'Phạm', 'Hoàng', 'Huỳnh', 'Phan', 'Vũ', 'Võ', 'Đặng', 'Bùi', 'Đỗ', 'Hồ', 'Ngô', 'Dương'];

    private const MIDDLE_MALE = ['Văn', 'Hữu', 'Minh', 'Quốc', 'Đức', 'Anh', 'Thành', 'Công'];

    private const MIDDLE_FEMALE = ['Thị', 'Ngọc', 'Thu', 'Kim', 'Thanh', 'Hồng', 'Bảo', 'Diễm'];

    private const FIRST_MALE = ['An', 'Bình', 'Cường', 'Dũng', 'Đạt', 'Hải', 'Huy', 'Khang', 'Long', 'Minh', 'Nam', 'Phát', 'Quân', 'Sơn', 'Tuấn', 'Việt', 'Vinh'];

    private const FIRST_FEMALE = ['Anh', 'Chi', 'Duyên', 'Giang', 'Hà', 'Hân', 'Linh', 'Mai', 'My', 'Nga', 'Ngân', 'Nhi', 'Phương', 'Thảo', 'Trang', 'Vy'];

    private const CLASS_PLAN = [
        'teacher@hoadat.local' => ['grade' => '10', 'classes' => ['10A1', '10A2', '10A3']],
        'lan@hoadat.local' => ['grade' => '11', 'classes' => ['11A1', '11A2', '11A3']],
        'minh@hoadat.local' => ['grade' => '12', 'classes' => ['12A1', '12A2', '12A3']],
    ];

    public function run(): void
    {
        mt_srand(20260719);

        $credentials = app(StudentCredentials::class);
        $passwords = app(StudentPasswordService::class);

        foreach (self::CLASS_PLAN as $email => $plan) {
            $teacher = User::query()->where('email', $email)->first();

            if (! $teacher) {
                continue;
            }

            foreach ($plan['classes'] as $className) {
                $class = StudentClass::query()->updateOrCreate(
                    ['teacher_id' => $teacher->id, 'name' => $className],
                    ['grade' => $plan['grade']]
                );

                $existing = Student::withTrashed()->where('class_id', $class->id)->count();

                if ($existing >= self::STUDENTS_PER_CLASS) {
                    continue;
                }

                $prefix = Str::slug($className);

                DB::transaction(function () use ($class, $teacher, $credentials, $passwords, $prefix, $existing) {
                    for ($i = $existing + 1; $i <= self::STUDENTS_PER_CLASS; $i++) {
                        $student = Student::create([
                            'teacher_id' => $teacher->id,
                            'class_id' => $class->id,
                            'student_code' => $credentials->generateStudentCode(),
                            'username' => $credentials->generateUsername($prefix, $i),
                            'display_name' => $this->randomDisplayName(),
                            'password' => Str::random(32),
                        ]);

                        $passwords->apply($student, $credentials->generatePassword(), $teacher, 'apply', null);
                    }
                });
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
