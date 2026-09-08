<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="index, follow">
    <title>Priority Discovery Bridge</title>
</head>
<body style="font-family: sans-serif; padding: 24px; max-width: 800px; margin: auto;">
    <h2>Network Hub Discovery Feed</h2>
    <p>Automated crawl pass for registered outgoing endpoints:</p>

    <ul>
        @forelse($urls as $target)
            <li style="margin-bottom: 8px;">
                <a href="{{ $target }}" rel="follow" target="_blank">{{ $target }}</a>
            </li>
        @empty
            <li>No submissions registered.</li>
        @endforelse
    </ul>
</body>
</html>