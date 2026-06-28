<?php

use App\Enums\LcTypes;
use App\Enums\LgTypes;
use App\Helpers\HArr;
use App\Helpers\HHelpers;
use App\Helpers\HStr;
use App\Helpers\HVero;
use App\Http\Controllers\Analysis\SalesGathering\BranchesAgainstAnalysisReport;
use App\Http\Controllers\Analysis\SalesGathering\BusinessSectorsAgainstAnalysisReport;
use App\Http\Controllers\Analysis\SalesGathering\CategoriesAgainstAnalysisReport;
use App\Http\Controllers\Analysis\SalesGathering\ExpenseAgainstAnalysisReport;
use App\Http\Controllers\Analysis\SalesGathering\ExportAgainstAnalysisReport;
use App\Http\Controllers\Analysis\SalesGathering\ProductsAgainstAnalysisReport;
use App\Http\Controllers\Analysis\SalesGathering\SalesChannelsAgainstAnalysisReport;
use App\Http\Controllers\Analysis\SalesGathering\SalesPersonsAgainstAnalysisReport;
use App\Http\Controllers\Analysis\SalesGathering\SKUsAgainstAnalysisReport;
use App\Http\Controllers\Analysis\SalesGathering\ZoneAgainstAnalysisReport;
use App\Http\Controllers\ExportTable;
use App\Models\Branch;
use App\Models\Bank;
use App\Models\CachingCompany;
use App\Models\CollectionSetting;
use App\Models\Company;
use App\Models\Country;
use App\Models\CustomizedFieldsExportation;
use App\Models\ExistingProductAllocationBase;
use App\Models\FinancialInstitution;
use App\Models\FinancialInstitutionAccount;
use App\Models\IncomeStatement;
use App\Models\IncomeStatementItem;
use App\Models\IncomeStatementSubItem;
use App\Models\ModifiedSeasonality;
use App\Models\Partner;
use App\Models\ProductSeasonality;
use App\Models\QuantityExistingProductAllocationBase;
use App\Models\QuantityModifiedSeasonality;
use App\Models\QuantityProductSeasonality;
use App\Models\QuantitySalesForecast;
use App\Models\QuantitySecondExistingProductAllocationBase;
use App\Models\SalesForecast;
use App\Models\SalesGathering;
use App\Models\SecondAllocationSetting;
use App\Models\SecondExistingProductAllocationBase;
use App\Models\SecondNewProductAllocationBase;
use App\Models\Section;
use App\Models\User;
use App\Services\IntervalSummationOperations;
use App\Traits\Intervals;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

const Customers_Against_Products_Trend_Analysis = 'Customers Against Products Trend Analysis';
const Customers_Against_Categories_Trend_Analysis = 'Customers Against Categories Trend Analysis';
const Customers_Against_Products_ITEMS_Trend_Analysis = 'Customers Against Products Items Trend Analysis';
const INVOICES = 'Invoices';


const uploadCustomerInvoiceData ='upload customer invoice analysis data';
const exportCustomerInvoiceData ='export customer invoice analysis data';
const deleteCustomerInvoiceData ='delete customer invoice analysis data';
const viewCustomerInvoiceData ='view customer invoice analysis data';

const uploadExportAnalysisData ='upload export analysis data';
const exportExportAnalysisData ='export export analysis data';
const deleteExportAnalysisData ='delete export analysis data';
const viewExportAnalysisData ='view export analysis data';

const uploadExpenseAnalysisData ='upload expense analysis data';
const exportExpenseAnalysisData ='export expense analysis data';
const deleteExpenseAnalysisData ='delete expense analysis data';
const viewExpenseAnalysisData ='view expense analysis data';

const uploadSupplierInvoiceData ='upload supplier invoice analysis data';
const exportSupplierInvoiceData ='export supplier invoice analysis data';
const deleteSupplierInvoiceData ='delete supplier invoice analysis data';
const viewSupplierInvoiceData ='view supplier invoice analysis data';

const uploadLoanScheduleData ='upload loan schedule analysis data';
const exportLoanScheduleData ='export loan schedule analysis data';
const deleteLoanScheduleData ='delete loan schedule analysis data';
const viewLoanScheduleData ='view loan schedule analysis data';



const CASH_VERO = 'cash-vero';





const MAX_YEARS_COUNT = 7 ;
// const FINANCIAL_PLANNING_MAX_YEARS_COUNT = 7 ;
const quantityIdentifier = ' ( Quantity )';




function getPeriods($interval)
{
    if ($interval == 'monthly') {
        return  [
            1 => [1],
            2 => [2],
            3 => [3],
            4 => [4],
            5 => [5],
            6 => [6],
            7 => [7],
            8 => [8],
            9 => [9],
            10 => [10],
            11 => [11],
            12 => [12],
        ];
    }

    if ($interval == 'quarterly') {
        return [
            3 => [1, 2, 3], 6 => [4, 5, 6], 9 => [7, 8, 9], 12 => [10, 11, 12]
        ];
    }
    if ($interval == 'semi-annually') {
        return [
            6 => [1, 2, 3, 4, 5, 6], 12 => [7, 8, 9, 10, 11, 12]
        ];
    }

    if ($interval == 'annually') {
        return [
            12 => [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12]
        ];
    }
}

function flatten(array $array)
{
    $return = [];
    array_walk_recursive($array, function ($a) use (&$return) {
        $return[] = $a;
    });

    return $return;
}


function camelize($input, $separator = '_')
{
    return str_replace($separator, '', ucwords($input, $separator));
}

if (!function_exists('lang')) {
    function lang()
    {
        return  app()->getLocale();
    }
}



if (!function_exists('exportableFields')) {
    function exportableFields($company_id, $model)
    {
        if (Auth::check()) {
            $fields = CustomizedFieldsExportation::where('model_name', $model)->where('company_id', $company_id)->first();
            return  $fields;
        }
    }
}


if (!function_exists('dateFormatting')) {
    function dateFormatting($date, $formate = 'd-m-Y')
    {
        return date($formate, strtotime($date));
    }
}
if (!function_exists('routeName')) {
    function routeName($route)
    {
        $route_array = explode('.', $route);
        $route = $route_array[0];

        return $route;
    }
}


function getExportableFields($companyId = null): array
{
    $company  = Company::find($companyId ?: Request()->segment(2));
    if ($company) {
        return (new ExportTable)->customizedTableField($company, 'SalesGathering', 'selected_fields');
    }

    return [];
}


function getExportableFieldsForModel($companyId, $modelName): array
{
    $company  = Company::find($companyId ?: Request()->segment(2));
    if ($company) {
        return (new ExportTable)->customizedTableField($company, $modelName, 'selected_fields');
    }
    return [];
}












function generateIdForExcelRow(int $companyId)
{
    return uniqid('company_' . $companyId) . Str::random(9) . $companyId . uniqid();
}

function getTotalUploadCacheKey($company_id, $jobId, string $modelName)
{
    return 'total_uploaded_for_company_' . $company_id . 'for_job_' . $jobId .'for_model'. $modelName;
}

function getShowCompletedTestMessageCacheKey($companyId, $modelName)
{
    return 'show_complete_test_phase_' . $companyId.$modelName;
}





function getCanReloadUploadPageCachingForCompany($companyId, $modelName)
{
    return 'can_reload_caching_page_for_company_' . $companyId.$modelName;
}




function getCurrentCompanyId()
{
    return Request()->segment(2) ?? null;
}

function getCompanyId()
{
    //  admin.get.revenue-business-line
    return Request()->segment(2);
}


function getModelNamespace()
{
    return '\App\Models\\';
}

function generateDatesBetweenTwoDates(Carbon $start_date, Carbon $end_date, $method = 'addMonth', $format = 'Y-m-d', $indexedArray = true, $indexFormat = 'Y-m-d')
{
    $dates = [];
    for ($date = $start_date->copy(); $date->lte($end_date); $date->{$method}()->setTime(0, 0)) {
        if ($indexedArray) {
            $dates[] = $date->format($format);
        } else {
            $dates[$date->format($indexFormat)] = $date->format($format);
        }
    }
    return $dates;
}
/**
 * ! USE HDate::generateDatesBetweenStartDateAndDuration instead of this function
 */
function generateDatesBetweenTwoDatesWithoutOverflow(Carbon $start_date, Carbon $end_date, $method = 'addMonthNoOverflow', $format = 'Y-m-d', $indexedArray = true, $indexFormat = 'Y-m-d')
{
    $dates = [];
    for ($date = $start_date->copy(); $date->lte($end_date); $date->{$method}()->setTime(0, 0)) {
        if ($indexedArray) {
            $dates[] = $date->format($format);
        } else {
            $dates[$date->format($indexFormat)] = $date->format($format);
        }
    }
    return $dates;
}







function formatOptionsForSelect(Collection $items, $idFun = 'getId', $valueFun = 'getName'): array
{
    $formattedData = [];
    foreach ($items as $item) {
        $formattedData[] = [
            'value' => $item->$idFun(),
            'title' => $item->$valueFun(),
        ];
    }

    return $formattedData;
}

function formatSelects($selects, $selectedItem, $id, $value, $addNew = false, $selectAll = false): string
{
    $result = '';
    if ($addNew) {
        // $result = '<option class="add-new-item" >'. __('Add New')  .' </option>';
    } elseif ($selectAll) {
        $result = '<option>' . __('All') . '</option> ';
    } else {
        $result = '';
    }

    foreach ($selects as $select) {
        $ID = $select->{$id};
        $val = $select->{$value};

        if (
            in_array($ID, explode(',', $selectedItem))
        ) {
            $result .= "<option value='$ID' selected> $val </option> ";
        } else {
            $result .= "<option value='$ID' > $val </option> ";
        }
    }

    return $result;
}

function getExportDateTime(): string
{
    return now()->toDateTimeString();
}
function getExportUserName()
{
   
    $user = Auth()->user() ;
    return  $user ? $user->getName() : null;
}




function isActualDate(string $dateString): bool
{
    
    $year = explode('-', $dateString)[0];
    $month = explode('-', $dateString)[1];

    $now = now()->format('Y-m-d');
    $currentYear = explode('-', $now)[0];
    $currentMonth = explode('-', $now)[1];
    $date = Carbon::make(Carbon::createFromDate($year, $month, 1)->format('Y-m-d'));
    $currentDate = Carbon::make(Carbon::createFromDate($currentYear, $currentMonth, 1)->format('Y-m-d'));

    return $currentDate->greaterThan($date);
}

function getPercentageColor($val): string
{
    if ($val > 0) {
        return 'green ';
    } elseif ($val < 0) {
        return 'red ';
    }

    return '';
}

function getPercentageColorOfSubTypes($val, $type): string
{
    if (($type == 'Sales Revenue' || $type == 'Gross Profit' || $type == 'Earning Before Interest Taxes Depreciation Amortization - EBITDA' || $type == 'Earning Before Interest Taxes - EBIT' || $type == 'Earning Before Taxes - EBT' || $type == 'Net Profit') && $val >= 0
        || (($type == 'Cost Of Goods / Service Sold' || $type == 'Marketing Expenses' || $type == 'Sales Expenses' || $type == 'General Expenses' || $type == 'Finance Income/(Expenses)' || $type == 'Corporate Taxes') && $val <= 0)

    ) {
        return 'green ';
    } else {
        return 'red ';
    }
    // if ($val > 0) {
    // 	return 'green ';
    // } elseif ($val < 0) {
    // 	return 'red ';
    // }
    // return '';
}

