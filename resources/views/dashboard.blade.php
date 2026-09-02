@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
<h1 class="mb-6 text-xl font-semibold">Submit a URL for Google indexing</h1>

<div class="mb-8 rounded-lg border border-gray-200 bg-white p-5">
    @if ($errors->any())
        <div class="mb-4 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST" action="{{ route('urls.store') }}" class="flex gap-3">
        @csrf
        <input type="url" name="url" placeholder="https://example.com/page" required
               value="{{ old('url') }}"
               class="flex-1 rounded-md border border-gray-300 px-3 py-2 text-sm focus:border-gray-500 focus:outline-none">
        <button type="submit" class="rounded-md bg-gray-900 px-4 py-2 text-sm font-medium text-white hover:bg-gray-700">
            Submit
        </button>
    </form>
    <p class="mt-2 text-xs text-gray-500">
        The URL is queued and sent to the Google Indexing API in the background. Refresh this page to see the
        real status once processing completes.
    </p>
</div>

<div class="mb-8 grid grid-cols-2 gap-3 sm:grid-cols-5">
    @php
        $labels = ['pending' => 'Pending', 'processing' => 'Processing', 'submitted' => 'Submitted', 'failed' => 'Failed', 'error' => 'Error'];
    @endphp
    @foreach ($labels as $key => $label)
        <div class="rounded-lg border border-gray-200 bg-white p-4 text-center">
            <div class="text-2xl font-semibold">{{ $counts[$key] ?? 0 }}</div>
            <div class="text-xs text-gray-500">{{ $label }}</div>
        </div>
    @endforeach
</div>

<h2 class="mb-3 text-lg font-semibold">Your submissions</h2>

<div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
                <th class="px-4 py-3">URL</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">HTTP</th>
                <th class="px-4 py-3">Submitted</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($submissions as $submission)
                <tr>
                    <td class="max-w-xs truncate px-4 py-3">{{ $submission->url }}</td>
                    <td class="px-4 py-3">
                        <x-status-badge :status="$submission->status" />
                    </td>
                    <td class="px-4 py-3 text-gray-500">{{ $submission->http_status_code ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-500">{{ $submission->created_at->format('Y-m-d H:i') }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('urls.show', $submission) }}" class="text-gray-900 underline">Details</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-4 py-6 text-center text-gray-500">No URLs submitted yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $submissions->links() }}
</div>
@endsection
