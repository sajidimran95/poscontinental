<?php

namespace App\Http\Controllers;

use App\Services\SalesOrderWindowManager;
use Illuminate\Http\RedirectResponse;

class SalesOrderWindowController extends Controller
{
    public function open(SalesOrderWindowManager $windows): RedirectResponse
    {
        $id = $windows->open();
        if ($id === '') {
            return redirect()->route('home')
                ->with('status', 'Maximum of '.\App\Services\DocumentTabManager::MAX_OPEN_WINDOWS.' windows open. Close one first.');
        }

        return redirect()->route('sales.orders.create', ['w' => $id]);
    }

    public function close(string $window, SalesOrderWindowManager $windows): RedirectResponse
    {
        $next = $windows->close($window);

        if ($next === null) {
            return redirect()->route('home');
        }

        return redirect()->route('sales.orders.create', ['w' => $next]);
    }
}
