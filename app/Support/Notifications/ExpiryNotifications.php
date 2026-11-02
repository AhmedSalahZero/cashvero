<?php

namespace App\Support\Notifications;

use App\Enums\LgTypes;
use App\Models\CleanOverdraft;
use App\Models\Company;
use App\Models\FactoringContract;
use App\Models\FullySecuredOverdraft;
use App\Models\LeasingContract;
use App\Models\LetterOfCreditFacility;
use App\Models\LetterOfGuaranteeFacility;
use App\Models\LetterOfGuaranteeIssuance;
use App\Models\MediumTermLoan;
use App\Models\OverdraftAgainstAssignmentOfContract;
use App\Models\OverdraftAgainstCommercialPaper;
use App\Notifications\DueInvoiceNotification;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * * تنبيهات الجرس لخطابات الضمان والتسهيلات اللي قربت تخلص.
 *
 * * بتتبني مع باقي الاشعارات في
 * * CheckDueAndPastedInvoicesJob
 * * (اللي بيمسح اشعارات الشركة ويعيد بنائها كل ما المستخدم يدخل الشركة)
 * * وبتشمل اللي تاريخ انتهاءه <= النهاردة + عدد الايام اللي في اعدادات الاشعارات:
 * *   - خطاب الضمان: 7 ايام افتراضيا ، مقسم على نوع الخطاب
 * *   - التسهيل: 30 يوم افتراضيا ، مقسم على نوع التسهيل
 * * يعني اللي خلص فعلا (ولسه ما اتلغاش / ما اتجددش) بيظهر هو كمان ، علشان
 * * لما العد يوصل صفر او اقل يبقى منتهي ولازم المستخدم يشوفه.
 * * عمود Status بيقول "انتهى من كام يوم" او "هينتهي بعد كام يوم".
 *
 * * التجديد بيحدّث تاريخ الانتهاء على الخطاب/التسهيل نفسه
 * * (renewal_date / contract_end_date) فا مش محتاجين جدول التاريخ.
 */
final class ExpiryNotifications
{
    public const LG_TAP_TYPE = 'letter_of_guarantee';

    public const FACILITY_TAP_TYPE = 'facility';

    public const CLEAN_OVERDRAFT = 'clean_overdraft';
    public const FULLY_SECURED_OVERDRAFT = 'fully_secured_overdraft';
    public const OVERDRAFT_AGAINST_COMMERCIAL_PAPER = 'overdraft_against_commercial_paper';
    public const OVERDRAFT_AGAINST_ASSIGNMENT_OF_CONTRACT = 'overdraft_against_assignment_of_contract';
    public const LG_FACILITY = 'lg_facility';
    public const LC_FACILITY = 'lc_facility';
    public const FACTORING_CONTRACT = 'factoring_contract';
    public const MEDIUM_TERM_LOAN = 'medium_term_loan';
    public const LEASING_CONTRACT = 'leasing_contract';

    /** The bell sub-type (notifications.data.type) of one LG type. */
    public static function lgType(string $lgType): string
    {
        return 'lg_expiry_'.$lgType;
    }

    /** The bell sub-type (notifications.data.type) of one facility type. */
    public static function facilityType(string $facilityType): string
    {
        return 'facility_expiry_'.$facilityType;
    }

    /** @return array<string, string> bell sub-type => title, for every LG type */
    public static function lgTitles(): array
    {
        $titles = [];
        foreach (LgTypes::getAll() as $lgType => $title) {
            $titles[self::lgType($lgType)] = $title;
        }

        return $titles;
    }

