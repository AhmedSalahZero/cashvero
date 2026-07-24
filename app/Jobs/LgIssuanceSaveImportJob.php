<?php

namespace App\Jobs;

use App\Http\Controllers\LetterOfGuaranteeIssuanceController;
use App\Http\Requests\StoreLetterOfGuaranteeIssuanceRequest;
use App\Models\Company;
use App\Models\LgIssuanceImportRun;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class LgIssuanceSaveImportJob implements ShouldQueue
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

        $rows = json_decode((string) $run->valid_rows_json, true) ?: [];
        $controller = app(LetterOfGuaranteeIssuanceController::class);
        $errors = [];
        $currentRowNumber = 2;

        if ($run->created_by) {
            Auth::onceUsingId((int) $run->created_by);
        }

        try {
            DB::transaction(function () use ($rows, $run, $company, $controller, &$errors, &$currentRowNumber) {
                foreach ($rows as $index => $row) {
                    $currentRowNumber = $index + 2;
                    $payload = array_merge($row, [
                        'company_id' => $company->id,
                        'created_by' => $run->created_by ?: 0,
                        'source' => $run->source,
                    ]);

                    $request = StoreLetterOfGuaranteeIssuanceRequest::create('/', 'POST', $payload);
                    $request->setContainer(app());
                    $request->setRedirector(app('redirect'));
                    $request->setUserResolver(function () use ($run) {
                        return $run->created_by ? User::find($run->created_by) : null;
                    });

                    $validator = Validator::make($request->all(), $request->rules());
                    if ($validator->fails()) {
                        foreach ($validator->errors()->toArray() as $column => $messages) {
                            foreach ($messages as $message) {
                                $errors[] = [
                                    'row_number' => $index + 2,
                                    'column' => $column,
                                    'value' => $payload[$column] ?? null,
                                    'message' => $message,
                                ];
                            }
                        }
                        break;
                    }

                    app()->call([$controller, 'store'], [
                        'company' => $company,
                        'request' => $request,
                        'source' => $run->source,
                    ]);
                }

                if (! empty($errors)) {
                    throw new \RuntimeException('Validation failed while saving.');
                }
            });
        } catch (\Throwable $e) {
            $systemError = [
                'row_number' => $currentRowNumber,
                'column' => 'system',
                'value' => null,
                'message' => $e->getMessage(),
            ];
            $run->update([
                'status' => empty($errors) ? 'failed_system' : 'failed_validation',
                'errors' => empty($errors) ? [$systemError] : $errors,
            ]);

            return;
        }

        $run->update([
            'status' => 'completed',
            'errors' => [],
        ]);
    }
}
