<?php

namespace App\Http\Controllers;
use App\FinancialPlan;
use App\Helpers\HDate;
use App\Models\Company;
use App\Models\Loan;

use App\ReadyFunctions\CalculateFixedLoanAtBeginningService;
use App\ReadyFunctions\CalculateFixedLoanAtEndService;
use App\ReadyFunctions\CalculateVariableLoanAtBeginningService;
use App\ReadyFunctions\CalculateVariableLoanAtEndService;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;

class Loans2Controller extends Controller
{
    
    public function index($company_id)
    {
        
        $company = Company::find($company_id);
        return view('admin.loan2.index', compact('company'));
    }

    
    public function create(Request $request , $company_id  )
    {
            $routeName = Request()->route()->getAction()['as'] ; 
        if($request->has('storeByAjax'))
        {
            $storeByAjax = true ;
           
        }
        else{
            $storeByAjax = false ;
            $longTermFunding = null ;
        }

        if($request->long_term_funding_id)
        {
             $longTermFunding = $request->long_term_funding_id ;
        }
        else{
            $longTermFunding = null ; 
        }
    
        if($request->has('loanType'))
        {
            $loanType = $request->get('loanType');
        }
        else{
            $loanType =null ;
        }
       if($request->trigger_click)
       {
           $triggerClick = 1 ;
       }
       else{
           $triggerClick = null;

       }
        $company = Company::find($company_id);
        $longTermFunding = null ;
        $loan = null ; 
       
		if(
			str_contains($routeName, 'fixed.loan.fixed.at.end')
	){
			$type ='fixed';
			$title = __('Fixed Loan At End');
            return view('admin.loan2.create', compact('company' ,'title', 'type' ,'storeByAjax','loanType','longTermFunding','loan','triggerClick'
             
         ));       
         }

           if(
			str_contains($routeName, 'fixed.loan.fixed.at.beginning')
		   )
			{
            $title = __('Fixed Loan At Beginning');
            $type ='fixed';
            $position = 'at_beginning';
            return view('admin.loan2.create_at_begining', compact('company' ,'title', 'type','routeName'
            ,'position','storeByAjax','loanType','longTermFunding','loan','triggerClick'
        
         ));       
         }


         if(
			str_contains($routeName, 'calc.loan.amount')
		)
		{
            $title = __('Calculate Loan Amount');
            $type ='fixed';
            return view('admin.loan2.create_loan_amount', compact('company' ,'title', 'type','storeByAjax','loanType','longTermFunding','loan','triggerClick'
        
         ));       
         }

         if(
			str_contains($routeName, 'calc.interest.percentage')
		)
		{
              $title = __('Calculate Interest Rate');
            $type ='fixed';
            return view('admin.loan2.create_interest_percentage', compact('company','title' , 'type','storeByAjax','loanType','longTermFunding','loan','triggerClick'
			
         ));       
         }

         if(
			str_contains($routeName, 'variable.payments')
		)
		{
  			$title = __('Calculate Variable Payments Loan');
            $type ='variable';
            return view('admin.loan2.variable', compact('company' ,'title', 'type','storeByAjax','loanType','longTermFunding','loan','triggerClick'
         ));       
         }
        return view('admin.loan2.create', compact('company' ,'storeByAjax','loanType','longTermFunding','loan','triggerClick'
         ));
    }

    public function store(Request $request, $company_id, Loan $loan)
    {
        // $company = Company::find($company_id);

        // $loan->name                      = $request->name;

        // $loan->company_id                = $company_id;
        // $loan->loan_start_month          = $request->start_month;
        // $loan->loan_amount               = $request->loan_amount;

        // $loan->borrowing_rate            = $request->loan_type == 'variable' ? $request->borrowing_rate : 0;
        // $loan->margin_interest           = $request->margin_interest;
        // $loan->min_interest              = $request->loan_type == 'variable' ? $request->min_interest : 0;
        // $loan->loan_type                 = $request->loan_type;

        // $loan->duration               = $request->repayment_duration;
        // $loan->grace_period           = $request->grace_period;
        // $loan->installment_interval   = $request->installment_interval;
        // $loan->interest_interval      = $request->loan_type == 'variable' ? $request->interest_interval : $request->installment_interval;

        // $loan->created_by                = auth()->user()->id;
        // // $loan->save();
        // //////////////// LONG LOAN DISTRIBUTION ////////////////////////

        // //if the margin and borrowing rate sum are less than the min interest the intrest rate will be the min interest
        // $margin_borrowing_rate = $request->borrowing_rate + $request->margin_interest;
        // $interest_rate = $margin_borrowing_rate > $request->min_interest ? $margin_borrowing_rate : $request->min_interest;
        // //total duration is to add the repayment_duration to the grace period
        // $total_duration = $loan->duration  + $loan->grace_period;
        // $loan_start_date = $request->get('start_date');
        // $loan->start_date = $loan_start_date ;
        // $loan_type = $loan->loan_type;


        // return redirect()->route('loans2.index' , ['company_id'=> $company_id , 'type'=>$loan_type])->with([
       
        
        // ]);
        

     
    }
  
