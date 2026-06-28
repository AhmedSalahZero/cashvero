<?php

namespace App\Helpers;

use Carbon\Carbon;
use Exception;
use Illuminate\Support\Arr;

class HArr
{
    public static function sumStatementAtDates(array $accumulatedStatement, array $oldStatement, array $statementKeys, array $sumKeys):array
    {
        $result =[];
        foreach ($statementKeys as $key) {
            foreach ($sumKeys as $dateAsIndex) {
                $value1 = $accumulatedStatement[$key][$dateAsIndex] ?? 0 ;
                $value2 = $oldStatement[$key][$dateAsIndex] ?? 0 ;
                $result[$key][$dateAsIndex] = $value1+$value2;
            }
        }
        return $result;
    }
    public static function sumJsonArr(array $items, array $sumKeys)
    {
        $result  = [];
        
        foreach ($items as $index => $jsonArr) {
            $currentArr = (array) (json_decode($jsonArr));
            $result = HArr::sumAtDates([$currentArr , $result], $sumKeys) ;
        }
        return $result;
    }
    public static function sumAtDates(array $items, array $dates, bool $debug = false)
    {
        $itemsCount = count($items);
        if (!$itemsCount) {
            return [];
        }
        if (!isset($items[0])) {
            throw new Exception('Custom Exception .. First Parameter Must Be Indexes Array That Contains Arrays like [ [] , [] , [] ]');
        }

        $total = [];
        foreach ($dates as $date) {
            $currenTotal = 0;
            for ($i = 0; $i< $itemsCount; $i++) {
                $currenTotal+=$items[$i][$date]??0;
            }
            $total[$date] = $currenTotal;
        }

        return $total;
    }

    public static function subtractAtDates(array $items, array $dates)
    {
        $itemsCount = count($items);
        if (!$itemsCount) {
            return [];
        }
        if (!isset($items[0])) {
            throw new Exception('Custom Exception .. First Parameter Must Be Indexes Array That Contains Arrays like [ [] , [] , [] ]');
        }

        $total = [];
        foreach ($dates as $date) {
            $currenTotal = 0;
            for ($i = 0; $i< $itemsCount; $i++) {
                if ($i == 0) {
                    $currenTotal += $items[$i][$date]??0;
                } else {
                    $currenTotal -= $items[$i][$date]??0;
                }
            }
            $total[$date] = $currenTotal;
        }

        return $total;
    }
    public static function fillMissedKeysFromPreviousKeys(array $items, array $dates, $defaultValue = 0)
    {
        $previousValue = $defaultValue;
        $newItems = [];
        foreach ($dates as $date) {
            if (isset($items[$date])) {
                $previousValue = $items[$date];
                $newItems[$date] = $items[$date];
            } else {
                $newItems[$date] = $previousValue;
            }
        }

        return $newItems;
    }

   
    
    public static function MultiplyWithNumber(array $items, float $number)
    {
        $newItems = [];
        foreach ($items as $key=>$value) {
            $newItems[$key]=$value * $number ;
        }
        return $newItems ;
    }
   
    public static function sortBasedOnKey(array $arr, string $key):array
    {
        usort($arr, function ($a, $b) use ($key) {
            return strtotime($a[$key]) - strtotime($b[$key]);
        });
        return $arr ;
    }

    public static function sortBySumOfKeyWithoutPreservingOriginalArray(array $items, string $sortBySumOfKeyName):array // by reference
    {
        uasort($items, function ($a, $b) use ($sortBySumOfKeyName) {
            $sumA = isset($a[$sortBySumOfKeyName]) ? array_sum($a[$sortBySumOfKeyName]) : 0;
            $sumB = isset($b[$sortBySumOfKeyName]) ? array_sum($b[$sortBySumOfKeyName]) : 0;
            return $sumB <=> $sumA; // Descending order
        });
        return $items;
    }
    public static function sortTwoDimArrayAndPreserveKeyNameBasedOnKeyDesc(array $items, string $key)
    {
        
        uasort($items, function ($a, $b) use ($key) {
            return $b[$key] <=> $a[$key]; // Descending order
        });
        return $items;
    }
    
