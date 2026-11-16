<?php

namespace App\Rules;

use App\Models\Company;
use App\Models\LetterOfGuaranteeIssuance;
use App\Models\Partner;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Contracts\Validation\Rule;

/**
 * * المستفيد لازم يكون من نفس الشركة ، و لازم يكون مربوط بأودو (odoo_id)
 * * لو الخطاب هيتبعت لأودو
 *
 * * من غير الفحص ده الخطاب كان بيتحفظ عادي ، و بعد الـ commit
 * * LetterOfGuaranteeService::createLgIssuanceCashCover(int $odooPartnerId)
 * * كان بيضرب TypeError لأن getBeneficiaryOdooId() بترجع null ، فقيود
 * * الكاش كفر و المصاريف و العمولة ماكانتش بتروح أودو
 *
 * * "هيتبعت لأودو ولا لأ" مش مكتوب هنا من الأول : بنبني خطاب مش محفوظ من
 * * قيم الطلب (بنفس الـ mutators اللي storeBasicForm بيشغّلها) و نسأله هو
 * * بنفس الدوال اللي في handleIssuanceAndCommissionFeesForOdoo
 *
 * * من غير شركة في الـ route (رفع الـ Excel بيبني الطلب من غير route)
 * * الـ rule مالوش رأي ، زي LgIssuanceDateMatchesCategoryRule
 */
class BeneficiaryLinkedToOdooRule implements Rule
{
    private string $message = '';

    public function __construct(
        private ?Company $company,
        private ?string $lgType,
        private ?string $categoryName,
        private ?string $issuanceDate,
        private ?string $activationDate,
    ) {}

    /**
     * @param  string  $attribute
     * @param  mixed  $value
     */
    public function passes($attribute, $value): bool
    {
        if (! $this->company) {
            return true;
        }

        /**
         * * find() على array بيرجّع Collection مش partner
         */
        $partner = is_numeric($value)
            ? Partner::where('company_id', $this->company->id)->find($value)
            : null;

        if (! $partner) {
            $this->message = __('The selected beneficiary is invalid.');

            return false;
        }

        if ($partner->getOdooId() || ! $this->willBeSentToOdoo()) {
            return true;
        }

        $this->message = __('The beneficiary :name is not linked to Odoo. Add it in Odoo, run "Read Odoo Partners", then select it again.', [
            'name' => $partner->getName(),
        ]);

        return false;
    }

    public function message(): string
    {
        return $this->message;
    }

    /**
     * * نفس شرط handleIssuanceAndCommissionFeesForOdoo . ده الأوسع : شرط
     * * handleLgIssuanceCashCoverForOdoo هو نفسه + انه مش CD/TD ، و الاتنين
     * * بيبعتوا getBeneficiaryOdooId()
     */
    private function willBeSentToOdoo(): bool
    {
        /**
         * * من غير نوع اصدار getCategoryName() بتضرب TypeError ، و الحفظ
         * * نفسه مش هيكمل ، فمالناش رأي
         */
        if (blank($this->categoryName)) {
            return false;
        }

        $issuance = $this->unsavedIssuance();

        try {
            $movementDate = $issuance->getMovementDate();
        } catch (InvalidFormatException) {
            /**
             * * تاريخ مش مفهوم : الحفظ مش هيكمل بيه ، و التاريخ ليه فحصه
             */
            return false;
        }

        return $issuance->isActivated()
            && $movementDate
            && $this->company->hasOdooIntegrationCredentials()
            && ! $issuance->postsAsOpeningBalance()
            && $this->company->withinIntegrationDate($movementDate);
    }

    /**
     * * الفورم بتبعت "null" للحقل الفاضي ، و storeBasicForm بيحوّلها null
     */
    private function unsavedIssuance(): LetterOfGuaranteeIssuance
    {
        $values = array_filter([
            'lg_type' => $this->lgType,
            'category_name' => $this->categoryName,
            'issuance_date' => $this->issuanceDate,
            'activation_date' => $this->activationDate,
        ], fn (?string $value) => filled($value) && $value !== 'null');

        $issuance = (new LetterOfGuaranteeIssuance())->forceFill($values + ['company_id' => $this->company->id]);
        $issuance->setRelation('company', $this->company);

        return $issuance;
    }
}
