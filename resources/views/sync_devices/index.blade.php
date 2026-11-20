<x-app-layout>
    <div class="p-4 md:p-6 space-y-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Desktop devices</h1>
            <p class="text-sm text-slate-600">
                PCs running the offline desktop app. Each PC gets its own <strong>desktop key</strong> (to connect and sync)
                and <strong>desktop prefix</strong> (added to order / invoice numbers made on that PC, e.g. <code>PC2-100045</code>).
            </p>
        </div>

        @if (session('status'))
            <div class="rounded border border-emerald-300 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="rounded border border-red-300 bg-red-50 px-3 py-2 text-sm text-red-900">{{ $errors->first() }}</div>
        @endif

        @if (! empty($new_token))
            <div class="rounded border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950 space-y-2">
                <div class="font-semibold">Desktop key for "{{ $new_token['name'] }}" (node {{ $new_token['node_no'] }})</div>
                <p>Copy it now: it is shown <strong>only once</strong>. On that PC, open the desktop app and enter both values below in the <strong>Connect this PC</strong> screen.</p>
                <div class="grid gap-2 max-w-3xl">
                    <label class="text-xs font-medium text-amber-900">Online server address</label>
                    <div class="flex gap-2">
                        <input type="text" id="sync_server_url" class="flex-1 rounded border border-slate-300 bg-white px-3 py-2 font-mono text-sm" value="{{ url('/') }}" readonly onclick="this.select()">
                        <button type="button" data-copy="sync_server_url" class="rounded bg-slate-800 px-3 py-2 text-white text-sm">Copy</button>
                    </div>
                    <label class="text-xs font-medium text-amber-900">Desktop key</label>
                    <div class="flex gap-2">
                        <input type="text" id="sync_device_token" class="flex-1 rounded border border-slate-300 bg-white px-3 py-2 font-mono text-sm" value="{{ $new_token['token'] }}" readonly onclick="this.select()">
                        <button type="button" data-copy="sync_device_token" class="rounded bg-slate-800 px-3 py-2 text-white text-sm">Copy</button>
                    </div>
                </div>
            </div>
        @endif

        <div class="rounded border border-slate-300 bg-white p-4 space-y-3">
            <h2 class="font-semibold text-slate-800">Add a desktop PC</h2>
            @if ($next_node_no)
                <form method="POST" action="{{ route('admin.desktop-devices.store') }}" class="grid gap-3 md:grid-cols-12 items-start">
                    @csrf
                    <div class="md:col-span-5">
                        <label for="name" class="block text-sm font-medium text-slate-700 mb-1">Device name *</label>
                        <input type="text" name="name" id="name" required maxlength="191" value="{{ old('name') }}"
                            placeholder="e.g. Main shop PC{{ $next_node_no }}"
                            class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="md:col-span-4">
                        <label for="invoice_prefix" class="block text-sm font-medium text-slate-700 mb-1">Desktop prefix</label>
                        <div class="flex gap-2">
                            <input type="text" name="invoice_prefix" id="invoice_prefix" maxlength="20" value="{{ old('invoice_prefix') }}"
                                placeholder="{{ $suggested_prefix }}" pattern="[A-Za-z0-9_\-]+"
                                class="w-full rounded border border-slate-300 px-3 py-2 text-sm uppercase">
                            <button type="button" id="generate_prefix" data-prefix="{{ $suggested_prefix }}"
                                class="rounded border border-slate-300 px-3 py-2 text-sm whitespace-nowrap hover:bg-slate-50">Generate</button>
                        </div>
                        <p class="text-xs text-slate-500 mt-1">Letters, numbers, - and _. Empty = {{ $suggested_prefix }}.</p>
                    </div>
                    <div class="md:col-span-3 md:pt-6">
                        <button type="submit" class="w-full rounded bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            Add device &amp; generate key
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
                        <th class="px-3 py-2">Prefix</th>
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
                            <td class="px-3 py-2 font-mono">{{ $device->invoice_prefix }}</td>
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
                                <button type="button" data-edit="{{ $device->node_no }}"
                                    class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50">Edit</button>
                                <form method="POST" class="inline" action="{{ route('admin.desktop-devices.token', $device->node_no) }}"
                                    onsubmit="return confirm('Generate a new desktop key for &quot;{{ e($device->name) }}&quot;? The current key stops working and the PC must be connected again with the new key.');">
                                    @csrf
                                    <button type="submit" class="rounded border border-slate-300 px-2 py-1 text-xs hover:bg-slate-50">New key</button>
                                </form>
                                <form method="POST" class="inline" action="{{ route('admin.desktop-devices.toggle', $device->node_no) }}"
                                    @if ($device->is_active) onsubmit="return confirm('Disable &quot;{{ e($device->name) }}&quot;? It can no longer sync until enabled again.');" @endif>
                                    @csrf
                                    @if ($device->is_active)
                                        <button type="submit" class="rounded border border-red-300 px-2 py-1 text-xs text-red-700 hover:bg-red-50">Disable</button>
                                    @else
                                        <button type="submit" class="rounded border border-emerald-300 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50">Enable</button>
                                    @endif
                                </form>
                            </td>
                        </tr>
                        <tr id="edit-row-{{ $device->node_no }}" class="hidden bg-slate-50">
                            <td colspan="9" class="px-3 py-3">
                                <form method="POST" action="{{ route('admin.desktop-devices.update', $device->node_no) }}" class="flex flex-wrap items-end gap-3">
                                    @csrf
                                    <div>
                                        <label class="block text-xs font-medium text-slate-700 mb-1">Device name</label>
                                        <input type="text" name="name" required maxlength="191" value="{{ $device->name }}"
                                            class="w-64 rounded border border-slate-300 px-3 py-1.5 text-sm">
                                    </div>
                                    <div>
                                        <label class="block text-xs font-medium text-slate-700 mb-1">Desktop prefix</label>
                                        <input type="text" name="invoice_prefix" required maxlength="20" pattern="[A-Za-z0-9_\-]+" value="{{ $device->invoice_prefix }}"
                                            class="w-40 rounded border border-slate-300 px-3 py-1.5 text-sm font-mono uppercase">
                                    </div>
                                    <button type="submit" class="rounded bg-indigo-600 px-3 py-1.5 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
                                    <button type="button" data-edit="{{ $device->node_no }}" class="rounded border border-slate-300 px-3 py-1.5 text-sm hover:bg-white">Cancel</button>
                                    <p class="w-full text-xs text-slate-500">A new prefix is used for new documents on that PC after its next sync. Existing document numbers do not change.</p>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-3 py-6 text-center text-slate-500">No desktop PCs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded border border-slate-200 bg-white p-4 text-sm text-slate-700 space-y-1">
            <div class="font-semibold text-slate-800">Connecting a PC</div>
            <ol class="list-decimal pl-5 space-y-0.5">
                <li>Install the desktop app (<code>{{ config('app.name') }} Setup.exe</code>) on the PC.</li>
                <li>Add the PC above and copy its desktop key.</li>
                <li>Start the app, enter the server address <code>{{ url('/') }}</code> and the key, click <em>Connect and download data</em>.</li>
                <li>Log in with the usual account. The PC works offline and syncs every minute when online.</li>
            </ol>
        </div>
    </div>

    <script>
        (function () {
            document.querySelectorAll('[data-copy]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var input = document.getElementById(btn.getAttribute('data-copy'));
                    if (!input) return;
                    input.select();
                    var done = function () { var t = btn.textContent; btn.textContent = 'Copied'; setTimeout(function () { btn.textContent = t; }, 1500); };
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(input.value).then(done);
                    } else {
                        document.execCommand('copy');
                        done();
                    }
                });
            });
            document.getElementById('generate_prefix')?.addEventListener('click', function () {
                document.getElementById('invoice_prefix').value = this.getAttribute('data-prefix');
            });
            document.querySelectorAll('[data-edit]').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    document.getElementById('edit-row-' + btn.getAttribute('data-edit'))?.classList.toggle('hidden');
                });
            });
        })();
    </script>
</x-app-layout>
