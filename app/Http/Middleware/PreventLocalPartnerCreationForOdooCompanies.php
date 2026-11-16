<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;

/**
 * * الشركة المربوطة بأودو ماينفعش تعمل partner من السيستم
 *
 * * الـ partner المحلي بيتعمل من غير odoo_id ، و "Read Odoo Partners"
 * * بيطابق بالـ odoo_id بس ، فعمره ما بيتربط — و أول ما يتبعت لأودو
 * * (قيد خطاب ضمان مثلا) بيضرب
 *
 * * الشرط على مستوى الشركة (hasOdooCredentials) مش اليوزر ، عشان يوزر
 * * من غير بيانات أودو مايعملش partner يكسر مع يوزر تاني بعدين
 */
class PreventLocalPartnerCreationForOdooCompanies
{
    public function handle(Request $request, Closure $next)
    {
        $company = $request->route('company');

        /**
         * * لو الـ binding لسه ماحصلش ، نجيب الشركة بالرقم بدل ما نعدّي الطلب
         */
        if (! $company instanceof Company && is_numeric($company)) {
            $company = Company::find($company);
        }

        if (! $company instanceof Company || ! $company->hasOdooCredentials()) {
            return $next($request);
        }

        $message = __('This company is linked to Odoo. Partners can only be added in Odoo and then loaded with "Read Odoo Partners".');

        /**
         * * طلب Inertia بيبعت X-Requested-With برضه ، فلازم يتعرف الأول :
         * * Inertia مش بيفهم JSON ، و الـ fail بيوصل للصفحة من
         * * HandleInertiaRequests
         *
         * * AJAX العادي بياخد نفس شكل رد AddNewCustomerController
         */
        $isInertia = (bool) $request->header('X-Inertia');

        if (! $isInertia && ($request->ajax() || $request->expectsJson())) {
            return response()->json([
                'status' => false,
                'message' => $message,
            ]);
        }

        return redirect()->back()->with('fail', $message);
    }
}