    public static function removeKeyFromArrayByValue(array $items, array $valuesToRemove)
    {
        foreach ($valuesToRemove as $valueToRemove) {
            $found = array_search($valueToRemove, $items);
            if ($found !== false) {
                unset($items[$found]);
            }
        }
        return array_values($items) ;
    }
    public static function removeNullValues(array $items)
    {
        $result = [];
        foreach ($items as $key => $val) {
            if (!trim($val)) {
                continue ;
            }
            $result[$key] = $val ;
        }
        return $result ;
    }
    /**
     * get only items that has keys
     */
    public static function filterByKeys(array $items, array $keys)
    {
        $newItems = [];
        foreach ($items as $key => $value) {
            if (in_array($key, $keys)) {
                $newItems[$key] = $value ;
            }
        }
        return $newItems ;
    }
    public static function removeKeysFromArray(array $items, array $keysToBeRemoved)
    {
        $result = [];
        foreach ($items as $currentKey => $value) {
            if (!in_array($currentKey, $keysToBeRemoved)) {
                $result[$currentKey] = $value ;
            }
        }
        return $result;
    }
    public static function filterTrulyValue(array $arr):array
    {
        return array_filter($arr, function ($value) {
            return $value ;
        });
    }
    public static function atLeastOneValueExistInArray(array $items, array $itemsToSearchIn)
    {
        foreach ($items as $item) {
            if (in_array($item, $itemsToSearchIn)) {
                return true  ;
            }
        }
        return false ;
    }
    public static function unformatValues(array $items)
    {
        $result = [];
        foreach ($items as $key=>$value) {
            $result[$key] = unformat_number($value);
        }
        return $result;
    }
    public static function mergeTwoAssocArr(array $items1, array $items2):array
    {
        $result = [];
        
        foreach ($items1 as $key => $val) {
            $result[$key] = $val ;
        }
        foreach ($items2 as $key => $val) {
            $result[$key] = $val ;
        }
        
        return $result ;
    }
    public static function twoArrayHasAtLeastNonZeroValue(array $firstItems, array $secondItems):bool
    {
        $hasAtLeastNonZeroValue = false ;
        if (count($firstItems) == 0 && count($secondItems) == 0) {
            $hasAtLeastNonZeroValue = false ;
        }
        foreach ($firstItems as $value) {
            if ($value != 0) {
                $hasAtLeastNonZeroValue = true ;
            }
        }
    
        foreach ($secondItems as $value) {
            if ($value != 0) {
                $hasAtLeastNonZeroValue = true ;
            }
        }
        return $hasAtLeastNonZeroValue ;
    }
    public static function orderByDayNameForTwoDimension(array $items)
    {

        $days = [
            'Friday',
            'Saturday',
            'Sunday',
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday'
        ];
        usort($items, function ($a, $b) use ($days) {
            $posA = array_search($a['item'], $days);
            $posB = array_search($b['item'], $days);
            return $posA <=> $posB;
        });
        return $items ;
    }
    public static function orderByDayNameForOneDimension(array $items)
    {

        $days = [
            'Friday',
            'Saturday',
            'Sunday',
            'Monday',
            'Tuesday',
            'Wednesday',
            'Thursday'
        ];
        uksort($items, function ($a, $b) use ($days) {
            $posA = array_search($a, $days);
            $posB = array_search($b, $days);
            return $posA <=> $posB;
        });
        return $items ;
    }
    public static function getKeysSortedDescByKey(array $items, $keyName = 'Sales Values'):array
    {
        $values = [];
        $result= [];
        foreach ($items as $categoryName => $itemArr) {
            $sumSalesValue = array_sum($itemArr[$keyName]);
            $values[$categoryName] = $sumSalesValue;
        }
        
        arsort($values);
        $sortedKeys = array_keys($values);
        foreach ($sortedKeys as $key) {
            $result[$key] = $items[$key];
        }
        return $result;
    }
    public static function fillMissingKeyInTwoDimArrWith(array $items, array $dates)
    {

        $allItems = [];

        foreach ($items as $cate=>$keyAndVal) {
            foreach ($dates as $date) {
                if (isset($keyAndVal[$date])) {
                    $allItems[$cate][$date] = $keyAndVal[$date];
                } else {
                    $allItems[$cate][$date] = 0;
                }
            }
                
        }
        return $allItems;
    }
    public static function fillMissingKeyInOneDimArrWith(array $items, array $dates)
    {

        $allItems = [];
        foreach ($dates as $date) {
            if (isset($items[$date])) {
                $allItems[$date] = $items[$date];
            } else {
                $allItems[$date] = 0;
            }
                
        }
        return $allItems;
    }
    public static function filterByUnique(array $items, array $uniqueKeys):array
    {
        return  collect($items)->unique(function ($item) use ($uniqueKeys) {
            $uniqueKey = '';
            foreach ($uniqueKeys as $key) {
                $uniqueKey.= $item->{$key};
            }
            return $uniqueKey;
        })->values()->toArray();
    }
    public static function getValueFromMonth(array $items, string $month)
    {
        foreach ($items as $date => $value) {
            if (Carbon::make($date)->format('m')== $month) {
                return $value ;
            }
        }
        return 0 ;
    }
    public static function getValueFromMonthAndYear(array $items, string $month, string $year)
    {
        foreach ($items as $date => $value) {
            if (Carbon::make($date)->format('m')== $month && $year == Carbon::make($date)->format('Y')) {
                return $value ;
            }
        }
        return 0 ;
    }
    public static function sliceWithDates($items, $endDate, $offsite = 11):array
    {
        $result = [];
        $startDate = Carbon::make($endDate)->subMonths($offsite)->format('Y-m-d');
        foreach ($items as $date => $value) {
            if (Carbon::make($date)->between(Carbon::make($startDate), Carbon::make($endDate))) {
                $result[$date] = $value ;
            }
        }
        return $result;
    }
 

   

