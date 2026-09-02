<?php

namespace Database\Factories;

use App\Models\UrlSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<UrlSubmission>
 */
class UrlSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'url' => $this->faker->url(),
            'notification_type' => 'URL_UPDATED',
            'status' => UrlSubmission::STATUS_PENDING,
        ];
    }
}
