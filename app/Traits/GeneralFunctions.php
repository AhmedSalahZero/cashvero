<?php

namespace App\Traits;

use App\Category_product;
use App\Channel;
use App\Product;
use App\RevenueStreamType;
use App\SalesItems\DurationYears;
use App\Sector;
use App\Zone;
use Carbon\Carbon;

trait GeneralFunctions
{
	public static function getPaginationLimit():int 
	{
		return 20;
	}
    public static function dateCalc($first_date, $number_of_months, $formate = "d-m-Y")
    {
        $dt = Carbon::parse($first_date);
        $final_date = $dt->addMonths($number_of_months)->format($formate);
        return $final_date;
    }
    public static function IntervalNumber($interval)
    {
		$count = 1;
        if ($interval == 'monthly') {
            $count = 1;
        } elseif ( $interval == 'quarterly') {
            $count = 3;
        } elseif ($interval == 'semi-annually' || $interval == 'semi annually') {
            $count = 6;
        }elseif ($interval == 'annually'){
            $count = 12;
        }

        return $count;
    }

    public static function finalTotal($array,$type_of_keys ='dates'   )
    {
        $final = [];
        if ($array !== null) {

            array_walk_recursive($array, function ($item, $key) use (&$final ) {
                if (is_numeric($item)) {
                        $final[$key] = isset($final[$key]) ?  (($item + $final[$key]) ) : $item ;
                    }
            });
        }
        $type_of_keys == 'dates' ? array_multisort(array_map('strtotime', array_keys($final)), SORT_ASC, $final) : '';
        return $final;
    }
    public static function summation($array,$type_of_keys ='dates')
    {
        $final = [];

            array_walk_recursive($array, function ($item, $key) use (&$final) {


                    $final[$key] = isset($final[$key]) ?  ($item??0) + $final[$key] : ($item??0);

            });


        $type_of_keys == 'dates' ? array_multisort(array_map('strtotime', array_keys($final)), SORT_ASC, $final) : '';
        return $final;
    }
    public static function requestAppendingValues($request, $plan, $revenue_stream_type_field = 'revenue_stream_type_id')
    {
        $request->allocation_base = $plan->allocation_base;
        $request->category_product_id = $plan->category_product_id;
        $request->product_id = $plan->product_id;
        $request->allocation_base_id = $plan->allocation_base_id;
        $request->revenue_stream_type_id = $plan->$revenue_stream_type_field;
        $request->allocation_type = $plan->allocation_type;

        return $request;
    }

    public static function operationAmongTwoArrays($array_one, $array_two, $operation = 'divide')
    {
        $dates = array_keys(array_merge($array_one, $array_two));
        $result = [];
        array_walk($dates, function ($date) use (&$result, $array_one, $array_two, $operation) {
            $value1 =  $array_one[$date] ?? 0;
            $value2 =  $array_two[$date] ?? 0;


            if ($operation == 'divide') {
                $result[$date] = $value2 != 0 ?  $value1 / $value2 : 0;
            } elseif ($operation == 'multiply') {
                $result[$date] = $value1 * $value2;
            }elseif ($operation == 'subtraction') {
                $result[$date] = $value1 - $value2;
            }
        });
        array_multisort(array_map('strtotime', array_keys($result)), SORT_ASC, $result);
        return $result;
    }

    public static function operationAmongArrayAndNumber($array, $number, $operation = 'divide')
    {

        $result = [];
        array_walk($array, function ($value , $date) use (&$result, $number, $operation) {


            if ($operation == 'divide') {
                $result[$date] = $number != 0 ?  $value / $number : 0;
            } elseif ($operation == 'multiply') {
                $result[$date] = $value * $number;
            }elseif ($operation == 'subtraction') {
                $result[$date] = $value - $number;
            }
        });
        array_multisort(array_map('strtotime', array_keys($result)), SORT_ASC, $result);
        return $result;
    }
    //Calculate Total Services
    public static function summationOfTwoArrays($array=[], $total=[])
    {

        $dates = array_keys(array_merge($array, $total));
        array_walk($dates, function ($date) use (&$total, &$array) {
            $value =  $array[$date] ?? 0;

            isset($total[$date]) ? $total[$date] += $value : $total[$date] = $value;
        });
        array_multisort(array_map('strtotime', array_keys($total)), SORT_ASC, $total);
        return $total;
    }
    // Function That Checks IF Their are old data to be added to the new data
    public static function addIfExist($array_to_be_checked_if_defined, $new_array)
    {
        return  self::summationOfTwoArrays($new_array, $array_to_be_checked_if_defined);
    }
    public function sorting($array)
    {

        array_multisort(array_map('strtotime', array_keys($array ?? [])), SORT_ASC, $array ?? []);
        return $array;
    }
    public static function sortingDatesAsValues($array)
    {

        array_multisort(array_map('strtotime', $array), SORT_ASC, $array);
        return $array;
    }



}
