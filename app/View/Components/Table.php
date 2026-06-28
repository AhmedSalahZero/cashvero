<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Table extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public $tableTitle;
    public $href;
    public $firstButtonName;
    public $icon;
    public $class;
    public $importHref;
    public $exportHref;
    public $exportTableHref;
    public $tableClass;
    public $truncateHref;
    public $lastUploadFailedHref;
    public $notPeriodClosedCustomerInvoices;
    public $instructionsIcon;
	

    public function __construct($tableTitle=null,$href='#',$class=null,$importHref='#',$exportHref='#',$exportTableHref='#',$firstButtonName='New Record',$icon='plus',$tableClass='kt_table_1',$truncateHref='#',$lastUploadFailedHref='#',$notPeriodClosedCustomerInvoices=[],$instructionsIcon=null)
    {
        $this->tableTitle           = $tableTitle;
        $this->href                 = $href;
        $this->icon                 = $icon;
        $this->firstButtonName      = $firstButtonName;
        $this->class               = $class;
        $this->importHref           = $importHref;
        $this->exportHref           = $exportHref;
        $this->tableClass          = $tableClass;
        $this->exportTableHref      = $exportTableHref;
        $this->truncateHref             = $truncateHref;
        $this->lastUploadFailedHref             = $lastUploadFailedHref;
        $this->notPeriodClosedCustomerInvoices             = $notPeriodClosedCustomerInvoices;
        $this->instructionsIcon             = $instructionsIcon;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\View\View|string
     */
    public function render()
    {
        return view('components.table');
    }
}
