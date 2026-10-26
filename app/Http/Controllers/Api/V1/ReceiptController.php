<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class ReceiptController extends Controller
{
    /**
     * Generate a printable PDF invoice (A4) for a sale.
     */
    public function pdf(Sale $sale): Response
    {
        $sale->load(['items', 'payments', 'customer', 'store', 'user']);

        $pdf = Pdf::loadView('receipts.invoice', [
            'sale' => $sale,
            'store' => $sale->store,
        ])->setPaper('a4');

        return $pdf->download("invoice-{$sale->invoice_no}.pdf");
    }
}
