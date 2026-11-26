<?php

namespace App\Http\Controllers;

use App\Enums\RequisitionStatus;
use App\Enums\TransactionType;
use App\Models\ApplicationSetting;
use App\Services\AuditLogger;
use App\Services\InventoryNotifier;
use App\Services\ReportService;
use App\Support\PdfRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $reports = collect(ReportService::REPORTS)->filter(fn ($r) => $request->user()->can($r[2]));

        return view('reports.index', ['reports' => $reports]);
    }

    public function show(Request $request, string $report, ReportService $service): Response|View
    {
        abort_unless(array_key_exists($report, ReportService::REPORTS), 404);
        Gate::authorize(ReportService::REPORTS[$report][2]);

        $filters = $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'item' => ['nullable', 'integer'],
            'stock_number' => ['nullable', 'string', 'max:50'],
            'category' => ['nullable', 'integer'],
            'office' => ['nullable', 'integer'],
            'user' => ['nullable', 'integer'],
            'type' => ['nullable', Rule::enum(TransactionType::class)],
            'status' => ['nullable', Rule::enum(RequisitionStatus::class)],
            'format' => ['nullable', 'in:html,print,pdf,csv'],
        ]);
        $format = $filters['format'] ?? 'html';
        unset($filters['format']);

        $data = [
            ...$service->build($report, $filters),
            'key' => $report,
            'filters' => $filters,
            'settings' => ApplicationSetting::allWithDefaults(),
            'generatedBy' => $request->user(),
            'generatedAt' => now(),
        ];

        if ($format !== 'html') {
            AuditLogger::log('report.exported', "{$data['title']} exported as ".strtoupper($format));
        }

        return match ($format) {
            'print' => view('reports.print', $data),
            'pdf' => PdfRenderer::make('reports.pdf', $data, $data['landscape'])->download("{$report}-".now()->format('Ymd-His').'.pdf'),
            'csv' => $this->csv($data, $report),
            default => view('reports.show', [...$data, 'options' => ReportService::filterOptions(), 'adminEmails' => app(InventoryNotifier::class)->administratorEmails()]),
        };
    }

    private function csv(array $data, string $report): Response
    {
        return response()->streamDownload(function () use ($data) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel detects the encoding
            fputcsv($out, [$data['title']]);
            fputcsv($out, ['Generated', $data['generatedAt']->format('Y-m-d H:i'), 'By', $data['generatedBy']->name]);
            fputcsv($out, []);
            fputcsv($out, array_values($data['columns']));
            foreach ($data['rows'] as $row) {
                fputcsv($out, array_map(fn ($k) => self::csvSafe($row[$k] ?? ''), array_keys($data['columns'])));
            }
            fclose($out);
        }, "{$report}-".now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Neutralize spreadsheet formula injection (cells beginning with = + - @). */
    private static function csvSafe(mixed $value): mixed
    {
        return is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }
}