function convertStringToClass(string $str): string
{
    $reg = " /^[\d]+|[!\"#$%&'\(\)\*\+,\.\/:;<=>\?\@\~\{\|\}\^ ]/ ";

    return preg_replace($reg, '-', $str);
}
function secondReportIsFirstInArray(string $firstReportType, string $secondReportType)
{
    return $firstReportType != 'forecast' && $secondReportType != 'modified' && $secondReportType != 'actual';
}
function getFirstSegmentInString(string $str, string $separator): string
{
    return 	explode($separator, $str)[0];
}
function getDependsMaps($financialStatementAbleId, $financialStatementAbleClass): array
{
    return $financialStatementAbleClass::find($financialStatementAbleId)->mainItems->pluck('depends_on', 'id')->toArray();
}
function getLastSegmentInRequest()
{
    return Request()->segments()[count(Request()->segments()) - 1];
}

function getUploadModelNameFromRequest(): string
{
    if (in_array('ContractLoanSchedule', Request()->segments(), true)) {
        return 'ContractLoanSchedule';
    }

    if (in_array('LoanSchedule', Request()->segments(), true)) {
        return 'LoanSchedule';
    }

    return getLastSegmentInRequest();
}

function getUploadContextIdFromRequest(): ?string
{
    if (! in_array(getUploadModelNameFromRequest(), ['LoanSchedule', 'ContractLoanSchedule'], true)) {
        return null;
    }

    $lastSegment = getLastSegmentInRequest();

    return is_numeric($lastSegment) ? (string) $lastSegment : null;
}

function getUploadingRouteParams(int $companyId, ?string $modelName = null, ?string $contextId = null): array
{
    $modelName ??= getUploadModelNameFromRequest();
    $contextId ??= getUploadContextIdFromRequest();

    $params = [
        'company' => $companyId,
        'model' => $modelName,
    ];

    if ($contextId) {
        $params['loanId'] = $contextId;
    }

    return $params;
}
// function getLastNonNumericSegmentInRequest()
// {
// 	return Request()->segments()[count(Request()->segments()) - 1];
// }
function getTotalPerYears(array $array)
{
    $totalPerYears = [];
    foreach ($array as $date => $valArr) {
        $year = explode('-', $date)[0];
        if (isset($totalPerYears[$year])) {
            $totalPerYears[$year] += $valArr['total_with_depreciation'];
        } else {
            $totalPerYears[$year] = $valArr['total_with_depreciation'];
        }
    }

    return $totalPerYears;
}
function getPreviousDate(?array $array, ?string $date, $datesExistsAsKeys = true)
{
    if (empty($array) || $date === null) {
        return null;
    }
    $searched = array_search($date, $datesExistsAsKeys ? array_keys($array) : $array);
    $arrayPlusOne = $datesExistsAsKeys ? @array_keys($array)[$searched - 1] : @($array)[$searched - 1];
    if ($searched !== false &&  isset($arrayPlusOne)) {
        return $datesExistsAsKeys ? array_keys($array)[$searched - 1] : ($array)[$searched - 1];
    }

    return null;
}




function strEndsWith($string, $endString)
{
    $len = strlen($endString);
    if ($len == 0) {
        return true;
    }

    return substr($string, -$len) === $endString;
}
function hideExportField($fieldName): bool
{
    $hidden  = ['local_or_export', 'sub_category', 'return_reason', 'quantity_status', 'quantity_bonus'];

    return in_array($fieldName, $hidden);
}








function convertJsonToArray(?string $json):array
{
    return $json ? (array)json_decode($json) : [];
}



function filterPermissionForSystemName($permissions, array $systemsNames):array
{
    $result =[];
    foreach ($permissions as $permissionArr) {
        if (HArr::atLeastOneValueExistInArray($systemsNames, $permissionArr['systems'])) {
            $result[] = $permissionArr;
        }
        
    }
    return $result ;
}
function generateReportName($reportName)
{
    if ($reportName === 'product items') {
        $reportName ='products items';
    }
    if ($reportName =='products / service') {
        $reportName ='products / services';
    }

    return 'view ' . $reportName . ' report';
}
function reportNames()
{
    return  [
        'zone'=>'zone', // here
        'sales channel'=>'sales channel',
        'customers'=>'customers',
        'business sector'=>'business sector',
        'business unit'=>'business unit',
        'branch'=>'branch',
        'category'=>'category', // here
        'principle'=>'principle',
        'products / services'=>'products / services', //here
        'products / service'=>'products / service', //here
        'products items'=>'products items', // here
        'product items'=>'product items', // here
        'average prices'=>'average prices', // here
        'sales persons'=>'sales persons',
        'discount'=>'discount',
        'invoice'=>'invoice',
        'country'=>'country',
        'service provider'=>'service provider', // here

    ];
}
function str_plural($str)
{
    return Str::plural($str);
}
function searchWordInstr(array $words, string $sentence)
{
    $foundWords = [];
    foreach ($words as $word) {
        if (strpos($sentence, $word) !== false || strpos($sentence, ucwords($word)) !== false
        || strpos($sentence, Str::plural($word)) !== false
        || strpos($sentence, Str::plural(ucwords($word))) !== false


        ) {
            $foundWords[]=$word;
        }
    }

    return $foundWords;
}
function getColorForIndexes($firstValue, $secondValue, $elementIndex)
{
    if (($elementIndex == 0 ||$elementIndex==2 ||$elementIndex==6|| $elementIndex==7||$elementIndex==9||$elementIndex==11) &&  ($secondValue >= $firstValue)) {
        return 'green !important';
    } elseif ($elementIndex == 0 ||$elementIndex==2 ||$elementIndex==6|| $elementIndex==7||$elementIndex==9||$elementIndex==11) {
        return 'red !important';
    }

    if (($elementIndex == 1 ||$elementIndex==3 ||$elementIndex==4|| $elementIndex==5||$elementIndex==8||$elementIndex==10) &&  ($secondValue < $firstValue)) {
        return 'green !important';
    } elseif ($elementIndex == 1 ||$elementIndex==3 ||$elementIndex==4|| $elementIndex==5||$elementIndex==8||$elementIndex==10) {
        return 'red !important';
    }
}
function checkIfAllDates(array $dates):array
{
    $validDates = [];
    foreach ($dates as $date) {
        if (DateTime::createFromFormat('Y-m', $date) !== false) {
            $validDates[] =$date;
        }
    }

    return $validDates;
}

function number_unformat($number, $force_number = true, $dec_point = '.', $thousands_sep = ',')
{
    $isNegativeNumber = str_starts_with($number, '-');
    if ($force_number) {
        $number = preg_replace('/^[^\d]+/', '', $number);
    } elseif (preg_match('/^[^\d]+/', $number)) {
        return false;
    }
    $type = (strpos($number, $dec_point) === false) ? 'int' : 'float';
    $number = str_replace([$dec_point, $thousands_sep], ['.', ''], $number);
    settype($number, $type);
    if ($isNegativeNumber) {
        $number  = $number * -1 ;
    }
    return $number;
}






function formatDateForView($date)
{
    return Carbon::make($date)->format('M\'Y');
}
function formatDateForViewWithDay($date)
{
    return Carbon::make($date)->format('d M\'Y');
}









function getNextDate(?array $array, ?string $date, $datesExistsAsKeys = true)
{

    $searched = array_search($date, $datesExistsAsKeys ? array_keys($array) : $array);
    $arrayPlusOne = $datesExistsAsKeys ? @array_keys($array)[$searched +1] : @($array)[$searched +1];
    if ($searched !== false &&  isset($arrayPlusOne)) {
        return $datesExistsAsKeys ? array_keys($array)[$searched +1] : ($array)[$searched +1];
    }
    return null;
}
function sumIntervals(array $dateValues, string $intervalName)
{
    return (new IntervalSummationOperations())->sumForInterval($dateValues, $intervalName);
}









function formatRatesWithDueDays(array $ratesAndDueDays): array
{
    $result = [];
    foreach ($ratesAndDueDays['due_in_days'] ?? [] as $index => $dueDay) {
        $rate = $ratesAndDueDays['rate'][$index] ?? 0;
        if ($rate) {
            if (isset($result[$dueDay])) {
                $result[$dueDay] += $rate;
            } else {
                $result[$dueDay] = $rate;
            }
        }
    }

    return $result;
}
const PERCENTAGE_DECIMALS = 2 ;
function cacheHas($key)
{
    return Cache::has($key);
}
function generateCacheFailedName($companyId, $userId, $modelName)
{
    return 'failed_company_'.$companyId.'user_id'.$userId . 'failed_job' . $modelName;
}
function CacheGetAndRemove($key)
{
    $message = Cache::get($key) ;
    Cache::forget($key);
    return $message;
}
function hasCachingCompany($companyId, $modelName)
{
    return CachingCompany::where('company_id', $companyId)->where('model', $modelName)->count();
}
function generateCacheKeyForValidationRow($company_id, $modelName)
{
    return 'validation_rows'.$modelName . $company_id;
}
function arrayMergeTwoDimArray(...$args)
{
    $mergedArray = [];
    foreach ($args as $index=>$array) {
        foreach ($array as $key=>$values) {
            $mergedArray[$key] = $values;
        }
    }
    return $mergedArray ;
}
function hasFailedRow($companyId, string $modelName)
{
    $cache=Cache::get(generateCacheKeyForValidationRow($companyId, $modelName));
    return $cache && count($cache);
}
function convertIdsToNames(array $elements)
{
    $newItems = [];
    foreach ($elements as $element) {
        $newItems[] =snakeToCamel($element);
    }
    return $newItems ;
}
function snakeToCamel($input)
{
    return ucfirst(str_replace(' ', ' ', ucwords(str_replace('_', ' ', $input))));
}

function getUploadParamsFromType(?string $type = null):array
{

    $params  = [
      
        'CustomerInvoice'=>[
            'fullModel'=>'\App\Models\CustomerInvoice',
            'dbName'=>'customer_invoices',
            'typePrefixName'=>__('Customer Invoice'),
            'orderByDateField'=>'invoice_date',
            'viewPermissionName'=>viewCustomerInvoiceData,
            'uploadPermissionName'=>uploadCustomerInvoiceData, // important:add this also into permission function names [getPermissions()]
            'exportPermissionName'=>exportCustomerInvoiceData,// important:add this also into permission function names[getPermissions()]
            'deletePermissionName'=>deleteCustomerInvoiceData,// important:add this also into permission function names[getPermissions()]
            'importHeaderText'=>__('Customer Invoice Import'),
        ],

        'SupplierInvoice'=>[
            'fullModel'=>'\App\Models\SupplierInvoice',
            'dbName'=>'supplier_invoices',
            'typePrefixName'=>__('Supplier Invoice'),
            'orderByDateField'=>'invoice_date',
            'viewPermissionName'=>viewSupplierInvoiceData,
            'uploadPermissionName'=>uploadSupplierInvoiceData, // important:add this also into permission function names [getPermissions()]
            'exportPermissionName'=>exportSupplierInvoiceData,// important:add this also into permission function names[getPermissions()]
            'deletePermissionName'=>deleteSupplierInvoiceData,// important:add this also into permission function names[getPermissions()]
            'importHeaderText'=>__('Supplier Invoice Import'),
        ],
        'LoanSchedule'=>[
            'fullModel'=>'\App\Models\LoanSchedule',
            'dbName'=>'loan_schedules',
            'typePrefixName'=>__('Loan Schedule'),
            'orderByDateField'=>'date',
            'viewPermissionName'=>viewLoanScheduleData,
            'uploadPermissionName'=>uploadLoanScheduleData,
            'exportPermissionName'=>exportLoanScheduleData,
            'deletePermissionName'=>deleteLoanScheduleData,
            'importHeaderText'=>__('Loan Schedule Import'),
        ],
        'ContractLoanSchedule'=>[
            'fullModel'=>'\App\Models\ContractLoanSchedule',
            'dbName'=>'contract_loan_schedules',
            'typePrefixName'=>__('Contract Loan Schedule'),
            'orderByDateField'=>'date',
            'viewPermissionName'=>viewLoanScheduleData,
            'uploadPermissionName'=>uploadLoanScheduleData,
            'exportPermissionName'=>exportLoanScheduleData,
            'deletePermissionName'=>deleteLoanScheduleData,
            'importHeaderText'=>__('Contract Loan Schedule Import'),
        ]

    ] ;
    if ($type) {
        return $params[$type];
    }
    return $params ;

}


