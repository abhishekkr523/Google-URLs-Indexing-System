@extends('layouts.app')

@section('title', 'Admin')

@section('content')
<h1 class="mb-6 text-xl font-semibold">Admin panel</h1>

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

<h2 class="mb-3 text-lg font-semibold">Users</h2>
<div class="mb-8 overflow-x-auto rounded-lg border border-gray-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
                <th class="px-4 py-3">Name</th>
                <th class="px-4 py-3">Email</th>
                <th class="px-4 py-3">Role</th>
                <th class="px-4 py-3">URLs submitted</th>
                <th class="px-4 py-3">Joined</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @foreach ($users as $user)
                <tr>
                    <td class="px-4 py-3">{{ $user->name }}</td>
                    <td class="px-4 py-3">{{ $user->email }}</td>
                    <td class="px-4 py-3">{{ $user->is_admin ? 'Admin' : 'User' }}</td>
                    <td class="px-4 py-3">{{ $user->url_submissions_count }}</td>
                    <td class="px-4 py-3 text-gray-500">{{ $user->created_at->format('Y-m-d') }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>

<h2 class="mb-3 text-lg font-semibold">All submitted URLs</h2>
<div class="overflow-x-auto rounded-lg border border-gray-200 bg-white">
    <table class="w-full text-left text-sm">
        <thead class="border-b border-gray-200 bg-gray-50 text-xs uppercase text-gray-500">
            <tr>
                <th class="px-4 py-3">URL</th>
                <th class="px-4 py-3">User</th>
                <th class="px-4 py-3">Status</th>
                <th class="px-4 py-3">HTTP</th>
                <th class="px-4 py-3">Submitted</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($submissions as $submission)
                <tr>
                    <td class="max-w-xs truncate px-4 py-3" title="{{ $submission->url }}">{{ $submission->url }}</td>
                    <td class="px-4 py-3 text-gray-500">{{ $submission->user->email ?? '—' }}</td>
                    <td class="px-4 py-3">
                        <x-status-badge :status="$submission->status" />
                        @if ($submission->failure_reason)
                            <div class="mt-1 max-w-xs truncate text-xs text-gray-500" title="{{ $submission->failure_reason }}">
                                {{ $submission->failure_reason }}
                            </div>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-gray-500">{{ $submission->http_status_code ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-500">{{ $submission->created_at->format('Y-m-d H:i') }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('admin.urls.show', $submission) }}" class="text-gray-900 underline">Details</a>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="6" class="px-4 py-6 text-center text-gray-500">No URLs submitted yet.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div class="mt-4">
    {{ $submissions->links() }}
</div>
@endsection
