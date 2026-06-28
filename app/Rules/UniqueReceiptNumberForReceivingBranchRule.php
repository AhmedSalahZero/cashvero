<?php

namespace App\Rules;


use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\DB;

class UniqueReceiptNumberForReceivingBranchRule implements Rule
{
    /**
     * Create a new rule instance.
     *
     * @return void
     */
	protected $id ;
	protected $branch_id ;
	protected $failedMessage ;
	protected $table_name ;
    public function __construct( $tableName , $branchId  , $excludeId = null , $failedMessage = null)
    {
		
        $this->table_name = $tableName ;
        $this->branch_id = $branchId ;
        $this->id = $excludeId ;
		$this->failedMessage = $failedMessage;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param  string  $attribute
     * @param  mixed  $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
		if(!is_numeric($this->branch_id)){
			return true ;
		}
        return !DB::table($this->table_name)->where('company_id',getCurrentCompanyId())
		->where($this->table_name =='cash_in_safes' ? 'receiving_branch_id' : 'delivery_branch_id',$this->branch_id)
		->where($attribute,'=',$value)->where('id','!=',$this->id)
		->exists();
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message()
    {
        return $this->failedMessage;
    }
}
