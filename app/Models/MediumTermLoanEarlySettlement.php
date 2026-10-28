<?php

namespace App\Models;

use App\Interfaces\Models\IHaveCreditOverdraftStatement;
use App\Traits\Models\HasCreditStatements;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * * السداد المعجل للقرض متوسط الاجل (Early Settlement)
 *
 * * العميل بيسدد اللي باقي من القرض مرة واحدة قبل ميعاده .. البنك بيجمعله ال
 * * principle
 * * المتبقي لحد النهاية (دي القيمة اللي اتفقوا عليها) وبيحط عليه غرامة سداد
 * * معجل. مثال: عشر اقساط كل قسط 100 الف .. سدد اتنين .. البنك قاله هات 800
 * * الف + غرامة .. القرض كدا اتسدد.
 *
 * * الفلوس بتتحرك مرة واحدة بس من الحساب اللي اختاره (صف بالقيمة وصف
 * * بالغرامة) .. وكل الاقساط اللي لسه مفتوحة بتتقفل من غير حركة فلوس لكل
 * * قسط (remaining = 0 وحالتها early_settled). اللي اتدفع قبل كدا (حتى لو
 * * نص قسط) بيفضل زي ما هو لانه حركة نقدية فعلية.
 *
 * * لو اتعمل بالغلط .. التعديل والحذف بيرجعوا كل حاجه لطبيعتها
 * @see self::reverse()
 *
 * @property int $id
 * @property int $company_id
 * @property int $medium_term_loan_id
 * @property string $date
 * @property numeric $amount
 * @property numeric $fee_amount
 * @property int $account_type_id
 * @property string $account_number
 * @property numeric $principle_settled
 * @property numeric $interest_waived
 * @property int|null $first_loan_schedule_id
 * @property-read \App\Models\MediumTermLoan|null $mediumTermLoan
 * @property-read \App\Models\AccountType|null $accountType
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\LoanSchedule> $loanSchedules
 * @mixin \Eloquent
 */
class MediumTermLoanEarlySettlement extends Model implements IHaveCreditOverdraftStatement
{
	use HasCreditStatements;

	protected $guarded = ['id'];

	/**
	 * * نفس انواع الحسابات اللي ينفع يتدفع منها مصروف: حساب جاري او اي نوع
	 * * اوفردرافت .. كلهم في نفس بنك القرض وبنفس عملته
	 */
	public static function getAllowedAccountTypes()
	{
		return AccountType::onlyCashAccounts()->get();
	}

	/**
	 * * بيقفل كل الاقساط المفتوحة على القرض وبينزل حركة الفلوس.
	 * * لازم يتنده جوه
	 * * DB::transaction
	 */
	public static function settle(MediumTermLoan $mediumTermLoan, string $date, float $amount, float $feeAmount, AccountType $accountType, string $accountNumber): self
	{
		$preview = $mediumTermLoan->getEarlySettlementPreview();

		/**
		 * @var self $earlySettlement
		 */
		$earlySettlement = self::create([
			'company_id' => $mediumTermLoan->company_id,
			'medium_term_loan_id' => $mediumTermLoan->id,
			'date' => $date,
			'amount' => $amount,
			'fee_amount' => $feeAmount,
			'account_type_id' => $accountType->id,
			'account_number' => $accountNumber,
			'principle_settled' => $preview['remaining_principle'],
			'interest_waived' => $preview['remaining_interest'],
			'first_loan_schedule_id' => $preview['first_loan_schedule_id'],
		]);

		/**
		 * * التريجر بتاع
		 * * loan_schedules
		 * * هو اللي بيحط الحالة
		 * * early_settled
		 * * طالما العمود دا مش فاضي
		 */
		LoanSchedule::whereIn('id', $preview['loan_schedule_ids'])->update([
			'medium_term_loan_early_settlement_id' => $earlySettlement->id,
			'remaining' => 0,
		]);

		$mediumTermLoan->update(['status' => MediumTermLoan::EARLY_SETTLED]);

		$earlySettlement->postStatements($mediumTermLoan, $accountType);

		return $earlySettlement;
	}