function camelToTitle(string $str)
{
    return  ucwords(implode(' ', preg_split('/(?=[A-Z])/', $str)));
    ;
}
function getUploadDataText($typePrefixName)
{
    return __("Upload New ". $typePrefixName  ." " . __('Data'));
}



function dateIsBetween(string $date, string $dateFrom, string $dateTo)
{
    return Carbon::make($date)->isBetween($dateFrom, $dateTo);
}
function getSegmentBeforeLast()
{
    return Request()->segments()[count(Request()->segments()) - 2 ];
}
function isValidDateFormat(string $date, string $format)
{
    $d = DateTime::createFromFormat($format, $date);
    return $d && $d->format($format) === $date;
}
function getInvoiceDayIntervals()
{
    return [
        '1-7',
        '8-15',
        '16-30',
        '31-45',
        '46-60',
        '61-90',
        '91-120',
        '121-150'
    ];
}


function getNumericExportFields():array
{
    return ['Quantity' , __('Quantity') , 'Quantity Discount' , __('Quantity Discount') , 'Cash Discount' , __('Cash Discount') , 'Special Discount' , __('Special Discount') , __('Other Discounts') , 'Net Sales Value' , __('Net Sales Value'),'Price Per Unit' , __('Price Per Unit') , __('Sales Value') , __('Sales Value'),'Collected Amount',__('Collection Amount'),'Collected Amount',__('Collected Amount'),'Expected Collection Days',__('Expected Collection Days'),'Contracted Collection Days',__('Contracted Collection Days'),'Net Invoice Amount',__('Net Invoice Amount'),'Withhold Amount',__('Withhold Amount'),'Net Balance',__('Net Balance') , 'Vat Amount',__('Vat Amount'),'Withhold Amount',__('Withhold Amount'),'VAT Amount'];
}
function getNumericWithNegativeAllowedExportFields():array
{
    return [
        'Invoice Amount',__('Invoice Amount'),
        'Invoice Amount'=>__('Invoice Amount')
];
}


function getBanksCurrencies():array
{
    return getCurrencies();

}
function getDiffBetweenTwoDatesInDays(?Carbon $firstDate, ?Carbon $secondDate)
{
    if ($firstDate && $secondDate) {
        return $firstDate->diffInDays($secondDate);
    }
    return 0 ;
}

function getCurrenciesForSuppliersAndCustomers(int $companyId):array
{
    $currencyFromBranch = Branch::where('company_id', $companyId)->pluck('currency', 'currency')->toArray();
    $currencyFromAccounts = FinancialInstitutionAccount::where('company_id', $companyId)->pluck('currency', 'currency')->toArray() ;
    return array_merge($currencyFromBranch, $currencyFromAccounts);

}
function getCurrencies()
{
    return [
        'EGP' => __('EGP'),
        'USD' => __('USD'),
        'EURO' => __('EURO'),
        'SAR' => __('SAR'),
        'AED' => __('AED'),
        'GBP' => __('GBP'),
        'OMR'=> __('OMR')
    ];
}
function isValidScheduleDate(?string $date): bool
{
    if ($date === null || $date === '' || $date === '0000-00-00' || str_starts_with((string) $date, '0000-')) {
        return false;
    }

    try {
        return Carbon::parse($date)->year > 0;
    } catch (\Exception $exception) {
        return false;
    }
}

function getSettlementDefaultDate(?string $scheduleDate): string
{
    if (isValidScheduleDate($scheduleDate)) {
        return Carbon::parse($scheduleDate)->format('Y-m-d');
    }

    return now()->format('Y-m-d');
}

function formatDateForDatePicker(?string $date)
{
    if (! isValidScheduleDate($date)) {
        return null;
    }

    return Carbon::make($date)->format('m/d/Y');
}

function parseDatePickerValue(?string $date): ?string
{
    if (!$date) {
        return null;
    }

    foreach (['m-d-y', 'm/d/Y', 'm-d-Y', 'Y-m-d'] as $format) {
        if (isValidDateFormat($date, $format)) {
            return Carbon::createFromFormat($format, $date)->format('Y-m-d');
        }
    }

    try {
        return Carbon::make($date)->format('Y-m-d');
    } catch (\Exception $e) {
        return null;
    }
}
function stdToArray($items)
{
    return json_decode(json_encode($items)) ;

}
function getColorFromIndex(int $index)
{
    if ($index % 2 == 0) {
        return 'brand';
    }
    return 'warning';
}
// success
// danger
// warning
// brand
function generateMenuItem(string $title, bool $show, string $link, array $submenu = [])
{
    return [
        'title'=>$title,
        'show'=>$show ,
        'link'=>$link,
        'submenu'=>$submenu
    ];
}
function getIncomeStatementSubmenu($user, $company)
{
    $companyId = $company->id ;
    return [
        'forecast-dashboard'=>generateMenuItem(__('Forecast Dashboard'), $user->can('view forecast income statement dashboard'), route('dashboard.breakdown.incomeStatement', ['company'=>$companyId,'reportType'=>'forecast',]), []),
        'actual-dashboard'=>generateMenuItem('view Actual dashboard', $user->can('view actual income statement dashboard'), route('dashboard.breakdown.incomeStatement', ['company'=>$companyId,'reportType'=>'actual']), []),
        'adjusted-dashboard'=>generateMenuItem('view Adjusted dashboard', $user->can('view adjusted income statement dashboard'), route('dashboard.breakdown.incomeStatement', ['company'=>$companyId,'reportType'=>'adjusted']), []),
        'modified-dashboard'=>generateMenuItem('view Modified dashboard', $user->can('view modified income statement dashboard'), route('dashboard.breakdown.incomeStatement', ['company'=>$companyId,'reportType'=>'modified']), []),
        'comparing-dashboard'=>generateMenuItem('Comparing Dashboard', $user->can('view income statement comparing dashboard'), route('dashboard.intervalComparing.incomeStatement', ['company'=>$companyId,'subItemType'=>'comparing']), []),
    ];
}







function resolveCompanyFromRequest(): ?Company
{
    $segments = request()->segments();

    if (in_array('user', $segments, true)) {
        for ($i = count($segments) - 1; $i >= 0; $i--) {
            if (! is_numeric($segments[$i])) {
                continue;
            }

            $previous = $segments[$i - 1] ?? null;

            if (in_array($previous, ['edit', 'all', 'create'], true)) {
                return Company::find((int) $segments[$i]);
            }

            break;
        }
    }

    $companyId = request()->segment(2);
    if (! is_numeric($companyId)) {
        $companyId = request()->segment(3);
    }

    if (! is_numeric($companyId)) {
        return Company::first();
    }

    return Company::find((int) $companyId);
}

function currentCompany(): ?Company
{
    return app('currentCompany');
}

function isMoneyFlowDarkPage(): bool
{
    static $resolved = null;

    if ($resolved !== null) {
        return $resolved;
    }

    $routeName = request()->route()?->getName();
    $darkRouteNames = [
        'view.uploading',
        'view.loan.schedule.settlements',
        'edit.loan.schedule.settlements',
        'view.contract.loan.schedule.settlements',
        'edit.contract.loan.schedule.settlements',
        'salesGatheringImport',
        'salesGatheringTest.editCachedRow',
        'last.upload.failed',
    ];

    if ($routeName && in_array($routeName, $darkRouteNames, true)) {
        $resolved = true;

        return true;
    }

    $path = request()->path();
    if (preg_match('#(^|/)uploading/LoanSchedule(/|$)#', $path)
        || preg_match('#(^|/)uploading/ContractLoanSchedule(/|$)#', $path)
        || preg_match('#(^|/)loan-schedule-settlement/#', $path)
        || preg_match('#(^|/)edit-loan-schedule-settlement/#', $path)
        || preg_match('#(^|/)contract-loan-schedule-settlement/#', $path)
        || preg_match('#(^|/)edit-contract-loan-schedule-settlement/#', $path)
        || preg_match('#(^|/)salesGatheringImport/LoanSchedule(/|$)#', $path)
        || preg_match('#(^|/)salesGatheringImport/ContractLoanSchedule(/|$)#', $path)) {
        $resolved = true;

        return true;
    }

    $resolved = false;
    $sections = \Illuminate\Support\Facades\View::getSections();

    foreach (['content', 'css', 'sub-header', 'sub_header'] as $sectionName) {
        $html = (string) ($sections[$sectionName] ?? '');

        if ($html !== '' && str_contains($html, 'money-flow-dark')) {
            $resolved = true;

            return true;
        }
    }

    return false;
}

