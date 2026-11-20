<?php

namespace App\Http\Controllers;

use App\Sync\SyncDevices;
use App\Sync\SyncNode;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

/**
 * Server (SYNC_MODE=server): File → Desktop devices.
 * Register offline desktop PCs and create their sync tokens. Admins only:
 * a device token gives full read/write access to the business data.
 */
class SyncDeviceController extends Controller
{
    public static function allowed($user = null): bool
    {
        $user = $user ?: auth()->user();

        return SyncNode::isServer() && $user && $user->isAdmin();
    }

    protected function authorizeAccess(): void
    {
        if (! self::allowed()) {
            abort(403, 'Unauthorized action.');
        }
    }

    public function index()
    {
        $this->authorizeAccess();

        $next = SyncDevices::freeNodeNo();

        return view('sync_devices.index', [
            'devices' => SyncDevices::all(),
            'next_node_no' => $next,
            'suggested_prefix' => $next ? SyncDevices::suggestPrefix($next) : '',
            'new_token' => session('sync_device_token'),
        ]);
    }

    /** Rename a device or change its document prefix (applied on the PC at its next sync). */
    public function update(Request $request, int $node_no)
    {
        $this->authorizeAccess();

        $device = SyncDevices::find($node_no);
        if (! $device) {
            return back()->with('status', 'Device not found.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'invoice_prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('sync_nodes', 'invoice_prefix')->ignore($node_no, 'node_no')],
        ]);

        SyncDevices::update($node_no, $data['name'], $data['invoice_prefix']);

        $msg = 'Device "'.$data['name'].'" saved.';
        if (strtoupper($data['invoice_prefix']) !== strtoupper((string) $device->invoice_prefix)) {
            $msg .= ' New documents on that PC use prefix '.strtoupper($data['invoice_prefix']).'- after its next sync.';
        }

        return redirect()->route('admin.desktop-devices')->with('status', $msg);
    }

    public function store(Request $request)
    {
        $this->authorizeAccess();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'invoice_prefix' => ['nullable', 'string', 'max:20', 'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('sync_nodes', 'invoice_prefix')],
        ]);

        try {
            $device = SyncDevices::add($data['name'], $data['invoice_prefix'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withInput()->with('status', $e->getMessage());
        }

        return redirect()->route('admin.desktop-devices')
            ->with('sync_device_token', [
                'name' => $data['name'],
                'node_no' => $device['node_no'],
                'token' => $device['token'],
            ])
            ->with('status', 'Device "'.$data['name'].'" added.');
    }

    public function newToken(int $node_no)
    {
        $this->authorizeAccess();

        $device = SyncDevices::find($node_no);
        $token = $device ? SyncDevices::newToken($node_no) : null;
        if (! $token) {
            return back()->with('status', 'Device not found.');
        }

        return redirect()->route('admin.desktop-devices')
            ->with('sync_device_token', ['name' => $device->name, 'node_no' => $node_no, 'token' => $token])
            ->with('status', 'New token created for "'.$device->name.'". The old token no longer works.');
    }

    public function toggle(int $node_no)
    {
        $this->authorizeAccess();

        $device = SyncDevices::find($node_no);
        if (! $device) {
            return back()->with('status', 'Device not found.');
        }

        SyncDevices::setActive($node_no, ! $device->is_active);

        return back()->with('status',
            '"'.$device->name.'" '.($device->is_active ? 'disabled: it can no longer sync.' : 'enabled.')
        );
    }
}
