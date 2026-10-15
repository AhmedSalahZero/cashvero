<?php

namespace App\Exports\LgImport;

use App\Models\Company;
use App\Support\LgImport\LgIssuanceImportTemplateService;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class LgIssuanceTemplateExport implements WithMultipleSheets
{
    public function __construct(
        protected Company $company,
        protected string $source
    ) {
    }

    public function sheets(): array
    {
        $columns = LgIssuanceImportTemplateService::columnsBySource($this->source);
        $options = LgIssuanceImportTemplateService::dropDownOptions($this->company, $this->source);
        $exampleRow = app()->environment('local')
            ? LgIssuanceImportTemplateService::buildLocalExampleTemplateRow($this->company, $this->source, $columns)
            : null;

        return [
            new LgIssuanceTemplateSheetExport($columns, $options, $exampleRow),
            new LgIssuanceLookupSheetExport($options),
        ];
    }
}