    // public function update(Request $request, $company_id, $financial_id, Loan $loan)
    // {
    //     $company = Company::find($company_id);
    //     $financial = FinancialPlan::with(['fundingRequired', 'loans'])->findOrFail($financial_id);

    //     $this->validation($request, $financial, $loan);

    //     $loan->name                      = $request->name;
    //     $loan->financial_plan_id         = $financial_id;
    //     $loan->company_id                = $company_id;
    //     $loan->loan_start_month          = $request->start_month;
    //     $loan->loan_amount               = $request->loan_amount;
    //     $loan->loan_type                 = $request->loan_type;


    //     //grace period
    //     $grace_period = $request->grace_period;
    //     // if ($request->loan_type == 'long') {
    //     //total duration is to add the repayment_duration to the grace period
    //     $new_duration =  $request->repayment_duration + $grace_period;
    //     $old_duration = $loan->duration + $loan->grace_period;
    //     //////////////// LONG LOAN DISTRIBUTION ////////////////////////
    //     //in case the old duration is not equal to the new one the old distributions rows will be deleted and the long loan will be distributed again according to the new distribution 
    //     if ($new_duration != $old_duration) {
    //         foreach ($loan->longDistributions as  $value) {
    //             $value->delete();
    //         }
    //         //if the margin and borrowing rate sum are less than the min interest the intrest rate will be the min interest
    //         // $margin_borrowing_rate = $request->borrowing_rate+$request->margin_interest;
    //         // $interest_rate = $margin_borrowing_rate > $request->min_interest ? $margin_borrowing_rate : $request->min_interest;
    //         $total_duration = $request->repayment_duration + $grace_period;
    //         // $loan_start_date = $loan->getStartDateAttribute();
    //         for ($month = 0; $month < $total_duration; $month++) {

    //             Loan_long_distribution::create([
    //                 'month' => $month,
    //                 'loan_id' => $loan->id,
    //                 'created_by' => Auth::id()
    //             ]);
    //         }
    //     }

    //     $loan->duration               = $request->repayment_duration;
    //     $loan->grace_period           = $grace_period;
    //     $loan->installment_interval   = $request->installment_interval;
    //     $loan->interest_interval      = $request->loan_type == 'variable' ? $request->interest_interval : $request->installment_interval;
    //     $loan->settlement_duration    = NULL;


    //     $loan->borrowing_rate            = $request->loan_type == 'variable' ? $request->borrowing_rate : 0;
    //     $loan->margin_interest           = $request->margin_interest;
    //     $loan->min_interest              = $request->loan_type == 'variable' ? $request->min_interest : 0;
    //     $loan->updated_by                = auth()->user()->id;
    //     $loan->save();


    //     // Saving Distribution data

    //     $loan_distributions = (new LoansDistributionController)->loanLongDistribution($company_id, $financial_id, $loan, 'array');
    //     $loan_type = $loan->loan_type;

    //     $distribution_data = $this->loanSavingDistributionData($loan_distributions, $loan_type);


    //     $loan->distribution_data = $distribution_data;
    //     $loan->save();
    //     if ($request->get('submit') == 'Submit') {
    //         return redirect()->route('loan.edit', compact('company', 'financial', 'loan'))->with('success', __('Updated Successfully'));
    //     } elseif ($request->get('submit') == 'Submit And Close') {

    //         return redirect()->route('loan.index', compact('company', 'financial'))->with('success', __('Updated Successfully'));
    //     }
    // }

   
    // public function edit($company_id, $financial_id, Loan $loan)
    // {

    //     $company = Company::find($company_id);
    //     $financial = FinancialPlan::with(['fundingRequired', 'loans'])->findOrFail($financial_id);

