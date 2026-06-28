<?php
namespace App\Traits;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

trait HasBasicStoreRequest
{
	public function storeBasicForm(Request $request  , array $except = ['_token','save','_method'] , $connectionName = null ):self{
		
		foreach($request->except($except) as $name => $value){
			$columnExist = Schema::connection($connectionName)->hasColumn($this->getTable(),$name);
			if(is_object($value)){
			//	static::addMediaFromRequest($name)->toMediaCollection($name);
			}
			elseif(!is_array($value)&& (Str::startsWith($value,'is_') || Str::startsWith($value,'can_')|| Str::startsWith($value,'has_')) ){
				if($columnExist){
					$this->{$name} = $request->boolean($name);
				}
			}
			elseif($columnExist){
				$val = $request->get($name) == 'null' ? null :$request->get($name);
				$this->{$name} = $val;
			}
		}
		$this->save();
		// store relations

		foreach($request->except($except) as $name => $value){
			// in store case
			if(is_array($request->get($name)) && method_exists($this,$name) && ! $this->id ){
				// is relationship
				foreach($request->get($name) as $index => $values){
					if(key_exists('company_id',$values)){
						
						$values['company_id'] = $request->get('company_id');
					}
					$this->$name()->create($values);
				}
			}
			// in update case
			elseif(is_array($request->get($name)) && method_exists($this,$name) && $this->id ){
				// is relationship
				$this->updateRepeaterRelation($request,$name,$this->$name()->getRelated()->getTable(),[
					
					/** @phpstan-ignore-next-line */
					'company_id'=>getCurrentCompanyId()  ?: $this->company_id
				]);
			}

		}

		return $this ;
	}
	public function updateRepeaterRelation(Request $request,string $relationName,string $relationTableName , array $additionRelationData = [],$oldIdsFromDatabase = null)
	{
        /**
         * * 	// for example
		 * * $relationName ='SalesOrder'
         * * وخلي اسم الريليشن هو نفسه الاسم اللي جي في الريكويست علشان هو اللي هيكون مبني عليه كل حاجه ;
         * * * $relationTableName = 'sales_orders';
         * * $additionRelationData لو حابب تضيف داتا اضافيه وليكن مثلا company_id
		 */
		$connectionName =$this->$relationName()->getModel()->getConnectionName();
        $relationDataArray = $request->input($relationName.'.sub_items',[]);
		$relationDataArray = count($relationDataArray) ? $relationDataArray:$request->input($relationName,[]);
		$oldIdsFromDatabase = is_null($oldIdsFromDatabase) ? $this->{$relationName}->pluck('id')->toArray() : $oldIdsFromDatabase;
		$idsFromRequest =array_column($relationDataArray,'id') ;
		$elementsToDelete = array_diff($oldIdsFromDatabase,$idsFromRequest);
		$elementsToUpdate = array_intersect($idsFromRequest,$oldIdsFromDatabase);
		$this->$relationName()->whereIn($relationTableName.'.id',$elementsToDelete)->delete();
		foreach($elementsToUpdate as $id){
			$dataToUpdate = findByKey($relationDataArray,'id',$id);
			$this->$relationName()->where($relationTableName.'.id',$id)->first()->update(array_merge($dataToUpdate,$additionRelationData));
		}
	
		foreach($relationDataArray as $data){
			if(!isset($data['id']) || $data['id'] <= 0){
				unset($data['id']);
				$currentDataArr = $this->filterTableColumnThatExistsOnly($connectionName,$relationTableName,array_merge($data,$additionRelationData));
				$this->$relationName()->create($currentDataArr);
			}
		}
		$this->refresh();
	
	}
	/**
	 * * دي هنفلتر بيها الكولومز اللي موجوده بس هنرجعها الباقي هنشيله
	 */
	protected function filterTableColumnThatExistsOnly(string $connectionName,string $relationTableName, array $items)
	{
		$newItems = [];
		
		foreach($items as $key => $value){
		
			if(Schema::connection($connectionName)->hasColumn($relationTableName,$key)){
				$newItems[$key] = $value ;
			}
		}
		return $newItems;
	}
	public function storeRelationsWithNoRepeater(Request $request,Company $company , array $except = [])
	{
		$columnsWithPayload = [
		];
		foreach($request->except($except) as $relationName => $values){
			
			if(!is_array($values) || !method_exists($this,$relationName) ){
				continue ;
			}
	
			foreach($values as $columnName => $payload){
				if(is_numeric($columnName)){
					continue;
				}
				$columnsWithPayload[$relationName]['company_id'] = $company->id ;
				$columnsWithPayload[$relationName][$columnName] = $payload;
			}
			
		}
		foreach($columnsWithPayload as $relationName => $values){
			if(is_null($this->{$relationName})){
				$this->{$relationName}()->create($values);
			}else{
				$this->{$relationName}()->update($values);
			}
		}
		$this->refresh();
		return $this;
	}
	public function storeRepeaterRelations(Request $request , array $relationNames,Company $company,$additionalData = [],$oldIdsFromDatabase=null)
	{
		foreach($relationNames as $relationName){
			$additionalData = array_merge([
				'company_id'=>$company->id
			],$additionalData) ;
			$this->updateRepeaterRelation($request,$relationName,$this->$relationName()->getRelated()->getTable(),$additionalData,$oldIdsFromDatabase);	
		}
		$this->refresh();
		
	}
}
