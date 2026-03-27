<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PurchaseReturn;
use Illuminate\Http\Request;

class PurchaseReturnPrintController extends Controller
{
    public function show(Request $request, PurchaseReturn $purchaseReturn)
    {
        $purchaseReturn->load(['supplier', 'purchaseOrder', 'items.product']);
        $paper = $request->string('paper')->toString() ?: 'a4';

        return view('admin.purchase-returns.print', [
            'purchaseReturn' => $purchaseReturn,
            'paper' => in_array($paper, ['a4', 'thermal'], true) ? $paper : 'a4',
        ]);
    }
}
