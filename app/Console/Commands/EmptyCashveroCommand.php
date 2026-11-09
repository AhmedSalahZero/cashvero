<?php

namespace App\Console\Commands;


use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Schema;

class EmptyCashveroCommand extends Command
{
   
    protected $signature = 'empty:cashvero {--force : Do not ask for confirmation}';

   
    protected $description = 'Empty CashVero';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function handle()
    {
		// Deletes the data of EVERY company except 41 — on a production server one typo away from
		// losing everything. Never without an explicit yes.
		if (! $this->option('force') && ! $this->confirm('Delete the data of EVERY company except company 41? This cannot be undone.', false)) {
			$this->warn('Nothing was deleted.');

			return self::FAILURE;
		}

		$cashVeroTables = getCashVeroTableNames();
		
		foreach($cashVeroTables as $tableName){
			if(Schema::hasColumn($tableName,'company_id')){
				DB::table($tableName)->where('company_id','!=',41)->delete();
			}
		}
		
       
    }	
}
