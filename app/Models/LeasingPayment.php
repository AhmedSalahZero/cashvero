<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The relation row of a "Through Leasing" money payment — the sibling of
 * OutgoingTransfer / PayableCheque / CashPayment.
 *
 * Deliberately tiny: this money type asks for a Leasing Company and a
 * Contract and nothing else. There is no bank, no account type and no
 * account number, because the paying party is the leasing company, not
 * the company's own bank. See the migration header for the full shape.
 *
 * @property int $id
 * @property int $money_payment_id
 * @property int $leasing_company_id
 * @property int $leasing_contract_id
 * @property int $company_id
 * @property string|null $actual_payment_date
 * @property-read \App\Models\MoneyPayment|null $moneyPayment
 * @property-read \App\Models\LeasingCompany|null $leasingCompany
 * @property-read \App\Models\LeasingContract|null $leasingContract
 * @mixin \Eloquent
 */
class LeasingPayment extends Model
{
    protected $guarded = ['id'];

    public function moneyPayment(): BelongsTo
    {
        return $this->belongsTo(MoneyPayment::class, 'money_payment_id');
    }

    public function leasingCompany(): BelongsTo
    {
        return $this->belongsTo(LeasingCompany::class, 'leasing_company_id', 'id');
    }

    public function leasingContract(): BelongsTo
    {
        return $this->belongsTo(LeasingContract::class, 'leasing_contract_id', 'id');
    }

    public function getLeasingCompanyId()
    {
        return $this->leasing_company_id;
    }

    public function getLeasingCompanyName()
    {
        return $this->leasingCompany ? $this->leasingCompany->getName() : __('N/A');
    }

    public function getLeasingContractId()
    {
        return $this->leasing_contract_id;
    }

    public function getLeasingContractName()
    {
        return $this->leasingContract ? $this->leasingContract->getName() : __('N/A');
    }

    /**
     * Mirrors OutgoingTransfer's accessor: the date the money actually
     * reached the supplier. There is no clearing period here, so it is
     * always the payment's delivery date.
     */
    public function actualPaymentDate()
    {
        return $this->actual_payment_date;
    }

    public function actualPaymentDateFormatted()
    {
        return $this->actual_payment_date
            ? Carbon::make($this->actual_payment_date)->format('d-m-Y')
            : '';
    }

    public function setActualPaymentDateAttribute($value)
    {
        $date = explode('/', $value ?? '');

        if (count($date) != 3) {
            $this->attributes['actual_payment_date'] = $value;

            return;
        }

        $this->attributes['actual_payment_date'] = $date[2].'-'.$date[0].'-'.$date[1];
    }
}
