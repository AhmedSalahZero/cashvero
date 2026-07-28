<?php

namespace App\Models;

use App\Helpers\HArr;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string|null $odoo_db_password
 * @property string|null $odoo_username
 * @property string $email
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $subscription
 * @property string|null $expiration_date
 * @property string|null $max_users
 * @property int|null $acceptance_of_privacy_policy
 * @property string|null $remember_token
 * @property int|null $created_by
 * @property int|null $updated_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property string|null $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Company> $companies
 * @property-read int|null $companies_count
 * @property-read bool|null $companies_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Log> $logs
 * @property-read int|null $logs_count
 * @property-read bool|null $logs_exists
 * @property-read \Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection<int, \Spatie\MediaLibrary\MediaCollections\Models\Media> $media
 * @property-read int|null $media_count
 * @property-read bool|null $media_exists
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $notifications
 * @property-read int|null $notifications_count
 * @property-read bool|null $notifications_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OverdraftAgainstAssignmentOfContract> $overdraftAgainstAssignmentOfContract
 * @property-read int|null $overdraft_against_assignment_of_contract_count
 * @property-read bool|null $overdraft_against_assignment_of_contract_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\OverdraftAgainstCommercialPaper> $overdraftAgainstCommercialPaper
 * @property-read int|null $overdraft_against_commercial_paper_count
 * @property-read bool|null $overdraft_against_commercial_paper_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Permission> $permissions
 * @property-read int|null $permissions_count
 * @property-read bool|null $permissions_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \Spatie\Permission\Models\Role> $roles
 * @property-read int|null $roles_count
 * @property-read bool|null $roles_exists
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $usersCreatedBy
 * @property-read int|null $users_created_by_count
 * @property-read bool|null $users_created_by_exists
 * @method static \Database\Factories\UserFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User permission($permissions, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User role($roles, $guard = null, $without = false)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereAcceptanceOfPrivacyPolicy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereCreatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereEmail($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereEmailVerifiedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereExpirationDate($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereMaxUsers($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereOdooDbPassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereOdooUsername($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User wherePassword($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereRememberToken($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereSubscription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereUpdatedBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User withoutPermission($permissions)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User withoutRole($roles, $guard = null)
 * @property int|null $odoo_id
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\User whereOdooId($value)
 * @mixin \Eloquent
 */
class User extends Authenticatable implements HasMedia
{
	const SUPER_ADMIN = 'super-admin';
	const COMPANY_ADMIN = 'company-admin';
	const MANAGER = 'manager';
	const USER = 'user';
	
    use Notifiable, HasRoles, InteractsWithMedia, HasFactory;
	protected $connection = 'mysql';
    protected $fillable = [
        'name', 'email', 'password','max_users',
		'created_by','odoo_username','odoo_db_password','odoo_id','dark_mode'
    ];
    protected $hidden = [
        'password', 'remember_token',
    ];
    protected $casts = [
        'email_verified_at' => 'datetime',
        'dark_mode' => 'boolean',
    ];
    public function companies():BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'companies_users');
    }
	public function hasAccessToSystems(array  $systemNames):bool{
			if($this->isSuperAdmin()){
				return true ;
			}
			$userSystemName = $this->getSystemsNames() ;
			return HArr::atLeastOneValueExistInArray($userSystemName,$systemNames);
			
	}
	public function getSystemsNames():array{
		if($this->isSuperAdmin()){
			return CompanySystem::getAllSystemNames();
		}
		$firstCompany = $this->companies->first() ;
		/**
		 * @var Company|null $firstCompany
		 */
		return $firstCompany ? $firstCompany->getSystemsNames() : [] ;
	}
    public function canViewIncomeStatement()
    {
		return true ;
    }

    public function getName():string
    {
        return $this->name ;
    }
	public function getRoleName()
	{
		return $this->roles->first()->name;
	}
	
	public function isSuperAdmin()
	{
		return  $this->roles->first()->name == 'super-admin';
	}
	public function isCompanyAdmin():bool 
	{
	
		return  $this->roles->first()->name == 'company-admin';
	}
	public function isManager():bool 
	{
		return  $this->roles->first()->name == 'manager';
	}
	public function isUser():bool 
	{
		return  $this->roles->first()->name == 'user';
	}
	public function usersCreatedBy():HasMany
	{
		return $this->hasMany(User::class , 'created_by','id');
	}
	public function canStoreMoreUser():bool
	{
		if($this->isCompanyAdmin())
		{
			return $this->usersCreatedBy->count() < $this->max_users;
		}	
		return true ;
	}
	public function canViewReport(string $reportName):bool
	{
	
		$canViewReport = false ;
		$user = Auth()->user() ; 
		/**
		 * @var User $user ;
		 */
		$reports  = searchWordInstr(reportNames(),$reportName);
		foreach($reports as $report){
			$canViewReport = $user->can(generateReportName($report));
			if(!$canViewReport){
				return false ;
			}
		}
		return $canViewReport ;
	}
	public function logs():HasMany
	{
		return $this->hasMany(Log::class , 'user_id','id');
	}
	public function hasRole($roleName):bool
	{
		return $this->roles->first()->name == $roleName ;
	}
	
	
	
	// public function downPayment()
	// {
	// 	return $this->hasMany(DownPayment::class , 'user_id','id')->where('company_id',getCurrentCompanyId());
	// }
	
	
	
	
	
	public function overdraftAgainstCommercialPaper():HasMany
	{
		return $this->hasMany(OverdraftAgainstCommercialPaper::class , 'created_by','id')->where('company_id',getCurrentCompanyId());
	}
	
	public function overdraftAgainstAssignmentOfContract():HasMany
	{
		return $this->hasMany(OverdraftAgainstAssignmentOfContract::class , 'created_by','id')->where('company_id',getCurrentCompanyId());
	}
	
	public function isFreeTrialAccount():bool
	{
		return $this->subscription == 'free_trial';	
	}
	public function getExpirationDaysLeft()
	{
		$now = strtotime(date('Y-m-d')); // or your date as well
            $your_date = strtotime($this->expiration_date);
            $datediff = $your_date - $now;
            return round($datediff / (60 * 60 * 24));
	}
	public function AccountExpired()
	{
		$expirationDate = $this->expiration_date ;
		if($expirationDate && $this->isFreeTrialAccount()){
			return now()->greaterThan($this->expiration_date);
		}
		return false ;
	}
	


	
	public function assignNewPermission(array $permissionArr , Permission $permission)
	{

		if(in_array($this->getRoleName(),$permissionArr['default-roles']) && $this->hasAccessToSystems($permissionArr['systems'])   
						){
							$this->givePermissionTo($permission->name);
						}
	}	
	public static function getUsersWithRoles(?Company $company,?string $roleName = null)
	{
		return static::getUsersWithRolesQuery($company, $roleName)->get();
	}

	/**
	 * Same visibility rules as getUsersWithRoles(), left unfetched so the
	 * caller can paginate. Every filter here was already a whereHas, so
	 * paging costs nothing in fidelity — the role gates still run in SQL.
	 *
	 * `companies` is eager-loaded alongside `roles` because the users list
	 * renders each user's companies; without it that column was one extra
	 * query per row.
	 */
	public static function getUsersWithRolesQuery(?Company $company,?string $roleName = null)
	{
		$authUser = auth()->user();
		return User::with(['roles', 'companies'])->when($company,function($q) use ($company,$authUser,$roleName){
			$q
			->whereHas('companies',function($q) use($company){
				$q->where('companies.id',$company->id);
			})
			->whereHas('roles',function($q) use ($authUser){
				if(!$authUser->can('view managers')){
					$q->where(function($q) use ($authUser){
						$q->where('roles.id','!=',4)->orWhere('users.id','=',$authUser->id);
					});
				}
				if(!$authUser->can('view company admin')){
					$q->where(function($q) use ($authUser){
						$q->where('roles.id','!=',2)->orWhere('users.id','=',$authUser->id);
					});
				}
				if(!$authUser->can('view super admin')){
					$q->where(function($q) use ($authUser){
						$q->where('roles.id','!=',1)->orWhere('users.id','=',$authUser->id);
					});
				}
				if(!$authUser->can('view users')){
					$q->where(function($q) use ($authUser){
						$q->where('roles.id','!=',3)->orWhere('users.id','=',$authUser->id);
					});
				}
			})->when($roleName,function($q) use ($roleName){
				$q->whereHas('roles',function($q) use ($roleName){
					$q->where('roles.name',$roleName);
				});
			})->whereHas('companies',function($q) use ($company){
				$q->where('companies.id',$company->id);
			});
		})
		->orderBy('users.id');
	}
	public function getOdooDBUserName()
    {
        return $this->odoo_username;
    }
    public function getOdooDBPassword()
    {
        return $this->odoo_db_password;
    }
	public function getOdooId():?int
    {
        return $this->odoo_id ;
    }
}
