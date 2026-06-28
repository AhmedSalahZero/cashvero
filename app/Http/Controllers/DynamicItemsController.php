<?php

namespace App\Http\Controllers;


use App\Models\Company;
use Illuminate\Http\Request;


class DynamicItemsController extends Controller
{
	
	
	public function createLabelingItems(Company $company ,Request $request )
	{
		
		$exportables = getExportableFieldsForModel($company->id,'SalesGathering');
		$subItemsCount = session()->get('sub_items_'.$company->id,[]);
		$subItemNames = session()->get('sub_item_names_'.$company->id,[]);
		$tableHeaders = array_merge(
			[
				$subItemsCount['main_field_name'] ?? ''
				]  , 
				$subItemNames
			);
			
			$howManyItems = $subItemsCount['how_many_items'] ?? 0;
		return view('admin.dynamic-items.create-labeling-items',[
			'pageTitle'=>__('Create'),
			'type'=>'_create',
			'exportables'=>$exportables,
			'subItemsCount'=>$subItemsCount,
			'subItemsNames'=>$subItemNames,
			'tableHeaders'=>$tableHeaders,
			'howManyItems'=>$howManyItems
		]);
	}
	
	public function createLabelingForm(Company $company ,Request $request )
	{
		
		$subItemsCount = session()->get('sub_items_'.$company->id,[]);
		$subItemNames = session()->get('sub_item_names_'.$company->id,[]);
		$tableHeaders = array_merge(
			[
				$subItemsCount['main_field_name'] ?? ''
				]  , 
				$subItemNames
			);
			
			$howManyItems = $subItemsCount['how_many_items'] ?? 0;
		return view('admin.dynamic-items.create-labeling-form',[
			'pageTitle'=>__('Create'),
			'type'=>'_create',
			// 'exportables'=>$exportables,
			'subItemsCount'=>$subItemsCount,
			'subItemsNames'=>$subItemNames,
			'tableHeaders'=>$tableHeaders,
			'howManyItems'=>$howManyItems
		]);
	}
	
	
	public function showBuildingLabel(Company $company ,Request $request )
	{
		$exportables = getExportableFieldsForModel($company->id,'SalesGathering');
		$subItemsCount = session()->get('sub_items_'.$company->id,[]);
		$subItemNames = [
			'Building Name',
					'Floor',
					'Room'
		];
		$tableHeaders = array_merge(
			[
				$subItemsCount['main_field_name'] ?? ''
				]  , 
				$subItemNames
			);
			
			$howManyItems = $subItemsCount['how_many_items'] ?? 0;
		return view('admin.dynamic-items.building-label',[
			'pageTitle'=>__('Create'),
			'type'=>'_create',
			'exportables'=>$exportables,
			'subItemsCount'=>$subItemsCount,
			'subItemsNames'=>$subItemNames,
			'tableHeaders'=>$tableHeaders,
			'howManyItems'=>$howManyItems
		]);
	}
	public function showffeLabel(Company $company ,Request $request )
	{
		// $exportables = getExportableFieldsForModel($company->id,'SalesGathering');
		$subItemsCount = session()->get('sub_items_'.$company->id,[]);
		// $subItemNames = session()->get('sub_item_names_'.$company->id,[]);
		$subItemNames = [
			'Name',
			'Category',
			'Item'
		];
		$tableHeaders = array_merge(
			[
				$subItemsCount['main_field_name'] ?? ''
				]  , 
				$subItemNames
			);
			
			$howManyItems = $subItemsCount['how_many_items'] ?? 0;
		return view('admin.dynamic-items.ffe-label',[
			'pageTitle'=>__('Create'),
			'type'=>'_create',
			// 'exportables'=>$exportables,
			'subItemsCount'=>$subItemsCount,
			'subItemsNames'=>$subItemNames,
			'tableHeaders'=>$tableHeaders,
			'howManyItems'=>$howManyItems
		]);
	}
	public function storeItemsCount(Company $company,Request $request){
		$mainFieldName =  $request->get('main_field_name');
		$hasSubItems = $request->boolean('has_sub_items');
		$subItemsCount = $hasSubItems ?  $request->get('how_many',0) : 0;
		$subItems = $request->get('sub_items_names',[]);
		session()->put('sub_item_names_'.$company->id ,$subItems);
		
		session()->put('sub_items_'.$company->id ,[
			'main_field_name'=>$mainFieldName , 
			'has_sub_items'=>$hasSubItems , 
			'how_many_items'=>$subItemsCount
		]);
		return redirect()->route('create.labeling.items',['company'=>$company->id ]);
	}
	public function storeSubItems(Company $company,Request $request){
	}
	
	public function storeNewModal(company $company , Request $request){
	
		// $companyId = $company->id ;
		// $modelName = '\App\Models\\' . $request->get('modalName') ;
		// $model = new $modelName;
		$value = $request->get('value');
		// $typeColumn = strtolower($request->get('modalName')) . '_type';
		// $type = $request->get('modalType');
		
		return response()->json([
			'status'=>true ,
			'value'=>$value ,
			'id'=>1
		]);
		
		// $previousSelectorNameInDb = $request->get('previousSelectorNameInDb');
		// $previousSelectorValue = $request->get('previousSelectorValue');
	
		// $modelName = $model->where('company_id',$companyId);
		// if($type){
		// 	$modelName = $modelName->where($typeColumn,$type)	;
		// }
		// $modelName = $modelName->where('name',$value)->first();
		// if($modelName){
		// 	return response()->json([
		// 		'status'=>false ,
		// 	]);
		// }
		// $model->company_id = $companyId;
		// $model->name = $value;
		// if($type){
		// 	$model->{$typeColumn} = $type;
		// }
		// if($previousSelectorNameInDb){
			
		// 	$model->{$previousSelectorNameInDb} = $previousSelectorValue;
		// }
		// $model->save();
		// return response()->json([
		// 	'status'=>true ,
		// 	'value'=>$value ,
		// 	'id'=>$model->id 
		// ]);
	}
	
	
	
}
