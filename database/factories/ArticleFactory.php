<?php

namespace Database\Factories;

use App\Models\Article;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Article>
 */
class ArticleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake('fa_IR')->sentence(5),
            'slug' => fake()->unique()->slug(),
            'category' => fake()->randomElement(array_keys(Article::CATEGORIES)),
            'excerpt' => fake('fa_IR')->sentence(10),
            'body' => fake('fa_IR')->paragraphs(3, true),
            'status' => 'draft',
            'published_at' => null,
            'user_id' => User::factory(),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'published',
            'published_at' => now(),
        ]);
    }
}
