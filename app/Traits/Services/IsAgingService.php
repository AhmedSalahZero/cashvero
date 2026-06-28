<?php
namespace App\Traits\Services;

use Carbon\Carbon;


 

trait IsAgingService 
{
	protected function getDueNameWithDiffInDays(string $date, string $agingDate): array
    {
        $date = Carbon::make($date);
        $agingDate = Carbon::make($agingDate);
        $diffInDays = $date->diffInDays($agingDate);
        if ($diffInDays == 0) {
            return ['current_due' => $diffInDays];
        }
        if ($agingDate->greaterThan($date)) {
            return ['past_due' => $diffInDays];
        }

        return ['coming_due' => $diffInDays];
    }

    protected function getInvoiceDayIntervals()
    {
        return getInvoiceDayIntervals();
    }

    protected function getDayInterval(int $diffDays)
    {
        foreach ($this->getInvoiceDayIntervals() as $dayInterval) {
            $days = explode('-', $dayInterval);
            $firstDay = $days[0];
            $twoDay = $days[1];
            if (in_array($diffDays, range($firstDay, $twoDay))) {
                return 				$dayInterval;
            }
        }

        return self::MORE_THAN_150 ;
    }

    public function getTotalAgainItemNameFromDue(string $dueName, string $againDate)
    {
        if ($dueName == 'past_due') {
            return 'Total Past Dues';
        }
        if ($dueName == 'current_due') {
            return 'Current Due [at date ' . Carbon::make($againDate)->format('d-m-Y') . '] ';
        }
        if ($dueName == 'coming_due') {
            return 'Total Coming Dues';
        }
    }
	
}
