<?php
namespace App\Traits;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait IsBankStatement
{
	public function handleFullDateAfterDateEdit(string $date,$debit,$credit , $additionUpdateData = [])
	{ 
			$date = Carbon::make($date)->format('Y-m-d');
			$modelName = get_class($this);
			$orderBy = Schema::hasColumn($this->getTable(),'priority') ? 'date asc , priority asc, id asc' : 'date asc, id asc';
			$currentFullDate =$this->full_date ;
			$currentDate =$this->date ;
			
			$time  = Carbon::make($currentFullDate)->format('H:i:s');
			$newFullDateTime = date('Y-m-d H:i:s', strtotime("$date $time")) ;
			// $minDateTime = min($currentFullDate ,$newFullDateTime );
			$minDate = min($currentDate , $date);
			$updatedData = [
				'date'=>$date,
				'full_date'=>$newFullDateTime ,
				'credit'=>$credit , 
				'debit'=>$debit 
			] ;
			$updatedData = array_merge($updatedData , $additionUpdateData);
			$row = DB::table($this->getTable())->where('id',$this->id)->first();
			$isEndOfMonthRow = false ;
			if(isset($row->interest_type)){
				$isEndOfMonthRow =  $row->interest_type=='end_of_month' || $row->interest_type =='end_of_month_final';
			}
			if($isEndOfMonthRow){
				if(Request()->has('is_end_of_month_final')){
					$updatedData['interest_type']='end_of_month_final';
				}else{
					$updatedData['interest_type']='end_of_month';
				}
			}
			
			DB::table($this->getTable())->where('id',$this->id)->update($updatedData);
			$query = 
			$modelName::where('date','>=',$minDate);
			foreach($this->getForeignKeyNamesThatUsedInFilter() as $columnName){
				$query->where($columnName,$this->{$columnName});
			}
			$query
			// ->where('id','!=',$this->id)
			->orderByRaw($orderBy)
			->first()
			->update([
				'updated_at'=>now()
			]);
			
	}
}
