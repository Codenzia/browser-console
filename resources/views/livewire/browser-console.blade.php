<div class="min-h-screen flex items-center justify-center p-4" x-data="{
    isFullscreen: false,
    copyText(text) {
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text);
        } else {
            const ta = Object.assign(document.createElement('textarea'), {
                value: text,
                style: 'position:fixed;opacity:0;left:-9999px'
            });
            document.body.appendChild(ta);
            ta.select();
            document.execCommand('copy');
            document.body.removeChild(ta);
        }
    },
    toggleFullscreen() {
        const wrapper = this.$refs.consoleWrapper;
        if (!document.fullscreenElement) {
            wrapper.requestFullscreen().catch(() => {});
        } else {
            document.exitFullscreen();
        }
    }
}"
    @fullscreenchange.window="isFullscreen = !!document.fullscreenElement">
    @if (!$this->isAuthenticated)
        {{-- Login Card --}}
        <div class="flex flex-col items-center w-full max-w-md">
            <div class="w-full">
                <div class="bg-slate-800 border border-slate-700 rounded-xl shadow-2xl px-8 py-10">
                    <div class="text-center mb-8">
                        <div
                            class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-indigo-500/10 mb-5">
                            <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <h1 class="text-2xl font-bold text-white">Artisan Console</h1>
                        <p class="text-sm text-slate-400 mt-2">Authenticate to continue</p>
                    </div>

                    @if ($loginError)
                        <div
                            class="mb-4 px-3 py-2 bg-red-500/10 border border-red-500/30 rounded-lg text-sm text-red-400">
                            {{ $loginError }}
                        </div>
                    @endif

                    <form wire:submit="authenticate" class="space-y-5">
                        <div>
                            <label for="username"
                                class="block text-sm font-medium text-slate-300 mb-1.5">Username</label>
                            <input wire:model="username" id="username" type="text" autocomplete="off"
                                class="w-full px-4 py-2.5 bg-slate-900 border border-slate-600 rounded-lg text-white placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                placeholder="Console username" required>
                        </div>

                        <div>
                            <label for="password"
                                class="block text-sm font-medium text-slate-300 mb-1.5">Password</label>
                            <input wire:model="password" id="password" type="password" autocomplete="current-password"
                                class="w-full px-4 py-2.5 bg-slate-900 border border-slate-600 rounded-lg text-white placeholder-slate-500 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                placeholder="Console password" required>
                        </div>

                        <button type="submit"
                            class="w-full flex items-center justify-center gap-2 px-4 py-3 bg-indigo-600 hover:bg-indigo-500 text-white font-medium rounded-lg transition text-sm mt-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1" />
                            </svg>
                            Sign In
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @else
        {{-- Main Console Container --}}
        <div x-ref="consoleWrapper" class="w-full flex flex-col bg-[#0f172a]"
            :class="isFullscreen ? 'h-screen p-4' : 'max-w-7xl mx-auto h-[calc(100vh-20rem)]'">

            {{-- Header with Mode Tabs --}}
            <div class="flex items-center justify-between mb-4 shrink-0">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>

                    {{-- Mode Tabs --}}
                    <div class="flex rounded-lg bg-slate-900 p-0.5 border border-slate-700">
                        <button wire:click="switchMode('artisan')"
                            class="px-3 py-1 text-sm font-medium rounded-md transition {{ $mode === 'artisan' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white' }}">
                            Artisan
                        </button>
                        <button wire:click="switchMode('shell')"
                            class="px-3 py-1 text-sm font-medium rounded-md transition {{ $mode === 'shell' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-white' }}">
                            Shell
                        </button>
                        <button wire:click="switchMode('logs')"
                            class="px-3 py-1 text-sm font-medium rounded-md transition {{ $mode === 'logs' ? 'bg-amber-600 text-white' : 'text-slate-400 hover:text-white' }}">
                            Logs
                        </button>
                        <button wire:click="switchMode('debug')"
                            class="px-3 py-1 text-sm font-medium rounded-md transition {{ $mode === 'debug' ? 'bg-cyan-600 text-white' : 'text-slate-400 hover:text-white' }}">
                            Debug
                        </button>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    {{-- Fullscreen Toggle --}}
                    <button @click="toggleFullscreen()" type="button"
                        class="flex items-center gap-2 px-3 py-1.5 text-sm text-slate-400 hover:text-white border border-slate-700 hover:border-slate-500 rounded-lg transition"
                        :title="isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen'">
                        <svg x-show="!isFullscreen" class="w-4 h-4" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
                        </svg>
                        <svg x-show="isFullscreen" x-cloak class="w-4 h-4" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M9 9V4.5M9 9H4.5M9 9L3.75 3.75M9 15v4.5M9 15H4.5M9 15l-5.25 5.25M15 9h4.5M15 9V4.5M15 9l5.25-5.25M15 15h4.5M15 15v4.5m0-4.5l5.25 5.25" />
                        </svg>
                        {{-- <span x-text="isFullscreen ? 'Exit' : 'Expand'" class="hidden sm:inline"></span> --}}
                    </button>

                    {{-- Lock Console --}}
                    <button wire:click="logout" wire:confirm="Are you sure you want to lock the console?"
                        class="flex items-center gap-2 px-3 py-1.5 text-sm text-slate-400 hover:text-red-400 border border-slate-700 hover:border-red-500/50 rounded-lg transition">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                        Lock
                    </button>
                </div>
            </div>

            {{-- Shell Unavailable Warning --}}
            @if ($mode === 'shell' && !$shellAvailable)
                <div
                    class="mb-4 px-4 py-3 bg-amber-500/10 border border-amber-500/30 rounded-lg flex items-start gap-3 shrink-0">
                    <svg class="w-5 h-5 text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor"
                        viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L4.082 16.5c-.77.833.192 2.5 1.732 2.5z" />
                    </svg>
                    <div>
                        <p class="text-sm font-medium text-amber-300">Shell execution unavailable</p>
                        <p class="text-xs text-amber-400/70 mt-1">
                            The <code class="bg-slate-800 px-1 rounded">proc_open()</code> function is disabled on
                            this
                            server. Contact your hosting provider to enable it, or use the Artisan mode instead.
                        </p>
                    </div>
                </div>
            @endif

            {{-- Content Area - Unified Layout Structure --}}
            <div class="flex-1 min-h-0">
                <div class="flex gap-4 h-full items-stretch">

                    {{-- Left: Main Content Panel --}}
                    <div class="flex-1 min-w-0 flex flex-col">

                        {{-- Terminal Modes (Artisan/Shell) --}}
                        @if ($mode !== 'logs' && $mode !== 'debug')
                            <div x-data="{ cmdRunning: false }" @scroll-to-bottom.window="cmdRunning = false"
                                class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden shadow-2xl flex flex-col h-full">
                                {{-- Terminal Title Bar --}}
                                <div
                                    class="flex items-center gap-2 px-4 py-2.5 bg-slate-900 border-b border-slate-700 shrink-0">
                                    <span class="w-3 h-3 rounded-full bg-red-500"></span>
                                    <span class="w-3 h-3 rounded-full bg-yellow-500"></span>
                                    <span
                                        class="w-3 h-3 rounded-full {{ $mode === 'shell' ? 'bg-emerald-500' : 'bg-green-500' }}"></span>
                                    <span
                                        class="text-xs text-slate-500 ml-2">{{ $mode === 'shell' ? 'shell' : 'artisan' }}</span>
                                    <a href="https://www.codenzia.com" target="_blank" rel="noopener"
                                        class="ml-auto text-slate-600 hover:text-slate-400 transition">©️</a>
                                </div>

                                {{-- Output Area --}}
                                <div id="terminal-output"
                                    class="terminal-output p-4 flex-1 overflow-y-auto space-y-3 min-h-0"
                                    x-init="if ($el) $el.scrollTop = $el.scrollHeight"
                                    @scroll-to-bottom.window="$nextTick(() => { if ($el) $el.scrollTop = $el.scrollHeight })">

                                    @php $modeHistory = array_filter($history, fn($e) => ($e['mode'] ?? 'artisan') === $mode); @endphp
                                    @if (count($modeHistory) === 0)
                                        <div class="text-slate-500 text-center py-12">
                                            <svg class="w-10 h-10 mx-auto mb-3 text-slate-600" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="1.5"
                                                    d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                            </svg>
                                            @if ($mode === 'shell')
                                                <p>Type a shell command or click one from the reference panel.</p>
                                                <p class="text-xs text-slate-600 mt-1">Example: git status,
                                                    composer
                                                    install, ls -la</p>
                                            @else
                                                <p>Type a command or click one from the reference panel.</p>
                                                <p class="text-xs text-slate-600 mt-1">Example: optimize,
                                                    migrate:status, cache:clear</p>
                                            @endif
                                        </div>
                                    @endif

                                    @foreach ($modeHistory as $entry)
                                        <div>
                                            {{-- Command --}}
                                            <div class="flex items-center gap-2">
                                                <span
                                                    class="{{ ($entry['mode'] ?? 'artisan') === 'shell' ? 'text-emerald-400' : 'text-green-400' }} select-none">$</span>
                                                <span class="text-white font-medium">
                                                    @if (($entry['mode'] ?? 'artisan') === 'artisan')
                                                        php artisan {{ $entry['command'] }}
                                                    @else
                                                        {{ $entry['command'] }}
                                                    @endif
                                                </span>
                                                <span class="text-slate-600 text-xs ml-auto flex items-center gap-1.5">
                                                    @if (($entry['mode'] ?? 'artisan') === 'shell')
                                                        <span
                                                            class="inline-block w-1.5 h-1.5 rounded-full bg-emerald-500/60"></span>
                                                    @endif
                                                    {{ $entry['timestamp'] }}
                                                </span>
                                            </div>

                                            {{-- Output --}}
                                            <pre class="mt-1 pl-5 {{ $entry['status'] === 'error' ? 'text-red-400' : 'text-slate-300' }}">{{ $entry['output'] }}</pre>
                                        </div>
                                    @endforeach

                                    {{-- Live streaming output (shell commands) --}}
                                    <div x-show="cmdRunning" x-cloak>
                                        <div class="flex items-center gap-2 mt-2">
                                            <span
                                                class="{{ $mode === 'shell' ? 'text-emerald-400' : 'text-green-400' }} select-none">$</span>
                                            <span class="text-yellow-400 text-sm animate-pulse">Running...</span>
                                        </div>
                                        <pre wire:stream="console-output" class="mt-1 pl-5 text-slate-300 text-sm"></pre>
                                    </div>
                                </div>

                                {{-- Input Area --}}
                                <div class="border-t border-slate-700 bg-slate-900 px-4 py-3 shrink-0">
                                    <form wire:submit="runCommand" @submit="cmdRunning = true"
                                        class="flex items-center gap-3">
                                        <span
                                            class="{{ $mode === 'shell' ? 'text-emerald-400' : 'text-green-400' }} select-none font-mono text-sm">$</span>

                                        @if ($mode === 'artisan')
                                            <span
                                                class="text-slate-500 font-mono text-sm select-none whitespace-nowrap">php
                                                artisan</span>
                                        @endif

                                        <input wire:model="command" type="text" autofocus autocomplete="off"
                                            x-bind:disabled="cmdRunning"
                                            @if ($mode === 'shell' && !$shellAvailable) disabled @endif
                                            class="terminal-input flex-1 bg-transparent border-0 text-white placeholder-slate-600 focus:ring-0 p-0 text-sm disabled:opacity-50"
                                            placeholder="{{ $mode === 'shell' ? 'composer install, git status, ls -la...' : 'type a command...' }}">

                                        <div class="flex items-center gap-2 shrink-0">
                                            <button type="submit" x-bind:disabled="cmdRunning"
                                                @if ($mode === 'shell' && !$shellAvailable) disabled @endif
                                                class="flex items-center gap-1.5 px-3 py-1.5 {{ $mode === 'shell' ? 'bg-emerald-600 hover:bg-emerald-500' : 'bg-indigo-600 hover:bg-indigo-500' }} text-white text-sm font-medium rounded-lg transition disabled:opacity-50 disabled:cursor-not-allowed">
                                                <svg x-show="!cmdRunning" class="w-3.5 h-3.5" fill="none"
                                                    stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round"
                                                        stroke-width="2"
                                                        d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z" />
                                                </svg>
                                                <svg x-show="cmdRunning" x-cloak class="w-3.5 h-3.5 animate-spin"
                                                    fill="none" viewBox="0 0 24 24">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10"
                                                        stroke="currentColor" stroke-width="4"></circle>
                                                    <path class="opacity-75" fill="currentColor"
                                                        d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z">
                                                    </path>
                                                </svg>
                                                <span x-show="!cmdRunning">Run</span>
                                                <span x-show="cmdRunning" x-cloak>Running...</span>
                                            </button>
                                            @if (count($history) > 0)
                                                <button type="button" wire:click="clearHistory"
                                                    class="flex items-center gap-1.5 px-3 py-1.5 text-slate-400 hover:text-white border border-slate-700 hover:border-slate-500 text-sm rounded-lg transition">
                                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                        viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2"
                                                            d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                                    </svg>
                                                    Clear
                                                </button>
                                            @endif
                                        </div>
                                    </form>
                                </div>
                            </div>

                            {{-- Logs Mode --}}
                        @elseif ($mode === 'logs')
                            <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden shadow-2xl flex flex-col h-full"
                                wire:poll.30s="loadLogs">

                                {{-- Log Controls Bar --}}
                                <div
                                    class="flex items-center gap-3 px-4 py-3 bg-slate-900 border-b border-slate-700 shrink-0 flex-wrap">
                                    <div class="flex items-center gap-2">
                                        <label for="log-lines" class="text-xs text-slate-400">Lines:</label>
                                        <select wire:model.live="logLines" id="log-lines"
                                            class="bg-slate-800 border border-slate-600 text-white text-xs rounded-md px-2 py-1 focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                                            <option value="50">50</option>
                                            <option value="100">100</option>
                                            <option value="200">200</option>
                                            <option value="500">500</option>
                                        </select>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <label for="log-level" class="text-xs text-slate-400">Level:</label>
                                        <select wire:model.live="logLevel" id="log-level"
                                            class="bg-slate-800 border border-slate-600 text-white text-xs rounded-md px-2 py-1 focus:ring-1 focus:ring-amber-500 focus:border-amber-500">
                                            <option value="all">All Levels</option>
                                            <option value="emergency">Emergency</option>
                                            <option value="alert">Alert</option>
                                            <option value="critical">Critical</option>
                                            <option value="error">Error</option>
                                            <option value="warning">Warning</option>
                                            <option value="notice">Notice</option>
                                            <option value="info">Info</option>
                                            <option value="debug">Debug</option>
                                        </select>
                                    </div>

                                    <div class="flex items-center gap-2 ml-auto">
                                        <button wire:click="loadLogs" type="button"
                                            class="flex items-center gap-1.5 px-3 py-1.5 text-slate-300 hover:text-white border border-slate-700 hover:border-slate-500 text-xs rounded-lg transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                            Refresh
                                        </button>
                                        <button wire:click="downloadLog" type="button"
                                            class="flex items-center gap-1.5 px-3 py-1.5 text-slate-300 hover:text-white border border-slate-700 hover:border-slate-500 text-xs rounded-lg transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            Download
                                        </button>
                                        <button wire:click="clearLog"
                                            wire:confirm="Are you sure you want to clear the entire log file?"
                                            type="button"
                                            class="flex items-center gap-1.5 px-3 py-1.5 text-red-400 hover:text-red-300 border border-red-500/30 hover:border-red-500/50 text-xs rounded-lg transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            Clear Log
                                        </button>
                                    </div>
                                </div>

                                {{-- Log Table --}}
                                <div class="flex-1 overflow-auto min-h-0">
                                    @if (count($logEntries) === 0)
                                        <div class="text-slate-500 text-center py-16">
                                            <svg class="w-10 h-10 mx-auto mb-3 text-slate-600" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="1.5"
                                                    d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                            </svg>
                                            <p>No log entries found.</p>
                                            <p class="text-xs text-slate-600 mt-1">The log file may be empty or
                                                doesn't
                                                exist yet.</p>
                                        </div>
                                    @else
                                        <table class="w-full text-left">
                                            <thead class="bg-slate-900/50 sticky top-0">
                                                <tr>
                                                    <th
                                                        class="px-4 py-2 text-xs font-semibold text-slate-400 uppercase tracking-wider w-44">
                                                        Timestamp</th>
                                                    <th
                                                        class="px-4 py-2 text-xs font-semibold text-slate-400 uppercase tracking-wider w-24">
                                                        Level</th>
                                                    <th
                                                        class="px-4 py-2 text-xs font-semibold text-slate-400 uppercase tracking-wider">
                                                        Message</th>
                                                </tr>
                                            </thead>
                                            <tbody class="divide-y divide-slate-700/50">
                                                @foreach ($logEntries as $index => $entry)
                                                    <tr class="group hover:bg-slate-700/30 transition-colors"
                                                        x-data="{ expanded: false, copied: false }">
                                                        <td
                                                            class="px-4 py-2 text-xs text-slate-400 font-mono whitespace-nowrap align-top">
                                                            {{ $entry['timestamp'] }}</td>
                                                        <td class="px-4 py-2 align-top">
                                                            @php
                                                                $levelColors = [
                                                                    'emergency' =>
                                                                        'bg-red-500/20 text-red-300 border-red-500/30',
                                                                    'alert' =>
                                                                        'bg-red-500/15 text-red-400 border-red-500/25',
                                                                    'critical' =>
                                                                        'bg-red-500/15 text-red-400 border-red-500/25',
                                                                    'error' =>
                                                                        'bg-red-500/10 text-red-400 border-red-500/20',
                                                                    'warning' =>
                                                                        'bg-amber-500/10 text-amber-400 border-amber-500/20',
                                                                    'notice' =>
                                                                        'bg-blue-500/10 text-blue-400 border-blue-500/20',
                                                                    'info' =>
                                                                        'bg-sky-500/10 text-sky-400 border-sky-500/20',
                                                                    'debug' =>
                                                                        'bg-slate-500/10 text-slate-400 border-slate-500/20',
                                                                ];
                                                                $color =
                                                                    $levelColors[$entry['level']] ??
                                                                    'bg-slate-500/10 text-slate-400 border-slate-500/20';
                                                            @endphp
                                                            <span
                                                                class="inline-block px-2 py-0.5 text-xs font-medium rounded border {{ $color }}">
                                                                {{ strtoupper($entry['level']) }}
                                                            </span>
                                                        </td>
                                                        <td class="px-4 py-2 align-top">
                                                            <div class="flex items-start gap-2">
                                                                <pre x-ref="msg" class="text-xs text-slate-300 whitespace-pre-wrap break-all font-mono cursor-pointer flex-1"
                                                                    @click="expanded = !expanded" x-text="expanded ? @js($entry['message']) : @js(Str::limit($entry['message'], 150))"
                                                                    :title="expanded ? 'Click to collapse' :
                                                                        'Click to expand'"></pre>
                                                                <button type="button"
                                                                    @click.stop="copyText($refs.msg.textContent); copied = true; setTimeout(() => copied = false, 1500)"
                                                                    class="shrink-0 mt-0.5 px-1.5 py-0.5 rounded text-xs border transition opacity-0 group-hover:opacity-100"
                                                                    :class="copied ?
                                                                        'text-green-400 border-green-500/30 bg-green-500/10' :
                                                                        'text-slate-500 border-slate-700 hover:text-slate-300 hover:border-slate-500'"
                                                                    :title="copied ? 'Copied!' : 'Copy to clipboard'">
                                                                    <span x-text="copied ? 'Copied' : 'Copy'"
                                                                        class="font-medium"></span>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    @endif
                                </div>

                                {{-- Log Footer --}}
                                <div
                                    class="border-t border-slate-700 bg-slate-900 px-4 py-2 text-xs text-slate-500 shrink-0">
                                    {{ count($logEntries) }} entries shown
                                    @if ($logLevel !== 'all')
                                        &middot; Filtered: {{ $logLevel }}
                                    @endif
                                    &middot; Auto-refreshes every 30s
                                </div>
                            </div>

                            {{-- Debug Mode --}}
                        @elseif ($mode === 'debug')
                            <div class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden shadow-2xl flex flex-col h-full"
                                wire:poll.3s="loadDebugEntries">

                                {{-- Debug Controls Bar --}}
                                <div
                                    class="flex items-center gap-3 px-4 py-3 bg-slate-900 border-b border-slate-700 shrink-0">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-4 h-4 text-cyan-400" fill="none" stroke="currentColor"
                                            viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                        </svg>
                                        <span class="text-sm font-medium text-cyan-300">Debug Console</span>
                                        <span class="text-xs text-slate-500">&middot; Polls every 3s</span>
                                    </div>

                                    <div class="flex items-center gap-2 ml-auto">
                                        <button wire:click="loadDebugEntries" type="button"
                                            class="flex items-center gap-1.5 px-3 py-1.5 text-slate-300 hover:text-white border border-slate-700 hover:border-slate-500 text-xs rounded-lg transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                            </svg>
                                            Refresh
                                        </button>
                                        <button wire:click="clearDebugEntries" wire:confirm="Clear all debug entries?"
                                            type="button"
                                            class="flex items-center gap-1.5 px-3 py-1.5 text-red-400 hover:text-red-300 border border-red-500/30 hover:border-red-500/50 text-xs rounded-lg transition">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor"
                                                viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                            </svg>
                                            Clear
                                        </button>
                                    </div>
                                </div>

                                {{-- Debug Entries --}}
                                <div class="flex-1 overflow-y-auto p-3 space-y-2 min-h-0">
                                    @if (count($debugEntries) === 0)
                                        <div class="text-slate-500 text-center py-16">
                                            <svg class="w-10 h-10 mx-auto mb-3 text-slate-600" fill="none"
                                                stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    stroke-width="1.5"
                                                    d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                                            </svg>
                                            <p>No debug entries yet.</p>
                                            <p class="text-xs text-slate-600 mt-1">
                                                Use <code
                                                    class="bg-slate-800 px-1.5 py-0.5 rounded text-cyan-400">console('value')</code>
                                                in your code to send debug output here.
                                            </p>
                                        </div>
                                    @else
                                        @foreach ($debugEntries as $entry)
                                            @php
                                                $colorMap = [
                                                    'green' => [
                                                        'border' => 'border-l-green-500',
                                                        'bg' => 'bg-green-500/5',
                                                        'badge' => 'bg-green-500/15 text-green-400 border-green-500/30',
                                                    ],
                                                    'blue' => [
                                                        'border' => 'border-l-blue-500',
                                                        'bg' => 'bg-blue-500/5',
                                                        'badge' => 'bg-blue-500/15 text-blue-400 border-blue-500/30',
                                                    ],
                                                    'orange' => [
                                                        'border' => 'border-l-orange-500',
                                                        'bg' => 'bg-orange-500/5',
                                                        'badge' =>
                                                            'bg-orange-500/15 text-orange-400 border-orange-500/30',
                                                    ],
                                                    'red' => [
                                                        'border' => 'border-l-red-500',
                                                        'bg' => 'bg-red-500/5',
                                                        'badge' => 'bg-red-500/15 text-red-400 border-red-500/30',
                                                    ],
                                                    'purple' => [
                                                        'border' => 'border-l-purple-500',
                                                        'bg' => 'bg-purple-500/5',
                                                        'badge' =>
                                                            'bg-purple-500/15 text-purple-400 border-purple-500/30',
                                                    ],
                                                    'gray' => [
                                                        'border' => 'border-l-slate-500',
                                                        'bg' => 'bg-slate-500/5',
                                                        'badge' => 'bg-slate-500/15 text-slate-400 border-slate-500/30',
                                                    ],
                                                ];
                                                $c = $colorMap[$entry['color'] ?? 'gray'] ?? $colorMap['gray'];
                                            @endphp
                                            <div class="group/debug rounded-lg border border-slate-700/50 {{ $c['bg'] }} border-l-4 {{ $c['border'] }} overflow-hidden"
                                                x-data="{ expanded: false, copied: false }">
                                                {{-- Entry Header --}}
                                                <div class="flex items-center gap-2 px-3 py-2 cursor-pointer"
                                                    @click="expanded = !expanded">
                                                    <svg class="w-3 h-3 text-slate-500 transition-transform"
                                                        :class="expanded && 'rotate-90'" fill="none"
                                                        stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round"
                                                            stroke-width="2" d="M9 5l7 7-7 7" />
                                                    </svg>

                                                    @if ($entry['label'] ?? null)
                                                        <span
                                                            class="inline-block px-2 py-0.5 text-xs font-medium rounded border {{ $c['badge'] }}">
                                                            {{ $entry['label'] }}
                                                        </span>
                                                    @endif

                                                    {{-- Value preview --}}
                                                    <span class="text-sm text-slate-300 font-mono truncate flex-1">
                                                        @foreach ($entry['values'] ?? [] as $i => $val)
                                                            @if ($i > 0)
                                                                <span class="text-slate-600">,</span>
                                                            @endif
                                                            @if ($val['type'] === 'string')
                                                                <span
                                                                    class="text-green-300">"{{ Str::limit($val['value'] ?? '', 80) }}"</span>
                                                            @elseif ($val['type'] === 'integer' || $val['type'] === 'float')
                                                                <span
                                                                    class="text-orange-300">{{ $val['value'] }}</span>
                                                            @elseif ($val['type'] === 'boolean')
                                                                <span
                                                                    class="text-purple-300">{{ $val['value'] ? 'true' : 'false' }}</span>
                                                            @elseif ($val['type'] === 'null')
                                                                <span class="text-slate-500">null</span>
                                                            @elseif ($val['type'] === 'array')
                                                                <span
                                                                    class="text-blue-300">array({{ count($val['value'] ?? []) }})</span>
                                                            @elseif ($val['type'] === 'object')
                                                                <span
                                                                    class="text-cyan-300">{{ $val['value']['class'] ?? 'Object' }}</span>
                                                            @else
                                                                <span
                                                                    class="text-slate-400">{{ Str::limit((string) ($val['value'] ?? ''), 80) }}</span>
                                                            @endif
                                                        @endforeach
                                                    </span>

                                                    {{-- Caller info --}}
                                                    @php
                                                        $callerFile = $entry['caller']['file'] ?? '';
                                                        $callerLine = $entry['caller']['line'] ?? 0;
                                                        $absolutePath = str_replace('/', '\\', base_path($callerFile));
                                                        $ideUrl =
                                                            'vscode://file/' .
                                                            rawurlencode($absolutePath) .
                                                            ':' .
                                                            $callerLine;
                                                    @endphp
                                                    <a href="{{ $ideUrl }}" @click.stop
                                                        class="text-xs text-slate-600 hover:text-cyan-400 whitespace-nowrap shrink-0 transition"
                                                        title="Open in VS Code">
                                                        {{ $callerFile }}:{{ $callerLine }}
                                                    </a>

                                                    <button type="button"
                                                        @click.stop="copyText($refs.debugDetail.textContent); copied = true; setTimeout(() => copied = false, 1500)"
                                                        class="shrink-0 px-1.5 py-0.5 rounded text-xs border transition opacity-0 group-hover/debug:opacity-100"
                                                        :class="copied ?
                                                            'text-green-400 border-green-500/30 bg-green-500/10' :
                                                            'text-slate-500 border-slate-700 hover:text-slate-300 hover:border-slate-500'"
                                                        :title="copied ? 'Copied!' : 'Copy value'">
                                                        <span x-text="copied ? 'Copied' : 'Copy'"
                                                            class="font-medium"></span>
                                                    </button>

                                                    <span class="text-xs text-slate-600 whitespace-nowrap shrink-0">
                                                        {{ \Illuminate\Support\Str::substr($entry['ts'] ?? '', 11, 8) }}
                                                    </span>
                                                </div>

                                                {{-- Expanded Detail --}}
                                                <div x-ref="debugDetail" x-show="expanded" x-collapse
                                                    class="border-t border-slate-700/50 px-3 py-2">
                                                    @foreach ($entry['values'] ?? [] as $val)
                                                        @if (in_array($val['type'], ['array', 'object']))
                                                            @php
                                                                $displayData =
                                                                    $val['type'] === 'object'
                                                                        ? $val['value']['data'] ?? $val['value']
                                                                        : $val['value'];
                                                            @endphp
                                                            @if ($val['type'] === 'object')
                                                                <div class="text-xs text-cyan-400 mb-1 font-medium">
                                                                    {{ $val['value']['class'] ?? 'Object' }}</div>
                                                            @endif

                                                            @if (($entry['type'] ?? 'dump') === 'table' && is_array($displayData))
                                                                <div class="overflow-x-auto">
                                                                    <table class="w-full text-xs">
                                                                        <thead>
                                                                            <tr class="border-b border-slate-700">
                                                                                <th
                                                                                    class="px-2 py-1 text-left text-slate-400 font-semibold">
                                                                                    Key</th>
                                                                                <th
                                                                                    class="px-2 py-1 text-left text-slate-400 font-semibold">
                                                                                    Value</th>
                                                                            </tr>
                                                                        </thead>
                                                                        <tbody>
                                                                            @foreach ($displayData as $key => $value)
                                                                                <tr
                                                                                    class="border-b border-slate-700/30">
                                                                                    <td
                                                                                        class="px-2 py-1 text-cyan-300 font-mono">
                                                                                        {{ $key }}</td>
                                                                                    <td
                                                                                        class="px-2 py-1 text-slate-300 font-mono">
                                                                                        @if (is_array($value))
                                                                                            <pre class="whitespace-pre-wrap">{{ json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                                                                        @else
                                                                                            {{ $value }}
                                                                                        @endif
                                                                                    </td>
                                                                                </tr>
                                                                            @endforeach
                                                                        </tbody>
                                                                    </table>
                                                                </div>
                                                            @else
                                                                <pre class="text-xs text-slate-300 font-mono whitespace-pre-wrap break-all">{{ json_encode($displayData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                                            @endif
                                                        @elseif ($val['type'] === 'string')
                                                            <pre class="text-xs text-green-300 font-mono whitespace-pre-wrap break-all">"{{ $val['value'] }}"</pre>
                                                        @else
                                                            <pre class="text-xs text-slate-300 font-mono">{{ json_encode($val['value'], JSON_PRETTY_PRINT) }}</pre>
                                                        @endif
                                                    @endforeach

                                                    <div
                                                        class="mt-2 pt-2 border-t border-slate-700/30 text-xs text-slate-600">
                                                        @if ($entry['caller']['class'] ?? null)
                                                            {{ $entry['caller']['class'] }}::{{ $entry['caller']['function'] ?? '' }}()
                                                        @elseif ($entry['caller']['function'] ?? null)
                                                            {{ $entry['caller']['function'] }}()
                                                        @endif
                                                        &mdash;
                                                        <a href="{{ $ideUrl }}"
                                                            class="hover:text-cyan-400 transition"
                                                            title="Open in VS Code">
                                                            {{ $entry['caller']['file'] ?? '' }}:{{ $entry['caller']['line'] ?? '' }}
                                                        </a>
                                                    </div>
                                                </div>
                                            </div>
                                        @endforeach
                                    @endif
                                </div>

                                {{-- Debug Footer --}}
                                <div
                                    class="border-t border-slate-700 bg-slate-900 px-4 py-2 text-xs text-slate-500 shrink-0">
                                    {{ count($debugEntries) }} entries &middot; Auto-refreshes every 3s
                                </div>
                            </div>
                        @endif
                    </div>

                    {{-- Right: Unified Side Panel --}}
                    <div class="w-80 shrink-0 hidden lg:flex flex-col">
                        <div
                            class="bg-slate-800 border border-slate-700 rounded-xl overflow-hidden shadow-2xl flex flex-col h-full">

                            @if ($mode !== 'logs' && $mode !== 'debug')
                                {{-- Artisan/Shell: Commands & Deploy Reference --}}
                                <div class="px-4 py-3 bg-slate-900 border-b border-slate-700 shrink-0">
                                    {{-- Tab Toggle --}}
                                    <div class="flex rounded-lg bg-slate-800 p-0.5 border border-slate-700 mb-2">
                                        <button wire:click="$set('refTab', 'commands')"
                                            class="flex-1 px-2 py-1 text-xs font-medium rounded-md transition {{ $refTab === 'commands' ? 'bg-indigo-600 text-white' : 'text-slate-400 hover:text-white' }}">
                                            Commands
                                        </button>
                                        <button wire:click="$set('refTab', 'deploy')"
                                            class="flex-1 px-2 py-1 text-xs font-medium rounded-md transition {{ $refTab === 'deploy' ? 'bg-amber-600 text-white' : 'text-slate-400 hover:text-white' }}">
                                            Deploy
                                        </button>
                                    </div>

                                    {{-- Search (commands tab only) --}}
                                    @if ($refTab === 'commands')
                                        <div class="relative">
                                            <svg class="absolute left-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-slate-500 pointer-events-none"
                                                fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                                            </svg>
                                            <input wire:model.live.debounce.300ms="commandSearch" type="text"
                                                placeholder="Search commands..."
                                                class="w-full pl-8 pr-3 py-1.5 bg-slate-800 border border-slate-600 rounded-md text-xs text-white placeholder-slate-500 focus:ring-1 focus:ring-indigo-500 focus:border-indigo-500">
                                        </div>
                                    @endif
                                </div>

                                @if ($refTab === 'commands')
                                    {{-- Commands List --}}
                                    <div class="p-3 space-y-4 flex-1 overflow-y-auto min-h-0">
                                        @php $filteredCommands = $this->getFilteredCommandReference(); @endphp
                                        @forelse ($filteredCommands as $group => $cmds)
                                            <div>
                                                <h3
                                                    class="text-xs font-semibold uppercase tracking-wider mb-2 px-1 {{ $mode === 'shell' ? 'text-emerald-400' : 'text-indigo-400' }}">
                                                    {{ $group }}</h3>
                                                <div class="space-y-0.5">
                                                    @foreach ($cmds as $cmd)
                                                        <button type="button"
                                                            wire:click="fillCommand('{{ addslashes($cmd['command']) }}')"
                                                            class="cmd-ref-btn w-full text-left px-2.5 py-1.5 rounded-md group"
                                                            title="{{ $cmd['description'] }}">
                                                            <span
                                                                class="text-sm font-mono {{ $mode === 'shell' ? 'text-slate-300 group-hover:text-emerald-300' : 'text-slate-300 group-hover:text-indigo-300' }}">{{ $cmd['command'] }}</span>
                                                            <span
                                                                class="block text-xs text-slate-500 group-hover:text-slate-400 mt-0.5">{{ $cmd['description'] }}</span>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @empty
                                            <div class="text-center py-4 text-xs text-slate-500">
                                                No commands match your search.
                                            </div>
                                        @endforelse
                                    </div>
                                @else
                                    {{-- Deploy Guide --}}
                                    <div class="p-3 space-y-5 flex-1 overflow-y-auto min-h-0">
                                        @foreach ($this->getDeploymentGuide() as $section => $steps)
                                            <div>
                                                <h3
                                                    class="text-xs font-semibold uppercase tracking-wider mb-2.5 px-1 text-amber-400">
                                                    {{ $section }}</h3>
                                                <div class="space-y-1">
                                                    @foreach ($steps as $step)
                                                        <button type="button"
                                                            wire:click="switchMode('{{ $step['mode'] }}'); fillCommand('{{ addslashes($step['command']) }}')"
                                                            class="cmd-ref-btn w-full text-left px-2.5 py-2 rounded-md group">
                                                            <div class="flex items-start gap-2">
                                                                <span
                                                                    class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-amber-500/15 text-amber-400 text-xs font-bold shrink-0 mt-0.5">{{ $step['step'] }}</span>
                                                                <div class="min-w-0">
                                                                    <span
                                                                        class="text-sm font-medium text-slate-200 group-hover:text-amber-300">{{ $step['title'] }}</span>
                                                                    <span
                                                                        class="block text-xs font-mono text-slate-500 group-hover:text-slate-400 mt-0.5 truncate">{{ $step['command'] }}</span>
                                                                    <span
                                                                        class="block text-xs text-slate-600 group-hover:text-slate-500 mt-0.5">{{ $step['description'] }}</span>
                                                                </div>
                                                            </div>
                                                        </button>
                                                    @endforeach
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            @elseif ($mode === 'logs')
                                {{-- Logs Reference --}}
                                <div class="px-4 py-3 bg-slate-900 border-b border-slate-700 shrink-0">
                                    <span class="text-sm font-medium text-amber-300">Log Reference</span>
                                </div>
                                <div class="p-3 space-y-4 flex-1 overflow-y-auto min-h-0">
                                    {{-- Log Levels --}}
                                    <div>
                                        <h3
                                            class="text-xs font-semibold uppercase tracking-wider mb-2 px-1 text-amber-400">
                                            Log Levels</h3>
                                        <div class="space-y-1">
                                            @php
                                                $levels = [
                                                    [
                                                        'name' => 'Emergency',
                                                        'desc' => 'System is unusable',
                                                        'color' => 'text-red-300',
                                                    ],
                                                    [
                                                        'name' => 'Alert',
                                                        'desc' => 'Action must be taken immediately',
                                                        'color' => 'text-red-400',
                                                    ],
                                                    [
                                                        'name' => 'Critical',
                                                        'desc' => 'Critical conditions',
                                                        'color' => 'text-red-400',
                                                    ],
                                                    [
                                                        'name' => 'Error',
                                                        'desc' => 'Runtime errors',
                                                        'color' => 'text-red-400',
                                                    ],
                                                    [
                                                        'name' => 'Warning',
                                                        'desc' => 'Exceptional occurrences',
                                                        'color' => 'text-amber-400',
                                                    ],
                                                    [
                                                        'name' => 'Notice',
                                                        'desc' => 'Normal but significant',
                                                        'color' => 'text-blue-400',
                                                    ],
                                                    [
                                                        'name' => 'Info',
                                                        'desc' => 'Informational messages',
                                                        'color' => 'text-sky-400',
                                                    ],
                                                    [
                                                        'name' => 'Debug',
                                                        'desc' => 'Detailed debug info',
                                                        'color' => 'text-slate-400',
                                                    ],
                                                ];
                                            @endphp
                                            @foreach ($levels as $level)
                                                <div class="px-2.5 py-1.5 rounded-md bg-slate-900/50 flex items-center justify-between group cursor-pointer"
                                                    wire:click="$set('logLevel', '{{ strtolower($level['name']) }}')">
                                                    <span
                                                        class="text-xs font-medium {{ $level['color'] }}">{{ $level['name'] }}</span>
                                                    <span
                                                        class="text-xs text-slate-500 group-hover:text-slate-400">{{ $level['desc'] }}</span>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>

                                    {{-- Quick Filters --}}
                                    <div>
                                        <h3
                                            class="text-xs font-semibold uppercase tracking-wider mb-2 px-1 text-amber-400">
                                            Quick Filters</h3>
                                        <div class="space-y-1">
                                            <button wire:click="$set('logLevel', 'error')"
                                                class="w-full text-left px-2.5 py-1.5 rounded-md bg-slate-900/50 hover:bg-slate-700/50 transition text-xs text-slate-300">Show
                                                only Errors</button>
                                            <button wire:click="$set('logLevel', 'warning')"
                                                class="w-full text-left px-2.5 py-1.5 rounded-md bg-slate-900/50 hover:bg-slate-700/50 transition text-xs text-slate-300">Warnings
                                                & Above</button>
                                            <button wire:click="$set('logLevel', 'all')"
                                                class="w-full text-left px-2.5 py-1.5 rounded-md bg-slate-900/50 hover:bg-slate-700/50 transition text-xs text-slate-300">Reset
                                                to All</button>
                                        </div>
                                    </div>

                                    {{-- Tips --}}
                                    <div>
                                        <h3
                                            class="text-xs font-semibold uppercase tracking-wider mb-2 px-1 text-amber-400">
                                            Tips</h3>
                                        <div
                                            class="px-2.5 py-2 rounded-md bg-slate-900/50 space-y-1.5 text-xs text-slate-400">
                                            <p>Click any message to expand/collapse</p>
                                            <p>Use filters to narrow down issues</p>
                                            <p>Download logs for external analysis</p>
                                            <p>Clear logs periodically to save space</p>
                                        </div>
                                    </div>
                                </div>
                            @elseif ($mode === 'debug')
                                {{-- Debug Reference --}}
                                <div class="px-4 py-3 bg-slate-900 border-b border-slate-700 shrink-0">
                                    <span class="text-sm font-medium text-cyan-300">Quick Reference</span>
                                </div>
                                <div class="p-3 space-y-4 flex-1 overflow-y-auto min-h-0">
                                    {{-- Basic Usage --}}
                                    <div>
                                        <h3
                                            class="text-xs font-semibold uppercase tracking-wider mb-2 px-1 text-cyan-400">
                                            Basic Usage</h3>
                                        <div class="space-y-1.5">
                                            <div class="px-2.5 py-2 rounded-md bg-slate-900/50">
                                                <code class="text-xs font-mono text-green-300">console('Hello
                                                    World');</code>
                                                <span class="block text-xs text-slate-500 mt-1">Send a simple
                                                    string</span>
                                            </div>
                                            <div class="px-2.5 py-2 rounded-md bg-slate-900/50">
                                                <code class="text-xs font-mono text-green-300">console($user,
                                                    $request);</code>
                                                <span class="block text-xs text-slate-500 mt-1">Multiple values at
                                                    once</span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Methods --}}
                                    <div>
                                        <h3
                                            class="text-xs font-semibold uppercase tracking-wider mb-2 px-1 text-cyan-400">
                                            Methods</h3>
                                        <div class="space-y-1">
                                            <div class="px-2.5 py-1.5 rounded-md bg-slate-900/50">
                                                <code class="text-xs font-mono text-cyan-300">->label(string)</code>
                                                <span class="text-xs text-slate-500 ml-2">Tag with a badge</span>
                                            </div>
                                            <div class="px-2.5 py-1.5 rounded-md bg-slate-900/50">
                                                <code class="text-xs font-mono text-cyan-300">->table()</code>
                                                <span class="text-xs text-slate-500 ml-2">Key-value table
                                                    view</span>
                                            </div>
                                            <div class="px-2.5 py-1.5 rounded-md bg-slate-900/50">
                                                <code class="text-xs font-mono text-cyan-300">->color(string)</code>
                                                <span class="text-xs text-slate-500 ml-2">Set entry color</span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Colors --}}
                                    <div>
                                        <h3
                                            class="text-xs font-semibold uppercase tracking-wider mb-2 px-1 text-cyan-400">
                                            Colors</h3>
                                        <div class="space-y-1">
                                            <div
                                                class="px-2.5 py-1.5 rounded-md bg-slate-900/50 border-l-2 border-l-green-500 flex items-center gap-2">
                                                <code class="text-xs font-mono text-green-300">->green()</code>
                                                <span class="text-xs text-slate-500">Success</span>
                                            </div>
                                            <div
                                                class="px-2.5 py-1.5 rounded-md bg-slate-900/50 border-l-2 border-l-blue-500 flex items-center gap-2">
                                                <code class="text-xs font-mono text-blue-300">->blue()</code>
                                                <span class="text-xs text-slate-500">Info</span>
                                            </div>
                                            <div
                                                class="px-2.5 py-1.5 rounded-md bg-slate-900/50 border-l-2 border-l-orange-500 flex items-center gap-2">
                                                <code class="text-xs font-mono text-orange-300">->orange()</code>
                                                <span class="text-xs text-slate-500">Warning</span>
                                            </div>
                                            <div
                                                class="px-2.5 py-1.5 rounded-md bg-slate-900/50 border-l-2 border-l-red-500 flex items-center gap-2">
                                                <code class="text-xs font-mono text-red-300">->red()</code>
                                                <span class="text-xs text-slate-500">Error</span>
                                            </div>
                                            <div
                                                class="px-2.5 py-1.5 rounded-md bg-slate-900/50 border-l-2 border-l-purple-500 flex items-center gap-2">
                                                <code class="text-xs font-mono text-purple-300">->purple()</code>
                                                <span class="text-xs text-slate-500">Special</span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Examples --}}
                                    <div>
                                        <h3
                                            class="text-xs font-semibold uppercase tracking-wider mb-2 px-1 text-cyan-400">
                                            Examples</h3>
                                        <div class="space-y-1.5">
                                            <div class="px-2.5 py-2 rounded-md bg-slate-900/50">
                                                <code
                                                    class="text-xs font-mono text-green-300">console($data)<br>&nbsp;&nbsp;->label('API
                                                    Response')<br>&nbsp;&nbsp;->green();</code>
                                            </div>
                                            <div class="px-2.5 py-2 rounded-md bg-slate-900/50">
                                                <code
                                                    class="text-xs font-mono text-green-300">console($settings)->table();</code>
                                            </div>
                                            <div class="px-2.5 py-2 rounded-md bg-slate-900/50">
                                                <code class="text-xs font-mono text-green-300">console($model);</code>
                                                <span class="block text-xs text-slate-500 mt-1">Objects show class
                                                    +
                                                    properties</span>
                                            </div>
                                        </div>
                                    </div>

                                    {{-- Value Types --}}
                                    <div>
                                        <h3
                                            class="text-xs font-semibold uppercase tracking-wider mb-2 px-1 text-cyan-400">
                                            Value Types</h3>
                                        <div class="grid grid-cols-2 gap-1 text-xs">
                                            <div class="px-2 py-1 rounded bg-slate-900/50"><span
                                                    class="text-green-300 font-mono">string</span></div>
                                            <div class="px-2 py-1 rounded bg-slate-900/50"><span
                                                    class="text-orange-300 font-mono">int/float</span></div>
                                            <div class="px-2 py-1 rounded bg-slate-900/50"><span
                                                    class="text-purple-300 font-mono">bool</span></div>
                                            <div class="px-2 py-1 rounded bg-slate-900/50"><span
                                                    class="text-slate-400 font-mono">null</span></div>
                                            <div class="px-2 py-1 rounded bg-slate-900/50"><span
                                                    class="text-blue-300 font-mono">array</span></div>
                                            <div class="px-2 py-1 rounded bg-slate-900/50"><span
                                                    class="text-cyan-300 font-mono">object</span></div>
                                        </div>
                                    </div>

                                    {{-- Notes --}}
                                    <div>
                                        <h3
                                            class="text-xs font-semibold uppercase tracking-wider mb-2 px-1 text-cyan-400">
                                            Notes</h3>
                                        <div
                                            class="px-2.5 py-2 rounded-md bg-slate-900/50 space-y-1.5 text-xs text-slate-400">
                                            <p>Log: <code
                                                    class="text-cyan-400/70">storage/logs/console-debug.log</code>
                                            </p>
                                            <p>Auto-prunes at 500KB (keeps 200 entries)</p>
                                            <p>Caller file:line captured automatically</p>
                                            <p>Click file links to open in VS Code</p>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                </div>
            </div>
        </div>
    @endif
</div>
