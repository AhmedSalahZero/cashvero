<?php
namespace App\Traits;

use App\ReadyFunctions\CalculateDurationService;
use Carbon\Carbon;
use Illuminate\Support\Arr;



trait HasIndexedDates
{
	
	
	public function updateStudyAndOperationDates(array $datesAsStringAndIndex,array $datesIndexWithYearIndex,array $yearIndexWithYear,array $dateIndexWithDate,array $dateWithMonthNumber)
	{
		
		$operationDurationDates = $this->getOperationDurationPerMonth($datesAsStringAndIndex,$datesIndexWithYearIndex,$yearIndexWithYear,$dateIndexWithDate,$dateWithMonthNumber,false);
		$studyDurationDates = $this->getStudyDurationPerMonth($datesAsStringAndIndex,$datesIndexWithYearIndex,$yearIndexWithYear,$dateIndexWithDate,$dateWithMonthNumber,false);
		$operationDurationDates = $this->editOperationDatesStartingIndex($operationDurationDates,$studyDurationDates);
		$this->update([
			'study_dates'=>$studyDurationDates,
			'operation_dates'=>$operationDurationDates,
		]);
	}
	protected function editOperationDatesStartingIndex($operationDurationDates,$studyDurationDates){
		$firstIndexInOperationDates = $operationDurationDates[0] ?? null;
		if(!$firstIndexInOperationDates)
		{
			return [];
		}
		$newDates = [];
		$firstIndex = array_search($firstIndexInOperationDates , $studyDurationDates);
		$loop = 0 ;
		foreach($operationDurationDates as $oldIndex=>$value){
				if($loop == 0){
					$newDates[$firstIndex] = $value;
				}else{
					$newDates[]=$value ;
				}
				$loop++;
		}
		return $newDates ;
	}
	
