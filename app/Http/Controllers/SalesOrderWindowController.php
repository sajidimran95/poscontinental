<?php

namespace App\Http\Controllers;

use App\Models\SalesOrder;
use App\Services\SalesOrderWindowManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class SalesOrderWindowController extends Controller
{
    public function open(SalesOrderWindowManager $windows): RedirectResponse
    {
        $id = $windows->open();
        if ($id === '') {
            return redirect()->back()
                ->with('pos_permission', \App\Services\DocumentTabManager::tabLimitMessage())
                ->with('status', \App\Services\DocumentTabManager::tabLimitMessage());
        }

        return redirect()->route('sales.orders.create', ['w' => $id]);
    }

    /**
     * Fast leave from a heavy sales-order screen (many lines).
     * Must NOT go through Livewire — hydrating 600+ lines makes Cancel feel stuck.
     */
    public function leave(Request $request, SalesOrderWindowManager $windows): RedirectResponse
    {
        $companyId = (int) $request->user()->company_id;

        if ($orderId = $request->integer('order')) {
            SalesOrder::query()
                ->where('company_id', $companyId)
                ->whereKey($orderId)
                ->first()
                ?->releaseEditLock($request->user());
        }

        $windowId = trim((string) $request->query('w', ''));
        if ($windowId !== '' && $windows->has($windowId)) {
            $windows->close($windowId);
        }

        if ($request->boolean('to_invoices')) {
            return redirect()->route('sales.invoices.index');
        }

        return redirect()->route('sales.orders.index');
    }

    public function close(string $window, SalesOrderWindowManager $windows, \Illuminate\Http\Request $request): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        $stayOn = $windows->activeId();
        $next = $windows->close($window);

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'windows' => collect($windows->list())->map(fn ($w) => [
                    'kind' => 'so',
                    'id' => $w['id'],
                    'label' => $w['label'],
                    'url' => $w['url'],
                    'close_url' => route('sales.orders.windows.close', $w['id']),
                ])->values()->all(),
            ]);
        }

        if ($next === null) {
            return redirect()->route('home');
        }

        if ($stayOn && $stayOn !== $window && $windows->has($stayOn)) {
            $next = $stayOn;
        }

        return redirect()->route('sales.orders.create', ['w' => $next]);
    }
}