    //     return view('admin.loan.edit', compact('loan', 'company', 'financial'));
    // }
    // /**
    //  * get phase assigned to project.
    //  *
    //  * @param  \Illuminate\Http\Request  $request
    //  * @return \json
    //  */

 
    // public function destroy($company_id, $financial_id, Loan $loan)
    // {
    //     $company = Company::find($company_id);
    //     $financial = FinancialPlan::findOrFail($financial_id);
    //     // Users Activity
    //     $table = ['en' => 'Loan', 'ar' => 'القروض'];
    //     $action = ['en' => 'DELETE', 'ar' => 'حذف'];
    //     app('App\Http\Controllers\UserActivities')->addAction($company_id, Auth::user()->id, $action, $table, $loan->id);

    //     $loan->delete();
    //     Session()->flash("error", __('Deleted Successfully'));
    //     return redirect()->route('loan.index', compact('company', 'financial'));
    // }
 
    // public function loanBorrowingRateUpadateview($company_id, $financial_id, Loan $loan)
    // {
    //     $company = Company::find($company_id);
    //     // $financial = FinancialPlan::findOrFail($financial_id);
    //     //the start date of the loan according for the selected project
    //     $loan->start_date = Request('start_date');
    //     return view('admin.loan2.borrowing_rate_update', compact('company', 'loan'));
    // }
 
    // public function loanBorrowingRateUpadate($company_id, $financial_id, Loan $loan)
    // {
    //     $financial = FinancialPlan::findOrFail($financial_id);
    //     $company = Company::find($company_id);
    //     if (count($loan->rates) > 0) {
    //         foreach ($loan->rates as  $value) {
    //             $value->delete();
    //         }
    //     }
    //     if (count(request()->rates) > 0) {
    //         foreach (request()->rates as $key => $value) {
    //             Loan_long_update_rate::create([
    //                 'loan_id' => $loan->id,
    //                 'month' => $value['month'],
    //                 'borrowing_rate' => $value['borrowing_rate'],
    //                 'created_by' => Auth::user()->id
    //             ]);
    //         }
    //     }
    //     if (request()->get('submit') == 'Submit') {
    //         return redirect()->route('long.loan.borrowing.rate', compact('company', 'financial', 'loan'))->with('success', __('Updated Successfully'));
    //     } elseif (request()->get('submit') == 'Submit And Close') {

    //         return redirect()->route('loan.index', compact('company', 'financial'))->with('success', __('Updated Successfully'));
    //     }
    // }

    // public function loanSavingDistributionData($loan_distributions, $loan_type)
    // {
    //     $distribution_data = [];
    //     array_walk($loan_distributions, function ($value, $key) use (&$distribution_data, $loan_type) {
    //         $distribution_data[$value['loan_date']] = [
    //             'loan_amount' => $value['loan_amount'],
    //             'interest_amount' => $value['interest_amount'],
    //             'interest_payment' => $value['interest_payment'],
    //             'installment_payment' => $loan_type == 'variable' ? $value['variable_installment'] : $value['fixed_installment'],
    //             'principle_payment' => $loan_type == 'variable' ? $value['variable_installment'] : $value['principle_payment'],
    //         ];
    //     });



    //     return $distribution_data;
    // }


    // public function validation($request, $loan = null)
    // {
      
    //     $validation["name"]             =  ['required', 'max:100', $loan !== null ? '' : "unique:loans"];
    //     $validation["loan_amount"]      ='numeric|min:0|required';
    //     $validation["borrowing_rate"]   =  $request->loan_type == 'variable' ? "required" : "";
    //     $validation["margin_interest"]  =  "required";
    //     $validation["start_month"]       =  "required";
    //     $validation["min_interest"]      =  $request->loan_type == 'variable' ? "required" : "";
        
    //     $this->validate($request, @$validation, [
    //         // 'loan_amount.size' => __('Loan Amount must be equal or less than Long Term Loan Total Amount ' . number_format($remaining))
    //     ]);
    // }
	
