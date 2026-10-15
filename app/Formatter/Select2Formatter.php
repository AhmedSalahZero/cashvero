<?php 
namespace App\Formatter;
class Select2Formatter
{
	/**
	 * * assoc array ['ahmed-salah'=>'ahmed salah']
	 * * return [['title'=>'ahmed-salah' , 'value'=>'ahmed salah']]
	 */
	public function formatForAssocArr(array $assocArr):array 
	{
		$result = [];
		foreach($assocArr as $id => $title){
			$result[] = [
				'title'=>$title ,
				'value'=>$id 
			];
		}
		return $result ;
	}
	public static function formatForIndexedArr(array $indexedArray)
	{
		$result = [];
		foreach($indexedArray as  $id ){
			$title = convertIdsToNames([$id])[0];
			$result[] = [
				'title'=>$title ,
				'value'=>$id 
			];
		}
		return $result ;
	}
}
