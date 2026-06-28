<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class RemoveUsercontroller extends Controller
{

    public function __invoke(Request $request)
    {
        $user_id = $request->get('user_id') ;
     
        $user = User::where('id',$user_id)->firstOrFail();
            $user->delete();

       return response()->json([
           'status'=>true 
       ]);

    }
}
