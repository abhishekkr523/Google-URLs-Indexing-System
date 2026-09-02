<?php

namespace App\Jobs;

use App\Models\UrlSubmission;
use App\Services\Google\GoogleIndexingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Runs the actual Google Indexing API submission for one URL in the
 * background, then records exactly what Google returned. This is the
 * backend process referenced throughout the requirement doc: submission
 * success and real indexing status are two different things, and this job
 * only ever records the former — the truthful result of the API call.
 */
class ProcessUrlSubmissionJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(private readonly int $urlSubmissionId) {}

    public function handle(GoogleIndexingService $indexingService): void
    {
        $submission = UrlSubmission::find($this->urlSubmissionId);

        if (! $submission) {
            return;
        }

        $submission->update([
            'status' => UrlSubmission::STATUS_PROCESSING,
            'requested_at' => now(),
        ]);

        try {
            $result = $indexingService->submit($submission->url, $submission->notification_type);
        } catch (Throwable $e) {
            Log::error('Google indexing submission threw an unhandled exception', [
                'url_submission_id' => $submission->id,
                'exception' => $e->getMessage(),
            ]);

            $submission->update([
                'status' => UrlSubmission::STATUS_ERROR,
                'failure_reason' => 'Unexpected error while processing the submission: '.$e->getMessage(),
                'processed_at' => now(),
            ]);

            return;
        }

        $submission->update([
            'status' => $result['success'] ? UrlSubmission::STATUS_SUBMITTED : UrlSubmission::STATUS_FAILED,
            'http_status_code' => $result['http_status'],
            'response_body' => $result['body'],
            'failure_reason' => $result['reason'],
            'processed_at' => now(),
        ]);
    }
}
