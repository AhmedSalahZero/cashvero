<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
class ExportData implements WithHeadings ,FromCollection {
    use Exportable;

    public $company_id;
    public $heads;
    public $query;


    public function __construct($company_id,$heads,$query){
        $this->company_id = $company_id;
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