function getHeaderMenu($currentCompany = null)
{
    

    
    /**
     * @var Company|null $company
     */
    $company = currentCompany();

    $company = $company ?: $currentCompany;
    $user = auth()->user();
    if (!$company) {
        return [
            'home'=>generateMenuItem(__('Home'), $user->can('view home'), route('home'), [])
        ];
    }

    $companyId = $company->id ;
    
   
    
    $canViewSafeStatement = $user->can('view safe statement report');
    $canViewCashExpenseStatement = $user->can('view cash expense report');
    $canViewPartnersStatement = $user->can('view partners statement report');
    $canViewBankStatement = $user->can('view bank statement report') ;
    $canViewLgByBeneficiaryNameReport = $user->can('view lg by beneficiary name report') ;
    $canViewLgByBankNameReport = $user->can('view lg by bank name report') ;
    $canViewLgLcStatement = $user->can('view lc & lg statement report') ;
    $canViewCashFlow = $user->can('view cash flow report');
    $canViewContractCashFlow = $user->can('view contract cash flow report');
    $canViewWithdrawalsSettlementReport = $user->can('view withdrawals settlement report');
    $canViewNotificationSetting = $user->can('view notification settings');
    $canViewCashExpenseCategories = $user->can('view cash expense categories');
    $canViewCustomersSettings = $user->can('view customers');
    $canViewSubsidiaryCompaniesSettings = $user->can('view subsidiary companies');
    $canViewOtherPartnersSettings = $user->can('view other partners');
    $canViewShareholdersSettings = $user->can('view shareholders');
    $canViewDeductionsSettings = $user->can('view deductions');
    $canViewEmployeesSettings = $user->can('view employees');
    $canViewSuppliersSettings = $user->can('view suppliers');
    $canViewBusinessSectorSettings = $user->can('view business sectors');
    $canViewBusinessUnitSettings = $user->can('view business units');
    $canViewSalesChannelsSettings = $user->can('view sales channels');
    $canViewSalesPersonsSettings = $user->can('view sales persons');
    $canViewBranchesSettings = $user->can('view branches');
    $canViewGeneralSetting = $canViewCustomersSettings || $canViewSubsidiaryCompaniesSettings || $canViewOtherPartnersSettings || $canViewShareholdersSettings || $canViewDeductionsSettings || $canViewEmployeesSettings || $canViewSuppliersSettings || $canViewBusinessSectorSettings || $canViewBusinessUnitSettings || $canViewSalesChannelsSettings || $canViewSalesPersonsSettings ||$canViewBranchesSettings || $canViewCashExpenseCategories;
    
    $notificationsSubItems[] = [
        'title'=>__('General Settings'),
        'link'=>'#',
        'show'=>$canViewGeneralSetting ,
        'submenu'=> [
            [
                'title'=>__('Cash Expense'),
            'link'=>route('cash.expense.category.index', ['company'=>$companyId]),
            'show'=>$canViewCashExpenseCategories,
            ],
            [
                'title'=>__('Partners'),
                'link'=>route('partners.index', ['company'=>$companyId]),
                'show'=>true ,
                'submenu'=>[
                    [
                        'title'=>__('All Partners'),
                        'link'=>route('partners.index', ['company'=>$companyId]),
                        'show'=>true ,
                    ],
                    [
                'title'=>__('Customers'),
                'link'=>route('customers.index', ['company'=>$companyId]),
                'show'=>$canViewCustomersSettings
            ],
                    [
                'title'=>__('Suppliers'),
                'link'=>route('suppliers.index', ['company'=>$companyId]),
                'show'=>$canViewSuppliersSettings
            ],
            [
                'title'=>__('Employees'),
                'link'=>route('employees.index', ['company'=>$companyId]),
                'show'=>$canViewEmployeesSettings
            ],
            [
                'title'=>__('Shareholders'),
                'link'=>route('shareholders.index', ['company'=>$companyId]),
                'show'=>$canViewShareholdersSettings
            ],
            [
                'title'=>__('Other Partners'),
                'link'=>route('other.partners.index', ['company'=>$companyId]),
                'show'=>$canViewOtherPartnersSettings
            ],
            
            
                ]
                // 'show'=>$canViewCustomersSettings
            ],
            // [
            // 	'title'=>__('Suppliers'),
            // 	'link'=>route('suppliers.index',['company'=>$companyId]),
            // 	'show'=>$canViewSuppliersSettings
            // ],
            
            [
                'title'=>__('Subsidiary Companies'),
                'link'=>route('subsidiary.companies.index', ['company'=>$companyId]),
                'show'=>$canViewSubsidiaryCompaniesSettings
            ],
            
            
            [
                'title'=>__('Deductions'),
                'link'=>route('deductions.index', ['company'=>$companyId]),
                'show'=>$canViewDeductionsSettings
            ],
            
            [
                'title'=>__('Other Settings'),
                'link'=>'#',
                'show'=>true ,
                'submenu'=>[
                    [
                'title'=>__('Business Sectors'),
                'link'=>route('business.sectors.index', ['company'=>$companyId]),
                'show'=>$canViewBusinessSectorSettings
            ],
            [
                'title'=>__('Business Units'),
                'link'=>route('business.units.index', ['company'=>$companyId]),
                'show'=>$canViewBusinessUnitSettings
            ]
            ,[
                'title'=>__('Sales Channels'),
                'link'=>route('sales.channels.index', ['company'=>$companyId]),
                'show'=>$canViewSalesChannelsSettings
            ],
            [
                'title'=>__('Sales Persons'),
                'link'=>route('sales.persons.index', ['company'=>$companyId]),
                'show'=>$canViewSalesPersonsSettings
            ],
                ]
            ],
            
            
            
        ]
    ];
    $notificationsSubItems2 = \App\Notification::formatForMenuItem($company);
    $notificationsSubItems = array_merge($notificationsSubItems, $notificationsSubItems2);
    
    $notificationsSubItems[]	= [
        'title'=>__('Notification Settings'),
    'link'=>route('notifications-settings.index', ['company'=>$companyId]),
    'show'=>$canViewNotificationSetting,
    ];

    $canViewNotificationsSettingAndGeneralSetting = $canViewNotificationSetting || $canViewGeneralSetting;
    
    
    
    
    $notificationsSubItems[]	= [
        'title'=>__('Permissions'),
        'link'=>route('roles.permissions.edit', ['company'=>$companyId]),
        'show'=>$user->can('update permissions') && ! $user->isSuperAdmin(),
    ];
    
    $notificationsSubItems[]	= [
        'title'=>__('Users'),
        'link'=>route('user.index', ['company'=>$companyId]),
        'show'=>$user->can('view users') && ! $user->isSuperAdmin(),
    ];
    
    
    
    $canViewCashStatusDashboard = $user->can('view cash status dashboard');
    $canViewCashForecastDashboard = $user->can('view cash Forecast dashboard');
    $canViewLgAndLcDashboard = $user->can('view lg & lc dashboard');
    $canViewCashDashboard = $canViewCashStatusDashboard || $canViewCashForecastDashboard ||$canViewLgAndLcDashboard;
    
    
    $canUpdateCashAndChequesOpeningBalances  =$user->can('update cash & cheques opening balances');
    // $canUpdateLgOpeningBalances  =$user->can('update lg opening balances');
    // $canUpdateLcOpeningBalances  =$user->can('update lc opening balances');
    $canViewOpeningBalances =$canUpdateCashAndChequesOpeningBalances
    // || $canUpdateLgOpeningBalances || $canUpdateLcOpeningBalances
    ;
    $resortedNotificationsSubItems = [];
    
    $cashManagementSubItems = [

        'home'=>generateMenuItem(__('Home'), $user->can('view home') && hasMiddleware('isCashManagement'), route('home'), []),
        'notifications'=>[
            'title'=>__('Notifications & Settings'),
            'link'=>'#',
            'show'=>$canViewNotificationsSettingAndGeneralSetting,
            'submenu'=>$notificationsSubItems,
            'is-notification'=>true
        ],
        'cash-dashboard'=>[
            'title'=>__('Dashboard'),
            'show'=>$canViewCashDashboard ,
            'link'=>'#',
            'submenu'=>[
                [
                    'title'=>__('Cash Status'),
                    'link'=>route('view.customer.invoice.dashboard.cash', ['company'=>$companyId]),
                    'show'=>$canViewCashStatusDashboard,
                    'submenu'=>[]
                ],
                [
                    'title'=>__('Cash Forecast'),
                    'link'=>route('view.customer.invoice.dashboard.forecast', ['company'=>$companyId]),
                    'show'=>$canViewCashForecastDashboard,
                    'submenu'=>[]
                ],
                [
                    'title'=>__('LG & LC Dashboard'),
                    'link'=>route('view.lglc.dashboard', ['company'=>$companyId]),
                    'show'=>$canViewLgAndLcDashboard,
                    'submenu'=>[]
                ],
            ]

        ]
        ,
        
        'reports'=>[
            'title'=>__('Reports'),
            'show'=>$canViewCashFlow || $canViewContractCashFlow ||  $canViewSafeStatement || $canViewCashExpenseStatement || $canViewPartnersStatement || $canViewBankStatement|| $canViewLgByBeneficiaryNameReport || $canViewLgByBankNameReport || $canViewLgLcStatement || $canViewWithdrawalsSettlementReport ,
            'link'=>'#',
            'submenu'=>
            [
        
                [
                    'title'=>__('Safe Statement'),
                    'link'=>route('view.safe.statement', ['company'=>$company->id]) ,
                    'show'=>$canViewSafeStatement,
                    'submenu'=>[]
                ],
                
                [
                    'title'=>__('Bank Statement'),
                    'link'=>route('view.bank.statement', ['company'=>$company->id]),
                    'show'=>$canViewBankStatement,
                    'submenu'=>[]
                ],
                [
                    'title'=>__('LG By Beneficiary Name Report'),
                    'link'=>route('view.lg.by.beneficiary.name.report', ['company'=>$company->id]),
                    'show'=>$canViewLgByBeneficiaryNameReport,
                    'submenu'=>[]
                ],[
                    'title'=>__('LG By Bank Name Report'),
                    'link'=>route('view.lg.by.bank.name.report', ['company'=>$company->id]),
                    'show'=>$canViewLgByBankNameReport,
                    'submenu'=>[]
                ]
                ,[
                    'title'=>__('LG & LC Statement'),
                    'link'=>route('view.lg.lc.bank.statement', ['company'=>$company->id]),
                    'show'=>$canViewBankStatement,
                    'submenu'=>[]
                ],
                [
                    'title'=>__('Cash Expense Statement'),
                    'link'=>route('view.cash.expense.statement', ['company'=>$company->id]) ,
                    'show'=>$canViewCashExpenseStatement,
                    'submenu'=>[]
                ],
                [
                    'title'=>__('Partners Statement'),
                    'link'=>route('view.partners.statement', ['company'=>$company->id]) ,
                    'show'=>$canViewPartnersStatement,
                    'submenu'=>[]
                ],
                [
                    'title'=>__('Company Cash Flow Report'),
                    'link'=>route('view.cashflow.report', ['company'=>$companyId]),
                    'show'=>$canViewCashFlow ,
                    'submenu'=>[]
                ],
                [
                    'title'=>__('Contract Cash Flow Report'),
                    'link'=>route('view.contract.cashflow.report', ['company'=>$companyId]),
                    'show'=>$canViewContractCashFlow ,
                    'submenu'=>[]
                ],
                [
                    'title'=>__('Consolidated Cash Flow'),
                    'link'=>route('reports.consolidated-cash-flow.index', ['company'=>$companyId]),
                    'show'=>$canViewCashFlow ,
                    'submenu'=>[]
                ],
                [
                    'title'=>__('Withdrawals Settlement Report'),
                    'link'=>route('view.withdrawals.settlement.report', ['company'=>$companyId]),
                    'show'=>$canViewWithdrawalsSettlementReport ,
                    'submenu'=>[]
                ]
                
                    ],
        ],
        'bank-and-cash-account'=>[
            'title'=>__('Cash & Bank Accounts'),
            'show'=>true ,
            'submenu'=>[
                [
            'title'=>__('Financial Institutions'),
            'link'=>route('view.financial.institutions', ['company'=>$companyId]),
            'show'=>$user->can('view financial institutions')
                ],
                [
                'title'=>__('Safe'),
                'link'=>route('branches.index', ['company'=>$companyId]),
                'show'=>$canViewBranchesSettings
                ],
                [
                    'title'=>__('Opening Balances'),
                    'link'=>'#',
                    'show'=>$canViewOpeningBalances ,
                    'submenu'=>[
                        [
                            'title'=>__('Cash & Cheques Opening Balance'),
                            'link'=>route('opening-balance.index', ['company'=>$companyId]),
                            'show'=>$canUpdateCashAndChequesOpeningBalances,
                        ],
                        [
                            'title'=>__('Customers Opening Balance'),
                            'link'=>route('customers-opening-balance.index', ['company'=>$companyId]),
                            'show'=>$canUpdateCashAndChequesOpeningBalances,
                        ],
                        [
                            'title'=>__('Suppliers Opening Balance'),
                            'link'=>route('suppliers-opening-balance.index', ['company'=>$companyId]),
                            'show'=>$canUpdateCashAndChequesOpeningBalances,
                        ],
        
                    ],
                    
                    
                        ],
                        [
                'title'=>__('Other Odoo Integration Settings'),
                'link'=>route('odoo-settings.index', ['company'=>$companyId]),
                'show'=>$company->hasOdooIntegrationCredentials(),
            ],
                ],
                
        ],
        // 'financial-institution'=>[
        // 	'title'=>__('Financial Institutions'),
        // 	'link'=>route('view.financial.institutions',['company'=>$companyId]),
        // 	'show'=>$user->can('view financial institutions')
        // ],
        'customer-sections'=>[
            'title'=>__('Customer Sections'),
            'link'=>'#',
            'show'=>true,
            'submenu'=>[

                [
                    'title'=>__('Customer Balances'),
                    'link'=>route('view.balances', ['company'=>$companyId,'modelType'=>'CustomerInvoice']),
                    'show'=>$user->can('view customer balances'),
                    'submenu'=>[]
                ],
                [
            'title'=>__('Customer Aging'),
            'link'=>route('view.aging.analysis', ['company'=>$companyId,'modelType'=>'CustomerInvoice']),
            'show'=>$user->can('view customer aging'),
            'submenu'=>[]
            ],
            
            [
                'title'=>__('Collections Effectiveness Index'),
                'link'=>route('view.collections.effectiveness.index', ['company'=>$company->id]) ,
                'show'=>$user->can('view collections effectiveness index'),
                'submenu'=>[]
            ],
            
            
            
            [
                'title'=>__('Customer Contracts'),
            'link'=>route('contracts.index', ['company'=>$companyId,'type'=>'Customer']),
            'show'=>$user->can('view customers contracts'),

            ],
            [
                'title'=>__('Upload New Customer Invoice Data'),
                'link'=>route('view.uploading', ['company'=>$company->id , 'model'=>'CustomerInvoice']),
                'show'=>$user->can(uploadCustomerInvoiceData),
                'submenu'=>[]
            ]







            ]
        ],
        'supplier-sections'=>[
            'title'=>__('Supplier Sections'),
            'link'=>'#',
            'show'=>true,
            'submenu'=>[

                [
                    'title'=>__('Supplier Balances'),
                    'link'=>route('view.balances', ['company'=>$companyId,'modelType'=>'SupplierInvoice']),
                    'show'=>$user->can('view supplier balances'),
                    'submenu'=>[]
                ],
                [
            'title'=>__('Supplier Aging'),
            'link'=>route('view.aging.analysis', ['company'=>$companyId,'modelType'=>'SupplierInvoice']),
            'show'=>$user->can('view supplier aging'),
            'submenu'=>[]
            ],
            [
                'title'=>__('Supplier Contracts'),
            'link'=>route('contracts.index', ['company'=>$companyId,'type'=>'Supplier']),
            'show'=>$user->can('view suppliers contracts'),

            ],
            [
                'title'=>__('Upload New Supplier Invoice Data'),
                'link'=>route('view.uploading', ['company'=>$company->id , 'model'=>'SupplierInvoice']),
                'show'=>$user->can(uploadSupplierInvoiceData),
                'submenu'=>[]
            ]







            ]
        ],
        
        'money-transactions'=>[
            'title'=>__('Money Transactions'),
            'link'=>'#',
            'show'=>true ,
            'submenu'=>[
                [
                    'title'=>__('Money Received'),
                    'link'=>route('view.money.receive', ['company'=>$companyId]),
                    'show'=>$user->can('view money received'),
                    'submenu'=>[]
                ],
                [
                    'title'=>__('Money Payment'),
                    'link'=>route('view.money.payment', ['company'=>$companyId]),
                    'show'=>$user->can('view supplier payment'),
                    'submenu'=>[]
                ],
                [
                    'title'=>__('Factoring'),
                    'link'=>'#',
                    'show'=>$user->can('view supplier payment'),
                    'submenu'=>[
                        [
                            'title'=>__('With Recourse'),
                            'link'=>'#',
                            'show'=>$user->can('view supplier payment'),
                        ],
                        [
                            'title'=>__('Without Recourse'),
                            'link'=>route('factoring.without-recourse.index', ['company'=>$companyId]),
                            'show'=>$user->can('view supplier payment'),
                        ],
                    ],
                ],
                [
                    'title'=>__('Cash Expenses'),
                    'link'=>route('view.cash.expense', ['company'=>$companyId]),
                    'show'=>$user->can('view cash expenses'),
                    'submenu'=>[]
                ],
                // [
                // 	'title'=>__('Approved Expenses'),
                // 	'link'=>route('odoo-expenses.index', ['company'=>$companyId]),
                // 	'show'=>$company->hasOdooIntegrationCredentials(),
                // 	'submenu'=>[]
                // ],
                
                [
                    'title'=>__('LC Settlement Internal Transfer'),
                    'link'=>route('lc-settlement-internal-money-transfers.index', ['company'=>$companyId]),
                    'show'=>$user->can('view lc settlement internal transfer'),
                    'submenu'=>[]
                        ],
                [
            'title'=>__('Internal Money Transfer'),
            'link'=>route('internal-money-transfers.index', ['company'=>$companyId]),
            'show'=>$user->can('view internal money transfer'),
            'submenu'=>[]
                ],
                [
                    'title'=>__('Sell Or Buy Currency'),
                    'link'=>route('buy-or-sell-currencies.index', ['company'=>$company->id ]),
                    'show'=>$user->can('view buy or sell currency'),
                    'submenu'=>[]
                ],
                [
                    'title'=>__('Foreign Exchange Rate'),
                    'link'=>route('view.foreign.exchange.rate', ['company'=>$company->id]),
                    'show'=>$user->can('view foreign exchange rate'),
                    'submenu'=>[]
                ],
                
                [
                    'title'=>__('Odoo Integration'),
                    'link'=>'#',
                    'show'=>$company->hasOdooIntegrationCredentials(),
                    'submenu'=>[
                        [
                            'title'=>__('Read Partners'),
                        'link'=>'#',
                        'show'=>true,
                        'data-show-notification-modal'=>'read-partners-modal'
                    ],
                        [
                            'title'=>__('Read Invoices'),
                        'link'=>'#',
                        'show'=>true,
                        'data-show-notification-modal'=>'read-invoices-modal'
                    ],
                            [
                            'title'=>__('Read Contracts'),
                        'link'=>'#',
                        'show'=>true,
                        'data-show-notification-modal'=>'read-contracts-modal'
                    ],
                        
                    // [
                    // 	'title'=>__('Send Collections Or Payments'),
                    // 	'link'=>'#',
                    // 	'show'=>true,
                    // 	'data-show-notification-modal'=>'send-invoices-modal',
                    // ],
                    // [
                    // 	'title'=>__('Read Approved Expenses'),
                    // 	'link'=>'#',
                    // 	'show'=>true,
                    // 	'data-show-notification-modal'=>'read-expenses-modal',
                    // ],
                    ],
                    
                ],
                
                        
                        
                        
                        
                    
                        
                        
                        

            ]
        ]
        ,
        'view letter of guarantee issuance'=>[
            'title'=>__('LG & LC Issuance'),
            'show'=>true ,
            'submenu'=>[
                [
            'title'=>__('Letter Of Guarantee (LG) Issuance'),
            'link'=>route('view.letter.of.guarantee.issuance', ['company'=>$companyId]),
            'show'=>$user->can('view letter of guarantee issuance'),
            'submenu'=>[]
            ],
            [
            'title'=>__('Letter Of Credit (LC) Issuance'),
            'link'=>route('view.letter.of.credit.issuance', ['company'=>$companyId]),
            'show'=>$user->can('view letter of credit issuance'),
            'submenu'=>[]
            ]
            ]
            
            
        ],
        
        ];
    $isCustomerOrSupplierUploading = in_array('CustomerInvoice', Request()->segments()) || in_array('SupplierInvoice', Request()->segments());
    if ($company->hasCashVero() && (hasMiddleware('isCashManagement') || $isCustomerOrSupplierUploading || in_array('LoanSchedule', Request()->segments()) || in_array('ContractLoanSchedule', Request()->segments()))) {
        return $cashManagementSubItems ;
    }
        
    // $canViewVeroAnalysisDashboard = $user->can('view sales dashboard') || $user->can('view breakdown dashboard') || ($user->can('view customer dashboard'))
    // || ($user->can('view sales person dashboard')) || $user->can('view interval comparing dashboard') || $user->can('view expense analysis dashboard')
    // || $user->can('view income statement dashboard');
        
        
    $canViewUploadSalesData = $user->can('upload sales gathering data') ;
    $canViewUploadExportData = $user->can(uploadExportAnalysisData) ;
    $canViewUploadCustomerInvoiceData = $user->can(uploadCustomerInvoiceData) ;
    $canViewUploadSupplierInvoiceData = $user->can(uploadSupplierInvoiceData) ;

    $canViewDataGathering = $canViewUploadSalesData || $canViewUploadExportData || $canViewUploadCustomerInvoiceData || $canViewUploadSupplierInvoiceData ;
        
    $salesAnalysisSubItems = [] ;
        
    // $canViewSalesAnalysisReport = count($salesAnalysisSubItems) ;
    // $canExportAnalysisReport = $user->can(viewExportAnalysisData) ;
    // $canExpenseAnalysisReport = $user->can(viewExpenseAnalysisData) ;
        
        

    // $user->can('view sales forecast value base');
    // $salesForecastQuantityBaseSubItems= [];
    // $canViewSalesForecastQuantityBase=count($salesForecastQuantityBaseSubItems);
  
        
        
        
    return [
        'home'=>generateMenuItem(__('Home'), $user->can('view home'), route('home'), []),
       
                'data-gathering'=>[
                    'title'=>__('Data Gathering'),
                    'show'=>$canViewDataGathering,
                    'link'=>'#',
                    'submenu'=>[
                        
                        'upload new customer invoice data'=>[
                            'title'=>__('Upload New Customer Invoice Data'),
                            'link'=>route('view.uploading', ['company'=>$company->id , 'model'=>'CustomerInvoice']),
                            'show'=>$canViewUploadCustomerInvoiceData,
                            'submenu'=>[]
                        ],
                        'upload new supplier invoice data'=>[
                            'title'=>__('Upload New Supplier Invoice Data'),
                            'link'=>route('view.uploading', ['company'=>$company->id , 'model'=>'SupplierInvoice']),
                            'show'=>$canViewUploadSupplierInvoiceData,
                            'submenu'=>[]
                        ],
                        
                    ]
                        ],
                        
                              
                                        
                                        'cash-management'=>[
                                            'title'=>__('Cash Management'),
                                            'link'=>'#',
                                            'show'=>$company->hasCashVero()   ,
                                            'submenu'=>$cashManagementSubItems
                                                ],




    ];
}
function getLgTypes():array
{
    return LgTypes::getAll();
}

