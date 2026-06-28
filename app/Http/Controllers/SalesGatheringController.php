<?php

namespace App\Http\Controllers;

use App\Exports\ExportData;
use App\Models\Company;
use App\Models\CustomerInvoice;
use App\Models\Deduction;
use App\Models\LeasingContract;
use App\Models\Log;
use App\Models\MediumTermLoan;
use App\Models\SalesGathering;
use Illuminate\Http\Request;
use Schema;

class SalesGatheringController extends Controller
{
   
	protected function getSearchDateFieldName(string $modelName,?string $fieldName){
		if(!$fieldName){
			return null;
		}
		if($modelName == 'CustomerInvoice' || $modelName == 'SupplierInvoice'){
			if($fieldName == 'invoice_due_date'){
				return 'invoice_due_date';
			}
			return 'invoice_date';
		}
		if($modelName == 'SalesGathering'){
			return 'date';
		}
		if($modelName == 'ExportAnalysis'){
			return 'purchase_order_date';
		}
		if($modelName == 'ExpenseAnalysis'){
			return 'date';
		}
		return 'date';
	}
    public function index(Company $company, Request $request, string $uploadType='SalesGathering',?string $loanId = null )
    {
		$loan = MediumTermLoan::find($loanId);
		$leasingContract = LeasingContract::find($loanId);
        $modelName = $uploadType;
		if ($modelName == 'LoanSchedule' && $loanId) {
			session(['loan_schedule_import_loan_id_' . $company->id => $loanId]);
		}
		if ($modelName == 'ContractLoanSchedule' && $loanId) {
			session(['contract_loan_schedule_import_contract_id_' . $company->id => $loanId]);
		}

		$orderByDirection = in_array($uploadType, ['LoanSchedule', 'ContractLoanSchedule'], true) ? 'asc' : 'desc';
		$fieldValue = $request->get('field') ;
		$searchDateField = $this->getSearchDateFieldName($modelName,$fieldValue);
		$hasField = $request->has('field') ;
        $uploadingArr = getUploadParamsFromType($uploadType);
        $fullModelPath = $uploadingArr['fullModel'];
        $mainDateOrderBy = $uploadingArr['orderByDateField'];
        $uploadPermissionName = $uploadingArr['uploadPermissionName'];
        $exportPermissionName = $uploadingArr['exportPermissionName'];
        $deletePermissionName = $uploadingArr['deletePermissionName'];
        Log::storeNewLogRecord('enterSection', null, __('Data Gathering [ '. $uploadType . ' ]'));
		$pageLength = 50 ;
        $salesGatherings = $fullModelPath::company()->when($hasField, function ($q) use ($request,$fieldValue) {
            $q->where($fieldValue, 'like', '%'.$request->get('value') .'%');
        })
        ->when($request->has('from'), function ($q) use ($request,$searchDateField) {
            $q->where($searchDateField, '>=', $request->get('from'));
        })
        ->when($request->has('to'), function ($q) use ($request,$searchDateField) {
            $q->where($searchDateField, '<=', $request->get('to'));
        })
		->when($uploadType == 'LoanSchedule',function($q) use ($loanId){
			$q->where('medium_term_loan_id',$loanId);
		})
		->when($uploadType == 'ContractLoanSchedule',function($q) use ($loanId){
			$q->where('leasing_contract_id',$loanId);
		})
        ->orderBy($mainDateOrderBy, $orderByDirection)->paginate($pageLength);
        $exportableFields  = (new ExportTable)->customizedTableField($company, $uploadType, 'selected_fields');
        if($modelName == 'CustomerInvoice' || 'SupplierName'==$modelName) {
            unset($exportableFields['withhold_amount']);
        }
        $viewing_names = array_values($exportableFields);
        $db_names = array_keys($exportableFields);
  
        $notPeriodClosedCustomerInvoices = $modelName == 'CustomerInvoice' ? CustomerInvoice::getOnlyNotClosedPeriods() : null;
		$firstIndexElementInLabeling = $salesGatherings->first() ? $salesGatherings->first()->id : 0;
		$lastIndexElementInLabeling = $salesGatherings->last() ? $salesGatherings->last()->id : 0;
        $navigators =$this->getUploadingPageExportNavigation($modelName,$uploadPermissionName,$exportPermissionName,$deletePermissionName,$firstIndexElementInLabeling,$lastIndexElementInLabeling,$loanId);

        return view('client_view.sales_gathering.index', compact('navigators','loan','leasingContract','loanId', 'salesGatherings', 'company', 'viewing_names', 'db_names', 'uploadPermissionName', 'exportPermissionName', 'deletePermissionName', 'modelName', 'notPeriodClosedCustomerInvoices'));
    }
    

   
    // public function create(Company $company)
    // {

