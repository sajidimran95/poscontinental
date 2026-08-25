<?php

namespace App\Http\Controllers;

use App\Services\DocumentTabManager;
use App\Services\SalesOrderWindowManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

class DocumentTabController extends Controller
{
    /**
     * Open a menu page as a document tab (or focus existing), then go there.
     */
    public function open(Request $request, DocumentTabManager $tabs, SalesOrderWindowManager $soWindows): RedirectResponse
    {
        $routeName = (string) $request->query('route', '');
        $label = trim((string) $request->query('label', ''));

        if ($routeName === '' || $routeName === 'home' || ! Route::has($routeName)) {
            return redirect()->route('home');
        }

        if ($routeName === 'sales.orders.create') {
            $openTotal = $soWindows->count() + $tabs->count();
            if ($soWindows->count() >= SalesOrderWindowManager::MAX_WINDOWS
                || ($soWindows->count() === 0 && $openTotal >= DocumentTabManager::MAX_OPEN_WINDOWS)) {
                if ($soWindows->count() > 0) {
                    $id = $soWindows->activeId() ?? $soWindows->list()[0]['id'];

                    return redirect()->route('sales.orders.create', ['w' => $id])
                        ->with('status', 'Maximum of '.DocumentTabManager::MAX_OPEN_WINDOWS.' windows open.');
                }

                return redirect()->route('home')
                    ->with('status', 'Maximum of '.DocumentTabManager::MAX_OPEN_WINDOWS.' windows open. Close one first.');
            }

            if ($soWindows->count() > 0 && $openTotal >= DocumentTabManager::MAX_OPEN_WINDOWS) {
                $id = $soWindows->activeId() ?? $soWindows->list()[0]['id'];

                return redirect()->route('sales.orders.create', ['w' => $id])
                    ->with('status', 'Maximum of '.DocumentTabManager::MAX_OPEN_WINDOWS.' windows open.');
            }

            $id = $soWindows->open();
            if ($id === '') {
                return redirect()->route('home')
                    ->with('status', 'Maximum of '.DocumentTabManager::MAX_OPEN_WINDOWS.' windows open. Close one first.');
            }

            return redirect()->route('sales.orders.create', ['w' => $id]);
        }

        try {
            $url = route($routeName);
        } catch (\Throwable) {
            return redirect()->route('home');
        }

        if ($label === '') {
            $label = str($routeName)->afterLast('.')->headline()->toString();
        }

        $already = collect($tabs->list())->first(fn (array $t) => $t['route'] === $routeName);
        if (! $already && ($soWindows->count() + $tabs->count()) >= DocumentTabManager::MAX_OPEN_WINDOWS) {
            return redirect()->back()
                ->with('status', 'Maximum of '.DocumentTabManager::MAX_OPEN_WINDOWS.' windows open. Close one first.');
        }

        $tabs->openOrFocus($label, $routeName, $url);

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
}
