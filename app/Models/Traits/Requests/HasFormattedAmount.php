<?php 
namespace App\Models\Traits\Requests;
trait HasFormattedAmount
{
	public function unformatNumericKeysFromArray(array $items , array $keysToBeUnformatted)
	{
		$result = [];
		foreach($items as $index =>$itemArr){
			foreach($itemArr as $currentKey => $currentValue){
				if(in_array($currentKey,$keysToBeUnformatted)){
					$result[$index][$currentKey] = number_unformat($currentValue);
				}else{
					$result[$index][$currentKey] = $currentValue;
				}
			}
		} 
		return $result;
	}
}
