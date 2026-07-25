<?php 
namespace App\Services\Api\Traits;
use App\Models\Company;
use App\Models\User;
use Exception;
use ripcord;
require_once(public_path('apis/ripcord.php'));

/**
 * @method mixed execute_kw(string $model, string $method, array $args, array $kwargs = [])
 */
trait AuthTrait 
{
	
	protected ?string $url ;
	protected ?String $db;
	protected ?string $username;
	protected ?string $password ; 
	protected \Ripcord_Client $models;
	protected int $company_id  ;
	protected Company $company ; 
	protected ?int $uid;
	public function __construct(Company $company ) 
	{
		$odooUrl = $company->getOdooDBUrl();
		$odooDbName = $company->getOdooDBName();
		if (!$odooUrl || !$odooDbName) {
			throw new \RuntimeException(__('Missing company Odoo DB URL/Name.'));
		}
		$this->url = (string) $odooUrl;
		$this->db = (string) $odooDbName;
		$user =auth()->user();
		/**
		 * @var User $user
		 */
		if (!$user) {
			throw new \RuntimeException(__('No authenticated user found for Odoo integration context.'));
		}
		$odooUsername = $user->getOdooDBUserName();
		$odooPassword = $user->getOdooDBPassword();
		if (!$odooUsername || !$odooPassword) {
			throw new \RuntimeException(__('Missing Odoo username/password for current user.'));
		}
		$this->username = (string) $odooUsername;
		$this->password = (string) $odooPassword;
		$this->company_id = $company->id;
		$this->company = $company;
		$currentOdooId = $user->getOdooId() ;
		$common = ripcord::client("$this->url/xmlrpc/2/common");
		$uid = null ;
		try{
			if(is_null($currentOdooId)){
					$uid = $common->authenticate($this->db, $this->username, $this->password, array());
				}else{
					$uid = $currentOdooId ;
				}
		}
		catch(\Exception $e){
			
			$uid = null;
		}
		if(is_array($uid)){
			$uid = null ;
		}
		if(is_null($currentOdooId)){
			$user->update([
				'odoo_id'=>$uid 
			]);
		}
		$models = ripcord::client("$this->url/xmlrpc/2/object");
		$this->models = $models;
		$this->uid = $uid;
	}
	   public function execute($model, $method, $args,$kwargs = [])
    {
        $result = $this->models->execute_kw(
            $this->db,
            $this->uid,
            $this->password,
            $model,
            $method,
            $args,
			$kwargs
        );
        if (isset($result['faultCode'])) {
			if(str_contains($result['faultString'], 'TypeError: cannot marshal None unless allow_none is enabled')){
				return ;
			}
         	throw new \Exception($result['faultString']);
        }
        return $result;
    }
	
	// private function executeWithoutThrowException($model, $method, $args)
    // {
    //     $result = $this->models->execute_kw(
    //         $this->db,
    //         $this->uid,
    //         $this->password,
    //         $model,
    //         $method,
    //         $args
    //     );
    //     if (isset($result['faultCode'])) {
    //      //  throw new \Exception($result['faultString']);
	// 		return ;
    //     }
    //     return $result;
    // }
	
	public function fetchData(string $modelName ,array $fields = [],  array $filters = [[]]  )
	{
		$ids=$this->models->execute_kw($this->db, $this->uid, $this->password, $modelName, 'search',$filters );
		return $this->models->execute_kw($this->db, $this->uid, $this->password, $modelName, 'read', array($ids),[
			'fields'=>$fields
		]);
	}

	
	//   protected function getAnalysisAccountIds(array $analytic_distribution,?int $partnerId = null):array
    // {
	// 	if (is_null($partnerId)) {
    //         return [[6, 0, []]];
    //     }
    //     $distribution_analytic_account_ids = [];
    //     foreach (array_keys($analytic_distribution) as $key) {
    //         if ($key > 0) {
    //             $distribution_analytic_account_ids[] = [0, (int)$key];
    //         }
    //     }
    //     // Wrap in outer array with 6 and 0
    //     if (count($distribution_analytic_account_ids)) {
    //         $distribution_analytic_account_ids = [[6, 0, ...$distribution_analytic_account_ids]];
    //     } else {
    //         $distribution_analytic_account_ids = [[6, 0, []]];
    //     }
    //     return $distribution_analytic_account_ids;
    // }
	
}
