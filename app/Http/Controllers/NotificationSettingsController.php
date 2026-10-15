<?php
namespace App\Http\Controllers;

use App\Models\Company;
use App\NotificationSetting;
use App\Traits\GeneralFunctions;
use Illuminate\Http\Request;

/**
 * NotificationSettingsController
 * ------------------------------------------------------------------
 * Per-company day thresholds used by notification jobs (coming/past
 * dues for customer & supplier invoices, cheques in safe / receivable
 * / payable). One row per company — upsert via index()/store().
 *
 * Frontend: Vue + Inertia (NotificationSettings/Form.vue). Defaults
 * when no row exists match the original Blade x-form.input defaults
 * (NotificationSetting::* constants).
 */
class NotificationSettingsController
{
    use GeneralFunctions;

    private const FIELDS = [
        'customer_coming_dues_invoices_notifications_days',
        'customer_past_dues_invoices_notifications_days',
        'cheques_in_safe_notifications_days',
        'coming_receivable_cheques_notifications_days',
        'supplier_coming_dues_invoices_notifications_days',
        'supplier_past_dues_invoices_notifications_days',
        'coming_payable_cheques_notifications_days',
    ];

    public function index(Company $company, Request $request)
    {
        $setting = $company->notificationSetting;

        return \Inertia\Inertia::render('NotificationSettings/Form', [
            'company' => ['id' => $company->id],
            'model' => $setting ? collect(self::FIELDS)
                ->mapWithKeys(fn (string $field) => [$field => $setting->{$field}])
                ->all() : null,
            'defaults' => [
                'customer_coming_dues_invoices_notifications_days' => NotificationSetting::CUSTOMER_COMING_DUES_INVOICES_NOTIFICATIONS_DAYS,
                'customer_past_dues_invoices_notifications_days' => NotificationSetting::CUSTOMER_PAST_DUES_INVOICES_NOTIFICATIONS_DAYS,
                'cheques_in_safe_notifications_days' => NotificationSetting::CHEQUES_IN_SAFE_NOTIFICATIONS_DAYS,
                'coming_receivable_cheques_notifications_days' => NotificationSetting::COMING_RECEIVABLE_CHEQUES_NOTIFICATIONS_DAYS,
                'supplier_coming_dues_invoices_notifications_days' => NotificationSetting::SUPPLIER_COMING_DUES_INVOICES_NOTIFICATIONS_DAYS,
                'supplier_past_dues_invoices_notifications_days' => NotificationSetting::SUPPLIER_PAST_DUES_INVOICES_NOTIFICATIONS_DAYS,
                'coming_payable_cheques_notifications_days' => NotificationSetting::COMING_PAYABLE_CHEQUES_NOTIFICATIONS_DAYS,
            ],
            'submitUrl' => route('notifications-settings.store', ['company' => $company->id]),
        ]);
    }

    public function store(Request $request, Company $company)
    {
        $setting = $company->notificationSetting;
        $data = $request->only(self::FIELDS);
        $setting ? $setting->update($data) : $company->notificationSetting()->create($data);

        return redirect()->route('notifications-settings.index', ['company' => $company->id]);
    }

    public function markAsRead(Company $company)
    {
        $company->unreadNotifications->markAsRead();
    }
}
