<?php

namespace App\Http\Controllers;

use App\Helpers\HArr;
use App\Helpers\HAuth;
use App\Models\Company;
use App\Models\User;
use App\Traits\ImageSave;
use Auth;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;

class UserController extends Controller
{
	use ImageSave;
	public function __construct()
	{
		$this->middleware(['can:view users'])->only(['index']);
	}
	public function freeSubscription(Request $request)
	{
		if ($request->isMethod('POST')) {
			$this->validate($request, [

				'name' => ['required', 'string', 'max:255'],
				'company_name' => 'required',
				'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
				'avatar' =>  'required',
				'company_avatar' =>  'required',
				'password' => ['required', 'string', 'min:8', 'confirmed'],
			]);

			$request['password'] = Hash::make($request->password);

			$dt = Carbon::parse(date('Y-m-d'));
			$expiration_date = $dt->addDays(15)->format('Y-m-d');
			$user = User::create($request->except('avatar'));
			$user->subscription = 'free_trial';
			$user->expiration_date = $expiration_date;
			$user->save();

			ImageSave::saveIfExist('avatar', $user);

			$companySection = Company::create(['name' => $request->company_name]);
			ImageSave::saveIfExist('company_avatar', $companySection);

			$user->companies()->attach($companySection->id);
			
			
			$user->assignRole('user');

		app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
		app()->make(\Spatie\Permission\PermissionRegistrar::class)->clearClassPermissions();
		$permissions = HAuth::getPermissions();
		foreach ($permissions as $permissionArr) {
			// if($permission !='view sales forecast quantity base'){
				$permission =Permission::findByName($permissionArr['name']);;
				$user->assignNewPermission($permissionArr,$permission);
			// }
		}
			Auth::login($user, $remember = true);

			return redirect()->route('home');
		} else {
			return view('free_subscription.form');
		}
	}

	
	public function index(?Company $company = null)
	{

		$users = collect([]);
		$authUser = Auth()->user() ;
		/**
		 * @var \App\Models\User $authUser;
		 */
		$users = User::getUsersWithRoles($company);

		return view('super_admin_view.users.index', compact('users','company'));
	}


	public function create(?Company $company = null)
	{

		$companies = Company::all();
		if($company){
			$companies = Company::where('id',$company->id)->get();
		}
		return view('super_admin_view.users.form', compact('companies','company'));
	}

	
	public function store(Request $request)
	{
		$user = Auth()->user();
		/**
		 * @var User $user
		 */
		$request->validate([
			'email'=>'unique:users,email'
		]);
		if (!$user->canStoreMoreUser()) {
			return redirect()->back()->with('fail', __('You Exceed Your Max Users [ ' . $user->max_users . ' ]'));
		}
		$request['password'] = Hash::make($request->password);
		$request['subscription'] = 'subscripted';

		$user = User::create(
			array_merge(
				$request->except('avatar', 'companies'),
				['created_by'=>Auth()->user()->id]
			),
		);
		$user->companies()->attach($request->companies);
		$user->assignRole($request->role);
		/**
		 * @var User $user
		 */
		app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
		app()->make(\Spatie\Permission\PermissionRegistrar::class)->clearClassPermissions();
		$permissions = HAuth::getPermissions($user->getSystemsNames());
		foreach ($permissions as $permissionArr) {
			$permission = Permission::findByName($permissionArr['name']);
			$user->assignNewPermission($permissionArr,$permission);
		}

		ImageSave::saveIfExist('image', $user);

		return redirect()->back();
	}

	
	public function show($id)
	{

	}

	
	public function edit(User $user,?Company $company = null)
	{
		$companies = Company::all();
		return view('super_admin_view.users.form', compact('companies', 'user','company'));
	}

	
	public function update(Request $request, User $user)
	{
		// $request['password'] = Hash::make($request->password);
		$user->update($request->except('avatar', 'companies'));
		$user->companies()->sync($request->companies);
		@count($user->roles) == 0 ?: $user->removeRole($user->roles[0]->name);

		$user->assignRole($request->role);
		ImageSave::saveIfExist('avatar', $user);

		return redirect()->back();
	}

	
	public function destroy($id)
	{

	}
	public function getUsersBasedOnCompanyAndRole(Request $request){
		$roleName = $request->input('roleName');
		$companyId = $request->input('companyId');
		$company = Company::find($companyId);
		$users = User::getUsersWithRoles($company,$roleName);
		return response()->json([
			'users'=>$users
		]);
	}
	public function renderPermissionForUser(Request $request)
	{
		$user = User::find($request->get('userId'));
		$permissionViews = view('super_admin_view.roles_and_permissions.permissions-radio',['user'=>$user])->render();
		return response()->json([
			'view'=>$permissionViews
		]);
	}
}
