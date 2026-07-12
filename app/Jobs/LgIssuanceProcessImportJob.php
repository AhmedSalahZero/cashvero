<?php

namespace App\Jobs;

use App\Models\Company;
use App\Models\LgIssuanceImportRun;
use App\Support\LgImport\LgIssuanceImportTemplateService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class LgIssuanceProcessImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(protected int $importRunId)
    {
    }

    public function handle(): void
    {
        $run = LgIssuanceImportRun::find($this->importRunId);
        if (! $run) {
            return;
        }

        $company = Company::find($run->company_id);
        if (! $company) {
            $run->update(['status' => 'failed_system', 'errors' => [['message' => __('Company not found.')]]]);
            return;
        }

        $run->update(['status' => 'processing', 'errors' => null, 'failed_rows' => 0, 'total_rows' => 0]);

        $sheets = Excel::toArray([], storage_path('app/'.$run->file_path));
        $rows = $sheets[0] ?? [];

        if (count($rows) < 1) {
            $run->update(['status' => 'failed_validation', 'errors' => [['message' => __('The uploaded file is empty.')]], 'failed_rows' => 1]);
            return;
        }

        $headers = array_map(fn ($value) => trim((string) $value), $rows[0]);
        $expectedHeaders = LgIssuanceImportTemplateService::columnsBySource($run->source);

        if ($headers !== $expectedHeaders) {
            $run->update([
                'status' => 'failed_validation',
                'failed_rows' => 1,
                'errors' => [[
                    'row_number' => 1,
                    'column' => 'headers',
                    'value' => implode(', ', $headers),
                    'message' => __('Template headers do not match the selected source.'),
                ]],
            ]);
            return;
        }

        $errors = [];
        $validRows = [];
        foreach (array_slice($rows, 1) as $index => $rowValues) {
            $rowNumber = $index + 2;
            if (implode('', array_map('strval', $rowValues)) === '') {
                continue;
            }

            $mapped = [];
            foreach ($expectedHeaders as $headerIndex => $headerName) {
                $mapped[$headerName] = $rowValues[$headerIndex] ?? null;
            }

            $normalized = LgIssuanceImportTemplateService::normalizeRow($mapped, $expectedHeaders);
            [$canonicalRow, $resolverErrors] = LgIssuanceImportTemplateService::resolveTemplateRowToCanonical($company, $run->source, $normalized);
            $rowErrors = array_merge_recursive($resolverErrors, LgIssuanceImportTemplateService::validateRow($company, $run->source, $canonicalRow));

            if (! empty($rowErrors)) {
                foreach ($rowErrors as $column => $messages) {
                    foreach ($messages as $message) {
                        $errors[] = [
                            'row_number' => $rowNumber,
                            'column' => (string) $column,
                            'value' => $normalized[$column] ?? ($canonicalRow[$column] ?? null),
                            'message' => $message,
                        ];
                    }
                }
                continue;
            }

            $validRows[] = $canonicalRow;
        }

        $run->update([
            'total_rows' => count($validRows) + (int) ceil(count($errors) > 0 ? 1 : 0),
            'failed_rows' => count($errors) > 0 ? count(array_unique(array_column($errors, 'row_number'))) : 0,
        ]);

        if (! empty($errors)) {
            $run->update([
                'status' => 'failed_validation',
                'errors' => $errors,
                'valid_rows_json' => null,
            ]);

            return;
        }

        $run->update([
            'status' => 'saving',
            'valid_rows_json' => json_encode($validRows),
        ]);

        LgIssuanceSaveImportJob::dispatch($run->id);
    }
}
