@extends('layouts.app')

@section('title', 'Submission detail')

@section('content')
@php
    $backRoute = request()->routeIs('admin.*') ? route('admin.index') : route('dashboard');
@endphp

<a href="{{ $backRoute }}" class="mb-4 inline-block text-sm text-gray-600 underline">&larr; Back</a>

<h1 class="mb-1 text-xl font-semibold break-all">{{ $submission->url }}</h1>
<p class="mb-6 text-sm text-gray-500">
    Submitted {{ $submission->created_at->format('Y-m-d H:i:s') }}
    @isset($submission->user)
        by {{ $submission->user->name }} ({{ $submission->user->email }})
    @endisset
</p>

<div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
    <div class="rounded-lg border border-gray-200 bg-white p-4">
        <div class="text-xs uppercase text-gray-500">Status</div>
        <div class="mt-1"><x-status-badge :status="$submission->status" /></div>
    </div>
    <div class="rounded-lg border border-gray-200 bg-white p-4">
        <div class="text-xs uppercase text-gray-500">HTTP status from Google</div>
        <div class="mt-1 font-medium">{{ $submission->http_status_code ?? '—' }}</div>
    </div>
    <div class="rounded-lg border border-gray-200 bg-white p-4">
        <div class="text-xs uppercase text-gray-500">Processed at</div>
        <div class="mt-1 font-medium">{{ $submission->processed_at?->format('Y-m-d H:i:s') ?? 'Not yet processed' }}</div>
    </div>
</div>

@if ($submission->status === 'submitted')
    <div class="mb-6 rounded-md border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
        Google's Indexing API accepted this submission request. This means the request was <strong>valid and
        acknowledged</strong> &mdash; it is not proof that the page has been crawled or indexed yet. Actual indexing
        status must be checked manually in Google Search (e.g. a <code>site:</code> search) or Search Console,
        typically 10&ndash;15 minutes to about an hour later.
    </div>
@endif

@if ($submission->failure_reason)
    <div class="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
        <strong>Failure reason:</strong> {{ $submission->failure_reason }}
    </div>
@endif

<h2 class="mb-2 text-sm font-semibold uppercase text-gray-500">Raw response received from Google</h2>
<pre class="mb-6 overflow-x-auto rounded-lg border border-gray-200 bg-gray-900 p-4 text-xs text-gray-100">{{ $submission->response_body ? json_encode($submission->response_body, JSON_PRETTY_PRINT) : 'No response captured yet.' }}</pre>

<h2 class="mb-2 text-sm font-semibold uppercase text-gray-500">Timeline</h2>
<ul class="rounded-lg border border-gray-200 bg-white p-4 text-sm">
    <li class="flex justify-between py-1">
        <span>Submitted by user</span>
        <span class="text-gray-500">{{ $submission->created_at->format('Y-m-d H:i:s') }}</span>
    </li>
    <li class="flex justify-between py-1">
        <span>Sent to Google (notification type: {{ $submission->notification_type }})</span>
        <span class="text-gray-500">{{ $submission->requested_at?->format('Y-m-d H:i:s') ?? '—' }}</span>
    </li>
    <li class="flex justify-between py-1">
        <span>Response captured, final status: {{ ucfirst($submission->status) }}</span>
        <span class="text-gray-500">{{ $submission->processed_at?->format('Y-m-d H:i:s') ?? '—' }}</span>
    </li>
</ul>
@endsection
