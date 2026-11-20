<?php

namespace App\Http\Controllers;

use App\Sync\Desktop\DesktopPrinter;
use Illuminate\Http\Request;

/**
 * Desktop app: receipt printer of this PC (direct printing without the print dialog).
 */
class DesktopPrintController extends Controller
{
    public function index()
    {
        $printers = [];
        $error = null;
        if (DesktopPrinter::available()) {
            try {
                $printers = DesktopPrinter::printers();
            } catch (\Throwable $e) {
                $error = $e->getMessage();
            }
        } else {
            $error = 'Direct printing is only available inside the desktop app.';
        }

        return view('desktop.printer', [
            'settings' => DesktopPrinter::settings(),
            'printers' => $printers,
            'papers' => DesktopPrinter::PAPER,
            'error' => $error,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'printer' => 'nullable|string|max:255',
            'paper' => 'nullable|string|max:20',
        ]);
        DesktopPrinter::save([
            'enabled' => $request->boolean('enabled'),
            'printer' => (string) $request->input('printer', ''),
            'paper' => (string) $request->input('paper', 'default'),
        ]);

        if ($request->input('action') === 'test') {
            try {
                DesktopPrinter::print(view('desktop.printer_test', ['printer' => $request->input('printer')])->render());

                return redirect()->route('desktop.printer')->with('printer_msg', ['ok' => true, 'text' => 'Test page sent to the printer.']);
            } catch (\Throwable $e) {
                return redirect()->route('desktop.printer')->with('printer_msg', ['ok' => false, 'text' => $e->getMessage()]);
            }
        }

        return redirect()->route('desktop.printer')->with('printer_msg', ['ok' => true, 'text' => 'Printer settings saved.']);
    }

    /** Receipt HTML from the POS screen, printed without a dialog. */
    public function print(Request $request)
    {
        $request->validate(['html' => 'required|string']);
        if (! DesktopPrinter::directEnabled()) {
            return response()->json(['success' => false, 'message' => 'Direct printing is off.'], 409);
        }
        try {
            $copies = max(1, min(3, (int) $request->input('copies', 1)));
            for ($i = 0; $i < $copies; $i++) {
                DesktopPrinter::print($request->input('html'));
            }
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }

        return response()->json(['success' => true]);
    }
}