function getLcTypes():array
{
    return LcTypes::getAll();
}
function getCommissionInterval():array
{
    return [
        'quarterly'=>__('Quarterly'),
        'annually'=>__('Annually')
    ];
}

function camelizeWithSpace($input, $separator = '-')
{
    return HStr::camelizeWithSpace($input, $separator);
}
function unformat_number($money)
{
    $cleanString = preg_replace('/([^0-9\.,])/i', '', $money);
    $onlyNumbersString = preg_replace('/([^0-9])/i', '', $money);

    $separatorsCountToBeErased = strlen($cleanString) - strlen($onlyNumbersString) - 1;

    $stringWithCommaOrDot = preg_replace('/([,\.])/', '', $cleanString, $separatorsCountToBeErased);
    $removedThousandSeparator = preg_replace('/(\.|,)(?=[0-9]{3,}$)/', '', $stringWithCommaOrDot);

    return (float) str_replace(',', '.', $removedThousandSeparator);
}


// function getRevenueBusinessLineOptions(): array
// {

//     // used by seeder

//     return [
//         'training_service' => __('Training Service'),
//         'consulting_service' => __('Consulting Service'),
//         'internship_service' => __('Internship Service'),
//         // 'internship_service' => __('Internship Service'),
//         'externship_service' => __('Externship Service'),
//         'observership_service' => __('Observership Service'),
//         'observership_service' => __('Observership Service'),
//         'scholarship_service' => __('Scholarship Service'),
//         'fellowship_service' => __('Fellowship Service'),