	public function getStudyDurationPerMonth(array $datesAsStringAndIndex,array $datesIndexWithYearIndex,array $yearIndexWithYear,array $dateIndexWithDate,array $dateWithMonthNumber, $maxYearIsStudyEndDate = true, $repeatIndexes = true)
	{
		$studyDurationPerMonth = [];
		$studyDurationPerYear = $this->getStudyDurationPerYear($datesAsStringAndIndex,$datesIndexWithYearIndex,$yearIndexWithYear,$dateIndexWithDate,$dateWithMonthNumber, false,$maxYearIsStudyEndDate, $repeatIndexes);

		foreach ($studyDurationPerYear as $year => $values) {
			foreach ($values as $date => $value) {
				$studyDurationPerMonth[$date] = $value;
			}
		}

		return array_keys($studyDurationPerMonth);
	}
	public function getDurationInYears()
	{
		return $this->duration_in_years;
	}
	public function getOperationStartDate(): ?string
	{
		$startDate=$this->operation_start_date;

		return $startDate;
	}
	public function getStudyDurationPerYear(array $datesAsStringAndIndex,array $datesIndexWithYearIndex,array $yearIndexWithYear,array $dateIndexWithDate,array $dateWithMonthNumber, $asIndexes = true, $maxYearIsStudyEndDate = true, $repeatIndexes = true)
	{
		$calculateDurationService = new CalculateDurationService();
		$studyStartDate  = $this->getStudyStartDate();
		$operationStartDate = $this->getOperationStartDate();
		if ($maxYearIsStudyEndDate) {
			$maxDate = $this->getStudyEndDate();
		} else {
			$maxDate = $this->getMaxDate($datesAsStringAndIndex,$datesIndexWithYearIndex ,$yearIndexWithYear,$dateIndexWithDate,$dateWithMonthNumber);
		}


		$studyDurationInYears = $this->getDurationInYears();

		// $limitationDate = $operationStartDate;
		$studyDurationPerYear = $calculateDurationService->calculateMonthsDurationPerYear($studyStartDate, $maxDate, $studyDurationInYears,true);
		$studyDurationPerYear = $this->removeDatesBeforeDate($studyDurationPerYear, $studyStartDate);
		
		$dates = [];
		if ($asIndexes) {
			$dates =  $this->convertMonthAndYearsToIndexes($studyDurationPerYear, $datesAsStringAndIndex,$datesIndexWithYearIndex);
		} else {
			$dates =  $studyDurationPerYear;
		}
		if ($repeatIndexes) {
			return $this->addMoreIndexes($dates,$yearIndexWithYear, $dateIndexWithDate,$dateWithMonthNumber ,$asIndexes);
		} else {
			return $dates;
		}
		// return $this->removeZeroValuesFromTwoDimArr($dates);
	}
	public function removeDatesBeforeDate(array $items, string $limitDate)
	{
		$newItems = [];
		$limitDate = Carbon::make($limitDate);
		foreach ($items as $year=>$dateAndValues) {
			foreach ($dateAndValues as $date=>$value) {
				$currentDate = Carbon::make($date);
				if ($limitDate->lessThanOrEqualTo($currentDate)) {
					$newItems[$year][$date]=$value;
				}
			}
		}

		return $newItems;
	}
	protected function addMoreIndexes(array $yearAndDatesValues,array $yearIndexWithYear , array $dateIndexWithDate,array $dateWithMonthNumber ,bool $asIndexes):array
	{
		$maxYearsCount = $this->getDurationInYears() + 1;
		$lastYear = array_key_last($yearAndDatesValues);
		$firstYear = array_key_first($yearAndDatesValues);
		$maxYear = $firstYear  + $maxYearsCount;
		$firstYearAfterLast = $lastYear+1;
		for ($firstYearAfterLast; $firstYearAfterLast < $maxYear; $firstYearAfterLast++) {
			$dates = $this->replaceIndexWithItsStringDate($yearAndDatesValues[$lastYear],$dateIndexWithDate);
			if ($asIndexes) {
				$yearAndDatesValues[$firstYearAfterLast] = $this->replaceYearWithAnotherYear($dates, $yearIndexWithYear[$firstYearAfterLast], $asIndexes,$dateIndexWithDate,$dateWithMonthNumber);
			} else {
				$yearAndDatesValues[$firstYearAfterLast] = $this->replaceYearWithAnotherYear($dates, $firstYearAfterLast, $asIndexes,$dateIndexWithDate,$dateWithMonthNumber);
			}
		}
		return $yearAndDatesValues;
	}
	public function getOperationDurationPerMonth(array $datesAsStringAndIndex , array $datesIndexWithYearIndex ,array $yearIndexWithYear,array $dateIndexWithDate,array $dateWithMonthNumber, $maxYearIsStudyEndDate  = true)
	{
		$operationDurationPerMonth = [];
		$operationDurationPerYear = $this->getOperationDurationPerYear($datesAsStringAndIndex, $datesIndexWithYearIndex,$yearIndexWithYear,$dateIndexWithDate,$dateWithMonthNumber, false, $maxYearIsStudyEndDate);
		foreach ($operationDurationPerYear as $key => $values) {
			foreach ($values as $k => $v) {
				if ($v) {
					$operationDurationPerMonth[$k] = $v;
				}
			}
		}

		return array_keys($operationDurationPerMonth);
	}	
	public function getOperationDurationPerYearFromIndexes() 
	{
		$datesAsStringAndIndex = $this->getDatesAsStringAndIndex();
		$datesIndexWithYearIndex = App('datesIndexWithYearIndex');
		$yearIndexWithYear = App('yearIndexWithYear');
		$dateIndexWithDate = App('dateIndexWithDate');
		$dateWithMonthNumber = App('dateWithMonthNumber');
		return $this->getOperationDurationPerYear($datesAsStringAndIndex,$datesIndexWithYearIndex,$yearIndexWithYear,$dateIndexWithDate,$dateWithMonthNumber);
		
	}
	public function getOperationDurationPerYear(array $datesAsStringAndIndex,array $datesIndexWithYearIndex,array $yearIndexWithYear,array $dateIndexWithDate,array $dateWithMonthNumber  , $asIndexes = true, $maxYearIsStudyEndDate = true)
	{
		$calculateDurationService = new CalculateDurationService();
		$operationStartDate  = $this->getOperationStartDateFormatted();
		if ($maxYearIsStudyEndDate) {
			$maxDate = $this->getStudyEndDate();
		} else {
			$maxDate = $this->getMaxDate($datesAsStringAndIndex,$datesIndexWithYearIndex ,$yearIndexWithYear,$dateIndexWithDate,$dateWithMonthNumber);
		}
		$studyDurationInYears = $this->getDurationInYears();
		$operationDurationPerYear = $calculateDurationService->calculateMonthsDurationPerYear($operationStartDate, $maxDate, $studyDurationInYears,true);

		$operationDurationPerYear = $this->removeZeroValuesFromTwoDimArr($operationDurationPerYear);
		if ($asIndexes) {
			return $this->convertMonthAndYearsToIndexes($operationDurationPerYear, $datesAsStringAndIndex,$datesIndexWithYearIndex);
		}

		return $operationDurationPerYear;
	}
	protected function removeZeroValuesFromTwoDimArr(array $dates)
	{
		$result = [];
		foreach ($dates as $year => $dateAndValues) {
			foreach ($dateAndValues as $date=>$value) {
				if ($value) {
					$result[$year][$date] = $value;
				}
			}
		}

		return $result;
	}
	protected function convertMonthAndYearsToIndexes(array $yearsAndItsDates, array $datesAsStringAndIndex, array $datesIndexWithYearIndex)
	{
		$result = [];
		foreach ($yearsAndItsDates as $yearNumber => $datesAndZeros) {
			foreach ($datesAndZeros as $date => $zeroOrOne) {
				$dateIndex = $datesAsStringAndIndex[$date];
				$yearIndex = $datesIndexWithYearIndex[$dateIndex];
				$result[$yearIndex][$dateIndex] = $zeroOrOne;
			}
		}

		return $result;
	}	
	