    public static function getValueOrPrevious(array $data, string $date)
    {
        return $data[$date];
        
    }
    
 
 

    public static function getLatestNonZeroExecutionKeys(array $data): array
    {
        $maxEndDate = null;
        $selectedIndex = null;

        // Iterate through possible indices (1 to 5 in your example)
        for ($i = 1; $i <= 5; $i++) {
            $executionPercentageKey = "execution_percentage_$i";
            $endDateKey = "end_date_$i";

            // Check if the keys exist and execution_percentage is greater than 0
            if (
                isset($data[$executionPercentageKey], $data[$endDateKey]) &&
                floatval($data[$executionPercentageKey]) > 0
            ) {
                $currentEndDate = \Carbon\Carbon::parse($data[$endDateKey]);

                // Update if this end_date is greater or if maxEndDate is not set
                if ($maxEndDate === null || $currentEndDate->greaterThan($maxEndDate)) {
                    $maxEndDate = $currentEndDate;
                    $selectedIndex = $i;
                }
            }
        }

        // If no valid set is found, return an empty array
        if ($selectedIndex === null) {
            return [];
        }

        // Collect all keys related to the selected index
        $result = [];
        $keys = [
            "start_date_$selectedIndex",
            "end_date_$selectedIndex",
            "execution_percentage_$selectedIndex",
            "execution_days_$selectedIndex",
            "collection_days_$selectedIndex",
            'so_number',
            'po_number',
            'amount'
        ];

        foreach ($keys as $key) {
            if (isset($data[$key])) {
                $r= '_'.$selectedIndex;
                $newKey = str_replace($r, '', $key);
                $result[$newKey] = $data[$key];
            }
        }

        return $result;
    }
    // public static function divideArrBy(array $items, int $num):array
    // {
    //     $result = [];
    //     foreach ($items as $index=> $val) {
    //         $result[$index] = $val / $num;
    //     }
    //     return $result ;
    // }
    public static function multipleTwoArrAtSameIndex(array $firstArr, array $secondArr)
    {
        $result = [];
        foreach ($firstArr as $index => $value) {
            $secondAtValue = $secondArr[$index]??0;
            $result[$index] = $value * $secondAtValue ;
        }
        return $result ;
    }
    public static function repeatThrough(float $value, array $keys):array
    {
        $result = [];
        foreach ($keys as $index) {
            $result[$index] = $value ;
        }
        return $result;
    }

