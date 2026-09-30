<?php

namespace App\Http\Controllers;

use App\Models\EcomCustomerLicense;
use Illuminate\Support\Facades\Storage;

class EcommerceLicenseFileController extends Controller
{
    public function show(EcomCustomerLicense $license)
    {
        $license->loadMissing('customer');
        abort_unless((int) $license->customer?->company_id === (int) auth()->user()->company_id, 404);
        abort_unless($license->file_path && Storage::disk('local')->exists($license->file_path), 404);

        return Storage::disk('local')->response($license->file_path, $license->original_name ?: basename($license->file_path));
    }
}