//     ];
// }
// function getServiceCategories(): array
// {

//     return [
//         'financial_courses' => __('Financial Courses'),
//         'marketing_courses' => __('Marketing Courses'),
//         'hr_courses' => __('Hr Courses'),
//         'financial_consulting' => __('Financial Consulting'),
//         'marketing_consulting' => __('Marketing Consulting'),
//         'hr_consulting' => __('Hr Consulting'),
//     ];
// }
// function getServiceName(): array
// {

//     return [
//         'accounting' => __('Accounting'),
//         'costing' => __('Costing'),
//         'budget' => __('Budget'),
//         'feasibility_study' => __('Feasibility Study'),
//         'valuation' => __('Valuation'),
//         'performance_analysis' => __('Performance Analysis'),
//     ];
// }
// function getServicesNature(): array
// {
//     return [
//         'online' => __('Online'),
//         'physical' => __('Physical')
//     ];
// }
// function getCountries(): array
// {
//     $countries = Country::whereNotIn('name_en', ['United States', 'Kenya'])
//         ->get()->pluck('name_' . App()->getLocale(), 'id')->toArray();
//     return $countries;
// }
// function getPositions(): array
// {
//     return [
//         'executive' => __('Executive'),
//         'senior' => __('Senior'),
//         'officer' => __('Officer')
//     ];
// }
function getCurrency()
{
    return getCurrencies();

}

/**
 * SQL expression for partner display name (supports name_en / name_ar when `name` column is absent).
 */
function partner_display_name_sql(string $tableAlias = 'partners', string $as = 'partner_name'): string
{
    if (Schema::hasColumn('partners', 'name')) {
        return "{$tableAlias}.name as {$as}";
    }

    $t = $tableAlias;
    $parts = [];
    if (Schema::hasColumn('partners', 'name_en')) {
        $parts[] = "NULLIF({$t}.name_en, '')";
    }
    if (Schema::hasColumn('partners', 'name_ar')) {
        $parts[] = "NULLIF({$t}.name_ar, '')";
    }
    if ($parts !== []) {
        return 'COALESCE('.implode(', ', $parts).", '-') as {$as}";
    }

    return "'' as {$as}";
}

function getAddNewFieldRule($fieldName)
{
    return Rule::requiredIf(Request()->get($fieldName) == 'Add New');
}

// route('view.uploading',['company'=>$company->id , 'model'=>$elementModelName])
function getTestBuildingArray()
{
    return [
        [
            'title'=>__('New Cataract'),
            'value'=>__('New Cataract'),
            'data-abb'=>'NECAT',
            'data-code'=>'01'
        ],
        [
            'title'=>__('Old Cataract'),
            'value'=>__('Old Cataract'),
            'data-abb'=>'ODCAT',
            'data-code'=>'02'
        ]
    ];
}
function getTestFfeArray()
{
    return [
        [
            'title'=>__('Furniture'),
            'value'=>'furniture',
            'data-abb'=>'FURN',
            'data-code'=>'01'
        ],
        [
            'title'=>__('Equipment'),
            'value'=>__('Equipment'),
            'data-abb'=>'EQUIP',
            'data-code'=>'02'
        ]
    ];
}

function getTestFloors()
{
    return [
        [
            'title'=>'Floor1',
            'value'=>'floor1',
            'data-abb'=>'FO1',
            'data-code'=>'01'
        ],
        [
            'title'=>'Floor2',
            'value'=>'floor2',
            'data-abb'=>'FO2',
            'data-code'=>'02'
        ],

    ];
}
function getTestCategory()
{
    return [
        [
            'title'=>'Beds',
            'value'=>'beds',
            'data-abb'=>'BDs',
            'data-code'=>'01'
        ],
        [
            'title'=>'Chairs',
            'value'=>'chairs',
            'data-abb'=>'CHs',
            'data-code'=>'02'
        ],

    ];
}
function getTestLabelForm()
{
    return [
        [
            'value'=>'Building',
        'title'=>'Building'
        ],
        [
            'value'=>'FF&E',
        'title'=>'FF&E'
        ]
    ];
}
function getTestBuildNames()
{
    return [
        [
            'value'=>'New Cataract',
        'title'=>'New Cataract'
        ],
        [
            'value'=>'Old Cataract',
        'title'=>'Old Cataract'
        ]
    ];
}
function filterByColumnName($filterByColumnName)
{
    $items = [];
    foreach ($filterByColumnName as $columnValue) {
        $attributes = $columnValue->getAttributes();

        foreach ($attributes as $colName => $colVal) {
            $items[$colName][$colVal] = $colVal ;
        }

    }
    $formatted=[];
    foreach ($items as $colName => $arr) {
        foreach ($arr as $col => $val) {
            $formatted[$colName][] =[
                'title'=>$col,
                'value'=>$val
            ];
        }
    }
    return $formatted ;
}
function formatColumnName($name)
{
    return trim(strtolower(str_replace(' ', '_', lcfirst($name))));
}
function FormatKeyAsColumnName($items)
{
    $result = [];
    foreach ($items as $key => $val) {
        $result[formatColumnName($key)] =$val;
    }
    return $result ;
}
function getValuesStartedAfterIndex(array $items, int $index)
{
    $result = ['QR Code'];
    foreach ($items as $i => $val) {
        if ($i > $index) {
            $result[]=$val ;
        }
    }
    return $result;
}
function qrcodeSpacing($code)
{
    return str_replace(['//','/'], ['// ','/ '], $code);
}

function normalizeImportCellValue(mixed $value): string
{
    if ($value === null) {
        return '';
    }

    if (is_bool($value)) {
        return $value ? '1' : '0';
    }

    if (is_int($value)) {
        return (string) $value;
    }

    if (is_float($value)) {
        if (is_finite($value) && floor($value) == $value) {
            return sprintf('%.0f', $value);
        }

        return rtrim(rtrim(sprintf('%.10F', $value), '0'), '.');
    }

    $stringValue = trim((string) $value);

    if ($stringValue === '') {
        return '';
    }

    if (preg_match('/^[\d.]+e[+-]?\d+$/i', $stringValue)) {
        return sprintf('%.0f', (float) $stringValue);
    }

    if (ctype_digit($stringValue)) {
        return $stringValue;
    }

    return $stringValue;
}

function getImportIdentifierFieldNames(): array
{
    return ['account_number', 'cheque_number'];
}

function isImportIdentifierHeading(string $heading): bool
{
    $normalizedHeading = mb_strtolower(trim($heading));

    foreach (getImportIdentifierFieldNames() as $fieldName) {
        $candidates = [
            $fieldName,
            str_replace('_', ' ', $fieldName),
            ucwords(str_replace('_', ' ', $fieldName)),
            __($fieldName),
            __(ucwords(str_replace('_', ' ', $fieldName))),
        ];

        foreach ($candidates as $candidate) {
            if ($normalizedHeading === mb_strtolower(trim((string) $candidate))) {
                return true;
            }
        }
    }

    return in_array($normalizedHeading, [
        mb_strtolower('Account Number'),
        mb_strtolower('Cheque Number'),
        mb_strtolower('رقم الحساب'),
        mb_strtolower('رقم الشيك'),
    ], true);
}

function sanitizeImportRowValues(array $row): array
{
    $sanitized = [];

    foreach ($row as $field => $cellValue) {
        if (in_array($field, getImportIdentifierFieldNames(), true)) {
            $sanitized[$field] = normalizeImportCellValue($cellValue);
            continue;
        }

        if (is_string($cellValue)) {
            $sanitized[$field] = str_replace(['"', "'", '\\'], ' ', $cellValue);
            continue;
        }

        $sanitized[$field] = $cellValue;
    }

    return $sanitized;
}

function isRawImportRowEmpty(iterable $row): bool
{
    foreach ($row as $value) {
        if ($value !== null && $value !== '' && normalizeImportCellValue($value) !== '') {
            return false;
        }
    }

    return true;
}

function isMappedImportRowEmpty(array $row): bool
{
    $ignoredKeys = ['id', 'company_id', 'created_by'];

    foreach ($row as $fieldName => $value) {
        if (in_array($fieldName, $ignoredKeys, true)) {
            continue;
        }

        if ($value !== null && $value !== '' && normalizeImportCellValue($value) !== '') {
            return false;
        }
    }

    return true;
}

function normalizeDraweeBankImportName(string $name): string
{
    return trim(preg_replace('/\s+/u', ' ', $name));
}

function isDraweeBankImportHeading(string $heading): bool
{
    $normalizedHeading = mb_strtolower(trim($heading));

    $candidates = [
        'drawee_bank',
        'Drawee Bank',
        __('Drawee Bank'),
        'البنك المسحوب عليه',
    ];

    foreach ($candidates as $candidate) {
        if ($normalizedHeading === mb_strtolower(trim((string) $candidate))) {
            return true;
        }
    }

    return false;
}

function getCompanyDraweeBankNames(int $companyId): array
{
    return FinancialInstitution::query()
        ->where('company_id', $companyId)
        ->where('type', FinancialInstitution::BANK)
        ->with('bank:id,view_name')
        ->get()
        ->map(fn (FinancialInstitution $financialInstitution) => (string) ($financialInstitution->bank?->view_name ?? ''))
        ->filter(fn (string $bankName) => $bankName !== '')
        ->unique()
        ->sort(SORT_NATURAL | SORT_FLAG_CASE)
        ->values()
        ->all();
}

function resolveDraweeBankFinancialInstitutionId(int $companyId, string $draweeBankName): ?int
{
    $draweeBankName = normalizeDraweeBankImportName($draweeBankName);

    if ($draweeBankName === '') {
        return null;
    }

    $draweeBankId = FinancialInstitution::query()
        ->where('company_id', $companyId)
        ->where('type', FinancialInstitution::BANK)
        ->whereHas('bank', function ($query) use ($draweeBankName) {
            $query->where('view_name', $draweeBankName);
        })
        ->value('id');

    if ($draweeBankId) {
        return (int) $draweeBankId;
    }

    $bankId = Bank::where('view_name', $draweeBankName)->value('id');

    if (! $bankId) {
        $normalizedInput = mb_strtolower($draweeBankName);
        $bankId = Bank::query()
            ->get(['id', 'view_name'])
            ->first(function (Bank $bank) use ($normalizedInput) {
                return mb_strtolower(normalizeDraweeBankImportName((string) $bank->view_name)) === $normalizedInput;
            })
            ?->id;
    }

    if (! $bankId) {
        return null;
    }

    $draweeBankId = FinancialInstitution::query()
        ->where('company_id', $companyId)
        ->where('type', FinancialInstitution::BANK)
        ->where('bank_id', $bankId)
        ->value('id');

    return $draweeBankId ? (int) $draweeBankId : null;
}

function accountNumberExistsForFinancialInstitution(int $companyId, int $financialInstitutionId, string $accountNumber): bool
{
    $accountNumber = normalizeImportCellValue($accountNumber);

    if ($accountNumber === '') {
        return false;
    }

    $financialInstitution = FinancialInstitution::query()
        ->with([
            'accounts',
            'cleanOverdrafts',
            'fullySecuredOverdrafts',
            'overdraftAgainstCommercialPapers',
            'overdraftAgainstAssignmentOfContracts',
            'certificatesOfDeposits',
            'timeOfDeposits',
        ])
        ->where('company_id', $companyId)
        ->find($financialInstitutionId);

    if (! $financialInstitution) {
        return false;
    }

    return in_array($accountNumber, $financialInstitution->getAllAccountNumbers(), true);
}

