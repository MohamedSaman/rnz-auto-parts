<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class PrintController extends Controller
{
    public function printSale($id)
    {
        // Load sale with all necessary relationships including returns
        $sale = Sale::with(['customer', 'items.product', 'payments', 'returns' => function ($q) {
            $q->with('product');
        }])->findOrFail($id);

        // Return the print view
        return view('components.sale-receipt-print', compact('sale'));
    }

    public function downloadSale($id)
    {
        // Load sale with all necessary relationships including returns
        $sale = Sale::with(['customer', 'items.product', 'payments', 'returns' => function ($q) {
            $q->with('product');
        }])->findOrFail($id);

        // Generate PDF
        $pdf = PDF::loadView('admin.sales.invoice', compact('sale'));
        $pdf->setPaper('a4', 'portrait');
        $pdf->setOption('dpi', 150);
        $pdf->setOption('defaultFont', 'sans-serif');

        return $pdf->download('invoice-' . $sale->invoice_number . '.pdf');
    }

    public function printQuotation($id)
    {
        // Load quotation with all necessary relationships
        $quotation = Quotation::findOrFail($id);

        // Decode items if stored as JSON
        if (is_string($quotation->items)) {
            $quotation->items = json_decode($quotation->items, true);
        }

        // Return the print view
        return view('admin.quotations.print', compact('quotation'));
    }
}
