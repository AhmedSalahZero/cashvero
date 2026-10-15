<?php 
namespace App\Helpers;

use Illuminate\Support\Str;

class HStr {
	
	
public static function generateUniqueStringOfLengthTo($length, $model = null, $columns = [], $onlyNumeric = false)
{
    // modes [string , numeric , string_numeric]
    if ($onlyNumeric === false) {
        $randomString = Str::random($length);
    } else {
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= mt_rand(0, 9);
        }

        return $randomString;
    }
    if ($model && $columns) {
        $query  =  ('App\Models\\' . $model)::query();
        foreach ($columns as $column) {
            $query->orWhere($column, $randomString);
        }
        if ($query->exists()) {
            return self::generateUniqueStringOfLengthTo($length, $model, $columns);
        }
        return $randomString;
    }

    return $randomString;
}

public static	function camel2dashed($className)
{
    return strtolower(preg_replace('/([a-zA-Z])(?=[A-Z])/', '$1-', $className));
}

	public static function camelizeWithSpace($input, $separator = '-')
	{
		return str_replace($separator, ' ', ucwords($input, $separator));
	}
	public static function replaceSpecialCharacters($string){
		return str_replace(array( '\'', '"',',' , ';', '<', '>','\\' ), ' ', $string);
	} 
	// public static function generateWhereFromMultipleArrs(array $wheres , string $orOrAnd):string 
	// {
	// 	$result = '';		
	// 	foreach($wheres as $index => $whereArr){
	// 		$column = $whereArr[0]; // company_id for example
	// 		$operator = $whereArr[1]; // > for example
	// 		$value = $whereArr[2]; // 21 for example
	// 		$result.= ('`'.$column.'`' . ' ' . $operator . ' ' . $value  . ' ' . $orOrAnd . ' ');
	// 	}
	// 	return trim($result , $orOrAnd.' ');

	// }
	public static function getLastWordInString(string $str, $separator = '/')
{
    $explodedStr = explode($separator, $str);
    return $explodedStr[count($explodedStr) - 1];
}

public static function isArabic($text)
{
    // التحقق مما إذا كان النص يحتوي على حروف عربية
    return preg_match('/[\p{Arabic}]/u', $text);
}
public static function generateReceiptNumber(string $code)
{
    return $code . floor(time()-999999999);
}
public static function convertModelToTableName(string $modelName)
{
    return Str::plural(strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $modelName))) ;
}

}
