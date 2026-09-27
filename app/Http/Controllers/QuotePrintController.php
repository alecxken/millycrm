<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use Illuminate\View\View;

/** Printable, PDF-style quote (use the browser's "Save as PDF"). */
class QuotePrintController extends Controller
{
    public function __invoke(Quote $quote): View
    {
        $quote->load(['items.supplier', 'enquiry.customer', 'enquiry.consultant', 'creator']);
        $this->authorize('view', $quote->enquiry);

        return view('quotes.print', ['quote' => $quote]);
    }
}
