<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
class ExportData implements WithHeadings ,FromCollection {
    use Exportable;

    public $heads;
    public $query;


    /**
     * @param mixed $company_id Accepted for call-site compatibility, but intentionally unused —
     *                          company scoping already happens in the query/collection passed in
     *                          before it reaches this class (confirmed, Stage 2 audit finding
     *                          #3.2, 2026-07-24).
     */
    public function __construct($company_id,$heads,$query){
        $this->query = $query;

        $this->heads = $heads;
    }
    public function collection()
    {
        return $this->query;
    }

    // Headings Names
    public function headings(): array
    {

        return $this->heads;
    }

}
