<?php

namespace App\Console\Commands;

use App\Http\Controllers\BuyOrSellCurrenciesController;
use App\Http\Controllers\CashExpenseController;
use App\Http\Controllers\FactoringWithoutRecourseController;
use App\Http\Controllers\FactoringWithRecourseController;
use App\Http\Controllers\InternalMoneyTransferController;
use App\Http\Controllers\LetterOfCreditIssuanceController;
use App\Http\Controllers\LetterOfGuaranteeIssuanceController;
use App\Http\Controllers\MoneyPaymentController;
use App\Http\Controllers\MoneyReceivedController;
use App\Http\Controllers\MultipleCashExpenseController;
use App\Http\Requests\DeleteMoneyPaymentRequest;
use App\Http\Requests\DeleteMoneyReceivedRequest;
use App\Models\BuyOrSellCurrency;
use App\Models\CashExpense;
use App\Models\Company;
use App\Models\FactoringTransaction;
use App\Models\InternalMoneyTransfer;
use App\Models\LetterOfCreditIssuance;
use App\Models\LetterOfGuaranteeIssuance;
use App\Models\MoneyPayment;
use App\Models\MoneyReceived;
use App\Models\MultipleCashExpense;
use App\Models\User;
use App\Rules\MoneyReceivedCanBeDeletedRule;
use App\Support\Permissions\PermissionResolver;
use App\Support\Permissions\RoutePermissionMap;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use LogicException;
use Throwable;

class DeletePermittedTreasuryTransactionsCommand extends Command
{
    protected $signature = 'treasury:delete-permitted-transactions
        {--company= : Restrict the operation to one company id}
        {--execute : Actually delete eligible records (default is report-only)}';

    protected $description = 'Delete Treasury Transactions that at least one ordinary company user may delete in the UI';

    /**
     * These entries intentionally mirror the delete controls exposed by
     * the Treasury pages. LC settlement transfers have no delete control
     * in their UI, and Foreign Exchange Rates are explicitly out of scope.
     *
     * @return array<string, array{model: class-string<Model>, permission: string, route: string, view_permission: string, view_route: string}>
     */
    private function targets(): array
    {
        return [
            'Money Received' => [
                'model' => MoneyReceived::class,
                'permission' => 'money_received.delete',
                'route' => 'delete.money.receive',
                'view_permission' => 'money_received.view',
                'view_route' => 'view.money.receive',
            ],
            'Money Payment' => [
                'model' => MoneyPayment::class,
                'permission' => 'money_payment.delete',
                'route' => 'delete.money.payment',
                'view_permission' => 'money_payment.view',
                'view_route' => 'view.money.payment',
            ],
            'Cash Expense' => [
                'model' => CashExpense::class,
                'permission' => 'cash_expense.delete',
                'route' => 'delete.cash.expense',
                'view_permission' => 'cash_expense.view',
                'view_route' => 'view.cash.expense',
            ],
            'Multiple Cash Expense' => [
                'model' => MultipleCashExpense::class,
                'permission' => 'multiple_cash_expense.delete',
                'route' => 'multiple-cash-expenses.destroy',
                'view_permission' => 'multiple_cash_expense.view',
                'view_route' => 'multiple-cash-expenses.index',
            ],
            'Internal Money Transfer' => [
                'model' => InternalMoneyTransfer::class,
                'permission' => 'internal_money_transfer.delete',
                'route' => 'internal-money-transfers.destroy',
                'view_permission' => 'internal_money_transfer.view',
                'view_route' => 'internal-money-transfers.index',
            ],
            'Buy Or Sell Currency' => [
                'model' => BuyOrSellCurrency::class,
                'permission' => 'buy_or_sell_currency.delete',
                'route' => 'buy-or-sell-currencies.destroy',
                'view_permission' => 'buy_or_sell_currency.view',
                'view_route' => 'buy-or-sell-currencies.index',
            ],
            'Factoring With Recourse' => [
                'model' => FactoringTransaction::class,
                'permission' => 'factoring_with_recourse.delete',
                'route' => 'factoring.with-recourse.destroy',
                'view_permission' => 'factoring_with_recourse.view',
                'view_route' => 'factoring.with-recourse.index',
            ],
            'Factoring Without Recourse' => [
                'model' => FactoringTransaction::class,
                'permission' => 'factoring_without_recourse.delete',
                'route' => 'factoring.without-recourse.destroy',
                'view_permission' => 'factoring_without_recourse.view',
                'view_route' => 'factoring.without-recourse.index',
            ],
            'Letter Of Guarantee Issuance' => [
                'model' => LetterOfGuaranteeIssuance::class,
                'permission' => 'lg_issuance.delete',
                'route' => 'delete.letter.of.guarantee.issuance',
                'view_permission' => 'lg_issuance.view',
                'view_route' => 'view.letter.of.guarantee.issuance',
            ],
            'Letter Of Credit Issuance' => [
                'model' => LetterOfCreditIssuance::class,
                'permission' => 'lc_issuance.delete',
                'route' => 'delete.letter.of.credit.issuance',
                'view_permission' => 'lc_issuance.view',
                'view_route' => 'view.letter.of.credit.issuance',
            ],
        ];
    }

