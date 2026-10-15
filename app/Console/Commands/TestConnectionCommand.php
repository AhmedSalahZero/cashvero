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

	protected $signature = 'run:odoo-connection {company=92 : رقم الشركة} {user? : رقم المستخدم — الافتراضي أول مستخدم في الشركة}';
	
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

		$companyId = (int) $this->argument('company');
		$company = Company::find($companyId);

		/**
		 * * الكوماند ده أداة تشخيص — لازم يقول المشكلة فين بدل ما يقع
		 * * بـ "Call to a member function on null"
		 */
		if (! $company) {
			$this->error("❌ Company {$companyId} not found.");

			return Command::FAILURE;
		}

		$userId = $this->argument('user');
		$user = $userId ? User::find((int) $userId) : $company->users()->first();

		if (! $user) {
			$this->error($userId ? "❌ User {$userId} not found." : "❌ Company {$companyId} has no users.");

			return Command::FAILURE;
		}

		$this->info(' User: '.$user->name);

		/**
		 * @var User $user
		 * @var Company $company
		 */

		/**
		 * * الخصائص تحت معرّفة string مش nullable ، فالشركة اللي
		 * * ماعندهاش odoo_db_url كانت بتطلّع
		 * *   TypeError: Cannot assign null to property ...::$url
		 * * بدل ما الكوماند يقول إن التكامل مش متظبط أصلا — و ده بالظبط
		 * * عكس الغرض من أداة اختبار اتصال
		 */
		$missing = [];
		if (! $company->getOdooDBUrl()) {
			$missing[] = 'company.odoo_db_url';
		}
		if (! $company->getOdooDBName()) {
			$missing[] = 'company.odoo_db_name';
		}
		if (! $user->getOdooDBUserName()) {
			$missing[] = 'user.odoo_db_username';
		}
		if (! $user->getOdooDBPassword()) {
			$missing[] = 'user.odoo_db_password';
		}

		if ($missing) {
			$this->error('❌ Odoo integration is not configured. Missing: '.implode(', ', $missing));

			return Command::FAILURE;
		}

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
