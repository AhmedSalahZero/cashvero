<?php
namespace App\Http\Controllers;
use App\Models\Company;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;

class NotificationSettingsController
{
    use GeneralFunctions;
	public function index(Company $company,Request $request)
	{
        return view('notification-settings.form', [
			'company'=>$company,
			'model'=>$company->notificationSetting
		]);
    }
	public function store(Request $request, Company $company){
		$setting = $company->notificationSetting;
		$data = $request->except(['_token']);
		$setting ? $setting->update($data) :$company->notificationSetting()->create($data) ;
		return redirect()->route('notifications-settings.index',['company'=>$company->id]);
	}
	public function markAsRead(Company $company)
	{
		$company->unreadNotifications->markAsRead();
	}
}