	public function datesAndIndexesHelpers(array $studyDates){
		$firstLoop = true ;
		$baseYear = null ;
		$datesIndexWithYearIndex = [];
		$yearIndexWithYear = [];
		$dateIndexWithDate = [];
		$dateIndexWithMonthNumber = [];
		$dateWithMonthNumber = [];
		$dateWithDateIndex = [];
		
		foreach($studyDates as $dateIndex => $dateAsString){
			$year = explode('-',$dateAsString)[0];
			$montNumber = explode('-',$dateAsString)[1];
			if($firstLoop ){
				$baseYear = $year ;
				$firstLoop = false ; 
			}
			$yearIndex = $year - $baseYear ;
			$datesIndexWithYearIndex[$dateIndex] =$yearIndex ;
			$yearIndexWithYear[$yearIndex] = $year ;
			$dateIndexWithDate[$dateIndex] = $dateAsString ;
			$dateIndexWithMonthNumber[$dateIndex] = $montNumber ;
			$dateWithMonthNumber[$dateAsString] = $montNumber ;
			$dateWithDateIndex[$dateAsString] =$dateIndex ;
			
		}
	
		return [
			'datesIndexWithYearIndex'=>$datesIndexWithYearIndex,
			'yearIndexWithYear'=>$yearIndexWithYear,
			'dateIndexWithDate'=>$dateIndexWithDate,
			'dateIndexWithMonthNumber'=>$dateIndexWithMonthNumber,
			'dateWithMonthNumber'=>$dateWithMonthNumber,
			'dateWithDateIndex'=>$dateWithDateIndex,
		];
		return $datesIndexWithYearIndex ;
	}
	
	public function getStudyDates(): array
	{
		return  $this->study_dates ?: [];
	}
	
		public function getDatesAsStringAndIndex()
	{
		return array_flip($this->getStudyDates());
	}
	public function getOperationStartMonth(): ?int
	{
		return $this->operation_start_month ?: 0;
	}
	public function financialYearStartMonth(): ?string
	{
		return $this->financial_year_start_month;
	}
	public function getOperationStartDateFormatted()
	{
		$operationStartDate = $this->getOperationStartDate();

		return  $operationStartDate ? Carbon::make($operationStartDate)->format('Y-m-d') : null;
	}
	public function getOperationStartDateFormattedForView()
	{
		$operationStartDate = $this->getOperationStartDate();

		return  $operationStartDate ? dateFormatting($operationStartDate, 'M\' Y') : null;
	}
	protected function getMaxDate(array $datesAsStringAndIndex,array $datesIndexWithYearIndex ,array $yearIndexWithYear ,array $dateIndexWithDate,array $dateWithMonthNumber)
	{
		$studyDurationPerMonth = $this->getStudyDurationPerMonth($datesAsStringAndIndex,$datesIndexWithYearIndex,$yearIndexWithYear,$dateIndexWithDate,$dateWithMonthNumber);

		return $studyDurationPerMonth[array_key_last($studyDurationPerMonth)];
	}
	public function getStudyStartDate(): ?string
	{
		return $this->study_start_date;
	}