    /**
     * Every facility type, in the order the bell lists them.
     *
     * @return array<string, array{model: class-string<Model>, end_date: string, title: string}>
     */
    public static function facilityTypes(): array
    {
        return [
            self::CLEAN_OVERDRAFT => ['model' => CleanOverdraft::class, 'end_date' => 'contract_end_date', 'title' => __('Clean Overdraft')],
            self::FULLY_SECURED_OVERDRAFT => ['model' => FullySecuredOverdraft::class, 'end_date' => 'contract_end_date', 'title' => __('Fully Secured Overdraft')],
            self::OVERDRAFT_AGAINST_COMMERCIAL_PAPER => ['model' => OverdraftAgainstCommercialPaper::class, 'end_date' => 'contract_end_date', 'title' => __('Overdraft Against Commercial Paper')],
            self::OVERDRAFT_AGAINST_ASSIGNMENT_OF_CONTRACT => ['model' => OverdraftAgainstAssignmentOfContract::class, 'end_date' => 'contract_end_date', 'title' => __('Overdraft Against Assignment Of Contract')],
            self::LG_FACILITY => ['model' => LetterOfGuaranteeFacility::class, 'end_date' => 'contract_end_date', 'title' => __('Letter Of Guarantee Facility')],
            self::LC_FACILITY => ['model' => LetterOfCreditFacility::class, 'end_date' => 'contract_end_date', 'title' => __('Letter Of Credit Facility')],
            self::FACTORING_CONTRACT => ['model' => FactoringContract::class, 'end_date' => 'contract_end_date', 'title' => __('Factoring Contract')],
            self::MEDIUM_TERM_LOAN => ['model' => MediumTermLoan::class, 'end_date' => 'end_date', 'title' => __('Medium Term Loan')],
            self::LEASING_CONTRACT => ['model' => LeasingContract::class, 'end_date' => 'end_date', 'title' => __('Leasing Contract')],
        ];
    }

    /** @return array<string, string> bell sub-type => title, for every facility type */
    public static function facilityTitles(): array
    {
        $titles = [];
        foreach (self::facilityTypes() as $facilityType => $config) {
            $titles[self::facilityType($facilityType)] = $config['title'];
        }

        return $titles;
    }

    /**
     * The bell sub-types of the facility types this company holds at least
     * one of — a company with only a clean overdraft sees only that child.
     *
     * @return list<string>
     */
    public static function facilityTypesHeldBy(int $companyId): array
    {
        $held = [];
        foreach (self::facilityTypes() as $facilityType => $config) {
            if ($config['model']::query()->where('company_id', $companyId)->exists()) {
                $held[] = self::facilityType($facilityType);
            }
        }

        return $held;
    }

    public static function notify(Company $company): void
    {
        $today = now()->startOfDay();
        self::notifyExpiringLgs($company, $today, $today->copy()->addDays($company->getLgExpiryNotificationDays()));
        self::notifyExpiringFacilities($company, $today, $today->copy()->addDays($company->getFacilityExpiryNotificationDays()));
    }

    private static function notifyExpiringLgs(Company $company, Carbon $today, Carbon $until): void
    {
        $lgs = LetterOfGuaranteeIssuance::query()
            ->with(['beneficiary', 'financialInstitutionBank'])
            ->where('company_id', $company->id)
            ->where('status', '!=', LetterOfGuaranteeIssuance::CANCELLED)
            // No lower bound: an LG already past its expiry is still shown.
            ->where('renewal_date', '<=', $until->format('Y-m-d'))
            ->orderBy('renewal_date')
            ->get();

        foreach ($lgs as $lg) {
            /** @var LetterOfGuaranteeIssuance $lg */
            if (! isset(LgTypes::getAll()[$lg->getLgType()])) {
                continue;
            }
            $expiryDate = Carbon::make($lg->getRenewalDate())->startOfDay();
            $subject = __('LG', [], 'en').' '.$lg->getLgCode();
            $subjectAr = __('LG', [], 'ar').' '.$lg->getLgCode();

            $company->notify(new DueInvoiceNotification(
                $subject.' — '.self::status($today, $expiryDate, 'en'),
                $subjectAr.' — '.self::status($today, $expiryDate, 'ar'),
                self::lgType($lg->getLgType()),
                self::LG_TAP_TYPE,
                [
                    'Beneficiary Name' => $lg->getBeneficiaryName(),
                    'Bank Name' => $lg->getFinancialInstitutionBankName(),
                    'LG Code' => $lg->getLgCode(),
                    'LG Amount' => number_format((float) $lg->getLgAmount(), 2),
                    // نفس قاعدة التقارير: الخطاب اللي لسه ما اتفعّلش مالوش غطاء
                    'Cash Cover' => number_format($lg->is_activated ? (float) $lg->getCashCoverAmount() : 0, 2),
                    'Expiry Date' => $expiryDate->format('d-m-Y'),
                    'Currency' => $lg->getLgCurrency(),
                    'Status' => self::status($today, $expiryDate),
                ]
            ));
        }
    }

