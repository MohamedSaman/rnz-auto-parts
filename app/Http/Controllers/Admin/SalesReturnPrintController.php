<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SalesReturn;
use Illuminate\Http\Request;

class SalesReturnPrintController extends Controller
{
    public function show(Request $request, SalesReturn $salesReturn)
    {
        $salesReturn->load(['sale.customer', 'items.product']);
        $paper = $request->string('paper')->toString() ?: 'a4';

        return view('admin.sales-returns.print', [
            'salesReturn' => $salesReturn,
            'paper' => in_array($paper, ['a4', 'thermal'], true) ? $paper : 'a4',
        ]);
    }
}
