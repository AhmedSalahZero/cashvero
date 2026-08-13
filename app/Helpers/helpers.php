<?php

use App\Enums\LcTypes;
use App\Enums\LgTypes;
use App\Helpers\HArr;
use App\Helpers\HHelpers;
use App\Helpers\HStr;
use App\Helpers\HVero;
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
use App\Models\SecondAllocationSetting;
use App\Models\SecondExistingProductAllocationBase;
use App\Models\SecondNewProductAllocationBase;
use App\Models\User;
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

/**
 * Tracks how many rows were silently skipped during a commit because
 * they matched an already-existing invoice (same invoice_number +
 * currency, same company) — see SalesGatheringTestJob::handle(). Read
 * by the upload page after a save completes, so the person knows
 * some rows were skipped rather than being left to wonder why the
 * count doesn't match what they uploaded.
 */
function getSkippedDuplicatesCacheKey($company_id, string $modelName)
{
    return 'skipped_duplicates_for_company_' . $company_id . 'for_model' . $modelName;
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
            'typePrefixName'=>__('Contract Leasing Schedule'),
            'orderByDateField'=>'date',
            'viewPermissionName'=>viewLoanScheduleData,
            'uploadPermissionName'=>uploadLoanScheduleData,
            'exportPermissionName'=>exportLoanScheduleData,
            'deletePermissionName'=>deleteLoanScheduleData,
            'importHeaderText'=>__('Contract Leasing Schedule Import'),
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
    return ['Quantity' , __('Quantity') , 'Quantity Discount' , __('Quantity Discount') , 'Cash Discount' , __('Cash Discount') , 'Special Discount' , __('Special Discount') , __('Other Discounts') , 'Net Sales Value' , __('Net Sales Value'),'Price Per Unit' , __('Price Per Unit') , __('Sales Value') , __('Sales Value'),'Collected Amount',__('Collection Amount'),'Collected Amount',__('Collected Amount'),'Excel Collected Amount',__('Excel Collected Amount'),'Excel Paid Amount',__('Excel Paid Amount'),'Previous Payments',__('Previous Payments'),'Previous Collection',__('Previous Collection'),'Expected Collection Days',__('Expected Collection Days'),'Contracted Collection Days',__('Contracted Collection Days'),'Net Invoice Amount',__('Net Invoice Amount'),'Withhold Amount',__('Withhold Amount'),'Net Balance',__('Net Balance') , 'Vat Amount',__('Vat Amount'),'Withhold Amount',__('Withhold Amount'),'VAT Amount'];
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
        // Same Carbon 3 bug class as IsInvoice::getAging() — diffInDays()
        // with no $absolute flag returns a signed, fractional value
        // under Carbon 3 (this project's Laravel 12) instead of Carbon
        // 2's always-positive whole-day count. This shared helper is
        // used purely for "how many days between these two dates"
        // displays (Cheque.php, PayableCheque.php,
        // TimeOfDepositRenewalDateController, and 3 renewal/adjustment
        // history tables) — every call site already passes dates in
        // chronological order, so forcing absolute + int is a safe,
        // root-cause fix for all of them at once, not just this page.
        return (int) $firstDate->diffInDays($secondDate, true);
    }
    return 0 ;
}

