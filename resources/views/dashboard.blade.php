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
        URL queue mein daali jati hai aur Google Indexing API ke through background mein process hoti hai.
        Status dekhne ke liye page refresh karein.
    </p>
</div>

{{-- Discovery Tools Info Box --}}
<!-- <div class="mb-8 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
    <p class="mb-2 font-semibold">⚠️ Google/Bing/Yandex — Sabhi APIs ke liye Domain Ownership Zaroori Hai</p>
    <p class="mb-3 text-amber-800">
        Google Indexing API aur IndexNow dono sirf <strong>aapki khud ki domain</strong> ke URLs accept karte hain.
        Wikipedia, meragym.com jaise baaki sites ke liye <strong>HTTP 403/422</strong> milega — yeh code ki galti nahi, internet ka security design hai.
        Iska koi reliable technical bypass nahi hai — kisi bhi service (Google ho ya IndexNow) mein arbitrary
        third-party URL ko bina uske owner ki verification ke index karwana possible nahi hai.
    </p>
    <hr class="mb-3 border-amber-200">
    <p class="mb-2 font-semibold">Zyada URLs succeed karwane ka asli tarika:</p>
    <p class="text-amber-800">
        Jitne bhi domains aap legitimately verify kar sakte hain (apne ya client ki sahmati se) — un sabko
        Search Console mein verify karke service account ko Owner add karein. Sirf unhi domains ke URLs
        genuinely "Submitted" dikhenge; baaki sab honestly failed record honge, jo is project ke requirements
        ke hisaab se hi valid outcome hai.
    </p>
</div> -->



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
                <th class="px-4 py-3">Google Status</th>
                <th class="px-4 py-3">IndexNow (Bing/Yandex)</th>
                <th class="px-4 py-3">HTTP</th>
                <th class="px-4 py-3">Submitted</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            @forelse ($submissions as $submission)
                <tr>
                    <td class="max-w-xs truncate px-4 py-3" title="{{ $submission->url }}">{{ $submission->url }}</td>

                    {{-- Google Indexing API status --}}
                    <td class="px-4 py-3">
                        <x-status-badge :status="$submission->status" />
                        @if ($submission->failure_reason)
                            <div class="mt-1 max-w-xs truncate text-xs text-gray-500" title="{{ $submission->failure_reason }}">
                                {{ $submission->failure_reason }}
                            </div>
                        @endif
                    </td>

                    {{-- IndexNow status --}}
                    <td class="px-4 py-3">
                        @if ($submission->indexnow_status === 'submitted')
                            <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                                ✓ Submitted
                            </span>
                        @elseif ($submission->indexnow_status === 'failed')
                            <span class="inline-flex items-center gap-1 rounded-full bg-orange-100 px-2 py-0.5 text-xs font-medium text-orange-700"
                                  title="{{ $submission->indexnow_reason }}">
                                ✗ Failed
                            </span>
                        @elseif ($submission->status === 'pending' || $submission->status === 'processing')
                            <span class="text-xs text-gray-400">Processing…</span>
                        @else
                            <span class="text-xs text-gray-400">—</span>
                        @endif
                    </td>

                    <td class="px-4 py-3 text-gray-500">{{ $submission->http_status_code ?? '—' }}</td>
                    <td class="px-4 py-3 text-gray-500">{{ $submission->created_at->format('Y-m-d H:i') }}</td>
                    <td class="px-4 py-3 text-right">
                        <a href="{{ route('urls.show', $submission) }}" class="text-gray-900 underline">Details</a>
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
