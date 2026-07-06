<?php

namespace Database\Factories;

use App\Models\Keyboard;
use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\Support\KeyboardTestConfig;

/**
 * @extends Factory<Keyboard>
 */
class KeyboardFactory extends Factory
{
    protected $model = Keyboard::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'subject' => 'chemistry',
            'config' => KeyboardTestConfig::minimalValid(),
            'preview_path' => null,
        ];
    }
}