    public static function calculateTotalFromSubItems(array $items):array
    {
        $result=[];
        foreach ($items as $item) {
            $data = $item['data']??[];
            foreach ($data as $dateOrYearIndex => $value) {
                $result[$dateOrYearIndex] = isset($result[$dateOrYearIndex]) ? $result[$dateOrYearIndex] + $value:$value;
            }
        }
        ksort($result);
        return $result;
    }

    
    public static function getPerYearIndexForCashAndBank(array $itemsAsDateIndexAndValue, array $yearWithItsMonths):array
    {
        $result = [];
        foreach ($yearWithItsMonths as $yearIndex => $itsMonths) {
            $currentYearTotal = 0;
            $isFirstLoop = true ;
            foreach ($itsMonths as $dateAsIndex => $dateAsString) {
                $currentValue = $itemsAsDateIndexAndValue[$dateAsIndex]??0 ;
                if ($isFirstLoop) {
                    $currentYearTotal =  $currentValue;
                    $isFirstLoop=false;
                }
            }
            /**
             * * هنحط النتيجه بتاعتك كل سنه عند اخر شهر في السنه دي
             */
			if(isset($dateAsIndex)){
				$result[$dateAsIndex] = $currentYearTotal;
			}
        }
        return $result ;
    }
    public static function calculateWorkingCapital($cashAndBankAmount, $totalCashInAsDateIndexAndValue, $totalCashOutAsDateIndexAndValue, $sumKeys)
    {
        $openingBalance = $cashAndBankAmount ;
        $statements = [];
        foreach ($sumKeys as $dateAsIndex) {
            $statements['beginning_balance'][$dateAsIndex] = $openingBalance;
            $currentTotalCashIn = $totalCashInAsDateIndexAndValue[$dateAsIndex]??0;
            $statements['total_cash_in'][$dateAsIndex] = $currentTotalCashIn;
            $currentTotalCashOut = $totalCashOutAsDateIndexAndValue[$dateAsIndex]??0;
            $statements['total_cashout'][$dateAsIndex] = $currentTotalCashOut;
            $netCashBeforeWorkingCapital = $openingBalance + $currentTotalCashIn - $currentTotalCashOut ;
            $statements['net_cash_before_working_capital'][$dateAsIndex] =$netCashBeforeWorkingCapital;
            $workingCapitalInjection = 0 ;
            if ($netCashBeforeWorkingCapital < 0) {
                $workingCapitalInjection = $netCashBeforeWorkingCapital * -1 ;
            }
            $statements['working_capital_injection'][$dateAsIndex] =$workingCapitalInjection;
            $endCashBalance = $netCashBeforeWorkingCapital + $workingCapitalInjection;
            $statements['cash_end_balance'][$dateAsIndex] = $endCashBalance;
            $openingBalance = $endCashBalance ;
        }
        return $statements;
    
    
    }
    
    public static function sumPerYearIndex(array $itemsAsDateIndexAndValue, array $yearWithItsMonths):array
    {
        $result = [];
        foreach ($yearWithItsMonths as $yearIndex => $itsMonths) {
            $currentYearTotal = 0;
            foreach ($itsMonths as $dateAsIndex => $dateAsString) {
                $currentValue = $itemsAsDateIndexAndValue[$dateAsIndex]??0 ;
                $currentYearTotal +=  $currentValue;
            }
            /**
             * * هنحط النتيجه بتاعتك كل سنه عند اخر شهر في السنه دي
             */
			if(isset($dateAsIndex)){
				$result[$dateAsIndex] = $currentYearTotal;
			}
        }
        return $result ;
    }
    
    public static function calculatePercentageOf(array $salesRevenues, array $items):array
    {
        $result = [];
        foreach ($salesRevenues as $dateIndex => $salesValue) {
            $currenItemVal = $items[$dateIndex]??0 ;
            $result[$dateIndex] =$salesValue ? $currenItemVal  / $salesValue * 100 : 0;
        }
        return $result;
    }
    public static function MultiplyWithNumberIfPositiveAndZeroOtherValues(array $items, float $number)
    {
        $newItems = [];
        foreach ($items as $key=>$value) {
            if ($value < 0) {
                $newItems[$key]=0;
            } else {
                $newItems[$key]=$value * $number ;
            }
        }
        return $newItems ;
    }
    public static function MultiplyWithNumberIfOnlyPositive(array $items, float $number)
    {
        $newItems = [];
        foreach ($items as $key=>$value) {
            if ($value < 0) {
                $newItems[$key]=$value;
            } else {
                $newItems[$key]=$value * $number ;
            }
        }
        return $newItems ;
    }
    public static function fillArr($collection):array
    {
        $firstKey = array_key_first($collection);
        $lastKey = array_key_last($collection);
        $dates = range($firstKey, $lastKey);
        $result = [];
        foreach ($dates as $dateAsIndex) {
            $result[$dateAsIndex] = $collection[$dateAsIndex]??0;
        }
        return $result;
    }
    
