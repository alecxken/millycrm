<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\PrivacyService;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Subject-access request: download everything held about a customer as JSON. */
class CustomerExportController extends Controller
{
    public function __invoke(Customer $customer, PrivacyService $privacy): StreamedResponse
    {
        $this->authorize('export', $customer);

        activity()->performedOn($customer)->causedBy(auth()->user())->log('exported personal data (subject access request)');

        $filename = 'customer-'.$customer->id.'-data-export-'.now()->format('Ymd').'.json';

        return response()->streamDownload(
            fn () => print (json_encode($privacy->export($customer), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }
}