    public function handle(): int
    {
        $companyId = $this->option('company');
        $execute = (bool) $this->option('execute');

        if ($companyId !== null && ! ctype_digit((string) $companyId)) {
            $this->error('--company must be a numeric company id.');

            return self::FAILURE;
        }

        $this->line($execute ? 'MODE: PREFLIGHT (no records have been changed)' : 'MODE: REPORT ONLY (nothing will be deleted)');
        $this->line('Eligibility requires a non-super-admin user attached to the record company with the matching UI and route permission.');
        $this->line('Reviewed rows, opening-balance rows hidden by the UI, cancelled LGs, paid LCs, LC settlement transfers, and foreign exchange rates are excluded.');

        $companyId = $companyId ? (int) $companyId : null;
        $summary = $this->processRecords(false, $companyId);
        $this->printSummary($summary);

        $errors = array_sum(array_column($summary, 'errors'));
        if ($errors > 0) {
            $this->error("Preflight failed with {$errors} error(s). No records were changed.");

            return self::FAILURE;
        }

        $eligible = array_sum(array_column($summary, 'eligible'));
        if (! $execute) {
            $this->info("Report complete. {$eligible} record(s) are eligible; rerun with --execute to delete them.");

            return self::SUCCESS;
        }

        if ($eligible === 0) {
            $this->info('No eligible records to delete.');

            return self::SUCCESS;
        }

        if (! $this->confirm("Delete these {$eligible} eligible record(s) through their controllers?")) {
            $this->warn('Cancelled; no records were changed.');

            return self::SUCCESS;
        }

        $this->line('MODE: DELETE (eligibility is checked again immediately before each controller call)');
        $summary = $this->processRecords(true, $companyId);
        $this->printSummary($summary);

        $errors = array_sum(array_column($summary, 'errors'));
        if ($errors > 0) {
            $this->error("Finished with {$errors} deletion error(s). See the messages above.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    private function printSummary(array $summary): void
    {
        $this->newLine();
        $this->table(
            ['Transaction type', 'Scanned', 'Eligible', 'Skipped', 'Deleted', 'Errors'],
            array_map(fn (array $row, string $label) => [
                $label,
                $row['scanned'],
                $row['eligible'],
                $row['skipped'],
                $row['deleted'],
                $row['errors'],
            ], $summary, array_keys($summary))
        );
    }

    /**
     * @return array<string, array{scanned: int, eligible: int, skipped: int, deleted: int, errors: int}>
     */
    private function processRecords(bool $execute, ?int $companyId): array
    {
        PermissionResolver::flush();

        $summary = [];
        $companies = [];
        /** @var array<int, array<string, User[]>> $eligibleUsers */
        $eligibleUsers = [];

        foreach ($this->targets() as $label => $target) {
            $this->assertPermissionMap($label, $target);
            $summary[$label] = ['scanned' => 0, 'eligible' => 0, 'skipped' => 0, 'deleted' => 0, 'errors' => 0];

            $query = $target['model']::query()->orderBy('id');
            if ($label === 'Money Received') {
                $query->with('cheque');
            }
            if ($companyId !== null) {
                $query->where('company_id', $companyId);
            }

            $query->chunkById(100, function ($records) use (
                $label,
                $target,
                $execute,
                &$summary,
                &$companies,
                &$eligibleUsers
            ): void {
                foreach ($records as $record) {
                    $summary[$label]['scanned']++;
                    $recordCompanyId = (int) $record->getAttribute('company_id');
                    $company = $companies[$recordCompanyId] ??= Company::find($recordCompanyId);

                    if (! $company) {
                        $summary[$label]['skipped']++;
                        $this->warn("{$label} #{$record->getKey()}: skipped because company #{$recordCompanyId} no longer exists.");

                        continue;
                    }

                    if (! $this->visibleDeleteAction($label, $record, $company)) {
                        $summary[$label]['skipped']++;

                        continue;
                    }

                    $actor = $this->eligibleUsers($company, $target, $eligibleUsers)[0] ?? null;
                    if (! $actor) {
                        $summary[$label]['skipped']++;

                        continue;
                    }

                    if ($label === 'Money Received') {
                        if (! $record instanceof MoneyReceived) {
                            throw new LogicException('Money Received target resolved to an unexpected model.');
                        }

                        if (! $this->moneyReceivedCanBeDeleted($record, $company)) {
                            $summary[$label]['skipped']++;
                            $this->line("{$label} #{$record->getKey()}: skipped by the controller's balance-safety validation.");

                            continue;
                        }
                    }

                    $summary[$label]['eligible']++;
                    if (! $execute) {
                        continue;
                    }

                    try {
                        $this->destroyAsUser($label, $record, $company, $actor);
                        if ($record->exists) {
                            throw new LogicException('The controller returned without deleting the transaction.');
                        }
                        $summary[$label]['deleted']++;
                    } catch (Throwable $exception) {
                        $summary[$label]['errors']++;
                        $this->error("{$label} #{$record->getKey()}: deletion failed: {$exception->getMessage()}");
                    }
                }
            });
        }

        return $summary;
    }

    /**
     * @param array{model: class-string<Model>, permission: string, route: string, view_permission: string, view_route: string} $target
     */
    private function assertPermissionMap(string $label, array $target): void
    {
        $mapped = RoutePermissionMap::for($target['route']);
        $viewMapped = RoutePermissionMap::for($target['view_route']);
        if ($mapped !== [$target['permission']] || $viewMapped !== [$target['view_permission']]) {
            throw new LogicException(
                "{$label} routes do not map to the expected view and delete permissions."
            );
        }
    }

    /**
     * @param array<int, array<string, User[]>> $cache
     * @param-out array<int, array<string, User[]>> $cache
     * @param array{model: class-string<Model>, permission: string, route: string, view_permission: string, view_route: string} $target
     * @return User[]
     */
    private function eligibleUsers(Company $company, array $target, array &$cache): array
    {
        $companyId = (int) $company->id;
        $permission = $target['permission'];

        if (isset($cache[$companyId][$permission])) {
            return $cache[$companyId][$permission];
        }

        /** @var \Illuminate\Database\Eloquent\Collection<int, User> $companyUsers */
        $companyUsers = $company->users()
            ->with(['roles', 'permissions'])
            ->orderBy('users.id')
            ->get();

        $users = $companyUsers->filter(function (User $user) use ($permission, $target): bool {
            return ! PermissionResolver::isSuperAdmin($user)
                && ! $user->AccountExpired()
                && PermissionResolver::allows($user, $target['view_permission'])
                && PermissionResolver::allows($user, $permission);
        })
            ->values()
            ->all();

        return $cache[$companyId][$permission] = $users;
    }

    private function visibleDeleteAction(string $label, Model $record, Company $company): bool
    {
        if (method_exists($record, 'isReviewed') && $record->isReviewed()) {
            return false;
        }

        if ($label === 'Money Received') {
            if (! $record instanceof MoneyReceived) {
                throw new LogicException('Money Received target resolved to an unexpected model.');
            }

            if ($record->isCheque() && $record->cheque?->isInSafe()) {
                return true;
            }

            if ($record->isCheque()) {
                return $record->cheque?->isRejected() && ! $record->isOpenBalance();
            }

            return in_array($record->getType(), [
                MoneyReceived::INCOMING_TRANSFER,
                MoneyReceived::CASH_IN_SAFE,
                MoneyReceived::CASH_IN_BANK,
            ], true);
        }

        if ($label === 'Money Payment' || $label === 'Cash Expense') {
            if (! $record instanceof MoneyPayment && ! $record instanceof CashExpense) {
                throw new LogicException("{$label} target resolved to an unexpected model.");
            }

            if ($record->isOpenBalance()) {
                return false;
            }
        }

        if ($label === 'Multiple Cash Expense' && $company->hasOdooCredentials()) {
            return false;
        }

        if (in_array($label, ['Factoring With Recourse', 'Factoring Without Recourse'], true)) {
            if (! $record instanceof FactoringTransaction) {
                throw new LogicException("{$label} target resolved to an unexpected model.");
            }

            $requiredType = $label === 'Factoring With Recourse'
                ? FactoringTransaction::WITH_RECOURSE
                : FactoringTransaction::WITHOUT_RECOURSE;

            return $record->recourse_type === $requiredType;
        }

        if ($label === 'Letter Of Guarantee Issuance') {
            if (! $record instanceof LetterOfGuaranteeIssuance) {
                throw new LogicException('Letter Of Guarantee Issuance target resolved to an unexpected model.');
            }

            return ! $record->isCancelled();
        }

        if ($label === 'Letter Of Credit Issuance') {
            if (! $record instanceof LetterOfCreditIssuance) {
                throw new LogicException('Letter Of Credit Issuance target resolved to an unexpected model.');
            }

            return ! $record->isPaid();
        }

        return true;
    }

    private function moneyReceivedCanBeDeleted(MoneyReceived $record, Company $company): bool
    {
        return (new MoneyReceivedCanBeDeletedRule($record, $company))->passes('net_balance', null);
    }

    private function destroyAsUser(string $label, Model $record, Company $company, User $actor): void
    {
        $previousUser = Auth::user();
        Auth::setUser($actor);

        try {
            if ($label === 'Money Received' && $record instanceof MoneyReceived) {
                app(MoneyReceivedController::class)->destroy($company, $record, new DeleteMoneyReceivedRequest());
            } elseif ($label === 'Money Payment' && $record instanceof MoneyPayment) {
                app(MoneyPaymentController::class)->destroy($company, $record, new DeleteMoneyPaymentRequest());
            } elseif ($label === 'Cash Expense' && $record instanceof CashExpense) {
                app(CashExpenseController::class)->destroy($company, $record);
            } elseif ($label === 'Multiple Cash Expense' && $record instanceof MultipleCashExpense) {
                app(MultipleCashExpenseController::class)->destroy($company, $record);
            } elseif ($label === 'Internal Money Transfer' && $record instanceof InternalMoneyTransfer) {
                app(InternalMoneyTransferController::class)->destroy($company, $record->getType(), $record);
            } elseif ($label === 'Buy Or Sell Currency' && $record instanceof BuyOrSellCurrency) {
                app(BuyOrSellCurrenciesController::class)->destroy($company, $record);
            } elseif ($label === 'Factoring With Recourse' && $record instanceof FactoringTransaction) {
                app(FactoringWithRecourseController::class)->destroy($company, $record);
            } elseif ($label === 'Factoring Without Recourse' && $record instanceof FactoringTransaction) {
                app(FactoringWithoutRecourseController::class)->destroy($company, $record);
            } elseif ($label === 'Letter Of Guarantee Issuance' && $record instanceof LetterOfGuaranteeIssuance) {
                app(LetterOfGuaranteeIssuanceController::class)->destroy($company, $record);
            } elseif ($label === 'Letter Of Credit Issuance' && $record instanceof LetterOfCreditIssuance) {
                app(LetterOfCreditIssuanceController::class)->destroy($company, $record);
            } else {
                throw new LogicException("Unsupported Treasury transaction model for {$label}.");
            }
        } finally {
            if ($previousUser) {
                Auth::setUser($previousUser);
            } else {
                Auth::forgetGuards();
            }
        }
    }
}