    private static function notifyExpiringFacilities(Company $company, Carbon $today, Carbon $until): void
    {
        foreach (self::facilityTypes() as $facilityType => $config) {
            $model = $config['model'];
            $endDateColumn = $config['end_date'];

            $facilities = $model::query()
                ->where('company_id', $company->id)
                // No lower bound: a facility already past its end date is still shown.
                ->where($endDateColumn, '<=', $until->format('Y-m-d'))
                // القرض اللي اتسدد سداد معجل اتقفل خلاص
                ->when($facilityType === self::MEDIUM_TERM_LOAN, fn ($query) => $query->where('status', '!=', MediumTermLoan::EARLY_SETTLED))
                ->orderBy($endDateColumn)
                ->get();

            foreach ($facilities as $facility) {
                $expiryDate = Carbon::make($facility->{$endDateColumn})->startOfDay();
                $facilityName = self::facilityName($facilityType, $facility);

                $company->notify(new DueInvoiceNotification(
                    $facilityName.' — '.self::status($today, $expiryDate, 'en'),
                    $facilityName.' — '.self::status($today, $expiryDate, 'ar'),
                    self::facilityType($facilityType),
                    self::FACILITY_TAP_TYPE,
                    [
                        'Bank Name' => self::ownerName($facilityType, $facility),
                        'Facility Name' => $facilityName,
                        'Expiry Date' => $expiryDate->format('d-m-Y'),
                        'Limit' => number_format((float) $facility->limit, 2),
                        'Currency' => $facility->currency,
                        'Status' => self::status($today, $expiryDate),
                    ]
                ));
            }
        }
    }

    /**
     * "Expired since 5 days" / "Expires today" / "Expires after 3 days" —
     * one column for every row, since the bell's table takes its headings
     * from the first row's keys.
     */
    private static function status(Carbon $today, Carbon $expiryDate, ?string $locale = null): string
    {
        $days = (int) $today->diffInDays($expiryDate, true);

        if ($expiryDate->lt($today)) {
            return __('Expired since :days days', ['days' => $days], $locale);
        }
        if ($expiryDate->eq($today)) {
            return __('Expires today', [], $locale);
        }

        return __('Expires after :days days', ['days' => $days], $locale);
    }

    /** The bank — or the leasing / factoring company — the facility is with. */
    private static function ownerName(string $facilityType, Model $facility): string
    {
        return match ($facilityType) {
            self::LEASING_CONTRACT => $facility->leasingCompany?->getName() ?? __('N/A'),
            self::FACTORING_CONTRACT => $facility->factoringCompany?->getName() ?? __('N/A'),
            default => $facility->financialInstitution?->getName() ?? __('N/A'),
        };
    }

    /**
     * Overdrafts have no name column — their account number is how every
     * other screen identifies them. Factoring has neither, so it goes by
     * its recourse type, as on the factoring screens.
     */
    private static function facilityName(string $facilityType, Model $facility): string
    {
        if ($facilityType === self::FACTORING_CONTRACT) {
            return $facility->getRecourseTypeLabel();
        }

        return (string) ($facility->name ?? $facility->account_number ?? '') ?: __('N/A');
    }
}
