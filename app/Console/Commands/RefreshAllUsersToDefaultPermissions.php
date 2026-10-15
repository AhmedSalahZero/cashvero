<?php

namespace App\Console\Commands;

use App\Helpers\HAuth;
use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

class RefreshAllUsersToDefaultPermissions extends Command
{

    protected $signature = 'refresh:permissions';

 
    protected $description = 'Refresh All Permissions To Default For All Users';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
		DB::table('permissions')->delete();
		DB::table('model_has_permissions')->delete();
		DB::table('role_has_permissions')->delete();
		app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
		app()->make(\Spatie\Permission\PermissionRegistrar::class)->clearClassPermissions();
        $users = User::orderBy('id','asc')->get();
		
		foreach(Company::all() as $company){
			if(!count($company->getSystemsNames())){
				$company->systems()->create([
					'system_name'=>CASH_VERO
				]);
			}
		}

		/**
		 * @var User[] $users
		 */
		$permissions = HAuth::getPermissions();
			
			foreach ($permissions as $permissionArr) {
				$permission = Permission::create([
					'name'=>$permissionArr['name']
				]);
				foreach($users as $user){
					$user->assignNewPermission($permissionArr,$permission);
				}
			}
		app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
		app()->make(\Spatie\Permission\PermissionRegistrar::class)->clearClassPermissions();
		return Command::SUCCESS;
    }
}
