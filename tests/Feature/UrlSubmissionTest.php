<?php

use App\Jobs\ProcessUrlSubmissionJob;
use App\Models\UrlSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

test('an authenticated user can submit a url and it is queued for background processing', function () {
    Queue::fake();

    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/urls', [
        'url' => 'https://example.com/page',
    ]);

    $response->assertRedirect('/dashboard');

    $this->assertDatabaseHas('url_submissions', [
        'user_id' => $user->id,
        'url' => 'https://example.com/page',
        'status' => UrlSubmission::STATUS_PENDING,
    ]);

    Queue::assertPushed(ProcessUrlSubmissionJob::class);
});

test('an invalid url is rejected and never reaches the database', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post('/urls', [
        'url' => 'not-a-url',
    ]);

    $response->assertSessionHasErrors('url');
    $this->assertDatabaseCount('url_submissions', 0);
});

test('a user cannot view another users submission detail', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();

    $submission = UrlSubmission::factory()->for($owner)->create();

    $this->actingAs($intruder)
        ->get("/urls/{$submission->id}")
        ->assertForbidden();
});

test('non-admins cannot access the admin panel, admins can', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($user)->get('/admin')->assertForbidden();
    $this->actingAs($admin)->get('/admin')->assertOk();
});
