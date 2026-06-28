<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Section;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class UsersAndPermissionsController extends Controller
{
    public function index($scope)
    {
        $roles = Role::where('scope',$scope)->get();
        return view('super_admin_view.roles_and_permissions.index',compact('scope','roles'));
    }
    public function create($scope)
    {
        $sections = Section::where('section_side','client')->get();
        return view('super_admin_view.roles_and_permissions.form',compact('scope','sections'));
    }
    public function store(Request $request ,$scope)
    {
        $role = Role::create(['name' => $request->role,'scope' => $scope]);
        $prev_name = '';
        $name_arr = [];
        $sections = Section::where('section_side','client')->get();
        $permissions = array_keys($request->permissions);
        $name_of_permissions = [
            'view',
            'create',
            'edit',
            'delete',
        ];
      
        $role->syncPermissions(array_keys($request->permissions));
        toastr()->success(__('Created Successfully'));
        return redirect()->back();
    }
    public function edit(User $user , ?Company $company = null)
    {
        return view('super_admin_view.users_permissions.form',compact('user','company'));
    }
    public function update(Request $request,user $user )
    {
		$user->syncPermissions(array_keys((array)$request->permissions));
        toastr()->success(__('updated Successfully'));
        return redirect()->back();
    }
}
