<?php 
namespace App\Repositories;

use App\Models\CashVeroBranch;

class SafeRepository 
{
	public function store(array $storeData):CashVeroBranch
	{
		return CashVeroBranch::create($storeData);
	}
}