function getContractLoanScheduleBankAccountValidationErrors(int $companyId, array $row): array
{
    $errors = [];
    $draweeBankName = trim((string) ($row['drawee_bank'] ?? ''));
    $accountNumber = normalizeImportCellValue($row['account_number'] ?? '');

    if ($accountNumber === '' && $draweeBankName === '') {
        return $errors;
    }

    $financialInstitutionId = $draweeBankName !== ''
        ? resolveDraweeBankFinancialInstitutionId($companyId, $draweeBankName)
        : null;

    if ($draweeBankName !== '' && ! $financialInstitutionId) {
        $errors['drawee_bank'] = __('Drawee Bank was not found for this company');
    }

    if ($accountNumber !== '') {
        if (! $financialInstitutionId) {
            $errors['account_number'] = __('Drawee Bank is required to validate Account Number');
        } elseif (! accountNumberExistsForFinancialInstitution($companyId, $financialInstitutionId, $accountNumber)) {
            $errors['account_number'] = __('Account Number does not exist for the selected Drawee Bank');
        }
    }

    return $errors;
}

function resolveLoanScheduleStatus(?float $remaining, ?float $schedulePayment, ?string $date): ?string
{
    if (! $date) {
        return null;
    }

    $remaining = (float) ($remaining ?? 0);
    $schedulePayment = (float) ($schedulePayment ?? 0);

    try {
        $scheduleDate = \Carbon\Carbon::parse($date)->startOfDay();
    } catch (\Exception $exception) {
        return null;
    }

    $today = now()->startOfDay();

    if ($remaining == 0) {
        return 'paid';
    }

    if ($remaining > 0 && $remaining < $schedulePayment && $scheduleDate->lt($today)) {
        return 'partially_paid_and_past_due';
    }

    if ($scheduleDate->gt($today)) {
        return 'not_due_yet';
    }

    if ($scheduleDate->eq($today)) {
        return 'due_to_day';
    }

    if ($remaining == $schedulePayment && $scheduleDate->lt($today)) {
        return 'past_due';
    }

    return null;
}

function getDefaultImage()
{
    return asset('custom/images/default-img.png');
}
function array_to_upper(array $items)
{
    $result = [];
    foreach ($items as $item) {
        $result[] = snakeToCamel($item);
    }
    return $result ;
}
function findByKey(array $items, $key, $searchId)
{
    foreach ($items as $item) {
        if (isset($item[$key]) && $item[$key] == $searchId) {
            return $item;
        }
    }
    return [];
}
function touppercase($currentName)
{
    return Str::upper($currentName);
}
function toupperfirst($currentName)
{
    return ucfirst($currentName);
}
function capitalize($currentName)
{
    return toupperfirst($currentName);
}

function dashesToCamelCase($string)
{
    $string = str_replace(['-', '_'], ' ', $string);
    return lcfirst(str_replace(' ', '', ucwords($string)));

}
function isAll($percentageOf)
{
    if (is_null($percentageOf)) {
        return false ;
    }
    $allItems  = is_array($percentageOf) ? $percentageOf : json_decode($percentageOf) ;
    return in_array('all', $allItems);

}


function hasMiddleware(string $middlewareName)
{
    if (is_null(Route::current())) {
        return false;
    }
    return in_array($middlewareName, array_values(Route::current()->gatherMiddleware()));
}
function getModelNameWithoutNamespace($object)
{
    return HHelpers::getClassNameWithoutNameSpace($object);
}
function formatWeeksDatesFromStartDate(string $agingDate, string $format = 'd-m-Y')
{
    return [
        'past_due' => [
            '1-7' => [
                'start_date' => $startDate = Carbon::make($agingDate)->subDay()->format($format),
                'end_date' => $endDate = Carbon::make($startDate)->subDays(6)->format($format)
            ],
            '8-15' => [
                'start_date' => $startDate = Carbon::make($endDate)->subDay()->format($format),
                'end_date' => $endDate = Carbon::make($startDate)->subDays(7)->format($format)
            ],
            '16-30' => [
                'start_date' => $startDate = Carbon::make($endDate)->subDay()->format($format),
                'end_date' => $endDate = Carbon::make($startDate)->subDays(14)->format($format)
            ],
            '31-45' => [
                'start_date' => $startDate = Carbon::make($endDate)->subDay()->format($format),
                'end_date' => $endDate = Carbon::make($startDate)->subDays(14)->format($format)
            ],
            '46-60' => [
                'start_date' => $startDate = Carbon::make($endDate)->subDay()->format($format),
                'end_date' => $endDate = Carbon::make($startDate)->subDays(14)->format($format)
            ],
            '61-90' => [
                'start_date' => $startDate = Carbon::make($endDate)->subDay()->format($format),
                'end_date' => $endDate = Carbon::make($startDate)->subDays(29)->format($format)
            ],
            '91-120' => [
                'start_date' => $startDate = Carbon::make($endDate)->subDay()->format($format),
                'end_date' => $endDate = Carbon::make($startDate)->subDays(29)->format($format)
            ],
            '121-150' => [
                'start_date' => $startDate = Carbon::make($endDate)->subDay()->format($format),
                'end_date' => $endDate = Carbon::make($startDate)->subDays(29)->format($format)
            ],
        ],

        'coming_due' => [
            '1-7' => [
                'start_date' => $startDate = Carbon::make($agingDate)->addDay()->format($format),
                'end_date' => $endDate = Carbon::make($startDate)->addDays(6)->format($format)
            ],
            '8-15' => [
                'start_date' => $startDate = Carbon::make($endDate)->addDay()->format($format),
                'end_date' => $endDate = Carbon::make($startDate)->addDays(7)->format($format)
            ],
            '16-30' => [
                'start_date' => $startDate = Carbon::make($endDate)->addDay()->format($format),
                'end_date' => $endDate = Carbon::make($startDate)->addDays(14)->format($format)
            ],
            '31-45' => [
                'start_date' => $startDate = Carbon::make($endDate)->addDay()->format($format),
                'end_date' => $endDate = Carbon::make($startDate)->addDays(14)->format($format)
            ],
            '46-60' => [
                'start_date' => $startDate = Carbon::make($endDate)->addDay()->format($format),
                'end_date' => $endDate = Carbon::make($startDate)->addDays(14)->format($format)
            ],
            '61-90' => [
                'start_date' => $startDate = Carbon::make($endDate)->addDay()->format($format),
                'end_date' => $endDate = Carbon::make($startDate)->addDays(29)->format($format)
            ],
            '91-120' => [
                'start_date' => $startDate = Carbon::make($endDate)->addDay()->format($format),
                'end_date' => $endDate = Carbon::make($startDate)->addDays(29)->format($format)
            ],
            '121-150' => [
                'start_date' => $startDate = Carbon::make($endDate)->addDay()->format($format),
                'end_date' => $endDate = Carbon::make($startDate)->addDays(29)->format($format)
            ],
        ]

    ];
}



if (!function_exists('str_to_upper')) {
    function str_to_upper($str)
    {
        return ucwords(str_replace(['_', '-'], ' ', $str));
    }
}
if (!function_exists('getFixedLoanTypes')) {
    function getFixedLoanTypes()
    {
        return [
            'normal', 'step-up', 'step-down', 'grace_period_with_capitalization', 'grace_period_without_capitalization', 'grace_step-up_with_capitalization', 'grace_step-up_without_capitalization',
            'grace_step-down_with_capitalization', 'grace_step-down_without_capitalization',
        ];
    }
}
function getDifferenceBetweenTwoDatesInDays(Carbon $firstDate, Carbon $secondDate)
{
    return $secondDate->diffInDays($firstDate);
}
function getBankStatementReviewed($stdClass)
{
    $tableName = null ;
        
    if ($id = $stdClass->money_received_id) {
        $tableName = 'money_received';
    } elseif ($id = $stdClass->money_payment_id) {
        $tableName = 'money_payments';
    } elseif ($id = $stdClass->cash_expense_id) {
        $tableName = 'cash_expenses';
    } elseif ($id = $stdClass->buy_or_sell_currency_id) {
        $tableName = 'buy_or_sell_currencies';
    } elseif ($id = $stdClass->internal_money_transfer_id) {
        $tableName = 'internal_money_transfers';
    }
    if (is_null($tableName)) {
        return [
            'can_not_be_reviewed'=>1,
        ];
    }
    $raw = DB::table($tableName)->find($id);
    if ($raw && !isset($raw->reviewed_by)) {
        return [
            'can_not_be_reviewed'=>1,
        ];
    }
    return $raw && isset($raw->reviewed_by)  ? ['is_reviewed'=>$raw->is_reviewed,'reviewed_by'=>$raw->reviewed_by] : [];
}
function getBankStatementComment($stdClass)
{
    $lang = app()->getLocale() ;
    $columnNameWithoutLang = 'comment_';
    $tableName = null ;
    if ($id = $stdClass->money_received_id) {
        $tableName = 'money_received';
    } elseif ($id = $stdClass->money_payment_id) {
        $tableName = 'money_payments';
    } elseif ($id = $stdClass->cash_expense_id) {
        $tableName = 'cash_expenses';
    } elseif ($id = $stdClass->buy_or_sell_currency_id) {
        $tableName = 'buy_or_sell_currencies';
        if ($stdClass->is_debit) {
            $columnNameWithoutLang = 'buy_comment_';
        } else {
            $columnNameWithoutLang = 'sell_comment_';
        }
    } elseif ($id = $stdClass->internal_money_transfer_id) {
        $tableName = 'internal_money_transfers';
        if ($stdClass->is_debit) {
            $columnNameWithoutLang = 'from_comment_';
        } else {
            $columnNameWithoutLang = 'to_comment_';
        }
    }
    
    if (is_null($tableName)) {
        return __('N/A', [], $lang);
    }
    $raw = DB::table($tableName)->find($id);
    return $raw ? $raw->{$columnNameWithoutLang.$lang} : __('N/A', [], $lang);
}
function getKeysWithSettlementAmount(array $items, string $keyName):string
{
    $result = [];

    foreach ($items as $key => $arr) {
        if (isset($arr[$keyName]) && $arr[$keyName] > 0) {
            $result[] =  $arr['invoice_number'] ;
            // $result[] =  $key ;
        }
    }
    return implode(',', $result) ;
}
function getAllDataKey(array $items):array
{
    $result = [];
    foreach ($items as $key => $value) {
        if (Str::startsWith($key, 'data-')) {
            $result[$key] = $value ;
        }
    }
    return $result ;
}