    public static function encodeArr(array $items):array
    {
        $result = [];
        foreach ($items as $key => $val) {
            if (is_array($val)) {
                $result[$key] = json_encode($val);
            } else {
                $result[$key] = $val ;
            }
        }
        return $result;
    }
    public static function slice_from_index(array $arr, int $index)
    {
        $result = [];
        foreach ($arr as $currentIndex => $value) {
            if ($currentIndex >= $index) {
                $result[$currentIndex] = $value;
            }
        }
        return $result;
    }
    public static function slice_from_start_index_and_end_index(array $arr, int $startIndex, $endIndex)
    {
        $result = [];
        foreach ($arr as $currentIndex => $value) {
            if ($currentIndex >= $startIndex && $currentIndex<= $endIndex) {
                $result[$currentIndex] = $value;
            }
        }
        return $result;
    }
    public static function getPerYearIndexForEndBalance(array $itemsAsDateIndexAndValue, array $yearWithItsMonths):array
    {
        $result = [];
        foreach ($yearWithItsMonths as $yearIndex => $itsMonths) {
            $currentYearTotal = 0;
            foreach ($itsMonths as $dateAsIndex => $dateAsString) {
                $currentValue = $itemsAsDateIndexAndValue[$dateAsIndex]??0 ;
                $currentYearTotal =  $currentValue;
            }
			 /**
             * * هنحط النتيجه بتاعتك كل سنه عند اخر شهر في السنه دي
             */
			if(isset($dateAsIndex)){
				$result[$dateAsIndex] = $currentYearTotal;
			}
           
        }
        return $result ;
    }
    public static function formatMultiSubItems(array $subItems, array $sumKeys, array $columns):array
    {
        $totalSubItems = [];
        foreach ($subItems as $subItemJson) {
        
            $subItemArr = (array)json_decode($subItemJson);
            if ($subItemArr) {
                foreach ($columns as $columnName) {
                    $subItemArr = (array)($subItemArr[$columnName]??[]);
                }
            }
            $totalSubItems = HArr::sumAtDates([$totalSubItems , $subItemArr], $sumKeys);
            
        }
        return $totalSubItems;
    }
    public static function formatMultiSubItemsPerKey(array $subItems, array $sumKeys, array $columns):array
    {
        $totalSubItems = [];
        foreach ($subItems as $name => $subItemJson) {
            
        
            $subItemArr = (array)json_decode($subItemJson);
            if ($subItemArr) {
                foreach ($columns as $columnName) {
                    $subItemArr = (array)($subItemArr[$columnName]??[]);
                }
            }
            $totalSubItems[$name] = HArr::sumAtDates([$totalSubItems , $subItemArr], $sumKeys);
            
        }
        return $totalSubItems;
    }

    public static function sumFromIndexToTheEnd($schedulePayments, $currentDateIndex):float
    {
        $result = 0 ;
        foreach ($schedulePayments as $dateAsIndex => $value) {
            if ($dateAsIndex > $currentDateIndex) {
                $result+= $value;
            }
        }
        return $result;
    }
    public static function sumStartingFromIndexToTheEnd($schedulePayments, $currentDateIndex):float
    {
        $result = 0 ;
        foreach ($schedulePayments as $dateAsIndex => $value) {
            if ($dateAsIndex >= $currentDateIndex) {
                $result+= $value;
            }
        }
        return $result;
    }
    public static function sumFromCurrentIndexToTheEnd(array $items, array $sumKeys):array
    {
        $result = [];
        foreach ($items as $item) {
            $schedulePayments = json_decode($item->endBalance, true);
            foreach ($schedulePayments as $currentDateIndex => $value) {
                $result[$currentDateIndex] = HArr::sumFromIndexToTheEnd($schedulePayments, $currentDateIndex);
            }
        }
        return $result;
    }
    