	public function getStudyStartDateFormattedForView(): string
	{
		$studyStartDate = $this->getStudyStartDate();

		return dateFormatting($studyStartDate, 'M\' Y');
	}
	public function getStudyEndDate(): ?string
	{
		return $this->study_end_date;
	}
	public function getStudyEndDateFormatted()
	{
	
	}
	public function getStudyEndDateFormattedForView(): string
	{
		$studyEndDate = $this->getStudyEndDate();
		return dateFormatting($studyEndDate, 'M\' Y');
	}

	protected function replaceYearWithAnotherYear(array $dateAndValues, $newYear, bool $asIndexes,array $dateIndexWithDate,array $dateWithMonthNumber)
	{
		$newDatesAndValues   = [];
		foreach ($dateAndValues as $date=>$value) {
			$dateAsIndex = null;
			if ($asIndexes) {
				$dateAsIndex = $date;
				$date = $dateIndexWithDate[$date];
			}
			$day = getDayFromDate($date);
			
			$monthNumber = $dateWithMonthNumber[$date] ?? getMonthFromDate($date);
			$fullDate =$newYear.'-' .$monthNumber . '-'  .$day  ;

			if ($asIndexes) {
				$newDatesAndValues[$dateAsIndex] = $value;
			} else {
				$newDatesAndValues[$fullDate] = $value;
			}
		}

		return $newDatesAndValues;
	}
	public function replaceIndexWithItsStringDate(array $dates,array $dateIndexWithDate):array
	{
		$stringFormattedDates = [];
		foreach ($dates as $dateIndex => $value) {
			if (is_numeric($dateIndex)) {
				// is index date like 25
				$stringFormattedDates[$dateIndexWithDate[$dateIndex]] =$value;
			} else {
				// is already date string like 10-10-2025
				$stringFormattedDates[$dateIndex] = $value;
			}
		}

		return $stringFormattedDates;
	}
	public function getStudyDurationPerYearFromIndexes() 
	{
		$datesAsStringAndIndex = $this->getDatesAsStringAndIndex();
		$datesIndexWithYearIndex = App('datesIndexWithYearIndex');
		$yearIndexWithYear = App('yearIndexWithYear');
		$dateIndexWithDate = App('dateIndexWithDate');
		$dateWithMonthNumber = App('dateWithMonthNumber');
		return $this->getStudyDurationPerMonth($datesAsStringAndIndex,$datesIndexWithYearIndex,$yearIndexWithYear,$dateIndexWithDate,$dateWithMonthNumber,false);
		
	}	
	public function getStudyDurationPerYearFromIndexesForView() 
	{
		$datesAsStringAndIndex = $this->getDatesAsStringAndIndex();
		$datesIndexWithYearIndex = App('datesIndexWithYearIndex');
		$yearIndexWithYear = App('yearIndexWithYear');
		$dateIndexWithDate = App('dateIndexWithDate');
		$dateWithMonthNumber = App('dateWithMonthNumber');
		return $this->getStudyDurationPerMonth($datesAsStringAndIndex,$datesIndexWithYearIndex,$yearIndexWithYear,$dateIndexWithDate,$dateWithMonthNumber,true,false);
		
	}			
	public function getDatesIndexesHelper()
	{
		$studyDates = $this->getStudyDates() ;
		$studyStartDate = Arr::first($studyDates);
		$studyEndDate = Arr::last($studyDates);
		$studyStartDate = $studyStartDate ? Carbon::make($studyStartDate)->format('Y-m-d'):null;
		$studyEndDate = $studyEndDate ? Carbon::make($studyEndDate)->format('Y-m-d'):null;
		return $this->datesAndIndexesHelpers($studyDates);
	}	
	

	
	
}