function formatAccumulatedNetCash(array $netCashItems, array $dates)
{
    $formattedResult = [];
    $netCashItems = HArr::removeKeysFromArray($netCashItems, ['total_of_total']);
    $accumulatedNetCash = 0 ;
    foreach ($dates as $weekAndYear => $startAndEndDateArray) {
        $endDate = $startAndEndDateArray['end_date'];
        $currentNetCash = $netCashItems[$weekAndYear] ?? 0 ;
        $accumulatedNetCash += $currentNetCash ;
        $formattedResult[] = ['date'=>$endDate,'value'=>$accumulatedNetCash ];
    }
    return $formattedResult ;
}
function hasAuthFor($permissionName)
{
    return auth()->user()->can($permissionName);
}
function formatArrayAsGroup(array $permissions):array
{
    $result = [];
    foreach ($permissions as $permissionArr) {
        $result[$permissionArr['group']][] =$permissionArr;
    }
    return $result;
}
function generateModelData($fieldName, $model, $functionName = null, $defaultValue = null)
{
    $oldFromModel = isset($model) ? $model->{$fieldName} : $defaultValue ;
    if ($functionName) {
        $oldFromModel = isset($model) ? $model->$functionName() : $defaultValue ;
    }
    return old($fieldName, $oldFromModel);
}
function fillObjectFromArray(array $items, $object)
{
    $result = [];
    $isString  = $object;
    
    foreach ($items as $arrWithItsKeys) {
        if ($isString) {
            $object = new $object;
        }
        foreach ((array)$arrWithItsKeys as $key => $val) {
            $object->{$key}  = $val;
        }
        $result[] = $object ;
    }

    return $result ;
}
function getCashVeroTableNames()
{
    return [
        'cash_expenses',
        'overdraft_against_commercial_papers',
        'clean_overdrafts',
        'overdraft_against_assignment_of_contracts',
        'fully_secured_overdrafts',
        'settlement_allocations',
        'buy_or_sell_currencies',
        'cash_in_banks','cash_in_safes','cash_in_safe_statements',
        'cash_payments','certificates_of_deposits','cheques'
        ,'supplier_invoices' ,'clean_overdrafts','customer_invoices','financial_institutions','financial_institution_accounts','fully_secured_overdrafts'
        ,'clean_overdraft_bank_statements','clean_overdraft_withdrawals',
        'notifications',
        'current_account_bank_statements','debugging','down_payment_money_payment_settlements','down_payment_settlements','due_date_histories','fully_secured_overdraft_bank_statements','fully_secured_overdraft_withdrawals','incoming_transfers','internal_money_transfers','lc_hundred_percentage_cash_cover_opening_balances'
, "lc_hundred_percentage_cash_cover_opening_balances"
, "lending_information"
, "lending_information_against_assignment_of_contracts"
, "letter_of_credit_cash_cover_statements"
, "letter_of_credit_facilities"
, "letter_of_credit_facility_term_and_conditions"
, "letter_of_credit_opening_balances"
, "letter_of_credit_statements"
, "letter_of_guarantee_cash_cover_statements"
, "letter_of_guarantee_facilities"
, "letter_of_guarantee_facility_term_and_conditions"
, "letter_of_guarantee_issuances"
, "letter_of_guarantee_opening_balances"
, "letter_of_guarantee_statements"
, "lg_against_td_or_cd_opening_balances"
, "lg_hundred_percentage_cash_cover_opening_balances"
, "lg_issuance_advanced_payment_histories"
, "lg_opening_balances"
, "loans",'opening_balances','outgoing_transfers',
'outstanding_breakdowns','overdraft_against_assignment_of_contract_bank_statements',
'overdraft_against_assignment_of_contract_limits','overdraft_against_assignment_of_contract_withdrawals',
'overdraft_against_commercial_paper_bank_statements','overdraft_against_commercial_paper_limits',
'overdraft_against_commercial_paper_withdrawals','payable_cheques',
'payment_settlements','settlements','money_received','money_payments','contracts'

    ];
}
function getReviewedText(array $reviewedArr)
{
    $reviewedText = '-';
    if (isset($reviewedArr['can_not_be_reviewed'])) {
        $reviewedText = '-';
    } elseif (isset($reviewedArr['is_reviewed']) && $reviewedArr['is_reviewed'] == 1) {
        $reviewedText = __('Yes');
    } elseif (isset($reviewedArr['is_reviewed']) && $reviewedArr['is_reviewed'] == 0) {
        $reviewedText = __('No');
    }
    return $reviewedText ;
}
function getReviewPermissionName($modelName):string
{
    if ($modelName == 'CashExpense') {
        return 'review cash expenses';
    }
    if ($modelName =='MoneyReceived') {
        return 'review money received';
    }
    if ($modelName=='MoneyPayment') {
        return 'review supplier payments';
    }
    throw new \Exception('custom exception .. please add permission name here');
}
function AtLeastOnKeyIsTrue(array $items, string $key)
{
    $show = false ;
    foreach (array_column($items, $key) as $boolean) {
        if ($boolean) {
            $show= true ;
        }
    }
    return $show ;
}
function getAllPartnerTypesForSuppliers():array
{
    return ['is_supplier'=>__('Supplier'),'is_subsidiary_company'=>__('Subsidiary Company') , 'is_shareholder'=>__('Shareholder') , 'is_employee'=>__('Employee'),
    'is_other_partner'=>__('Other Partner'),
    'is_tax'=>__('Taxes & Social Insurance')
];

}
function getAllPartnerTypesForCustomers():array
{
    return ['is_customer'=>__('Customer'),'is_subsidiary_company'=>__('Subsidiary Company') , 'is_shareholder'=>__('Shareholder') , 'is_employee'=>__('Employee'),
'is_other_partner'=>__('Other Partner')
];

}
function hasExport(array $fields, int $companyId, $modelName='SalesGathering')
{
    $fieldRow = CustomizedFieldsExportation::where('company_id', $companyId)->where('model_name', $modelName)->first();
    $exportableFields = $fieldRow ? $fieldRow->fields : [];
    foreach ($fields as $field) {
        if (!in_array($field, $exportableFields)) {
            return false ;
        }
    }
    return true ;
}



function sort_by_key_date_string($element1, $element2)
{
    $datetime1 = strtotime($element1);
    $datetime2 = strtotime($element2);
    return $datetime1 - $datetime2;
}
function getDayFromDate(string $date)
{
    return explode('-', $date)[2];
}
function getMonthFromDate(string $date)
{
    return explode('-', $date)[1];
}

function repeatJson($jsonItems)
{
    $itemsArray = is_array($jsonItems) ? $jsonItems : convertJsonToArray($jsonItems);
    if (!count($itemsArray)) {
        return null ;
    }
    $lastKey = array_key_last($itemsArray);
    $loopingKey = $lastKey+1;
    for ($loopingKey ; $loopingKey < MAX_YEARS_COUNT ; $loopingKey++) {
        $itemsArray[$loopingKey] =$itemsArray[$lastKey];
    }
    return json_encode($itemsArray);
}




function sumNumberOfOnes(array $items, int $year, array $datesIndexWithYearIndex)
{
    $counter = [];
    foreach ($items as $loopYear => $dateAndValues) {
        foreach ($dateAndValues as $dateIndex => $value) {
            $loopYear = $datesIndexWithYearIndex[$dateIndex];
            if ($value == 1) {
                $counter[$loopYear] = isset($counter[$loopYear]) ? $counter[$loopYear] + 1 : $value;
            }
        }
    }
    return $counter[$year] ?? 0;
}
// function getPreviousValue(array $array, $specificValue)
// {
//     $keys = array_keys($array); // Get all keys from the array
//     $values = array_values($array); // Get all values from the array
//     $index = array_search($specificValue, $values); // Find the index of the specific value

//     if ($index === false || $index === 0) {
//         // Return null if the value doesn't exist or it's the first value
//         return null;
//     }

//     return $values[$index - 1]; // Return the previous value
// }
function removeSquareBrackets($input)
{
    // Use preg_replace to remove [ ] and text between them
    $result = preg_replace('/\[[^\]]*\]/', '', $input);
    return $result;
}



const SHAREABLE_LINKS = 'sharable-links';





// function getDepreciationDurations():array
// {
//     $result = [];
//     for ($i = 2 ; $i <= 25 ; $i++) {
//         $result[] = [
//             'title'=> $i . ' ' . __('Years'),
//             'value'=>$i
//         ];
//     }
//     return $result;
// }
// function getReplacementInterval():array
// {
//     $result = [];
//     for ($i = 1 ; $i <= 5 ; $i++) {
//         $result[] = [
//             'title'=> $i . ' ' . __('Years'),
//             'value'=>$i
//         ];
//     }
//     return $result;
// }
// function sumKeyAcrossArrays($data, $key)
// {
//     $sum = 0;
//     foreach ($data as $subArray) {
//         if (isset($subArray[$key])) {
//             $sum += $subArray[$key];
//         }
//     }
//     return $sum;
// }

function convertIndexKeysToString(array $items, array $datesAsIndexAndString)
{
    $result = [];
    foreach ($items as $dateAsIndex => $value) {
        $dateAsString = $datesAsIndexAndString[$dateAsIndex];
        $result[$dateAsString] = $value;
    }
    return $result ;
}
function sumIntervalsIndexes(array $dateValues, string $intervalName, string $financialYearStartMonth, array $dateIndexWithDate)
{
    return (new IntervalSummationOperations())->sumForInterval($dateValues, $intervalName, $financialYearStartMonth, $dateIndexWithDate, true);
}
function getIntervalFormatted():array
{
    return ['monthly'=>__('Monthly')
    ,'quarterly'=>__('Quarterly'),'semi-annually'=>__('Semi-annually'),'annually'=>__('Annually')
];
}
function removeDateFrom(array $dateIndexWithDate)
{
    $result = [];
    foreach ($dateIndexWithDate as $dateAsIndex => $dateAsString) {
        $dateExploded = explode('-', $dateAsString);
        $month = $dateExploded[1];
        $year = $dateExploded[0];
        $dateMonthAndYear =$month.'-'.$year;
        $result[$dateMonthAndYear] = $dateAsIndex;
    }
    return $result;
}
function getValueFromArrayStringAndIndex(array $items, $dateAsString, $dateAsIndex, $defaultValue = 0)
{
    if (isset($items[$dateAsString])) {
        return $items[$dateAsString];
    }
    if (isset($items[$dateAsIndex])) {
        return $items[$dateAsIndex];
    }
    return $defaultValue ;
}
function convertStringWithNumberToNumber(string $value):float
{
    $numericString = preg_replace('/[^0-9.,]/', '', $value);

    // Remove commas
    $numericString = str_replace(',', '', $numericString);

    // Convert to float
    $number = floatval($numericString);

    return  $number; // 2496335

}

function getExpensesTypes():array
{
    return [
        // 'varying_amount',
        // 'fixed_percentage_of_sales',
        // 'varying_percentage_of_sales',
        // 'fixed_cost_per_unit',
        // 'varying_cost_per_unit',
        // 'expense_per_employee',
        // 'intervally_repeating_amount',
        // 'one_time_expense',
        'fixed_monthly_repeating_amount',
        'expense_as_percentage',
            'cost_per_unit',
            'one_time_expense'
    ];
}
function getTableNames(?string $connectionName = null):array
{
    $connectionName = $connectionName ?? config('database.default');
    $database = DB::connection($connectionName)->getDatabaseName();
    // $tableName = config('app.env') == 'local' ? 'TABLE_NAME': 'table_name';
    return DB::connection($connectionName)->table('information_schema.tables')
    ->selectRaw('TABLE_NAME as table_name')
        ->where('table_schema', $database)
        ->where('table_type', 'BASE TABLE')
        ->pluck('table_name')->toArray();
}
function getTableNamesThatHasColumn(string $columnName, ?string $connectionName = null)
{
    $database = DB::connection($connectionName)->getDatabaseName();
    // $tableName = config('app.env') == 'local' ? 'TABLE_NAME': 'table_name';
  
    return DB::connection($connectionName)->table('information_schema.columns')
        ->selectRaw('TABLE_NAME as table_name')
        ->where('column_name', $columnName)
        ->where('table_schema', $database)
        ->distinct()->pluck('table_name')->toArray();

}





    
