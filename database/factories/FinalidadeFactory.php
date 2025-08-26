<?php

namespace Database\Factories;

use App\Models\Finalidade;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Finalidade>
 */
class FinalidadeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Finalidade::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'legenda' => $this->faker->sentence(2),
            'cor' => $this->faker->hexColor(),
        ];
    }
}
