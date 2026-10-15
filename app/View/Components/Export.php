<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Export extends Component
{
    /**
     * Create a new component instance.
     *
     * @return void
     */
    public $href;
    public $firstButtonName;
    public $icon;
    public $class;
    public $importHref;
    public $exportHref;
    public $exportTableHref;
    public $truncateHref;
    public $lastUploadFailedHref;
    public $notPeriodClosedCustomerInvoices;

    public function __construct($href='#',$class=null,$importHref='#',$exportHref='#', $exportTableHref ='#',$firstButtonName='New Record',$icon='plus',$truncateHref='#',$lastUploadFailedHref='#',$notPeriodClosedCustomerInvoices=[])
    {

        $this->href                 = $href;
        $this->icon                 = $icon;
        $this->firstButtonName      = $firstButtonName;
        $this->class               = $class;
        $this->importHref           = $importHref;
        $this->exportHref           = $exportHref;
        $this->exportTableHref      = $exportTableHref;
        $this->truncateHref         = $truncateHref;
        $this->lastUploadFailedHref         = $lastUploadFailedHref ;
        $this->notPeriodClosedCustomerInvoices    = $notPeriodClosedCustomerInvoices ;

    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\View\View|string
     */
    public function render()
    {
        return view('components.export');
    }
}
