<?php
    namespace App\Traits;
    use Illuminate\Support\Facades\Auth;
    // use trait App\Traits\StaticBoot;
trait StaticBoot
{
    /**
     * ⚠️ REAL BUG FIXED HERE (2026-07-24 audit).
     *
     * This previously hardcoded created_by/updated_by to the literal
     * integer 1 on every create/update, regardless of who was actually
     * logged in — meaning every CustomerInvoice, SupplierInvoice,
     * LoanSchedule, ContractLoanSchedule, Company, and Section
     * (the 6 models that use this trait) had its audit trail silently
     * and permanently wrong, always attributed to user ID 1 (a real
     * named person) no matter which of the real users actually
     * performed the action. `Auth` was imported and never used —
     * the strongest sign this was placeholder/debug code, not a
     * deliberate design choice. Confirmed with the project owner
     * (2026-07-24): created_by/updated_by should reflect the real
     * logged-in user going forward.
     *
     * Fixed by using Auth::id() instead. Both columns are nullable
     * on all 6 affected tables (confirmed against the schema), so
     * this is safe for the cases where these models are created
     * outside an authenticated request — e.g. CustomerInvoice rows
     * created by the scheduled Odoo import job (ImportOdooInvoicesJob)
     * run with no logged-in user. In that case Auth::id() correctly
     * returns null, which is honestly "no human did this" rather than
     * falsely attributing a background job's action to a real person.
     *
     * NOTE — historical data: every existing created_by/updated_by
     * value of 1 on these 6 tables predates this fix and is ambiguous
     * (it may be the real user 1, or it may be this bug). This fix
     * only affects records created/updated from this point forward;
     * it does not and cannot retroactively correct historical rows.
     */
    protected static function boot()
    {
        parent::boot();

        static::updating(function ($model) {
            $model->updated_by = Auth::id();
        });

        static::creating(function ($model) {
            $model->created_by = Auth::id();
        });


    }
}
