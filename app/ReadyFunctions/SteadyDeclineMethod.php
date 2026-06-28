<?php

namespace App\ReadyFunctions;

use Carbon\Carbon;

class SteadyDeclineMethod
{
	public function calculateSteadyDeclineAmount(float $amount, string $startDate, int $duration)
	{
		$steadyDeclineCount = [];
		for ($i = $duration; $i >= 1; $i--) {
			$steadyDeclineCount[] = $i;
		}
		$steadyDeclineFactor = array_sum($steadyDeclineCount);
		$steadyFactorAmount = $steadyDeclineFactor != 0 ? $amount / $steadyDeclineFactor : $amount;
		$steadyDeclineAmount = [];
		$steadyDeclineDate = Carbon::make($startDate)->format('d-m-Y'); // 01-02-2023
		foreach ($steadyDeclineCount as $steadyDeclineCountElement) {
			$steadyDeclineAmount[$steadyDeclineDate] = $steadyFactorAmount * $steadyDeclineCountElement;
			$steadyDeclineDate = Carbon::make($steadyDeclineDate)->addMonth()->format('d-m-Y'); // [$steadDeclineRate = 01-02-2023]
		}

		return $steadyDeclineAmount;
	}
}
