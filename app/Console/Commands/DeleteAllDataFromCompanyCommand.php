<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Schema;

class DeleteAllDataFromCompanyCommand extends Command
{
   
    protected $signature = 'delete:all {company_id} {--force : Do not ask for confirmation (used by the Remove Company screen, which confirms itself)}';

    protected $description = 'Delete All Data For Company';

    
    public function __construct()
    {
        parent::__construct();
    }

	protected function tryToDeleteThisTables(int $companyId, array $tablesNamesToBeDeleted , int $attemptNumber = 1 ):array
	{
		$tablesCanNotBeDeletedInFirstAttempt = [];
		foreach($tablesNamesToBeDeleted as $tableNameToBeDeleted){
			try{
				if(Schema::hasColumn($tableNameToBeDeleted,'company_id')){
					DB::table($tableNameToBeDeleted)->where('company_id',$companyId)->delete();
				}
			}catch(\Exception $e){
				$tablesCanNotBeDeletedInFirstAttempt[]=$tableNameToBeDeleted;
			}
		}
		return $tablesCanNotBeDeletedInFirstAttempt;
		
	}
    public function handle()
    {
		$companyId = $this->argument('company_id') ;
		// Every record of the company is deleted and cannot be brought back: ask first, unless the
		// caller (the Remove Company screen) has already asked.
		if (! $this->option('force') && ! $this->confirm("Delete ALL data of company {$companyId}? This cannot be undone.", false)) {
			$this->warn('Nothing was deleted.');

			return self::FAILURE;
		}
		$tablesNamesToBeDeleted = getTableNames();
		$attemptNumber = 1 ;
		while($attemptNumber <= 10 && count($tablesNamesToBeDeleted)){
			$tablesNamesToBeDeleted = $this->tryToDeleteThisTables($companyId,$tablesNamesToBeDeleted,$attemptNumber);
			$attemptNumber++ ;
		}
    }
}