	/**
	 * * صف بالقيمة وصف بالغرامة على نفس الحساب (زي كسر الوديعة) علشان الكشف
	 * * يفرق بينهم
	 */
	protected function postStatements(MediumTermLoan $mediumTermLoan, AccountType $accountType): void
	{
		$companyId = $this->company_id;
		$financialInstitutionId = $mediumTermLoan->getFinancialInstitutionId();
		$loanName = $mediumTermLoan->getName();
		/**
		 * * نفس نوع الحركة اللي المصروفات بتبعته .. تريجرز الاوفردرافت بتحسب
		 * * منه ميعاد استحقاق السحبة
		 */
		$moneyType = MoneyPayment::OUTGOING_TRANSFER;

		$this->handleCreditStatement(
			$companyId, $financialInstitutionId, $accountType, $this->getAccountNumber(), $moneyType,
			$this->getDate(), (float) $this->getAmount(), null, null,
			__('Early Settlement For Loan :name', ['name' => $loanName], 'en'),
			__('Early Settlement For Loan :name', ['name' => $loanName], 'ar')
		);

		if ($this->getFeeAmount() > 0) {
			$this->handleCreditStatement(
				$companyId, $financialInstitutionId, $accountType, $this->getAccountNumber(), $moneyType,
				$this->getDate(), (float) $this->getFeeAmount(), null, null,
				__('Early Settlement Fees For Loan :name', ['name' => $loanName], 'en'),
				__('Early Settlement Fees For Loan :name', ['name' => $loanName], 'ar')
			);
		}

		/**
		 * * لو القرض كان بيتدفع منه فواتير (اتسحب منه فعلا) يبقي ال
		 * * principle
		 * * اللي اتقفل بينزل
		 * * debit
		 * * على حساب القرض نفسه .. فا المسحوب بيرجع وال
		 * * room
		 * * بيرجع يزيد .. زي ما بيحصل مع سداد القسط العادي
		 * @see LoanScheduleSettlement::handleMediumTermLoanRepayment()
		 */
		if ($mediumTermLoan->isNotConsumedYet() && $mediumTermLoan->hasDrawdowns() && $this->getPrincipleSettled() > 0) {
			$this->mediumTermLoanBankStatements()->create([
				'medium_term_loan_id' => $mediumTermLoan->id,
				'company_id' => $companyId,
				'type' => MediumTermLoanBankStatement::EARLY_SETTLEMENT,
				'date' => $this->getDate(),
				'limit' => $mediumTermLoan->getLimit(),
				'beginning_balance' => 0,
				'debit' => $this->getPrincipleSettled(),
				'credit' => 0,
				'interest_amount' => 0,
				'comment_en' => __('Early Settlement For Loan :name', ['name' => $loanName], 'en'),
				'comment_ar' => __('Early Settlement For Loan :name', ['name' => $loanName], 'ar'),
			]);
		}
	}

	/**
	 * * عكس السداد المعجل بالكامل: بيمسح حركات الفلوس وبيرجع كل قسط لحالته
	 * * قبل السداد المعجل (remaining بيتحسب تاني من السدادات العادية اللي
	 * * عليه) والقرض بيرجع
	 * * running .
	 * * لازم يتنده جوه
	 * * DB::transaction
	 */
	public function reverse(): void
	{
		$this->deleteAllRelations();

		/**
		 * * فك الربط بيتحفظ مع ال
		 * * remaining
		 * * في نفس ال
		 * * update
		 * * فا التريجر بيحسب الحالة الاصلية مرة واحدة
		 */
		$this->loanSchedules()->get()->each(function (LoanSchedule $loanSchedule) {
			$loanSchedule->medium_term_loan_early_settlement_id = null;
			$loanSchedule->recalculateRemaining();
		});

		$this->mediumTermLoan?->update(['status' => MediumTermLoan::RUNNING]);

		$this->delete();
	}

	public function deleteAllRelations(): void
	{
		CurrentAccountBankStatement::deleteButTriggerChangeOnLastElement($this->currentAccountCreditBankStatements);
		CleanOverdraftBankStatement::deleteButTriggerChangeOnLastElement($this->cleanOverdraftCreditBankStatements);
		FullySecuredOverdraftBankStatement::deleteButTriggerChangeOnLastElement($this->fullySecuredOverdraftCreditBankStatements);
		OverdraftAgainstCommercialPaperBankStatement::deleteButTriggerChangeOnLastElement($this->overdraftAgainstCommercialPaperCreditBankStatements);
		OverdraftAgainstAssignmentOfContractBankStatement::deleteButTriggerChangeOnLastElement($this->overdraftAgainstAssignmentOfContractCreditBankStatements);
		MediumTermLoanBankStatement::deleteButTriggerChangeOnLastElement($this->mediumTermLoanBankStatements);
	}

	public function mediumTermLoan(): BelongsTo
	{
		return $this->belongsTo(MediumTermLoan::class, 'medium_term_loan_id', 'id');
	}
	public function accountType(): BelongsTo
	{
		return $this->belongsTo(AccountType::class, 'account_type_id', 'id');
	}
	/**
	 * * الاقساط اللي اتقفلت بالسداد المعجل دا
	 */
	public function loanSchedules(): HasMany
	{
		return $this->hasMany(LoanSchedule::class, 'medium_term_loan_early_settlement_id', 'id');
	}

