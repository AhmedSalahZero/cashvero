<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

class DeletingClass
{
    public function truncate(Company $company, $model)
    {
        $model_name = 'App\\Models\\' . $model;
        $model_obj = new $model_name();
        $all_model_data = $model_obj->company()->get();
        if (count($all_model_data) > 0) {
            $all_model_data->each->delete();
           
        }

        toastr()->success('All Rows Were Deleted  Successfully');

        return redirect()->back();
    }

    public function multipleRowsDeleting(Request $request, Company $company, $model)
    {

        if ($request->rows === null || count($request->rows) == 0) {
            toastr()->error('No Rows Were Selected');

            return redirect()->back();
        }

        $model_name = 'App\\Models\\' . $model;
        $model_obj = new $model_name();
        $all_model_data = null ;
        if ($request->has('delete_date_from')) {
			$deleteColumnName = method_exists($model_obj,'getDeleteByDateColumnName') ?  $model_obj->getDeleteByDateColumnName() : 'date';
            $all_model_data = $model_obj->company()->whereBetween($deleteColumnName, [$request->get('delete_date_from'), $request->get('delete_date_to')])->get();
        } elseif ($request->has('delete_serial_from')) {
            $all_model_data = $model_obj->company()->get()->filter(function ($element, $index) use ($request) {
                return $index + 1 >= $request->get('delete_serial_from') && $index + 1 <= $request->get('delete_serial_to') ;
            });
        } else {
            $all_model_data = $model_obj->company()->whereIn('id', is_array($request->rows) ? $request->rows : [$request->rows])->get();
        }
        if (count($all_model_data) > 0) {
            $all_model_data->each->delete();
        }
      
        if ($request->ajax()) {
            return response()->json([
                'status' => true
            ]);
        }
        toastr()->success('Deleted Selected Rows Successfully');

        return redirect()->back();
    }
}