function getCurrenciesForSuppliersAndCustomers(int $companyId):array
{
    $currencyFromBranch = Branch::where('company_id', $companyId)->pluck('currency', 'currency')->toArray();
    $currencyFromAccounts = FinancialInstitutionAccount::where('company_id', $companyId)->pluck('currency', 'currency')->toArray() ;
    return array_values(array_merge($currencyFromBranch, $currencyFromAccounts));

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

/**
 * SQL expression matching FinancialInstitution::getName():
 * bank-type institutions use banks.view_name (with name_en/name_ar fallback);
 * other types use financial_institutions.name.
 */
function financial_institution_display_name_sql(
    string $fiAlias = 'financial_institutions',
    string $bankAlias = 'banks',
    string $as = 'bank_name'
): string {
    $bankName = "COALESCE(NULLIF({$bankAlias}.view_name, ''), NULLIF({$bankAlias}.name_en, ''), NULLIF({$bankAlias}.name_ar, ''))";

    return "CASE WHEN {$fiAlias}.type = 'bank' THEN {$bankName} ELSE {$fiAlias}.name END as {$as}";
}

function getAddNewFieldRule($fieldName)
{
    return Rule::requiredIf(Request()->get($fieldName) == 'Add New');
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

function getCompanyDraweeBankNames(int $companyId, ?array $bankIds = null): array
{
    return FinancialInstitution::query()
        ->where('company_id', $companyId)
        ->where('type', FinancialInstitution::BANK)
        ->when($bankIds !== null && $bankIds !== [], function ($query) use ($bankIds) {
            $query->whereIn('id', $bankIds);
        })
        ->with('bank:id,view_name')
        ->get()
        ->map(fn (FinancialInstitution $financialInstitution) => (string) ($financialInstitution->bank?->view_name ?? ''))
        ->filter(fn (string $bankName) => $bankName !== '')
        ->unique()
        ->sort(SORT_NATURAL | SORT_FLAG_CASE)
        ->values()
        ->all();
}

/**
 * Company's banks, for the "which bank(s) does the company issue cheques
 * against?" picker shown on the Contract Loan Schedule "Export Fields"
 * screen before download (see ExportTable::customizedTableField()).
 */
function getCompanyBanksForDraweeBankPicker(int $companyId): array
{
    return FinancialInstitution::query()
        ->where('company_id', $companyId)
        ->where('type', FinancialInstitution::BANK)
        ->with('bank:id,view_name')
        ->get()
        ->map(fn (FinancialInstitution $financialInstitution) => [
            'id' => $financialInstitution->id,
            'name' => (string) ($financialInstitution->bank?->view_name ?? $financialInstitution->getName()),
        ])
        ->filter(fn (array $bank) => $bank['name'] !== '')
        ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
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

function getAccountNumbersForDraweeBankName(int $companyId, string $draweeBankName): array
{
    $financialInstitutionId = resolveDraweeBankFinancialInstitutionId($companyId, $draweeBankName);

    if (! $financialInstitutionId) {
        return [];
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
        return [];
    }

    return collect($financialInstitution->getAllAccountNumbers())
        ->filter(fn ($accountNumber) => normalizeImportCellValue((string) $accountNumber) !== '')
        ->unique()
        ->sort(SORT_NATURAL | SORT_FLAG_CASE)
        ->values()
        ->all();
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
/**
 * REAL BUG FIXED HERE (same Carbon 3 sign-bug class found and fixed
 * throughout this codebase -- see getDiffBetweenTwoDatesInDays()
 * above, TimeOfDeposit, Cheque, FactoringTransaction, the LG renewal
 * commission calculation, Odoo contract sync, and
 * TdRenewalDateHistory::getDuration()).
 *
 * NOTE: as of this fix, this specific helper has zero call sites
 * anywhere in the codebase -- it was dead code. Fixed anyway (rather
 * than removed) so it is not a live trap for whoever wires it up
 * next; $absolute = true restores the always-positive guarantee this
 * function's name implies. Consider removing this function and
 * standardizing on getDiffBetweenTwoDatesInDays() instead, since the
 * two near-identical names are themselves a source of confusion.
 */
function getDifferenceBetweenTwoDatesInDays(Carbon $firstDate, Carbon $secondDate)
{
    return $secondDate->diffInDays($firstDate, true);
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
        'current_account_bank_statements','down_payment_money_payment_settlements','down_payment_settlements','due_date_histories','fully_secured_overdraft_bank_statements','fully_secured_overdraft_withdrawals','incoming_transfers','internal_money_transfers'
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
, "lg_issuance_advanced_payment_histories"
, "lg_opening_balances"
, 'opening_balances','outgoing_transfers',
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

/**
 * Flash a success message for both Inertia (native page.flash) and any
 * remaining Blade/session consumers. Prefer this (or redirect()->with)
 * over toastr()/flash() on migrated pages.
 */
function flash_success(string $message): void
{
    session()->flash('success', $message);
    \Inertia\Inertia::flash('success', $message);
}

/**
 * Flash a failure/error message for both Inertia and session consumers.
 * Uses session key 'fail' to match the rest of this app's convention.
 */
function flash_fail(string $message): void
{
    session()->flash('fail', $message);
    \Inertia\Inertia::flash('error', $message);
}





    