	/**
	 * * العلاقات ال
	 * * hasOne
	 * * دي بتستخدمها
	 * * HasCreditStatements::handleCreditStatement()
	 * * علشان تنزل الحركة .. وال
	 * * hasMany
	 * * مرتبة من الكبير للصغير بال
	 * * full_date
	 * * علشان
	 * * deleteButTriggerChangeOnLastElement
	 */
	public function currentAccountCreditBankStatement(): HasOne
	{
		return $this->hasOne(CurrentAccountBankStatement::class, 'medium_term_loan_early_settlement_id', 'id')->where('is_credit', 1);
	}
	public function currentAccountCreditBankStatements(): HasMany
	{
		return $this->hasMany(CurrentAccountBankStatement::class, 'medium_term_loan_early_settlement_id', 'id')->where('is_credit', 1)->orderBy('full_date', 'desc');
	}
	public function cleanOverdraftCreditBankStatement(): HasOne
	{
		return $this->hasOne(CleanOverdraftBankStatement::class, 'medium_term_loan_early_settlement_id', 'id');
	}
	public function cleanOverdraftCreditBankStatements(): HasMany
	{
		return $this->hasMany(CleanOverdraftBankStatement::class, 'medium_term_loan_early_settlement_id', 'id')->orderBy('full_date', 'desc');
	}
	public function fullySecuredOverdraftCreditBankStatement(): HasOne
	{
		return $this->hasOne(FullySecuredOverdraftBankStatement::class, 'medium_term_loan_early_settlement_id', 'id');
	}
	public function fullySecuredOverdraftCreditBankStatements(): HasMany
	{
		return $this->hasMany(FullySecuredOverdraftBankStatement::class, 'medium_term_loan_early_settlement_id', 'id')->orderBy('full_date', 'desc');
	}
	public function overdraftAgainstCommercialPaperCreditBankStatement(): HasOne
	{
		return $this->hasOne(OverdraftAgainstCommercialPaperBankStatement::class, 'medium_term_loan_early_settlement_id', 'id');
	}
	public function overdraftAgainstCommercialPaperCreditBankStatements(): HasMany
	{
		return $this->hasMany(OverdraftAgainstCommercialPaperBankStatement::class, 'medium_term_loan_early_settlement_id', 'id')->orderBy('full_date', 'desc');
	}
	public function overdraftAgainstAssignmentOfContractCreditBankStatement(): HasOne
	{
		return $this->hasOne(OverdraftAgainstAssignmentOfContractBankStatement::class, 'medium_term_loan_early_settlement_id', 'id');
	}
	public function overdraftAgainstAssignmentOfContractCreditBankStatements(): HasMany
	{
		return $this->hasMany(OverdraftAgainstAssignmentOfContractBankStatement::class, 'medium_term_loan_early_settlement_id', 'id')->orderBy('full_date', 'desc');
	}
	public function mediumTermLoanBankStatements(): HasMany
	{
		return $this->hasMany(MediumTermLoanBankStatement::class, 'medium_term_loan_early_settlement_id', 'id')->orderBy('full_date', 'desc');
	}
	/**
	 * * موجودة بس علشان ال
	 * * IHaveCreditOverdraftStatement
	 * * بيطلبها .. السداد المعجل عمره ما بيتدفع من الخزنة
	 * * (isCashPayment = false) فا عمرها ما بتتنده
	 */
	public function cashInSafeCreditStatement(): HasOne
	{
		return $this->hasOne(CashInSafeStatement::class, 'medium_term_loan_early_settlement_id', 'id');
	}
	public function isCashPayment(): bool
	{
		return false;
	}

	public function getDate()
	{
		return $this->date;
	}
	public function getDateFormatted()
	{
		$date = $this->getDate();
		return $date ? Carbon::make($date)->format('d-m-Y') : __('N/A');
	}
	public function getAmount()
	{
		return $this->amount ?: 0;
	}
	public function getFeeAmount()
	{
		return $this->fee_amount ?: 0;
	}
	public function getPrincipleSettled()
	{
		return $this->principle_settled ?: 0;
	}
	public function getInterestWaived()
	{
		return $this->interest_waived ?: 0;
	}
	public function getAccountNumber()
	{
		return $this->account_number;
	}
	/**
	 * * الـ 3 دول بيستخدمهم
	 * * MoneyReceivedController::updateNetBalanceBasedOnAccountNumber()
	 * * في وضع التعديل علشان يرجّع اللي اتخصم قبل كدا للرصيد المتاح
	 */
	public function getAccountTypeId()
	{
		return $this->account_type_id;
	}
	public function getPaidAmount()
	{
		return $this->getAmount() + $this->getFeeAmount();
	}
}
