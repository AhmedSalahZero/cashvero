<?php

namespace App\Console\Commands;

use App\Http\Controllers\CustomerInvoiceDashboardController;
use App\Http\Controllers\LetterOfGuaranteeIssuanceController;
use App\Http\Controllers\ReadOdooInvoices;
use App\Models\Company;
use App\Models\FinancialStatement;
use App\Models\MoneyPayment;

use App\Models\Partner;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use MathPHP\Finance;
use Schema;
use Str;

class TestCommand extends Command
{


	protected $signature = 'run:test';

	protected $description = 'Test Code Command';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}
	public function testConvertRate()
	{
	
	}
	public function testRefreshBankMovementChart(): void
{
    $company = Company::find(92); // عدّل الـ ID حسب شركتك
    if (!$company) {
        $this->error('Company not found');
        return;
    }
    $request = new Request([
        'currencyName'  => 'EGP',                   // عدّلها
        'accountNumber' => '1234567890',            // عدّلها
        'date'          => '2026-03-18',            // عدّلها
        'modelName'     => 'FinancialInstitutionAccount', // لازم يكون موجود داخل App\Models
        'bankId'        => 1,                       // عدّلها
    ]);
    $controller = new CustomerInvoiceDashboardController();
    $response = $controller->refreshBankMovementChart($request, $company);
    // response()->json(...) يرجّع JsonResponse
    $this->info($response->getContent());
}
	
	public function handle()
	{	
		$this->testRefreshBankMovementChart();
	// 	$odooPaymentService = (new OdooPayment(Company::find(92)));
	// 	$res=$fetch->fetchData('account.move',[],[[['id','=',14783]]]);
	//  $res=$odooPaymentService->fetchData('account.move.line',[],[[['id','=',33960]]]);
	
		// $contracts = Contract::get();
		// /**
		//  * @var Contract $contract
		//  */
		// foreach($contracts as $contract){
		// 	$contract->reCalculateRentRevenuesAndRentCollections();
		// }
		// return 'good';
		// foreach(Company::get() as $company){
		// 	Category::createAllForCompany($company->id );
		// 	Ownership::createAllForCompany($company->id );
		// }
		
		// $rows = CashAndBankOpeningBalance::get();
		// foreach($rows as $row){
		// 	DB::connection(NON_BANKING_SERVICE_CONNECTION_NAME)->table('cash_and_bank_opening_balances')->where('id', $row->id)->update([
		// 		'interests_before_adjustment'=>$row->interests,
		// 		'non_performing_interests_before_adjustment'=>$row->non_performing_interests
		// 	])
		// ;
		// }
		// $moneyPayments = MoneyPayment::find(541)->downPaymentSettlements->sum('down_payment_amount');
		// $new = new DirectFactoringController;
		// $study = Study::find(86);
		// $company = $study->company;
		// $request = new Request;
		// $expense = Expense::where('company_id',31)->where('study_id',86)->where('relation_name','percentage_of_sales')->first();
		// $clonedExpense = $expense->replicate()->toArray();
		// for($i = 0 ; $i<=200;$i++){
		// 	Expense::create($clonedExpense);
		// }
		
		// $columnName = 'partner_id';
		// $partnerTables = getTableNamesThatHasColumn($columnName);
		// $rows=[];
		// foreach([
		// 	250 ,
		// 	807,
		// 	249,
		// 	863
		// ] as $partnerId){
		// 	foreach($partnerTables as $partnerTable){
		// 		$row = DB::table($partnerTable)->where($columnName,$partnerId)->get() ;
		// 		if(count($row)){
		// 			$rows[$partnerId][$partnerTable] = $row;
		// 		}
		// 	}
		// }
		// $name = 'Arabia for Design and Engineering Consulting';
		// $name = 'Arabia for Design and Engineering Consulting';
		// $partner = Partner::findByName($name,92);
		// $money  = MoneyReceived::where('id',331)->first();
		// // $money  = MoneyReceived::where('id',341)->first();
		// SupplierInvoice::where('id','>',0)->update([
		// 	'updated_at'=>now()
		// ]);
		
		// $letterOfGuaranteeIssuance = LetterOfGuaranteeIssuance::find(200);
		// $company = $letterOfGuaranteeIssuance->company;
		// $source = 'lg-facility';
		// (new LetterOfGuaranteeIssuanceController)->backToRunningStatus($company,new Request , $letterOfGuaranteeIssuance,$source);
		//$odooPaymentService = (new OdooPayment(Company::find(92)));
		// $res=$fetch->fetchData('account.move',[],[[['id','=',14783]]]);
	//  $res=$odooPaymentService->fetchData('account.move.line',[],[[['id','=',33960]]]);
	 
		//  $x = $odooPaymentService->execute(
        //         'account.move.line',
        //         'write',
        //         [33960, [
		// 			// 'amount_currency'=>1800,
		// 			'currency_rate'=>(float)0.0111111111111
        //         ]]
        //     );
		// // $res=$fetch->fetchData('account.payment',[],[[['id','=',466]]]);
		// $isCustomer = false;
		//  $accountType = $isCustomer === 'customer' ? 'receivable' : 'payable';
		// $invoiceMatches = [
		// 	[
		// 		'invoice_id'=>14821 ,
		// 		'amount'=>5000 ,
		// 		'amount_currency'
		// 	],
		// 	[
		// 		'invoice_id'=>14844,
		// 		'amount'=>10000
		// 	]
		// ];
		// $downPaymentOdooId = 14849;
        // $result = $fetch->removeReconciliation(14849);
        // $result = $fetch->matchDownPaymentToMultipleInvoices(
        //     $downPaymentOdooId,
        //     $invoiceMatches,
        //     $accountType
        // );
	//	$x = 	$fetch->partialReconcile(14845,14844,'payable');
	
	// $lastIndex = count($x) -1 ;
		
		// $request  = new Request;
		// $request->merge([
		// 	'odoo_start_date'=>'2025-01-01',
		// 	'odoo_end_date'=>'2025-12-31',
		// ]);
		// $readInvoices = new ReadOdooInvoices();
		// $readInvoices->handle($request,Company::find(92));
		// $x = $fetch->fetchData('account.bank.statement.line',[],[[['name','=','MISR/2025/00431']]]);
		// $x = $fetch->un('account.bank.statement.line',[],[[['name','=','MISR/2025/00431']]]);
		// $fetch = 
		// $x = $fetch->fetchData('account.payment',[],[[['id','>',0]]])[0];
		// $moneyPaymentOdooService = new MoneyPaymentOdooService(Company::find(92));
		// $linesIds = $moneyPaymentOdooService->fetchData('account.move',[],[[['id','=',15845]]])[0]['line_ids'];
		// $moneyPaymentOdooService->unlink(15845);
		// foreach($linesIds as $lineId){
		// 	$line=$moneyPaymentOdooService->fetchData('account.move.line',[],[[['id','=',$lineId]]]);
		// }
		// $x = $unlink->unlink(15845);
		
		// $unlink->unlinkBankStatementLine(8936);
		// $unlink->unlinkBankCollection(8950);
		// ($unlink->unlink('account.bank.statement.line',8936));
		
		// $this->models->execute_kw(
		// 		$this->db,
		// 		$this->uid,
		// 		$this->password,
		// 		$modelName,
		// 		'unlink',
		// 		[[$id]]
		// 	);
			
		// $x =  $fetch->fetchData(
        //             'account.move',
		// 			[],[[['name','=','MISR/2025/00491']]],
				
		// );
		
			
		// $settlement = Settlement::find(261);
		// $fetch->reCreatePayment($settlement);
	
		
		}
	
	
	// public function convertIncomeStatementDatesToIndexes()
	// {
	// 	$financialStatements = FinancialStatement::
	
	// 	get();
	// 	/**
	// 	 * @var FinancialStatement $financialStatement
	// 	 */
	// 	foreach($financialStatements as $financialStatement){
	// 		$request = (new Request)->merge([
	// 			'name'=>$financialStatement->getName(),
	// 			'start_from'=>$financialStatement->start_from,
	// 			'duration'=>$financialStatement->duration,
	// 			'duration_type'=>$financialStatement->duration_type,
	// 			'corporate_taxes_rate'=>22.5
	// 		]);

	// 		$financialStatement->storeMainSection($request);
	// 		$financialStatement->updateIndexedDates();
	// 		$incomeStatement = $financialStatement->incomeStatement;
	// 		if(is_null($incomeStatement)){
	// 			continue;
	// 		}
	// 		$datesHelper = $financialStatement->getDatesIndexesHelper();
	// 		$dateWithDateIndex = $datesHelper['dateWithDateIndex'];
	// 		foreach([
	// 			'financial_statement_able_main_item_calculations',
	// 		'financial_statement_able_main_item_sub_items'] as $tableName){
	
	// 		$rows = DB::table($tableName)
	// 		->where('financial_statement_able_id',$incomeStatement->id)
	// 		->get();
	// 		foreach($rows as $row ){
				
	// 			$indexedPayload = [];
	// 			$payload = (array)json_decode($row->payload);
	// 			foreach($payload as $date => $value){
	// 				$dateIndex = $dateWithDateIndex[$date]??null;
	// 				if(is_null($dateIndex)){
	// 					$indexedPayload[$date] = $value;
						
	// 				}else{
	// 					$indexedPayload[$dateIndex] = $value;
	// 				}
	// 			}
	// 			DB::table($tableName)->where('id',$row->id)->update([
	// 				'payload'=>json_encode($indexedPayload)
	// 			]);
				
	// 		}
	// 	}
		
	// }
		
	// }
	
	public function insertCustomersIntoPartnerTable(int $companyId)
	{
		$salesGatherings = DB::table('sales_gathering')->where('customer_name','!=',null)->where('company_id',$companyId)->get();
		foreach($salesGatherings as $salesGathering){
			$customerName = $salesGathering->customer_name;
			$isFound = Partner::where('company_id',$companyId)->where('name',$customerName)->where('is_customer',1)->first() ;
			if($isFound){
				continue ;
			}
			Partner::create([
				'company_id'=>$companyId,
				'name'=>$customerName,
				'is_customer'=>1 
			]);
			
		}
		
	}
	
	
	public function refreshStatement($statementModelName,$dateColumnName = 'full_date'){
		$fullModelName ='App\Models\\'.$statementModelName;
		$fullModelName::orderBy($dateColumnName)->get()->each(function($statementRaw){
			$statementRaw->update([
				'updated_at'=>now()
			]);
		});
	}
	// public function getTableNamesThatHasColumn(string $columnName,$connectionName = null):array 
	// {
	// 	$contains = [];
	// 	$notContains = [];
	// 	$connection = DB::connection($connectionName);
		
	// 	$tables = $connection->getDoctrineSchemaManager()->listTableNames();
	// 	foreach($tables as $tableName){
	// 		if(Schema::connection($connectionName)->hasColumn($tableName,$columnName)){
	// 			$contains[] = $tableName;
	// 		}else{
	// 			$notContains[] = $tableName;
	// 		}
	// 	}
	// 	return [
	// 		'contains'=>$contains,
	// 		'not_contains'=>$notContains
	// 	]; 
	// }
	// public function calculateIrr()
	// {
	// 	$pythonFilePath = resource_path('python/valuation/irr.py');
	// 	$irr = json_encode([-1000, 200, 300, 400, 500]);
	// 	$x = shell_exec('python3 '. $pythonFilePath .' '. $irr  );
	// }
	public function forecastQualityValidation(array $data)
	{
		$max = max($data) * 1.75;
		$dataFormatted = [];
		foreach($data as $date => $value){
			$year = Carbon::make($date)->format('Y');
			$month = Carbon::make($date)->format('m');
			$day = Carbon::make($date)->format('d');
			$date = $year . '-'.$month.'-'.$day;
			$dataFormatted['"ds"'][] = '"'.$date.'"' ;
			$dataFormatted['"y"'][] = $value ;
		}

		
		$pythonFilePath = resource_path('python/forecast/prophet_predicit.py');

		$dataFormatted = json_encode($dataFormatted);

		$x = shell_exec('python3 '. $pythonFilePath .' '. $dataFormatted  . ' ' . $max . ' ' . 4 );
		preg_match('/\[(.*?)\]/s', $x, $matches);

	// Step 2: Remove any newlines and extra spaces
	$cleaned_data = preg_replace('/\s+/', ' ', $matches[1]);

	// Step 3: Split the cleaned data into an array of values
	$values = explode(' ', $cleaned_data);
	$values = collect($values)->filter(function($val){return is_numeric($val);})->values()->toArray();
	return $values ;
	}
	public function calculateSalesForecast()
	{
		$salesGathering = DB::table('sales_gathering')->where('company_id','=','105')
		->groupByRaw('year,month')
		->selectRaw('LAST_DAY(concat(year,"-",month,"-","01")) as date,sum(net_sales_value) as net_sales_value')
		->whereRaw("date between '2021-01-01' and '2024-11-30'")
		->orderByRaw('year asc,month asc')
		->get();
		$salesGatherFormatted=[];
		$dates =[]; 
		$salesValues = [];
		foreach($salesGathering as $salesItem){
			$day = explode('-',$salesItem->date)[2] ;
			$month = explode('-',$salesItem->date)[1] ;
			$year  = explode('-',$salesItem->date)[0];
			$salesGatherFormatted['"date"'][] ='"'. $year.'-'.$month.'-'.$day .'"';
			$dates[] = '"'. $year.'-'.$month.'-'.$day .'"' ;
			$salesGatherFormatted['"net_sales_value"'][] = $salesItem->net_sales_value ;
		//	$salesValues[] = $salesItem->net_sales_value ; 
		}
		$pythonFilePath = resource_path('python/forecast/sales-forecast.py');
		// $irr = json_encode([-1000, 200, 300, 400, 500]);
		// $x = shell_exec('python3 '. $pythonFilePath .' '. $irr  );
		$forecast = shell_exec('python3 '.$pythonFilePath .' ' . json_encode($salesGatherFormatted));
	}
	
	public function getJsonColumns($table , $connectionName)
{
    $columns = Schema::connection($connectionName)->getColumnListing($table);

    $jsonColumns = [];

    foreach ($columns as $column) {
        $type = DB::connection($connectionName)->getSchemaBuilder()
            ->getColumnType($table, $column); // returns "json" if column type is JSON

        if ($type === 'json') {
            $jsonColumns[] = $column;
        }
    }

    return $jsonColumns;
}
public function getAllColumnNamesFromTable($table , $connectionName)
{
    $columns = Schema::connection($connectionName)->getColumnListing($table);

    $jsonColumns = [];

    foreach ($columns as $column) {
        $type = DB::connection($connectionName)->getSchemaBuilder()
            ->getColumnType($table, $column); // returns "json" if column type is JSON

            $jsonColumns[] = $column;
    }

    return $jsonColumns;
}
public function getSupplierInvoicesWithout()
{
	$moneyPayments = MoneyPayment::where('company_id',92)->get();
	$result = [];
	foreach($moneyPayments as $moneyPayment){
		$settlements = $moneyPayment->settlements;
		foreach($settlements as $settlement){
			if($settlement->invoice_id && !$settlement->supplierInvoice){
				$message = 'money_payment_id = ' .$moneyPayment->id.' - '.' settlement amount ' . $settlement->settlement_amount .'settlement id ' . $settlement->id. ' comment '.$moneyPayment->comment_en;
				$result[]=$message;
			}
		}
		
	}
	
}


}
