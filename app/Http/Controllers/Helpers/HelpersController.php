<?php 
namespace App\Http\Controllers\Helpers;

use App\Models\Company;
use App\Models\QuickPricingCalculator;
use Illuminate\Http\Request;

class HelpersController {
	
	public function storeNewModal(company $company , Request $request){
		$companyId = $company->id ;
		$fullModelName = '\App\Models\\' . $request->get('modalName') ;
		$model = new $fullModelName;
		$value = $request->get('value');
		
		$typeColumn = strtolower($request->get('modalName')) . '_type';
		if($request->get('modalName') == 'PricingExpense'){
			$typeColumn = 'expense_type';
		}
		$type = $request->get('modalType');
		$previousSelectorNameInDb = $request->get('previousSelectorNameInDb');
		$previousSelectorValue = $request->get('previousSelectorValue');
		$modelName = $model->where('company_id',$companyId);
		if($type){
			$modelName = $modelName->where($typeColumn,$type)	;
		}
		$modelName = $modelName->where('name',$value)->first();
		if($modelName){
			return response()->json([
				'status'=>false ,
			]);
		}
		$model->company_id = $companyId;
		$model->name = $value;
		if($type){
			$model->{$typeColumn} = $type;
		}
		if($previousSelectorNameInDb){
			
			$model->{$previousSelectorNameInDb} = $previousSelectorValue;
		}
		if($additionalColumnName = $request->get('additionalColumnName')){
			$model->{$additionalColumnName} = $request->get('additionalColumnValue');
		}
		$model->save();
		return response()->json([
			'status'=>true ,
			'value'=>$value ,
			'id'=>$model->id 
		]);
	}
	
	
}
