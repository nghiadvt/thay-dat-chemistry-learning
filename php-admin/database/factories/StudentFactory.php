<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        return [
            'teacher_id' => User::factory()->teacher(),
            'class_id' => null,
            'student_code' => 'HS-'.strtoupper(fake()->unique()->bothify('??####')),
            'username' => 'hs-'.fake()->unique()->numberBetween(1000, 999999),
            'display_name' => 'Học sinh '.fake()->numberBetween(1, 50),
            'password' => 'secret@1',
            'status' => 'active',
        ];
    }

    public function locked(): static
    {
        return $this->state(fn () => [
            'status' => 'locked',
            'locked_at' => now(),
            'failed_attempts' => Student::MAX_FAILED_ATTEMPTS,
        ]);
    }
}