    public static function getPerYearIndexForFirstMonthInYear(array $itemsAsDateIndexAndValue, array $yearWithItsMonths):array
    {
        $result = [];
        foreach ($yearWithItsMonths as $yearIndex => $itsMonths) {
            $currentYearTotal = 0;
            $isFirstMonth = true ;
            foreach ($itsMonths as $dateAsIndex => $dateAsString) {
                if ($isFirstMonth) {
                    $currentValue = $itemsAsDateIndexAndValue[$dateAsIndex]??0 ;
                    $currentYearTotal =  $currentValue;
                    $isFirstMonth = false ;
                }
            }
            /**
             * * هنحط النتيجه بتاعتك كل سنه عند اخر شهر في السنه دي
             */
			if(isset($dateAsIndex)){
				$result[$dateAsIndex] = $currentYearTotal;
			}
        }
        return $result ;
    }
    public static function calculateRetainEarning(float $retainedEarningOpening, array $netProfit):array
    {
        $retainedEarnings  = [0 => $retainedEarningOpening];
        foreach ($netProfit as $dateAsIndex => $value) {
            if ($dateAsIndex == 0) {
                continue ;
            }
            $previousNetProfit = $netProfit[$dateAsIndex-1] ?? 0 ;
            $previousRetainedEarning = $retainedEarnings[$dateAsIndex-1]??0;
            $retainedEarnings[$dateAsIndex] = $previousNetProfit + $previousRetainedEarning;
            
        }
        return $retainedEarnings;
    }
    public static function onlyLastValuesInMultiArr(array $items):array
    {
        $months = [];
        foreach ($items as $key => $itemArr) {
            foreach ($itemArr as $k1 => $v1) {
                $months[] = $v1;
            }
        }
        return $months;
        
    }
    public static function onlyKeysWithValues(array $items):array
    {
        $result =[];
        foreach ($items as $key => $value) {
            if ($value > 0) {
                $result[] = $key;
            }
        }
        return $result;
    }
    
    
    // public static function getNowOrPreviousNonZeroValue(array $items, int $key)
    // {
     
    //     $key = $key - 1 ;
    //     while ($key != 0) {
    //         if (!isset($items[$key])) {
    //             return null;
    //         }
    //         if ($items[$key] > 0) {
    
    //             return $key;
    //         }
    //         $key--;
    //     }
        
    // }
    
    
    public static function getNowOrNextNonZeroValue(array $items, int $key)
    {
        if (isset($items[$key]) && $items[$key ]>0) {
            return $key;
        }
        $key = $key+1;
        $lastKey = array_key_last($items);
        while ($key != 0) {
            if (!isset($items[$key]) && $key > $lastKey) {
                return null;
            }
            if (isset($items[$key]) && $items[$key] > 0) {
                return $key;
            }
            $key++;
        }
        
    }
    
    public static function getNextNonZeroValue(array $items, int $key)
    {
        //   if (isset($items[$key]) && $items[$key ]>0) {
        //         return $key;
        //     }
    
        $key = $key+1;
        $lastKey = array_key_last($items);
        
        while ($key != 0) {
            if (!isset($items[$key]) && $key > $lastKey) {
                return null;
            }
            if (isset($items[$key]) && $items[$key] > 0) {
                return $key;
            }
            $key++;
        }
        
    }
    
    
    
