<?php

namespace App\Http\Controllers;

use App\Services\ReportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportExportController extends Controller
{
    public function __invoke(Request $request, ReportService $reports): StreamedResponse
    {
        $data = $request->validate([
            'entity' => ['required', 'in:'.implode(',', array_keys(ReportService::ENTITIES))],
            'group_by' => ['required', 'string'],
            'filters' => ['array'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $result = $reports->adHoc($data['entity'], $data['group_by'], $data['filters'] ?? [], $data['from'] ?? null, $data['to'] ?? null);
        $groupLabel = ReportService::ENTITIES[$data['entity']]['group_by'][$data['group_by']];
        $valueLabel = ['customers' => 'Lifetime value (KES)', 'enquiries' => 'Expected value (KES)', 'bookings' => 'Revenue (KES)'][$data['entity']];

        return response()->streamDownload(function () use ($result, $groupLabel, $valueLabel) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [$groupLabel, 'Count', $valueLabel]);
            foreach ($result['rows'] as $row) {
                fputcsv($out, [$row['label'], $row['count'], $row['value']]);
            }
            fputcsv($out, ['Total', $result['total_count'], $result['total_value']]);
            fclose($out);
        }, "wanderlink-{$data['entity']}-by-{$data['group_by']}-".now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv']);
    }
}
