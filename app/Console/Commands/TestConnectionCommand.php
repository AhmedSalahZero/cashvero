<?php

namespace App\Console\Commands;


use App\Models\Company;


use App\Models\User;

use App\Services\Api\ExchangeRateService;

use Exception;
use Illuminate\Console\Command;
use ripcord;
require_once(public_path('apis/ripcord.php'));
class TestConnectionCommand extends Command
{

	protected $signature = 'run:odoo-connection';
	
	protected $description = 'Test Odoo Connection Code Command';

	/**
	 * Create a new command instance.
	 *
	 * @return void
	 */
	public function __construct()
	{
		parent::__construct();
	}
	

	protected string $url ;
	protected String $db;
	protected string $username;
	protected ?string $password ; 
	protected \Ripcord_Client $models;
	protected int $company_id  ;
	protected Company $company ; 
	protected ?int $uid;
	
	
	
	public function handle()
	{
		    $this->info(' Trying to connect to Odoo...');

		$company = Company::find(92);
		$user = $company->users()->first();
		/**
		 * @var User $user
		 * @var Company $company
		 */
			$this->url = $company->getOdooDBUrl();
		$this->db = $company->getOdooDBName();
		$this->username =$user->getOdooDBUserName();
		$this->password = $user->getOdooDBPassword();
		$this->company_id = $company->id;
		$this->company = $company;
		$common = ripcord::client("$this->url/xmlrpc/2/common");
		$uid = null ;
		try{
					$uid = $common->authenticate($this->db, $this->username, $this->password, array());
				
		}
		catch(\Exception $e){
			$this->error('❌ Connection failed: ' . $e->getMessage());
			$uid = null;
			 return Command::FAILURE;
		}
		  if (!is_int($uid)) {
        $this->error('❌ Authentication failed: Invalid credentials or Odoo not reachable.');
        return Command::FAILURE;
    }
	$this->info('✅ Connected successfully!');
  

    return Command::SUCCESS;
		
	}

	
}
