<?php

namespace App\Http\Controllers;

use App\Models\Log;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class LogController extends Controller
{
   public function show()
  {
	if(!Auth()->user()->isSuperAdmin())
	{
		abort(404);
	}
	// $logs = Log::groupBy('user_id')->get();
	$logs=Log::with('user')
	->whereRaw('id IN (select MAX(id) FROM logs GROUP BY user_id)')
	->orderBy('created_at','desc')
	->get();
	
	return view('super_admin_view.logs.index',[
		'logs'=>$logs
	]);
  }
  public function showDetail(User $user){
	if(!Auth()->user()->isSuperAdmin())
	{
		abort(404);
	}
	return view('super_admin_view.logs.user-detail',[
		'logs'=>$user->logs
	]);
	
  }
}
