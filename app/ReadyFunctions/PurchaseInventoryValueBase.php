<?php

namespace App\ReadyFunctions\PurchaseInventory;

class PurchaseInventoryValueBase
{
	public function coverageDurationInMonths($days)
	{
		$coverage_duration = 0;
		if ($days == 7) {
			$coverage_duration = 0.25;
		} elseif ($days == 15) {
			$coverage_duration = 0.5;
		} elseif ($days == 30) {
			$coverage_duration = 1;
		} elseif ($days == 45) {
			$coverage_duration = 1.5;
		} elseif ($days == 60) {
			$coverage_duration = 2;
		} elseif ($days == 75) {
			$coverage_duration = 2.5;
		} elseif ($days == 90) {
			$coverage_duration = 3;
		} elseif ($days == 120) {
			$coverage_duration = 4;
		} elseif ($days == 150) {
			$coverage_duration = 5;
		} elseif ($days == 180) {
			$coverage_duration = 6;
		}
		return $coverage_duration;
	}
}
