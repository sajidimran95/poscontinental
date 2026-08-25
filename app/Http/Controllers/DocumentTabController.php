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
            $id = $soWindows->open();

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
}
