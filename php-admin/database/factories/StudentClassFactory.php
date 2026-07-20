<?php

namespace Database\Factories;

use App\Models\StudentClass;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentClass>
 */
class StudentClassFactory extends Factory
{
    protected $model = StudentClass::class;

    public function definition(): array
    {
        return [
            'teacher_id' => User::factory()->teacher(),
            'name' => 'Lớp 10A'.fake()->unique()->numberBetween(1, 99),
            'grade' => '10',
            'default_policy' => null,
        ];
    }
}
