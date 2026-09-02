<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessUrlSubmissionJob;
use App\Models\UrlSubmission;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class UrlSubmissionController extends Controller
{
    /**
     * The user dashboard: submission form + this user's submission history.
     */
    public function index(Request $request): View
    {
        $submissions = $request->user()
            ->urlSubmissions()
            ->latest()
            ->paginate(15);

        $counts = $request->user()
            ->urlSubmissions()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return view('dashboard', [
            'submissions' => $submissions,
            'counts' => $counts,
        ]);
    }

    /**
     * Accept a URL, record it immediately with status=pending, then hand the
     * actual Google Indexing API call off to a queued background job. The
     * request only ever does validation + a DB write — never a fake result.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'url' => ['required', 'url', 'max:2048'],
        ]);

        $submission = $request->user()->urlSubmissions()->create([
            'url' => $data['url'],
            'notification_type' => 'URL_UPDATED',
            'status' => UrlSubmission::STATUS_PENDING,
        ]);

        ProcessUrlSubmissionJob::dispatch($submission->id);

        return redirect()->route('dashboard')->with('status', 'URL submitted for indexing processing.');
    }

    /**
     * Full detail for one submission: raw request/response trail, so the
     * "how the backend processed the request" requirement is inspectable.
     */
    public function show(Request $request, UrlSubmission $urlSubmission): View
    {
        abort_unless($urlSubmission->user_id === $request->user()->id, 403);

        return view('urls.show', ['submission' => $urlSubmission]);
    }
}
