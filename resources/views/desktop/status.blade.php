<x-app-layout>
    <div class="p-4 md:p-6 space-y-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Sync status</h1>
            <p class="text-sm text-slate-600">This PC works offline and syncs with the online POS every minute.</p>
        </div>

        @php
            $last = $status['last_run'] ?? null;
            $result = session('sync_result');
        @endphp

        @if ($result)
            <div class="rounded border px-3 py-2 text-sm {{ !empty($result['error']) ? 'border-red-300 bg-red-50 text-red-900' : 'border-emerald-300 bg-emerald-50 text-emerald-900' }}">
                {{ $result['error'] ?: 'Synced: '.$result['pushed'].' sent, '.$result['pulled'].' received.' }}
            </div>
        @endif

        <div class="rounded border border-slate-300 bg-white p-4">
            <table class="text-sm w-full max-w-3xl">
                <tr class="border-b border-slate-100"><th class="py-2 pr-4 text-left w-64 text-slate-600 font-medium">This PC</th><td>{{ $status['node']['name'] ?? '-' }} (node {{ $status['node']['node_no'] ?? '-' }}, prefix {{ $status['node']['invoice_prefix'] ?? '-' }})</td></tr>
                <tr class="border-b border-slate-100"><th class="py-2 pr-4 text-left text-slate-600 font-medium">Server</th><td>{{ $status['node']['server_url'] ?? '-' }}</td></tr>
                <tr class="border-b border-slate-100"><th class="py-2 pr-4 text-left text-slate-600 font-medium">Last successful sync</th><td>{{ $status['last_success_at'] ?? 'never' }}</td></tr>
                <tr class="border-b border-slate-100"><th class="py-2 pr-4 text-left text-slate-600 font-medium">Last attempt</th><td>
                    {{ $last['at'] ?? '-' }}
                    @if (! empty($last['error']))
                        <span class="text-red-600">— {{ $last['error'] }}</span>
                    @elseif ($last)
                        <span class="text-emerald-700">— OK ({{ $last['pushed'] }} sent, {{ $last['pulled'] }} received)</span>
                    @endif
                </td></tr>
                <tr class="border-b border-slate-100"><th class="py-2 pr-4 text-left text-slate-600 font-medium">Changes waiting</th><td><strong>{{ $status['pending_changes'] }}</strong></td></tr>
                <tr><th class="py-2 pr-4 text-left text-slate-600 font-medium">Unresolved conflicts</th><td>{{ $status['conflicts'] }}</td></tr>
            </table>

            <div class="mt-4 flex gap-2">
                <form method="POST" action="{{ route('desktop.sync_now') }}">
                    @csrf
                    <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Sync now</button>
                </form>
                <a href="{{ route('desktop.printer') }}" class="rounded border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Receipt printer</a>
            </div>
        </div>

        @if ($conflicts->isNotEmpty())
            <div class="rounded border border-amber-300 bg-white overflow-x-auto">
                <div class="px-4 py-3 border-b border-amber-200 bg-amber-50 font-semibold text-amber-950">Records that could not be applied</div>
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left">
                        <tr>
                            <th class="px-3 py-2">When</th>
                            <th class="px-3 py-2">Direction</th>
                            <th class="px-3 py-2">Table</th>
                            <th class="px-3 py-2">Key</th>
                            <th class="px-3 py-2">Error</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($conflicts as $c)
                            <tr class="border-t border-slate-200">
                                <td class="px-3 py-2">{{ $c->created_at }}</td>
                                <td class="px-3 py-2">{{ $c->direction }}</td>
                                <td class="px-3 py-2">{{ $c->table_name }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $c->pk }}</td>
                                <td class="px-3 py-2 text-xs">{{ \Illuminate\Support\Str::limit($c->error, 300) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <form method="POST" action="{{ route('desktop.resolve_conflicts') }}" class="p-3">
                    @csrf
                    <button type="submit" class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-slate-50">Mark all as reviewed</button>
                </form>
            </div>
        @endif
    </div>
</x-app-layout>
