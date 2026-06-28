<?php 
namespace App\Traits;

use Carbon\Carbon;

trait HasCreatedAt
{
	public function getCreatedAt()
	{
		return $this->created_at ;
	}
	public function getCreatedAtFormatted()
	{
		$createdAt = $this->getCreatedAt();
		return $createdAt ? Carbon::make($createdAt)->format('d-m-Y'):__('N/A');
	}
	
}
