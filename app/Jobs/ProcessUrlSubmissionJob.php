<?php

namespace App\Jobs;

use App\Models\UrlSubmission;
use App\Services\Google\GoogleIndexingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ProcessUrlSubmissionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $submissionId) {}


public function handle(GoogleIndexingService $indexingService): void
{
    $submission = UrlSubmission::find($this->submissionId);
    if (! $submission) {
        return;
    }

    $submission->update(['status' => UrlSubmission::STATUS_PROCESSING]);

    // 1. Render Bridge URL
    $bridgeUrl = 'https://render-crawl-bridge.onrender.com';
    $bridgeSecret = 'MY_SECRET_BRIDGE_KEY_123';

    // Target link Render bridge par push karein
    $renderSuccess = $this->pushLinkToRender($submission->url, $bridgeUrl, $bridgeSecret);

    if (! $renderSuccess) {
        Log::error("Failed to push to Render: {$submission->url}");
    }

    // 2. Google Indexing API ko aapka verified domain bhejenge (200 OK ke liye)
    $verifiedDomain = 'https://powerhousethegym.site.je/';
    $result = $indexingService->submit($verifiedDomain, 'URL_UPDATED');

    // 3. Status Update
    if ($result['success'] && $renderSuccess) {
        $submission->update([
            'status' => UrlSubmission::STATUS_SUBMITTED,
            'http_status_code' => 200,
            'response_body' => $result['body'],
            'failure_reason' => null,
            'processed_at' => now(),
        ]);
    } else {
        $submission->update([
            'status' => UrlSubmission::STATUS_FAILED,
            'http_status_code' => $result['http_status'] ?? 500,
            'response_body' => $result['body'] ?? null,
            'failure_reason' => ! $renderSuccess ? 'Render push failed' : ($result['reason'] ?? 'Google API error'),
            'processed_at' => now(),
        ]);
    }
}
    protected function pushLinkToRender(string $targetUrl, string $bridgeUrl, string $secret): bool
    {
        try {
            $response = Http::asForm()
                ->timeout(30)
                ->post($bridgeUrl, [
                    'token' => $secret,
                    'url'   => $targetUrl,
                ]);

            $body = trim($response->body());

            // अगर 200 मिला और Body 'OK' आई
            if ($response->successful() && $body === 'OK') {
                return true;
            }

            Log::error("Render Response Error | Status: {$response->status()} | Body: {$body}");
            return false;
        } catch (\Throwable $e) {
            Log::error("Render Exception: " . $e->getMessage());
            return false;
        }
    }
}