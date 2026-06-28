<?php
namespace App\Traits\Models;




trait HasUserComment
{
	public function getUserComment():?string 
	{
		return $this->user_comment ?: '' ;
	}
	public function hasComment():bool
	{
		return (bool) $this->getUserComment(); 
	}
	
}