    //     return view('client_view.sales_gathering.form', $customerInvoice);
    // }

 
    public function store(Request $request, Company $company)
    {
        // $request['company_id'] = $company->id;
        SalesGathering::create($request->all());
        return redirect()->back();
    }


    public function show(SalesGathering $salesGathering)
    {
        //
    }


    public function edit(Company $company, SalesGathering $salesGathering)
    {

        // $salesGathering  = new SalesGatheringViewModel($company, $salesGathering);

        return view('client_view.sales_gathering.form', $salesGathering);
    }

 
    // public function update(Request $request, Company $company, SalesGathering $salesGathering)
    // {
    //     $salesGathering->update($request->all());
    //     toastr()->success('Updated Successfully');
    //     return (new SalesGatheringViewModel($company, $salesGathering))->view('client_view.sales_gathering.form');
    // }

 
    public function destroy(Company $company,Request $request, $modelId)
    {
		$modelType  = $request->get('modelType');
		$fullModelName = 'App\Models\\'.$modelType ;
		$model = $fullModelName::find($modelId);
		toastr()->error('Deleted Successfully');
        $model->delete();
        return redirect()->back();
    }
    public function export(Company $company, string $modelName)
    {
        $uploadParams = getUploadParamsFromType($modelName);
        $exportableFields = exportableFields($company->id, $modelName);
        // If there are no exportable fields were found return with a warning msg
        if ($exportableFields === null) {
            toastr()->warning('Please choose exportable fields first');
            return redirect()->back() ;
        }
        // Get The Selected exportable fields returns a pair of ['field_name' => 'viewing name']
        $selected_fields = (new ExportTable)->customizedTableField($company, $modelName, 'selected_fields');
        // Array Contains Only the name of fields
        $exportable_fields = array_keys($selected_fields);
        
        $salesGathering = $uploadParams['fullModel']::where('company_id', $company->id)->get();
        // Customizing the collection to be exported
        $salesGathering = collect($salesGathering)->map(function ($invoice) use ($exportable_fields) {
            $data = [];
            foreach ($exportable_fields as $field) {
                if (str_contains($field, 'deduction_id_')) {
                    $value = Deduction::find($invoice->$field)->name[lang()] ??null;
                } elseif (str_contains($field, 'date')) {
                    $value = $invoice->$field ===null ?: date('d-m-Y', strtotime($invoice->$field));
                } else {
                    $value = $invoice->$field;
                }
                $data[$field] = $value ;
            }
            return $data;
        });

        return (new ExportData($company->id, array_values($selected_fields), $salesGathering))->download($modelName.'.xlsx');

    }
    public function getUploadingPageExportNavigation(string $modelName,string $uploadPermissionName,string $exportPermissionName,string $deletePermissionName,int $fromIndex=  0 , int $toIndex=0, ?string $loanId = null)
    {
		$additionalUploadDataArray = [];
		if ($modelName == 'LoanSchedule' && $loanId) {
			$additionalUploadDataArray = ['medium_term_loan_id' => $loanId];
		}
		if ($modelName == 'ContractLoanSchedule' && $loanId) {
			$additionalUploadDataArray = ['leasing_contract_id' => $loanId];
		}
		$viewName = in_array($modelName, ['LoanSchedule', 'ContractLoanSchedule'], true) ? 'upload' : 'sales_gathering';
		$user = auth()->user();
		$company = currentCompany();
		$deleteAllDataTitle = $modelName == 'LabelingItem' ? __('Delete All Data (With Columns)') : __('Delete All Data') ;
		$deleteAllDataRouteName = $modelName == 'LabelingItem'? route('delete.all.labeling.items.with.columns',[$company->id]) : route('truncate',[$company,$modelName]);
		
		$exportNavArr = $modelName != 'LabelingItem' ? [
			'name'=>__('Export All Data'),
			'link'=>$user->can($exportPermissionName) ? route('salesGathering.export',['company'=>$company->id , 'model'=>$modelName]):'#',
			'show'=>$user->can($exportPermissionName),
			'icon'=>'fas fa-file-import',
			'attr'=>[
				// 'data-toggle'=>'modal',
				// 'data-target'=>'#search-form-modal',
			]
			] : 
			[
				'name'=>__('Export Data'),
				'link'=>'#',
				'show'=>$user->can($exportPermissionName),
				'icon'=>'fas fa-file-import',
				'sub_items'=>[
					[
						'name'=>__('Export Excel'),
						'link'=>$user->can($exportPermissionName) ? route('export.labeling.item',['company'=>$company->id , 'type'=>'excel']):'#',
						'show'=>$user->can($exportPermissionName),
						'icon'=>'fas fa-file-import',
					],
					[
						'name'=>__('Export PDF'),
						'link'=>$user->can($exportPermissionName) ? route('export.labeling.item',['company'=>$company->id , 'type'=>'pdf']):'#',
						'show'=>$user->can($exportPermissionName),
						'icon'=>'fas fa-file-import',
						'attr'=>[
							'data-toggle'=>'modal',
							'data-target'=>'#print_report'
						]
					],
					
					
				]
				]
			;
			
			
		
		
        return [
            
        [
           'name'=>__('Upload Data'),
           'link'=>'#',
           'show'=>true,
		   'icon'=>'fas fa-upload',
           'sub_items'=>[
               [
                   'name'=>__('Template Download'),
                   'link'=>$user->can($uploadPermissionName)?route('table.fields.selection.view',[$company,$modelName,$viewName]) : '#' ,
                   'show'=>$modelName != 'LabelingItem'
               ],
               [
                   'name'=>__('Upload Data'),
                   'link'=>$user->can($uploadPermissionName) ? route('salesGatheringImport',array_merge(['company'=>$company->id , 'model'=>$modelName],$additionalUploadDataArray)) : '#',
                   'show'=>true
               ]
           


			   ],
			   
        ],
		[
			'name'=>__('Filter'),
			'link'=>'#',
			'show'=>$modelName != 'LabelingItem',
			'icon'=>'fas fa-search ',
			'attr'=>[
				'data-toggle'=>'modal',
				'data-target'=>'#search-form-modal',
			]
			],
			
			$exportNavArr,
			// [
			// 	'name'=>__('Print QR Code'),
			// 	'link'=>$user->can($exportPermissionName) ? route('print.labeling.item.qrcode',['company'=>$company->id,'fromIndex'=>$fromIndex,'toIndex'=>$toIndex ]):'#',
			// 	'show'=>$modelName == 'LabelingItem',
			// 	'icon'=>'fas fa-print',
			// 	],
				// [
				// 	'name'=>__('Print Report'),
				// 	'link'=>$user->can($exportPermissionName) ? route('print.labeling.item.qrcode',['company'=>$company->id,'fromIndex'=>$fromIndex,'toIndex'=>$toIndex ]):'#',
				// 	'show'=>$modelName == 'LabelingItem',
				// 	'icon'=>'fas fa-print',
				// 	'attr'=>[
				// 		'data-toggle'=>'modal',
				// 		'data-target'=>'#print_report'
				// 	]
				// ]
				
			
			// ,
				
				
				
				[
					'name'=>__('Delete'),
					'link'=>'#',
					'show'=>true,
					'icon'=>'fas fa-trash',
					'sub_items'=>[
						[
							'name'=>Request()->segment(4) == 'LabelingItem'? __('Delete By Serial') :__('Delete By Date'),
							'link'=>'#',
							'show'=>true,
							'attr'=>[
								'data-toggle'=>'modal',
								'data-target'=>'#delete_from_to_modal'
							]
						],
						[
							'name'=>$deleteAllDataTitle,
							'link'=>$user->can($deletePermissionName)?$deleteAllDataRouteName:'#',
							'show'=>$user->can($deletePermissionName)
						]
					
		 
		 
						],
						
				 ],
				
				
				
				
				
				
				
				
        
    	];
    }

	

	
}
