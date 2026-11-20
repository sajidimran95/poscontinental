<x-app-layout>
    <div class="p-4 md:p-6 space-y-4">
        <div>
            <h1 class="text-xl font-semibold text-slate-900">Receipt printer <span class="text-sm font-normal text-slate-500">this PC</span></h1>
            <p class="text-sm text-slate-600">
                With direct printing on, every <strong>Print</strong> button goes straight to the printer chosen here, without
                the print dialog. This setting is only for this PC. Install the printer in Windows first
                (Settings → Printers &amp; scanners) and check that a Windows test page prints.
            </p>
        </div>

        @if ($msg = session('printer_msg'))
            <div class="rounded border px-3 py-2 text-sm {{ $msg['ok'] ? 'border-emerald-300 bg-emerald-50 text-emerald-900' : 'border-red-300 bg-red-50 text-red-900' }}">{{ $msg['text'] }}</div>
        @endif
        @if ($error)
            <div class="rounded border border-amber-300 bg-amber-50 px-3 py-2 text-sm text-amber-950">{{ $error }}</div>
        @endif

        <form method="POST" action="{{ route('desktop.printer.save') }}" class="rounded border border-slate-300 bg-white p-4 space-y-4 max-w-2xl">
            @csrf
            <div>
                <label for="printer" class="block text-sm font-medium text-slate-700 mb-1">Printer</label>
                <select id="printer" name="printer" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    <option value="">-- choose a printer --</option>
                    @foreach ($printers as $p)
                        <option value="{{ $p['name'] }}" @selected($settings['printer'] === $p['name'])>
                            {{ $p['label'] }}{{ $p['default'] ? ' (Windows default)' : '' }}
                        </option>
                    @endforeach
                    @if ($settings['printer'] !== '' && ! collect($printers)->contains('name', $settings['printer']))
                        <option value="{{ $settings['printer'] }}" selected>{{ $settings['printer'] }} (not found)</option>
                    @endif
                </select>
                @if (empty($printers) && ! $error)
                    <p class="text-xs text-red-600 mt-1">No printers found. Install the printer in Windows, then reload this page.</p>
                @endif
            </div>

            <div>
                <label for="paper" class="block text-sm font-medium text-slate-700 mb-1">Paper size</label>
                <select id="paper" name="paper" class="w-full rounded border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($papers as $key => $label)
                        <option value="{{ $key }}" @selected($settings['paper'] === $key)>{{ $label }}</option>
                    @endforeach
                </select>
                <p class="text-xs text-slate-500 mt-1">Keep "Printer default" unless pages come out the wrong size.</p>
            </div>

            <label class="flex items-center gap-2 text-sm">
                <input type="checkbox" name="enabled" value="1" @checked($settings['enabled']) class="rounded border-slate-300">
                <strong>Print directly (no print dialog)</strong>
            </label>

            <div class="flex gap-2">
                <button type="submit" name="action" value="save" class="rounded bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Save</button>
                <button type="submit" name="action" value="test" class="rounded border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Save and print test page</button>
            </div>
        </form>
    </div>
</x-app-layout>
