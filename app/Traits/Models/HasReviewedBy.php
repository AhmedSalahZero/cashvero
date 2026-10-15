<?php
namespace App\Traits\Models;

use App\Models\User;

trait HasReviewedBy
{

	public function isReviewed():bool
	{
		return (bool)$this->is_reviewed;		
	}	
	public function reviewedBy()
	{
		return $this->belongsTo(User::class,'reviewed_by','id');
	}
	
}
