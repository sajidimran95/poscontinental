<?php

namespace App\Http\Controllers;

use App\Services\DocumentTabManager;
use App\Services\SalesOrderWindowManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class DocumentTabController extends Controller
{
    /**
     * Open a menu page as a document tab (or focus existing), then go there.
     */
    public function open(Request $request, DocumentTabManager $tabs, SalesOrderWindowManager $soWindows): RedirectResponse|JsonResponse
    {
        $json = $request->expectsJson() || $request->ajax();
        $routeName = (string) $request->query('route', '');
        $label = trim((string) $request->query('label', ''));

        if ($routeName === '' || $routeName === 'home' || ! Route::has($routeName)) {
            return $json
                ? response()->json(['ok' => false, 'home' => true], 422)
                : redirect()->route('home');
        }

        $limitPayload = [
            'ok' => false,
            'limit' => true,
            'message' => DocumentTabManager::tabLimitMessage(),
        ];
        $limitRedirect = fn () => redirect()->back()
            ->with('pos_permission', DocumentTabManager::tabLimitMessage())
            ->with('status', DocumentTabManager::tabLimitMessage());

        if ($routeName === 'sales.orders.create') {
            $openTotal = $soWindows->count() + $tabs->count();
            if ($soWindows->count() >= SalesOrderWindowManager::MAX_WINDOWS
                || $openTotal >= DocumentTabManager::MAX_OPEN_WINDOWS) {
                if ($soWindows->count() > 0) {
                    $id = $soWindows->activeId() ?? $soWindows->list()[0]['id'];
                    $url = route('sales.orders.create', ['w' => $id]);
                    if ($json) {
                        return response()->json([
                            'ok' => false,
                            'limit' => true,
                            'kind' => 'so',
                            'url' => $url,
                            'message' => DocumentTabManager::tabLimitMessage(),
                            'windows' => collect($soWindows->list())->map(fn ($w) => [
                                'kind' => 'so',
                                'id' => $w['id'],
                                'label' => $w['label'],
                                'url' => $w['url'],
                                'close_url' => route('sales.orders.windows.close', $w['id']),
                            ])->values()->all(),
                        ]);
                    }

                    return redirect()->to($url)
                        ->with('pos_permission', DocumentTabManager::tabLimitMessage())
                        ->with('status', DocumentTabManager::tabLimitMessage());
                }

                return $json ? response()->json($limitPayload) : $limitRedirect();
            }

            $id = $soWindows->open();
            if ($id === '') {
                return $json ? response()->json($limitPayload) : $limitRedirect();
            }

            $url = route('sales.orders.create', ['w' => $id]);
            $windowsPayload = collect($soWindows->list())->map(fn ($w) => [
                'kind' => 'so',
                'id' => $w['id'],
                'label' => $w['label'],
                'url' => $w['url'],
                'close_url' => route('sales.orders.windows.close', $w['id']),
            ])->values()->all();
            $window = collect($windowsPayload)->firstWhere('id', $id);
            $windowLabel = $window['label'] ?? 'New Sales Order';

            if ($json) {
                return response()->json([
                    'ok' => true,
                    'kind' => 'so',
                    'id' => $id,
                    'label' => $windowLabel,
                    'url' => $url,
                    'close_url' => route('sales.orders.windows.close', $id),
                    'windows' => $windowsPayload,
                ]);
            }

            return redirect()->to($url);
        }

        try {
            $url = route($routeName);
        } catch (\Throwable) {
            return $json
                ? response()->json(['ok' => false, 'home' => true], 422)
                : redirect()->route('home');
        }

        if ($label === '') {
            $label = str($routeName)->afterLast('.')->headline()->toString();
        }

        $already = collect($tabs->list())->first(fn (array $t) => $tabs->tabFamily($t['route']) === $tabs->tabFamily($routeName));
        if ($already) {
            $tabs->openOrFocus($label, $routeName, $already['url'], false);
            if ($json) {
                return response()->json([
                    'ok' => true,
                    'kind' => 'doc',
                    'already' => true,
                    'id' => $already['id'],
                    'label' => $already['label'] ?? $label,
                    'route' => $routeName,
                    'url' => $already['url'],
                    'close_url' => route('pos.tabs.close', $already['id']),
                ]);
            }

            return redirect()->to($already['url']);
        }

        if (($soWindows->count() + $tabs->count()) >= DocumentTabManager::MAX_OPEN_WINDOWS) {
            return $json ? response()->json($limitPayload) : $limitRedirect();
        }

        $opened = $tabs->openOrFocus($label, $routeName, $url);
        if ($opened === '') {
            return $json ? response()->json($limitPayload) : $limitRedirect();
        }

        $tab = $tabs->find($opened);
        if ($json) {
            return response()->json([
                'ok' => true,
                'kind' => 'doc',
                'already' => false,
                'id' => $opened,
                'label' => $tab['label'] ?? $label,
                'route' => $routeName,
                'url' => $tab['url'] ?? $url,
                'close_url' => route('pos.tabs.close', $opened),
            ]);
        }

        return redirect()->to($url);
    }

    public function close(string $tab, DocumentTabManager $tabs): RedirectResponse
    {
        $nextUrl = $tabs->close($tab);

        if ($nextUrl === '__back__') {
            return redirect()->back();
        }

        if ($nextUrl) {
            return redirect()->to($nextUrl);
        }

        return redirect()->route('home');
    }

    /**
     * Close all document tabs and New Sales Order windows, then go Home.
     */
    public function closeAll(DocumentTabManager $tabs, SalesOrderWindowManager $soWindows): RedirectResponse
    {
        $soWindows->closeAll();
        $tabs->closeAll();

        return redirect()->route('home');
    }

    public function remember(Request $request, DocumentTabManager $tabs): \Illuminate\Http\JsonResponse
    {
        $tabs->rememberUrl((string) $request->input('url', ''));

        return response()->json(['ok' => true]);
    }

    /**
     * Keep list + edit as separate session tabs so both survive a full reload.
     */
    public function ensure(Request $request, DocumentTabManager $tabs): JsonResponse
    {
        $tab = $tabs->ensureFromUrl((string) $request->input('url', ''));
        if (! $tab) {
            return response()->json(['ok' => false]);
        }

        return response()->json([
            'ok' => true,
            'kind' => 'doc',
            'id' => $tab['id'],
            'label' => $tab['label'],
            'route' => $tab['route'],
            'url' => $tab['url'],
            'close_url' => route('pos.tabs.close', $tab['id']),
        ]);
    }
}
