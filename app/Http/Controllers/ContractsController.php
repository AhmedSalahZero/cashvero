<?php
namespace App\Http\Controllers;
use App\Http\Requests\StoreContractRequest;
use App\Models\Company;
use App\Models\Contract;
use App\Models\CustomerInvoice;
use App\Models\Partner;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;

/**
 *  * 
 * * type = Customer or Supplier
 */
class ContractsController
{
    use GeneralFunctions;
	public function index(Company  $company ,$type)
    {
		$hasProjectNameColumn = $type == 'Customer'?  CustomerInvoice::hasProjectNameColumn() : false;
		
		$contractStatues = [
			Contract::RUNNING ,
			Contract::RUNNING_AND_AGAINST ,
			Contract::FINISHED 
		];
		
		$runningContracts = Contract::where('contracts.company_id',$company->id)->where('status',Contract::RUNNING )->where('model_type',$type)->join('partners','partners.id','=','contracts.partner_id')->selectRaw('contracts.*,partners.name as partner_name')->orderByRaw('start_date desc , partner_name asc')->with(['relatedContracts'])->get();
		$runningAndAgainstContracts = Contract::where('contracts.company_id',$company->id)->where('status',Contract::RUNNING_AND_AGAINST )->join('partners','partners.id','=','contracts.partner_id')->selectRaw('contracts.*,partners.name as partner_name')->orderByRaw('start_date desc , partner_name asc')->with(['relatedContracts'])->where('model_type',$type)->get();
		$finishedContracts = Contract::where('contracts.company_id',$company->id)->where('status',Contract::FINISHED )->where('model_type',$type)->join('partners','partners.id','=','contracts.partner_id')->selectRaw('contracts.*,partners.name as partner_name')->orderByRaw('start_date desc , partner_name asc')->with(['relatedContracts'])->get();
	
		$contracts = [
			Contract::RUNNING=>$runningContracts ,
			Contract::RUNNING_AND_AGAINST=>$runningAndAgainstContracts,
			Contract::FINISHED=>$finishedContracts
		];
		
		$customerOrSupplierContractsText = $type == 'Supplier' ? __('Supplier Contracts') : __('Customer Contracts');
		$items = [];
		foreach($contractStatues as $contractStatus){
			foreach($contracts[$contractStatus] as $index=>$contract){
				$contractId = $contract->id ;
				$customerInvoices = $contract->customerInvoices;
				$items[$contractStatus][$contractId]['parent'] = [
					'name'=>$contract->getName() ,
					'contract'=>$contract,
					'client_name'=>$contract->getClientName(),
					'contract_code'=>$contract->getCode(),
					'start_date'=>$contract->getStartDateFormatted(),
					'end_date'=>$contract->getEndDateFormatted(),
					'currency'=> $contract->getCurrency() ,
					'amount'=>$contract->getAmountFormatted(),
					'invoices'=>$customerInvoices
				];
				foreach($contract->getOrders() as $order){
					$items[$contractStatus][$contractId]['sub_items'][$order->id][$order->getOrderColumnName()] =$order->getNumber() ;
					$items[$contractStatus][$contractId]['sub_items'][$order->id]['amount'] =$order->getAmountFormatted() ;
					$items[$contractStatus][$contractId]['sub_items'][$order->id]['id'] =$order->id ;
					$items[$contractStatus][$contractId]['sub_items'][$order->id]['allocations'] =$order->allocations ;
				}
		}
		}

		$commonVars = $this->getCommonVars($company,$type);
		$clientsWithContracts = $commonVars['clientsWithContracts'];
		
        return view('contracts.index',compact('clientsWithContracts','company','items','type','customerOrSupplierContractsText','contractStatues','hasProjectNameColumn'));
    }
	public function create(Request $request,Company $company,string $type)
	{
		return view('contracts.form',$this->getCommonVars($company,$type));
	}
	public function getCommonVars(Company $company,string $type,$model = null):array 
	{
		$salesOrderOrPurchaseOrderInformationText = __('Sales Order Information');
		$salesOrderOrPurchaseNumberText =  $type == 'Supplier' ? __('Purchase Order Number') : __('Sales Order Number'); 
		$salesOrderOrPurchaseNoText =  $type == 'Supplier' ? 'po_number' : 'so_number'; 
		$salesOrderOrPurchaseOrderRelationName = $type == 'Supplier' ? 'purchasesOrders' : 'salesOrders'; ;
		$contractsRelationName = 'contracts' ;
		$salesOrderOrPurchaseOrderObject =  $type == 'Supplier' ? new PurchaseOrder() : new SalesOrder(); 
		$clients = Partner::onlyCompany($company->id);
		$formTitle = __('Customer Contract Form');
		$clientsWithContracts = collect([]);
		if($type == 'Supplier'){
			$clients =$clients->onlySuppliers();
			$salesOrderOrPurchaseOrderInformationText = __('Purchases Order Information');
			$formTitle = __('Supplier Contract Form');
			$clientsWithContracts = Partner::onlyCompany($company->id)->onlyCustomers()->onlyThatHaveCustomerContracts()->orderBy('name')->get();
			$reverseTypeText = __('Customers');
		}else{
			$clients =$clients->onlyCustomers();
			$reverseTypeText = __('Suppliers');
		}
		$clients = $clients->get();
		return [
			'reverseTypeText'=>$reverseTypeText,
			'contractsRelationName'=>$contractsRelationName,
			'clientsWithContracts'=>$clientsWithContracts,
			'formTitle'=>$formTitle,
			'company'=>$company,
			'clients'=>$clients,
			'type'=>$type,
			'salesOrderOrPurchaseOrderInformationText'=>$salesOrderOrPurchaseOrderInformationText,
			'salesOrderOrPurchaseNumberText'=>$salesOrderOrPurchaseNumberText,
			'salesOrderOrPurchaseNoText'=>$salesOrderOrPurchaseNoText,
			'salesOrderOrPurchaseOrderObject'=>$salesOrderOrPurchaseOrderObject,
			'salesOrderOrPurchaseOrderRelationName'=>$salesOrderOrPurchaseOrderRelationName,
			'model'=>$model,
			'inEditMode'=>isset($model)
		];
	}
	public function store(StoreContractRequest $request, Company $company,string $type){
			$contract = new Contract ;
			$contract->storeBasicForm($request);
			return redirect()->route('contracts.index',['company'=>$company->id,'type'=>$type]);
	}
	public function edit(Request $request,Company $company,Contract $contract,string $type)
	{
		return view('contracts.form',$this->getCommonVars($company,$type,$contract));
	}
	public function update(Company $company , StoreContractRequest $request , Contract $contract,string $type){
			$contract->storeBasicForm($request);
			return redirect()->route('contracts.index',['company'=>$company->id,'type'=>$type]);
	}
	public function destroy(Company $company , Request $request , Contract $contract,string $type){
		$contract->delete();
		return redirect()->route('contracts.index',['company'=>$company->id,'type'=>$type]);  
	}	
	public function markAsFinished(Company $company , Request $request , Contract $contract,string $type){
		$contract->update([
			'status'=>Contract::FINISHED ,
		]);
		return redirect()->route('contracts.index',['company'=>$company->id,'type'=>$type]);  
	}
	public function markAsRunningAndAgainst(Company $company , Request $request , Contract $contract,string $type){
		$contract->update([
			'status'=>Contract::RUNNING_AND_AGAINST ,
		]);
		return redirect()->route('contracts.index',['company'=>$company->id,'type'=>$type]);  
	}
	public function updateContractsBasedOnCustomer(Request $request , Company $company ){
		$customer = Partner::find($request->get('customerId'));
		$isFromLc = $request->boolean('is_lc');
		if(!$customer){
			return response()->json([
				'contracts'=>[]
			]);
		}
		$contracts = $customer->contracts;
		$contractFormatted = [];
		foreach($contracts as $contract){
			$contractCanBeReturned= $isFromLc ? $contract->forSupplier()  :$contract->forCustomer();
			if($contractCanBeReturned){
				$contractFormatted[$contract->name] = [
					'id'=>$contract->id ,
					'currency'=>$contract->getCurrency()
				];
			}
		}
		$isCustomer = $customer->is_customer ;
		return response()->json([
			'contracts'=>$contractFormatted,
			'is_customer'=>$isCustomer
		]);
	}
	public function updateSalesOrdersBasedOnContract(Request $request , Company $company ){
		$contract = Contract::find($request->get('contractId'));
		$purchaseOrders = $contract->salesOrders->pluck('so_number','id')->toArray();
		return response()->json([
			'purchase_orders'=>$purchaseOrders
		]);
	}
	public function updatePurchaseOrdersBasedOnContract(Request $request , Company $company ){
		$contractId = $request->get('contractId') ;
		if($contractId == -1){ // no po
			return response()->json([
				'status'=>true ,
				'showTextInputForNewPO'=>true 
			]);
		}
		if($contractId == -2){ // existing po
			$currentPoNumber = $request->get('currentNewPurchaseOrder');
			return response()->json([
				'status'=>true ,
				'purchase_orders'=>PurchaseOrder::where('company_id',$company->id)->where('po_number','!=',$currentPoNumber)->where('contract_id',null)->pluck('po_number','id')
			]);
		}
		$contract = Contract::find($contractId);
		
		$purchaseOrders = $contract->purchasesOrders->pluck('po_number','id')->toArray();
		return response()->json([
			'purchase_orders'=>$purchaseOrders
		]);
	}
	public function getContractsForCustomerOrSupplier(Company $company , Request $request){
		$partner = Partner::find($request->get('partnerId'));
		if(!$partner){
			return [
				'contracts'=>[]
			];
		}
		/**
		 * @var Partner $partner 
		 */
		$contracts = $partner->contracts->sortBy('name') ;
		if(!$request->boolean('inEditMode')){
			$contracts = $contracts->where('parent_id',null)->values() ;
		}
		return response()->json([
			'status'=>true ,
			'contracts'=>$contracts 
		]);
		
	}
	public function generateRandomCode(Request $request,Company $company, string $modelType)
	{
		$partnerId = $request->get('partnerId');
		$partner = Partner::find($partnerId);
		$startDate = $request->get('startDate');
		$code = Contract::generateRandomContract($company->id,$partner->getName(),$startDate,$modelType);
		return response()->json([
			'code'=>$code
		]);
		
	}	
	public function storePoAllocations(Request $request , Company $company){
		$purchaseOrder = PurchaseOrder::find($request->get('po_id'));
		$purchaseOrder->allocations()->delete();
		foreach( $request->get('poAllocations',[])  as $index => $purchaseOrderArr){
				$purchaseOrderArr['allocation_amount'] = number_unformat($purchaseOrderArr['allocation_amount'] ?? 0);
				$purchaseOrder->allocations()->create($purchaseOrderArr);
		}
		return redirect()->back();
	}
}