    public static function getNetPresentValueFromEachMonth(array $items):array
    {
        $result = [];
        foreach ($items as $portfolioCategoryId => $item) {
            $item = json_decode($item, true);
            foreach ($item as $monthIndex => $netPresentValue) {
                $result[$portfolioCategoryId][$monthIndex] = $netPresentValue['net_present_value']??0 ;
            }
        }
        return $result ;
    }
    public static function removeIndexesFrom(array $items, int $dateAsIndex)
    {
        $result = [];
        foreach ($items as $key => $values) {
            foreach ($values as $currentDateAsIndex => $value) {
                if ($currentDateAsIndex < $dateAsIndex) {
                    $result[$key][$currentDateAsIndex] = $value;
                }
                
            }
        }
        return $result;

    }
    public static function fillMissedKeysByZero(array $items, array $dates, $value = 0)
    {
        $result = [];
        foreach ($dates as $dateAsIndex) {
            $currentValue = $items[$dateAsIndex] ?? $value ;
            $result[$dateAsIndex] =$currentValue;
        }
        return $result;
    }
    public static function zeroIfAtRange(array $items, int $min, int $max)
    {
        foreach ($items as $dateAsIndex => &$value) {
            if ($value >= $min && $value <= $max) {
                $value = 0;
            }
        }
        return $items;
    }
    public static function divideTwoArrAtSameIndex(array $firstArr, array $secondArr)
    {
        $result = [];
        foreach ($firstArr as $index => $value) {
            $secondAtValue = $secondArr[$index]??0;
            $result[$index] = $secondAtValue ?  $value / $secondAtValue  : 0;
        }
        return $result ;
    }
    public static function allValuesZeroIfTotalIsLessThanOrEqualZero($calculatedCorporateTaxesPerYear, $ebt):array
    {
        if (array_sum($ebt) <= 0) {
            foreach ($calculatedCorporateTaxesPerYear as $dateAsIndex => &$value) {
                $value = 0 ;
            }
        }
        return $calculatedCorporateTaxesPerYear;
        
    }
    public static function sumFormattedArr(array $items)
    {
        $sum = 0 ;
        foreach ($items as $no) {
            $sum+=number_unformat($no);
        }
        return $sum;
    }
    public static function deepMergeAndSum($arr1, $arr2)
    {
        foreach ($arr2 as $key => $value) {

            // key exists in arr1
            if (array_key_exists($key, $arr1)) {

                // 1) both values arrays → merge recursively
                if (is_array($arr1[$key]) && is_array($value)) {
                    $arr1[$key] = self::deepMergeAndSum($arr1[$key], $value);
                }

                // 2) both values numeric → sum
                elseif (is_numeric($arr1[$key]) && is_numeric($value)) {
                    $arr1[$key] += $value;
                }

                // 3) if type mismatch or not summable → keep arr1 value
                else {
                    // do nothing
                }

            } else {
                // key missing in arr1 → copy from arr2
                $arr1[$key] = $value;
            }
        }

        return $arr1;
    }
    public static function MultiplyWithNumberIfPositive(array $items, float $number)
    {
        $newItems = [];
        foreach ($items as $key=>$value) {
            if ($value < 0) {
                $newItems[$key]=0;
            } else {
                $newItems[$key]=$value * $number ;
            }
        }
        return $newItems ;
    }
    public static function calculateChangeInAfter(array $customerReceivables, float $openingBalance, array $yearIndexWithLastMonth, $debug=false)
    {
        
        $isFirst = true ;
        $result = [];
        foreach ($yearIndexWithLastMonth as $yearIndex => $lastMonthAsDateIndex) {
            $currentCustomerReceivables = $customerReceivables[$lastMonthAsDateIndex]??0;
            if ($isFirst) {
                $currentCustomerReceivables= $openingBalance - $currentCustomerReceivables ;
                $isFirst = false ;
            } else {
                $nextIndex = $lastMonthAsDateIndex - 12 ;
                $nextYearValue = $customerReceivables[$nextIndex]??0;
                $currentCustomerReceivables = $nextYearValue - $currentCustomerReceivables ;
            }
                
            $result[$lastMonthAsDateIndex] = $currentCustomerReceivables ;
            
        }
        
        return $result;
    }
    public static function calculateChangeInBefore(array $customerReceivables, float $openingBalance, array $yearIndexWithLastMonth)
    {
        
        $isFirst = true ;
        $result = [];
        foreach ($yearIndexWithLastMonth as $yearIndex => $lastMonthAsDateIndex) {
            $currentCustomerReceivables = $customerReceivables[$lastMonthAsDateIndex]??0;
            if ($isFirst) {
                $currentCustomerReceivables= $currentCustomerReceivables- $openingBalance  ;
                $isFirst = false ;
            } else {
                $nextIndex = $lastMonthAsDateIndex - 12 ;
                $nextYearValue = $customerReceivables[$nextIndex]??0;
                $currentCustomerReceivables = $currentCustomerReceivables- $nextYearValue  ;
            }
                
            $result[$lastMonthAsDateIndex] = $currentCustomerReceivables ;
            
        }
        
        return $result;
        
    }
    public static function getLastMonthOfYear(array $yearWithItsMonths)
    {
        $result = [];
        foreach ($yearWithItsMonths as $yearAsIndex => $itsMonths) {
            $result[$yearAsIndex]  = array_key_last($itsMonths);
        }
        return $result;
    }
    public static function replacePreviousValues(array $items, int $backStepsNo):array
    {
    
        $formattedResult = $items;
    
        foreach ($items as $keyName => $dateAndValues) {
            $formattedResult[$keyName] = $dateAndValues;
            if ($keyName == 'beginning' || $keyName == 'endBalance') {
                $formattedResult[$keyName] = HArr::replaceNumberWithItsNextNumber($dateAndValues, $backStepsNo);
            } else {
                $formattedResult[$keyName] = HArr::addNumberWithItsPreviousNumberAndMakeItZero($dateAndValues, $backStepsNo);
            }
        }
        return $formattedResult;
    }
    protected static function replaceNumberWithItsNextNumber($dateAndValues, int $backStepsNo)
    {
        $firstDateIndex = array_key_first($dateAndValues);
        $formattedResult = [];
        foreach ($dateAndValues as $currentDateIndex => $value) {
            $newIndex = 	$currentDateIndex-$backStepsNo;
            if ($newIndex>=$firstDateIndex) {
                $formattedResult[$newIndex] = $value;
            }
            
        }
        return $formattedResult;
    }
    protected static function addNumberWithItsPreviousNumberAndMakeItZero($dateAndValues, int $backStepsNo)
    {
        $formattedResult = [];
        $firstDateIndex = array_key_first($dateAndValues);
        foreach ($dateAndValues as $currentDateIndex => $value) {
            $newIndex = 	$currentDateIndex-$backStepsNo;
            if ($currentDateIndex==$firstDateIndex) {
                $formattedResult[$currentDateIndex] = $value;
            } elseif ($newIndex<=$firstDateIndex) {
                $formattedResult[$firstDateIndex]=$value +($formattedResult[$firstDateIndex]);
                $formattedResult[$currentDateIndex]=0;
            } else {
                $formattedResult[$newIndex]=$value ;
            }
            
        }
    
        return $formattedResult;
    }
    public static function array_get($array, $key, $default = [])
    {
        return Arr::get($array, $key, $default);
    }

	
public static function getFinancialMonthsForSelect(): array
{
    $formattedMonths = [];
    $months = [

        'january' => __('January'), "april" => __('April'), 'july' => __('July')
    ];
    foreach ($months as $monthName => $monthNameFormatted) {
        $formattedMonths[$monthName] = ['title' => $monthNameFormatted, 'value' => $monthName];
    }
    return $formattedMonths;
}

public static function repeatLastValueInArrayUntil(array $itemsArray, int $studyEndDate)
{

    if (!count($itemsArray)) {
        return null ;
    }
    $lastKey = array_key_last($itemsArray);
    $loopingKey = $lastKey+1;
    for ($loopingKey ; $loopingKey <= $studyEndDate ; $loopingKey++) {
        $itemsArray[$loopingKey] =$itemsArray[$lastKey];
    }
    return $itemsArray;
}


public static function sliceArrayKeyToEnd($array, $key)
{
    $keys = array_keys($array);

    // Find the index of "Total Cash Inflow"
    $totalCashInflowIndex = array_search($key, $keys);

    // Get the sub-array starting from the key after "Total Cash Inflow"
    return array_slice($array, $totalCashInflowIndex + 1);
}

public static function calculateAccumulatedDepreciation(array $totalMonthlyDepreciation, array $studyDates)
{
    $result = [];
    foreach ($studyDates as $dateIndex) {
        $value = $totalMonthlyDepreciation[$dateIndex] ?? 0;
        $previousDateAsIndex = $dateIndex-1;
        $result[$dateIndex] = $previousDateAsIndex >=0 ?  $result[$previousDateAsIndex] + $value : $value;
    }
    return $result;
}

public static function calculateReplacementDates(array $studyDates, int $operationStartDateAsIndex, int $studyEndDateAsIndex, int $propertyReplacementIntervalInMonths)
{
    $replacementDates = [];
    foreach ($studyDates as $studyDateAsString=>$studyDateAsIndex) {
        if ($operationStartDateAsIndex > $studyEndDateAsIndex) {
            break ;
        }
        $replacementDates[$studyDateAsIndex] = $operationStartDateAsIndex+ $propertyReplacementIntervalInMonths;
        $operationStartDateAsIndex = $replacementDates[$studyDateAsIndex] ;
    }
    return $replacementDates ;
}

public static function sumTwoArray(array $first, array $second)
{
    $result  =[];
    $dates = array_values(array_unique(array_merge(array_keys($first), array_keys($second))));
    foreach ($dates as $date) {
        $secondVal = $second[$date] ?? 0;
        $value = $first[$date] ?? 0;
        $result[$date] = $value  + $secondVal ;
    }
    return $result ;
}
public static function formatMonths($numberOfMonths):array
{
    $result =[ ];
    for ($i = 0 ; $i<= $numberOfMonths ; $i++) {
        $result[$i] = __('Mth-').$i;
    }
    return $result;
}

public static function stringArrayToArray(string $str)
{
    if (!$str) {
        return [];
    }

    return 	eval('return ' . $str . ';');
}


public static function replaceArr($mainIdsWithItsValues, $equation)
{
    $number = preg_replace('/[0-9]+/', ',', $equation);
    $numberExploded = explode(',', $number);
    $signs = array_filter($numberExploded, function ($n) {
        return $n;
    });
    $signs = array_values($signs);
    $result = '';
    $index = 0;
    array_map(function ($n) use (&$result, &$index, $signs) {
        $sign = $signs[$index] ?? '';
        $result .= $n . $sign;
        $index++;
    }, $mainIdsWithItsValues);
    return str_replace('--', '+', $result);
}



}
