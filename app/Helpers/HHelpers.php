<?php 
namespace App\Helpers;

use Illuminate\Support\Str;

class HHelpers 
{
	public static function getClassNameWithoutNameSpace($object){
		$class_parts = explode('\\', get_class($object));
 		 return end($class_parts);
	}
	public static function generateUniqueCodeForModel( string $modelName ,string $columnName,int $length){
			$modelFullName = 'App\Models\\'.$modelName;
			$randomCode = self::generateCodeOfLength($length); ;			
            $model = $modelFullName::where($columnName,$randomCode)->exists();
            if ($model) {
				return self::generateUniqueCodeForModel($modelName,$columnName,$length);
            }
			return $randomCode ; 
	}
	public static function generateCodeOfLength($length,$onlyNumbers = false )
	{
		
		$characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
		if($onlyNumbers){
			$characters = '0123456789';
		}
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
		return $randomString ;
	}
	public static function getModelFullNameFromTableName(?string $tableName = null):string 
	{
		$tableName = $tableName?: self::getTableNameFromRequest();
		return 'App\Models\\' . Str::studly(Str::singular($tableName));
	}
	public static function getTableNameFromRequest()
	{
		return Request()->segment(2);
	}
	public static function formatForSelect2(array $items):array
	{
		$formatted = [];
		foreach($items as $value => $title){
			$formatted[] = ['title'=>$title,'value'=>$value];
		}
		return $formatted ; 		
	}
	
public static function newInstanceOf($class, $arrayOfItems)
{
    $collection = collect([]);
    foreach ($arrayOfItems as $index=>$arr) {
        $newClass = new $class ;
        foreach ($arr as $key => $value) {
            $newClass->{$key}  = $value ;
        }
        $collection[$index] = $newClass ;
    }
    return $collection;
}

public static function sortMonthsByItsNames(array $array): array
{
    $formatted = [];
    $months = [
        1 => 'January',
        2 => 'February',
        3 => 'March',
        4 => 'April',
        5 => 'May',
        6 => 'June',
        7 => 'July',
        8 => 'August',
        9 => 'September',
        10 => 'October',
        11 => 'November',
        12 => 'December'
    ];

    for ($i = 1; $i <= 12; $i++) {
        $month = $months[$i];
        $formatted[$month] = $array[$month] ?? 0;
    }

    return $formatted;
}

}