	public function viewFixedAntEndAndBeginning(Request $request , $company_id   )
    {
        
        $company = Company::find($company_id);
   		  $loan = null;
		return view('admin.loan2.at-end-and-beginning', compact('company'  ,'loan'));
    }
	public function viewVariable(Request $request , $company_id   )
    {
        
        $company = Company::find($company_id);
   		  $loan = null;
		$title = 'Variable Payment Loan'; 
		return view('admin.loan2.variable', compact('company'  ,'loan','title'));
    }
	public function calculateFixedAtEndAndBeginning(Request $request,$company_id)
	{
        $company = Company::find($company_id);
     	$loan = new Loan;
		

		 $loan->company_id = $company_id ;
		 $loanType = $request->get('fixed_loan_type');
		 
		 $isGraceType = str_contains($loanType,'grace');
		 $isStepUp = in_array($loanType,Loan::stepUpTypes());
		 $isStepDown = in_array($loanType,Loan::stepDownTypes());
		 $request->merge([
			 'grace_period'=>$isGraceType ? $request->get('grace_period',0):0,
			 'step_up_rate'=> $isStepUp ? $request->get('step_up_rate') : 0 ,
			 'step_up_interval'=> $isStepUp  ? $request->get('step_up_interval') : null ,
			 'step_down_rate'=> $isStepDown ? $request->get('step_down_rate') : 0 ,
			 'step_down_interval'=> $isStepDown  ? $request->get('step_down_interval') : null ,
		 ]);
		 
		 $loan->fixedType =$loanType; 
		 $loanStartDate = $request->get('start_date');
		 $loan->start_date =$loanStartDate; 
		 $loanStartDate = Carbon::make($loanStartDate)->format('Y-m-d');
		 $loanAmount = $request->get('loan_amount');
		 $loan->loan_amount =$loanAmount; 
		 $baseRate = $request->get('base_rate');
		 $loan->base_rate =$baseRate; 
		 $marginRate = $request->get('margin_rate');
		 $loan->margin_rate =$marginRate; 
		 $loan->pricing = strval($marginRate +$baseRate) ; 
		 $tenor = $request->get('duration');
		 $loan->duration =$tenor; 
		 $gracePeriod = $request->get('grace_period',0);
		 $gracePeriod = $gracePeriod ? $gracePeriod : 0 ;
		 $loan->grace_period = $gracePeriod;
		 $installmentPaymentIntervalName = $request->get('installment_interval');
		 $loan->installment_interval = $installmentPaymentIntervalName;
		 $stepUpRate = $request->get('step_up_rate');
		 $loan->step_up_rate =$stepUpRate; 
		 $stepUpIntervalName = $request->get('step_up_interval');
		 $loan->step_up_interval =$stepUpIntervalName; 
		 $stepDownRate = $request->get('step_down_rate');
		 $loan->step_down_rate =$stepDownRate; 
		 $stepDownIntervalName = $request->get('step_down_interval');
		 $loan->step_down_interval =$stepDownIntervalName; 
		 $calculateFixedLoanAtEndService = new CalculateFixedLoanAtEndService();	
		 $calculateFixedLoanAtBeginningService = new CalculateFixedLoanAtBeginningService();	
		 $loan->is_with_capitalization = (int)Loan::isWithCapitalization($loanType);
		 $loan->save();
		
			$currentNatureType = $request->get('nature_type');
			$isAtEnd = $request->get('nature_type') == 'fixed_at_end' ;

			$datesAsIndexString=HDate::generateDatesBetweenStartDateAndDuration(0,$loanStartDate,$tenor,$installmentPaymentIntervalName);
	
		// for($i = 0 ; $i <= 600 ; $i++){
			$fixedAtEndResult = [];
			if($isAtEnd){
				// $previousResult ,int $indexOfLoop,string $loanType, string $startDate, float $loanAmount,  $baseRate, float $marginRate, float $tenor, string $installmentPaymentIntervalName, float $stepUpRate = 0, string $stepUpIntervalName = null, float $stepDownRate = 0, string $stepDownIntervalName = null, float $gracePeriod  = 0,$currentStartDateAsIndex=0$previousResult ,int $indexOfLoop,string $loanType, string $startDate, float $loanAmount,  $baseRate, float $marginRate, float $tenor, string $installmentPaymentIntervalName, float $stepUpRate = 0, string $stepUpIntervalName = null, float $stepDownRate = 0, string $stepDownIntervalName = null, float $gracePeriod  = 0,$currentStartDateAsIndex=0
				$fixedAtEndResult = $calculateFixedLoanAtEndService->__calculate([],-1,$loanType, $loanStartDate, $loanAmount,$baseRate,  $marginRate,  $tenor, $installmentPaymentIntervalName, $stepUpRate, $stepUpIntervalName ,$stepDownRate ,  $stepDownIntervalName ,$gracePeriod  );
			}else{
				$fixedAtEndResult = $calculateFixedLoanAtBeginningService->__calculate([],-1,$loanType, $loanStartDate, $loanAmount,$baseRate,  $marginRate,  $tenor, $installmentPaymentIntervalName, $stepUpRate, $stepUpIntervalName ,$stepDownRate ,  $stepDownIntervalName ,$gracePeriod  );
				
			}
		
		
		$fixedAtEndResult = $fixedAtEndResult['final_result']??[];
	
		$loanDates = array_keys($fixedAtEndResult['beginning']??[]);
		return view('admin.loan2.at-end-and-beginning',compact('company' ,'loanStartDate','currentNatureType' ,'loan','loanDates','fixedAtEndResult','datesAsIndexString'));
	}
	public function calculateVariableAtEndAndBeginning(Request $request,$company_id)
	{
        $company = Company::find($company_id);
     	$loan = new Loan;
		

		 $loan->company_id = $company_id ;
		 $loanType = $request->get('fixed_loan_type');
		 
		 $isGraceType = str_contains($loanType,'grace');
		 $isStepUp = in_array($loanType,Loan::stepUpTypes());
		 $isStepDown = in_array($loanType,Loan::stepDownTypes());
		 $request->merge([
			 'grace_period'=>$isGraceType ? $request->get('grace_period',0):0,
			 'step_up_rate'=> $isStepUp ? $request->get('step_up_rate') : 0 ,
			 'step_up_interval'=> $isStepUp  ? $request->get('step_up_interval') : null ,
			 'step_down_rate'=> $isStepDown ? $request->get('step_down_rate') : 0 ,
			 'step_down_interval'=> $isStepDown  ? $request->get('step_down_interval') : null ,
		 ]);
		 
		 $loan->fixedType =$loanType; 
		 $loanStartDate = $request->get('start_date');
		 $loan->start_date =$loanStartDate; 
		 $loanStartDate = Carbon::make($loanStartDate)->format('Y-m-d');
		 $loanAmount = $request->get('loan_amount');
		 $loan->loan_amount =$loanAmount; 
		 $baseRate = $request->get('base_rate');
		 $loan->base_rate =$baseRate; 
		 $marginRate = $request->get('margin_rate');
		 $loan->margin_rate =$marginRate; 
		 $loan->pricing = strval($marginRate +$baseRate) ; 
		 $tenor = $request->get('duration');
		 $loan->duration =$tenor; 
		 $gracePeriod = $request->get('grace_period',0);
		 $gracePeriod = $gracePeriod ? $gracePeriod : 0 ;
		 $loan->grace_period = $gracePeriod;
		 $installmentPaymentIntervalName = $request->get('installment_interval');
		 $interestPaymentIntervalName = $request->get('interest_interval');
		 $loan->installment_interval = $installmentPaymentIntervalName;
		 $loan->interest_interval = $interestPaymentIntervalName;
		 $stepUpRate = $request->get('step_up_rate');
		 $loan->step_up_rate =$stepUpRate; 
		 $stepUpIntervalName = $request->get('step_up_interval');
		 $loan->step_up_interval =$stepUpIntervalName; 
		 $stepDownRate = $request->get('step_down_rate');
		 $loan->step_down_rate =$stepDownRate; 
		 $stepDownIntervalName = $request->get('step_down_interval');
		 $loan->step_down_interval =$stepDownIntervalName; 
		 $calculateVariableLoanAtEndService = new CalculateVariableLoanAtEndService();	
		 $calculateVariableLoanAtBeginningService = new CalculateVariableLoanAtBeginningService();	
		 $loan->is_with_capitalization = (int)Loan::isWithCapitalization($loanType);
		 $loan->save();
		//  $loan->storeBasicForm($request);
		// $baseRatesMapping = [
		// 	"01-01-2025"=>31.75,  
		// 	// "01-02-2025"=>0.3175,  
		// 	// "01-03-2025"=>0.3175,  
		// 	// "01-04-2025"=>0.3175,  
		// 	// "01-05-2025"=>0.3175,  
		// 	// "01-06-2025"=>0.3175,  
		// 	// "01-07-2025"=>0.3175,  
		// 	// "01-08-2025"=>0.3175,  
		// 	// "01-09-2025"=>0.3175,  
		// 	// "01-10-2025"=>0.3175,  
		// 	// "01-11-2025"=>0.3175,  
		// 	// "01-12-2025"=>0.3175,  
		// 	"01-01-2026"=>29.25,  
		// 	// "01-02-2026"=>0.2925,  
		// 	// "01-03-2026"=>0.2925,  
		// 	// "01-04-2026"=>0.2925,  
		// 	// "01-05-2026"=>0.2925,  
		// 	// "01-06-2026"=>0.2925,  
		// 	// "01-07-2026"=>0.2925,  
		// 	// "01-08-2026"=>0.2925,  
		// 	// "01-09-2026"=>0.2925,  
		// 	// "01-10-2026"=>0.2925,  
		// 	// "01-11-2026"=>0.2925,  
		// 	// "01-12-2026"=>0.2925,  
		// 	"01-01-2027"=>26.75,  
		// 	// "01-02-2027"=>0.2675,  
		// 	// "01-03-2027"=>0.2675,  
		// 	// "01-04-2027"=>0.2675,  
		// 	// "01-05-2027"=>0.2675,  
		// 	// "01-06-2027"=>0.2675,  
		// 	// "01-07-2027"=>0.2675,  
		// 	// "01-08-2027"=>0.2675,  
		// 	// "01-09-2027"=>0.2675,  
		// 	// "01-10-2027"=>0.2675,  
		// 	// "01-11-2027"=>0.2675,  
		// 	// "01-12-2027"=>0.2675,  
		// 	"01-01-2028"=>24.25,  
		// 	// "01-02-2028"=>0.2425,  
		// 	// "01-03-2028"=>0.2425,  
		// 	// "01-04-2028"=>0.2425,  
		// 	// "01-05-2028"=>0.2425,  
		// 	// "01-06-2028"=>0.2425,  
		// 	// "01-07-2028"=>0.2425,  
		// 	// "01-08-2028"=>0.2425,  
		// 	// "01-09-2028"=>0.2425,  
		// 	// "01-10-2028"=>0.2425,  
		// 	// "01-11-2028"=>0.2425,  
		// 	// "01-12-2028"=>0.2425,  
		// 	"01-01-2029"=>21.75,  
		// 	// "01-02-2029"=>0.2175,  
		// 	// "01-03-2029"=>0.2175,  
		// 	// "01-04-2029"=>0.2175,  
		// 	// "01-05-2029"=>0.2175,  
		// 	// "01-06-2029"=>0.2175,  
		// 	// "01-07-2029"=>0.2175,  
		// 	// "01-08-2029"=>0.2175,  
		// 	// "01-09-2029"=>0.2175,  
		// 	// "01-10-2029"=>0.2175,  
		// 	// "01-11-2029"=>0.2175,  
		// 	// "01-12-2029"=>0.2175,  
		// 	// "01-01-2030"=>0.2175,  	
		// ];
		//	$time = 0 ;
			$currentNatureType = $request->get('nature_type');
			$interestInterval = $request->get('interest_interval',$request->get('installment_interval'));
			$isAtEnd = $request->get('nature_type') == 'variable_at_end' ;
			$datesAsIndexString=HDate::generateDatesBetweenStartDateAndDuration(0,$loanStartDate,$tenor,'monthly');
			$result = [];
			$title = 'Variable Payments Loan At End';
			if($isAtEnd){
				$result = $calculateVariableLoanAtEndService->__calculate([],-1,$loanType, $loanStartDate, $loanAmount,$baseRate,  $marginRate,  $tenor, $installmentPaymentIntervalName,$interestInterval, $stepUpRate, $stepUpIntervalName ,$stepDownRate ,  $stepDownIntervalName ,$gracePeriod,0  );
			}else{
				$title = 'Variable Payments Loan At Beginning';
				$result = $calculateVariableLoanAtBeginningService->__calculate([],-1,$loanType, $loanStartDate, $loanAmount,$baseRate,  $marginRate,  $tenor, $installmentPaymentIntervalName,$interestInterval, $stepUpRate, $stepUpIntervalName ,$stepDownRate ,  $stepDownIntervalName ,$gracePeriod,0  );
			}
	
		
		$result = $result['final_result']??[];
		
		$loanDates = array_keys($result['beginning']??[]);
		
		return view('admin.loan2.variable',compact('company' ,'currentNatureType' ,'loan','loanDates','result','datesAsIndexString','title'));
	}
	
}
