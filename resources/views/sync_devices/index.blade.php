<x-app-layout>
    <div class="p-4 md:p-6 space-y-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Desktop devices</h1>
            <p class="text-sm text-slate-600">PCs running the offline desktop app. Each device gets a token for sync.</p>
        </div>

        @if (session('status'))
            <div class="rounded border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif

        @if (! empty($new_token))
            <div class="rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 space-y-2">
                <div class="font-semibold">Device token for "{{ $new_token['name'] }}" (node {{ $new_token['node_no'] }})</div>
                <p>Copy it now — it is shown <strong>only once</strong>. Enter it on that PC in the <strong>Connect this PC</strong> screen.</p>
                <div class="flex gap-2 max-w-3xl">
                    <input type="text" id="sync_device_token" class="flex-1 rounded border border-slate-300 px-3 py-2 font-mono text-sm" value="{{ $new_token['token'] }}" readonly onclick="this.select()">
                    <button type="button" id="copy_sync_device_token" class="rounded bg-slate-800 px-3 py-2 text-white text-sm">Copy</button>
                </div>
                <p>Server address to enter on the PC: <strong>{{ url('/') }}</strong></p>
            </div>
        @endif

        <div class="rounded border border-slate-300 bg-white p-4 space-y-3">
            <h2 class="font-semibold text-slate-800">Add a desktop PC</h2>
            @if ($next_node_no)
                <form method="POST" action="{{ route('admin.desktop-devices.store') }}" class="grid gap-3 md:grid-cols-12 items-end">
                    @csrf
                    <div class="md:col-span-5">
                        <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Device name *</label>
                        <input type="text" name="name" id="name" required maxlength="191" value="{{ old('name') }}"
                            placeholder="e.g. Main shop PC{{ $next_node_no }}"
                            class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        @error('name')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-3">
                        <label for="invoice_prefix" class="block text-sm font-medium text-slate-700 mb-1">Invoice prefix</label>
                        <input type="text" name="invoice_prefix" id="invoice_prefix" maxlength="20" value="{{ old('invoice_prefix') }}"
                            placeholder="PC{{ $next_node_no }}"
                            class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                        <p class="text-xs text-slate-500 mt-1">Added to order/invoice numbers on this PC.</p>
                        @error('invoice_prefix')<p class="text-xs text-red-600 mt-1">{{ $message }}</p>@enderror
                    </div>
                    <div class="md:col-span-4">
                        <button type="submit" class="rounded bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            Add device &amp; create token
                        </button>
                    </div>
                </form>
            @else
                <p class="text-sm text-red-600">No free device numbers left. Disable unused devices, or raise SYNC_ID_STEP.</p>
            @endif
        </div>

        <div class="rounded border border-slate-300 bg-white overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-100 text-left">
                    <tr>
                        <th class="px-3 py-2">Node</th>
                        <th class="px-3 py-2">Name</th>
                        <th class="px-3 py-2">Invoice prefix</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2">Last seen</th>
                        <th class="px-3 py-2">Last sent / received</th>
                        <th class="px-3 py-2">App version</th>
                        <th class="px-3 py-2">IP</th>
                        <th class="px-3 py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($devices as $device)
                        <tr class="border-t border-slate-200">
                            <td class="px-3 py-2">{{ $device->node_no }}</td>
                            <td class="px-3 py-2">{{ $device->name }}</td>
                            <td class="px-3 py-2">{{ $device->invoice_prefix }}</td>
                            <td class="px-3 py-2">
                                @if ($device->is_active)
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-emerald-800 text-xs">Active</span>
                                @else
                                    <span class="rounded bg-slate-200 px-2 py-0.5 text-slate-700 text-xs">Disabled</span>
                                @endif
                            </td>
                            <td class="px-3 py-2">{{ $device->last_seen_at ?: 'never' }}</td>
                            <td class="px-3 py-2">{{ $device->last_push_at ?: '-' }} / {{ $device->last_pull_at ?: '-' }}</td>
                            <td class="px-3 py-2">{{ $device->app_version ?: '-' }}</td>
                            <td class="px-3 py-2">{{ $device->last_ip ?: '-' }}</td>
                            <td class="px-3 py-2 whitespace-nowrap space-x-1">
                                <form method="POST" class="inline" action="{{ route('admin.desktop-devices.token', $device->node_no) }}"
                                    onsubmit="return confirm('Create a new token for &quot;{{ $device->name }}&quot;? The current token stops working.');">
                                    @csrf
                                    <button type="submit" class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50">New token</button>
                                </form>
                                <form method="POST" class="inline" action="{{ route('admin.desktop-devices.toggle', $device->node_no) }}"
                                    @if ($device->is_active) onsubmit="return confirm('Disable &quot;{{ $device->name }}&quot;? It can no longer sync until enabled again.');" @endif>
                                    @csrf
                                    @if ($device->is_active)
                                        <button type="submit" class="rounded border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50">Disable</button>
                                    @else
                                        <button type="submit" class="rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">Enable</button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-3 py-6 text-center text-slate-500">No desktop PCs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <script>
        document.getElementById('copy_sync_device_token')?.addEventListener('click', function () {
            var input = document.getElementById('sync_device_token');
            if (!input) return;
            input.select();
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(input.value);
            } else {
                document.execCommand('copy');
            }
        });
    </script>
</x-app-layout>
