<?php

namespace App\Imports;

use App\Helpers\HArr;
use App\Models\ActiveJob;
use App\Models\CachingCompany;
use App\Models\Company;
use App\Models\LastUploadFileName;
use App\Models\TablesField;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Concerns\RegistersEventListeners;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithBatchInserts;
use Maatwebsite\Excel\Concerns\WithCalculatedFormulas;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Events\ImportFailed;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;

HeadingRowFormatter::default('none');

class ImportData implements
	// ToModel,
	ToCollection,
	WithChunkReading,
	ShouldQueue,
	WithCalculatedFormulas,
	WithHeadingRow,
	WithBatchInserts,
	WithEvents,
	WithMultipleSheets
{
	use RegistersEventListeners;

	public $timeout = 50000*60;

	public $failOnTimeout = true;
	public $hasFailed = false;

	public static $static_model;

	public static $company_id;

	public $modelFields;

	public $format;

	public $model;

	private $job_id;

	private $companyId;

	// private $batch;
	private $uploadModelName;

	private $errorMessage='';


	private $userId='';

	// private $rows = 0 ;

	public function __construct($company_id, $format, $model, $modelFields, $jobId, $userId,$uploadModelName)
	{
		Self::$company_id = $company_id;
		Self::$static_model = $model;
		$this->modelFields = $modelFields;
		$this->format = $format;
		$this->model = $model;
		$this->companyId = $company_id;
		$this->job_id = $jobId;
		$this->userId = $userId;
		$this->uploadModelName = $uploadModelName;
	}
	public function collection(Collection $chunks)
	{
		$dates = [];
		$validationRow = null;
		$isInvalidData = false;
		$rowId = 2 ;
		
		foreach ($chunks as $key=>$rows) {
			if (isRawImportRowEmpty($rows)) {
				continue;
			}

			$data = $this->dataCustomizationImport($rows,$rowId);
			$rowId ++ ;

			if (! isset($data['validations']) && isMappedImportRowEmpty($data)) {
				continue;
			}

			if (isset($data['validations'])) {
				$isInvalidData = true;
				$validationRow = $data['validations'];
				
				DB::table('caching_company')->where('job_id', $this->job_id)->delete();
				$cachingKey = generateCacheKeyForValidationRow($this->companyId,$this->uploadModelName);
				// Company::find($this->companyId)->deleteAllOldLastUploadFileNamesFor($this->uploadModelName,LastUploadFileName::CURRENT);
				$validationRows = $validationRow;
				if (Cache::has($cachingKey)) {
					$validationRows = arrayMergeTwoDimArray($validationRows,Cache::get($cachingKey, []));
				}
				Cache::forever($cachingKey , $validationRows);
				
			}
			$dates[] = $data;
		}
		
		if(!$isInvalidData){
			$key = Str::random(10) . 'for_company_' . $this->companyId;
			
			
			Cache::forever($key, $dates);
			DB::table('caching_company')->insert([
				'key_name'=>$key,
				'company_id'=>$this->companyId,
				'job_id'=>$this->job_id,
				'model'=>$this->uploadModelName
			]);
			
		}
	}

	public function batchSize(): int
	{
		return 1000;
	}

	public function chunkSize(): int
	{
		return 50000;
	}

	public function sheets(): array
	{
		return [
			0 => $this,
		];
	}

	public function dateFormatting($date)
	{
		if (is_numeric($date)) {
			$date = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject($date)->format('Y-m-d');
		} else {
			if (str_contains($date, '/')) {
				$this->format = str_replace('-', '/', $this->format);
			}
			$strtotimeValue = date_create_from_format($this->format, $date);
			if(!$strtotimeValue){
				$this->format = str_replace('/', '-', $this->format);
				$strtotimeValue = date_create_from_format($this->format, $date);
				$this->format = str_replace('-', '/', $this->format);
			}
			if (!$strtotimeValue) {
		
				$this->errorMessage = __('Some Date Formate Is Not Correct');
				//TODO:if format [$this->format] is not correct it return false . so the following code causes error
				return null;
			} else {
				$date =  $strtotimeValue->format('Y-m-d');
			}
		}
				
		return $date;
	}

	protected function validateRowValue($key, $value):array
	{
		
		$invalidDates = [];
		$allValidations =[];
		if(in_array($key , ['Date'  , __('Date') , 'Estimated',__('Estimated')])){
			$dateValidation = $this->dateFormatting($value);
				if (is_null($dateValidation)) {
					$allValidations[$key] =  [
						'value'=>$value,
						'message'=>__('Invalid Date Format'),
					];
				}
		}

		if(in_array($key , ['Document Type',__('Document Type')])){
			if (!in_array($value, ['INV', 'inv', 'invoice', 'INVOICE', 'فاتوره'])) {
				$allValidations[$key] =  [
					'value'=>$value,
					'message'=>__('Invalid Document Type Only Allowed [INV , inv , invoice , INVOICE ,فاتوره ] '),
				];
			}	
		}
		if(in_array($key , array_merge(getNumericExportFields() , getNumericWithNegativeAllowedExportFields()) )){
			
			if (!is_numeric($value) && !is_null($value) && $value != '') {
				$allValidations[$key] =  [
					'message'=>__('Invalid Numeric Value'),
					'value'=>$value
				];
			}
		}
		if(in_array($key , self::getNonEmptyFields())){
			if ( is_null($value) || $value == '') {
				$allValidations[$key] =  [
					'message'=>__('Empty Value Not Allowed'),
					'value'=>$value
				];
			}
		}
	
		return $allValidations;
		
		
	}

	public function dataCustomizationImport($row,$rowId)
	{
		$data = [];
		$row_with_no_spaces = [];
		$validations = [];
		
		foreach ($row as $key => $value) {
		
			
			$row_with_no_spaces[trim($key)] = normalizeImportCellValue($value);
			$rowValidation = $this->validateRowValue(trim($key), normalizeImportCellValue($value));
			if (isset($rowValidation[$key]) && count($rowValidation[$key])) {
				$validations[$rowId][$key] =  $rowValidation[$key] ;
			}
		}
		if(count($validations)){
			return [
				'validations'=>$validations
			] ;
		}

		foreach ($this->modelFields as $field_name => $row_name) {
			if (is_int($row_name)) {
				$data[$field_name] = $row_name;
			} else {
				$cellValue = $this->resolveImportFieldValue($row_with_no_spaces, $field_name, $row_name);

				if (
					($cellValue === null || $cellValue === '')
					&& ! is_int($row_name)
					&& ! in_array($field_name, $this->fieldsExcludedFromPositionalFallback(), true)
				) {
					$cellValue = $this->resolveImportFieldValueByPosition($row_with_no_spaces, $field_name);
				}

				if ($cellValue !== null && $cellValue !== '') {
			
					if (str_contains($field_name, 'date') || str_contains($field_name,'estimated')) {
						$data[$field_name] = $this->dateFormatting($cellValue);
					} else {
						$item = str_replace('\\', '', $cellValue);
						$data[$field_name] = in_array($field_name, getImportIdentifierFieldNames(), true)
							? normalizeImportCellValue($item)
							: trim(preg_replace('/\s+/', ' ', $item));
						if($field_name == 'currency' && $item == 'EUR'){
							$data[$field_name] = 'EURO';
						}
					}
				} else {
					$data[$field_name] = null;
				}
			}
		}
		$data['id'] = generateIdForExcelRow($this->companyId);

		if ($this->uploadModelName === 'ContractLoanSchedule') {
			$contractValidations = $this->validateContractLoanScheduleImportRow($data, $rowId);
			if (count($contractValidations)) {
				return [
					'validations' => $contractValidations,
				];
			}
		}

		return $data;
	}

	protected function validateContractLoanScheduleImportRow(array $data, int $rowId): array
	{
		$validations = [];
		$fieldLabels = [
			'drawee_bank' => __('Drawee Bank'),
			'account_number' => __('Account Number'),
		];

		foreach (getContractLoanScheduleBankAccountValidationErrors($this->companyId, $data) as $field => $message) {
			$validations[$rowId][$fieldLabels[$field] ?? $field] = [
				'value' => $data[$field] ?? '',
				'message' => $message,
			];
		}

		return $validations;
	}

	public function registerEvents(): array
	{
		$error = $this->errorMessage;

		return [
			ImportFailed::class => function (ImportFailed $event) use ($error) {
				ActiveJob::where('id', $this->job_id)->where('model',$this->uploadModelName)->delete();
				CachingCompany::where('job_id', $this->job_id)->where('model',$this->uploadModelName)->delete();
				$key = generateCacheFailedName($this->companyId, $this->userId,$this->uploadModelName);
				$err = __('Excel Import Failed') . ' ' . $error;
				Cache::forever($key, $err);
			},
		];
	}

	protected static function getNonEmptyFields():array
{
    return [
        'Supplier Name' , __('Supplier Name'),
        'Invoice Date' , __('Invoice Date'),
        'Invoice Number',__('Invoice Number'),
        'Currency',__('Currency'),
        'Exchange Rate',__('Exchange Rate'),
        'Invoice Amount',__('Invoice Amount'),
        'Total Invoice Amount',__('Total Invoice Amount'),
        'Net Invoice Amount',__('Net Invoice Amount'),
        'Contracted Payment Days',__('Contracted Payment Days'),
        'Contracted Collection Days',__('Contracted Collection Days'),
        'Invoice Due Date',__('Invoice Due Date')
    ];
}

	protected function resolveImportFieldValue(array $row, string $fieldName, string $rowName): ?string
	{
		$candidates = array_unique(array_filter([
			$rowName,
			$fieldName,
			str_replace('_', ' ', $fieldName),
			ucwords(str_replace('_', ' ', $fieldName)),
		]));

		$modelClass = '\\App\\Models\\' . $this->uploadModelName;
		if (class_exists($modelClass) && method_exists($modelClass, 'getImportHeaderAliases')) {
			$aliases = $modelClass::getImportHeaderAliases();
			if (isset($aliases[$fieldName])) {
				$candidates = array_merge($candidates, $aliases[$fieldName]);
			}
		}

		foreach ($candidates as $candidate) {
			$normalizedCandidate = mb_strtolower(trim((string) $candidate));

			foreach ($row as $key => $value) {
				if (mb_strtolower(trim((string) $key)) === $normalizedCandidate) {
					return normalizeImportCellValue($value);
				}
			}
		}

		return null;
	}

	protected function getImportableFieldOrder(): array
	{
		$orderedFields = [];

		foreach ($this->modelFields as $fieldName => $rowName) {
			if (! is_int($rowName)) {
				$orderedFields[] = $fieldName;
			}
		}

		return $orderedFields;
	}

	/**
	 * Fields that must NEVER be guessed by column position when the
	 * uploaded file has no matching header for them. These drive
	 * automatic Contract/SO/PO linking (see SalesGatheringTestJob) —
	 * if the file is genuinely missing a "Contract Name"/"Contract
	 * Code" column, every column after it shifts by one position, and
	 * the positional fallback would silently pull an unrelated
	 * column's value (e.g. Contracted Payment Days) into contract_code.
	 * Leaving these blank when there's no header match is the safe
	 * behavior — confirmed with the project owner.
	 */
	protected function fieldsExcludedFromPositionalFallback(): array
	{
		return [
			'contract_name', 'contract_code', 'contract_date', 'contract_amount',
			'purchases_order_number', 'purchases_order_date',
			'sales_order_number', 'sales_order_date',
		];
	}

	protected function resolveImportFieldValueByPosition(array $row, string $fieldName): ?string
	{
		$orderedFields = $this->getImportableFieldOrder();
		$fieldIndex = array_search($fieldName, $orderedFields, true);

		if ($fieldIndex === false) {
			return null;
		}

		$values = array_values($row);

		if (! array_key_exists($fieldIndex, $values)) {
			return null;
		}

		return normalizeImportCellValue($values[$fieldIndex]);
	}
	
}
