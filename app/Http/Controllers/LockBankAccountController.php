<?php

namespace App\Http\Controllers;

use App\Models\AccountType;
use App\Models\Company;
use Illuminate\Http\RedirectResponse;

class LockBankAccountController extends Controller
{
    private const LOCKABLE_MODELS = [
        'FinancialInstitutionAccount',
        'CleanOverdraft',
        'FullySecuredOverdraft',
        'OverdraftAgainstCommercialPaper',
        'OverdraftAgainstAssignmentOfContract',
        'CertificatesOfDeposit',
        'TimeOfDeposit',
    ];

    public function lockOrUnlock(Company $company, AccountType $accountType, int $accountId): RedirectResponse
    {
        $modelName = $accountType->getModelName();

        if (! in_array($modelName, self::LOCKABLE_MODELS, true)) {
            abort(404);
        }

        $modelClass = '\App\Models\\'.$modelName;
        $account = $modelClass::where('company_id', $company->id)->findOrFail($accountId);

        $account->is_active = (int) (! $account->isActive());
        $account->save();

        return redirect()->back()->with('success', __('Item Has Been Updated Successfully'));
    }
}
