<?php

namespace App\Http\Controllers;

use App\Enums\LgSources;
use App\Exports\LgImport\LgIssuanceTemplateExport;
use App\Jobs\LgIssuanceProcessImportJob;
use App\Models\Company;
use App\Models\LgIssuanceImportRun;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class LgIssuanceImportController extends Controller
{
    public function downloadTemplate(Company $company, string $source)
    {
        abort_unless(array_key_exists($source, LgSources::getAll()), 404);

        $fileName = 'lg-issuance-template-'.$source.'.xlsx';

        return Excel::download(new LgIssuanceTemplateExport($company, $source), $fileName);
    }

    public function upload(Company $company, Request $request, string $source)
    {
        abort_unless(array_key_exists($source, LgSources::getAll()), 404);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240'],
        ]);

        $path = $request->file('file')->store('lg-imports');

        $run = LgIssuanceImportRun::create([
            'company_id' => $company->id,
            'created_by' => \Auth::id(),
            'source' => $source,
            'file_path' => $path,
            'status' => 'pending',
        ]);

        LgIssuanceProcessImportJob::dispatch($run->id);

        return response()->json([
            'status' => true,
            'import_id' => $run->id,
            'message' => __('Import started successfully.'),
        ]);
    }

    public function status(Company $company, LgIssuanceImportRun $importRun)
    {
        abort_if($importRun->company_id !== $company->id, 404);

        return response()->json([
            'status' => true,
            'import' => [
                'id' => $importRun->id,
                'state' => $importRun->status,
                'total_rows' => $importRun->total_rows,
                'failed_rows' => $importRun->failed_rows,
                'completed' => in_array($importRun->status, ['completed', 'failed_validation', 'failed_system'], true),
            ],
        ]);
    }

    public function errors(Company $company, LgIssuanceImportRun $importRun)
    {
        abort_if($importRun->company_id !== $company->id, 404);

        return response()->json([
            'status' => true,
            'errors' => $importRun->errors ?: [],
            'failed_rows' => $importRun->failed_rows,
        ]);
    }
}
