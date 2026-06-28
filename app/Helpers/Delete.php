<?php 
namespace App\Helpers ;
use Illuminate\Http\Request;
class Delete 
{
    public function __invoke(Request $request):array 
    {
        return [
            'age'=>$request->get('age'),
            

        ];
    }
}