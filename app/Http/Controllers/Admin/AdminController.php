<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\UrlSubmission;
use App\Models\User;
use Illuminate\View\View;

class AdminController extends Controller
{
    /**
     * Admin overview: every user + every submitted URL across the system,
     * with the actual status/response Google returned for each one.
     */
    public function index(): View
    {
        $users = User::withCount('urlSubmissions')->orderBy('name')->get();

        $submissions = UrlSubmission::with('user')->latest()->paginate(25);

        $counts = UrlSubmission::selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return view('admin.index', [
            'users' => $users,
            'submissions' => $submissions,
            'counts' => $counts,
        ]);
    }

    public function show(UrlSubmission $urlSubmission): View
    {
        return view('urls.show', ['submission' => $urlSubmission->load('user')]);
    }
}
