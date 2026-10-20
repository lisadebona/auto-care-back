<?php

namespace Database\Factories;

use App\Models\EmailMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailMessage>
 */
class EmailMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'mailgun_id' => (string) fake()->unique()->numerify('########'),
            'subject' => fake()->sentence(),
            'from_email' => fake()->safeEmail(),
            'from_name' => fake()->name(),
            'to_email' => fake()->safeEmail(),
            'to_name' => fake()->name(),
            'text_body' => fake()->paragraph(),
            'html_body' => '<p>'.e(fake()->paragraph()).'</p>',
            'sent_at' => now(),
            'is_read' => false,
            'synced_at' => now(),
        ];
    }
}
