<?php

namespace App\Exports\LgImport;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;

class LgIssuanceLookupSheetExport implements FromArray, WithTitle
{
    public function __construct(protected array $dropDownOptions)
    {
    }

    public function array(): array
    {
        $maxRows = 1;
        foreach ($this->dropDownOptions as $options) {
            $maxRows = max($maxRows, count($options));
        }

        $headers = array_keys($this->dropDownOptions);
        $rows = [$headers];

        for ($row = 0; $row < $maxRows; $row++) {
            $current = [];
            foreach ($this->dropDownOptions as $options) {
                $values = array_values($options);
                $current[] = $values[$row] ?? '';
            }
            $rows[] = $current;
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Lookups';
    }
}
