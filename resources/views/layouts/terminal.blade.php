<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">

    <title>Browser Console</title>

    <!-- Tailwind CDN — standalone, no build step needed (developer tool, not public-facing) -->
    <script src="https://cdn.tailwindcss.com"></script>

    <style>
        [x-cloak] { display: none !important; }

        body {
            background: #0f172a;
            color: #e2e8f0;
        }

        .terminal-output {
            font-family: 'Cascadia Code', 'Fira Code', 'JetBrains Mono', 'Consolas', monospace;
            font-size: 0.8125rem;
            line-height: 1.6;
        }

        .terminal-output pre {
            white-space: pre-wrap;
            word-break: break-word;
        }

        .terminal-input {
            font-family: 'Cascadia Code', 'Fira Code', 'JetBrains Mono', 'Consolas', monospace;
        }

        .cmd-ref-btn {
            transition: background-color 0.15s ease, color 0.15s ease;
        }

        .cmd-ref-btn:hover {
            background-color: rgba(99, 102, 241, 0.15);
            color: #818cf8;
        }
    </style>
</head>

<body class="antialiased min-h-screen">
    {{ $slot }}

    @livewireScripts
</body>

</html>
