<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Section;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsController extends Controller
{
    // public function index($scope,Company $company = null)
    // {
    //     $roles = Role::where('scope',$scope)->get();
    //     return view('super_admin_view.roles_and_permissions.index',compact('scope','roles','company'));
    // }
    // public function create($scope)
    // {
    //     $sections = Section::where('section_side','client')->get();
    //     return view('super_admin_view.roles_and_permissions.form',compact('scope','sections'));
    // }
    // public function store(Request $request ,$scope)
    // {
    //     $role = Role::create(['name' => $request->role,'scope' => $scope]);
    //     $role->syncPermissions(array_keys($request->permissions));
    //     toastr()->success(__('Created Successfully'));
    //     return redirect()->back();

    // }
	public function __construct()
	{
		$this->middleware(['can:update permissions'])->only(['edit','update']);
	}
    public function edit($scope,?Company $company=null)
    {
		$companies = $company ? Company::where('id',$company->id)->get() : Company::all();
        $sections = Section::where('section_side','client')->get();
        return view('super_admin_view.roles_and_permissions.form',compact('scope','sections','companies','company'));
    }
    public function update(Request $request )
    {
		$user = User::find($request->get('user_id'));
		$user->syncPermissions(array_keys($request->permissions));
        toastr()->success(__('updated Successfully'));
        return redirect()->back()->withInput();
    }
}
