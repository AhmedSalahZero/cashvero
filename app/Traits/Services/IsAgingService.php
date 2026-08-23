<?php
namespace App\Traits\Services;

use Carbon\Carbon;


 

trait IsAgingService 
{
	protected function getDueNameWithDiffInDays(string $date, string $agingDate): array
    {
        $date = Carbon::make($date);
        $agingDate = Carbon::make($agingDate);
        // Carbon 3 (this project's Laravel 12) changed diffInDays() to
        // return a SIGNED value by default instead of Carbon 2's
        // always-positive count — same bug class as IsInvoice::getAging()
        // and getDiffBetweenTwoDatesInDays(), but with real consequences
        // here: getDayInterval() below matches this value against
        // positive-only ranges ('1-7', '8-15', ...). A negative value
        // (any "coming due" invoice, where $date is in the future)
        // can never match a positive range(), so every coming-due
        // invoice was silently falling through to the "More Than 150"
        // bucket regardless of its real due date. Forcing absolute
        // restores the original intended always-positive day count —
        // the past/coming DIRECTION below is already decided separately
        // via greaterThan(), untouched by this.
        $diffInDays = $date->diffInDays($agingDate, true);
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

	/**
	 * * بتحوّل جدول الإجماليات (due_type => interval => total) لشكل الرسم البياني
	 * * اللي AgingDivergingBarChart بيفهمه: صف لكل فترة، والمتأخر بعلامة سالب.
	 *
	 * * كانت متكتوبة جوّه InvoiceAgingService بس، فالشيكات — رغم إنها بتحسب
	 * * نفس التقسيمة بالظبط — ماكانش عندها الشكل ده وكانت مضطرة ترسم دونات.
	 * * اتنقلت هنا عشان الاتنين يطلعوا نفس العقد من مصدر واحد.
	 */
	public function formatAgingBucketsForChart(array $totals): array
	{
		$formatted = [];
		foreach ($totals as $dueType => $intervalTotals) {
			if (!is_array($intervalTotals)) {
				continue;
			}
			foreach ($intervalTotals as $interval => $total) {
				// no_invoices عدّادات مش مبالغ، و total إجمالي الفترة كلها
				if (!is_numeric($total) || in_array($interval, ['total', 'no_invoices'], true)) {
					continue;
				}
				$minus = $dueType == 'past_due' ? '-' : '';
				$formatted[] = [
					'region' => camelizeWithSpace($dueType, '_'),
					'state'  => $minus . $interval . ' ' . __('Days'),
					'sales'  => $total + 0,
				];
			}
		}

		return $formatted;
	}

}
