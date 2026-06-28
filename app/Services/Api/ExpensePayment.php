<?php 
namespace App\Services\Api;

use App\Services\Api\Traits\AuthTrait;
use Exception;
use Illuminate\Support\Facades\Log;

class ExpensePayment
{
	
	use AuthTrait;

    public function getExpenseAccount($expenseSheetId)
    {
        try {
            Log::info("Odoo: Fetching expense account for sheet ID {$expenseSheetId}");

            // Fetch expense lines for the given expense sheet
            $expenseLines = $this->fetchData(
                'hr.expense',
                ['account_id'],
                [[['sheet_id', '=', $expenseSheetId]]]
            );

            if (empty($expenseLines)) {
                Log::warning("Odoo: No expense lines found for sheet ID {$expenseSheetId}");
                return [
                    'success' => false,
                    'message' => "No expense lines found for sheet ID {$expenseSheetId}",
                    'account_id' => null
                ];
            }

            // Get the account_id from the first expense line (assuming all lines use the same account)
            $accountId = is_array($expenseLines[0]['account_id']) && !empty($expenseLines[0]['account_id'][0]) 
                ? $expenseLines[0]['account_id'][0] 
                : null;

            if (!$accountId) {
                Log::error("Odoo: No valid account ID found for sheet {$expenseSheetId}");
                return [
                    'success' => false,
                    'message' => "No valid account ID found for sheet {$expenseSheetId}",
                    'account_id' => null
                ];
            }

            // Fetch account details (e.g., code or name) for better context
            $account = $this->fetchData(
                'account.account',
                ['id', 'code', 'name'],
                [[['id', '=', $accountId]]]
            );

            if (empty($account)) {
                Log::warning("Odoo: No account details found for account ID {$accountId}");
                return [
                    'success' => true,
                    'message' => "Account ID found but no account details available",
                    'account_id' => $accountId,
                //    'account_code' => null,
                    'account_name' => null
                ];
            }

            Log::info("Odoo: Successfully fetched expense account for sheet {$expenseSheetId}", ['account' => $account[0]]);
            return [
                'success' => true,
                'message' => "Expense account fetched successfully for sheet {$expenseSheetId}",
                'account_id' => $accountId,
              //  'account_code' => $account[0]['code'],
                'account_name' => $account[0]['name']
            ];
        } catch (Exception $e) {
            Log::error("Odoo getExpenseAccount Error: " . $e->getMessage(), [
                'expenseSheetId' => $expenseSheetId,
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'message' => "Failed to fetch expense account: {$e->getMessage()}",
                'account_id' => null,
               // 'account_code' => null,
                'account_name' => null
            ];
        }
    }

    protected function postDraftPayment($expenseSheetId)
    {
        try {
            Log::info("Odoo: Starting postDraftPayment for sheet ID {$expenseSheetId}");

            // Fetch sheet with payment_mode
            $sheets = $this->fetchData(
                'hr.expense.sheet',
                ['id', 'name', 'state', 'payment_state', 'employee_id', 'total_amount', 'account_move_ids', 'journal_id', 'payment_mode'],
                [[['id', '=', $expenseSheetId]]] 
            ); 
            if (empty($sheets)) {
                return [
                    'success' => false,
                    'message' => "No expense sheet found with ID {$expenseSheetId}",
                    'payment_id' => null
                ];
            }

            $sheet = $sheets[0];
          ///  Log::info("Odoo: Sheet payment_mode", ['sheet_id' => $expenseSheetId, 'payment_mode' => $sheet['payment_mode']]);

            if ($sheet['state'] !== 'approve' || $sheet['payment_state'] !== 'not_paid') {
                // Log::warning("Odoo: Expense sheet {$expenseSheetId} has invalid state or payment_state", [
                //     'state' => $sheet['state'],
                //     'payment_state' => $sheet['payment_state']
                // ]);
                return [
                    'success' => false,
                    'message' => "Expense sheet {$expenseSheetId} is not in state=approve or payment_state=not_paid",
                    'payment_id' => null
                ];
            }

            // Only require partner_id for own_account (employee-paid) expenses
            $employeePartnerId = false;
            if ($sheet['payment_mode'] === 'own_account') {
                $employeeId = is_array($sheet['employee_id']) && !empty($sheet['employee_id'][0]) ? $sheet['employee_id'][0] : false;
                if ($employeeId) {
                    $employee = $this->fetchData(
                        'hr.employee',
                        ['address_home_id', 'name'],
                        [[['id', '=', $employeeId]]]
                    );
                    if (!empty($employee) && isset($employee[0])) {
                        if (!empty($employee[0]['address_home_id']) && is_array($employee[0]['address_home_id'])) {
                            $employeePartnerId = $employee[0]['address_home_id'][0];
                        } else {
                            // Log::info("Odoo: Creating partner for employee {$employee[0]['name']}");
                            $partnerData = [
                                'name' => $employee[0]['name'],
                                'company_id' => $this->company_id
                            ];
                            $partnerId = $this->execute('res.partner', 'create', [$partnerData]);
                            if ($partnerId) {
                                $employeePartnerId = $partnerId;
                                $this->execute(
                                    'hr.employee',
                                    'write',
                                    [[$employeeId], ['address_home_id' => $employeePartnerId]]
                                );
                                Log::info("Odoo: Created partner ID {$employeePartnerId} for employee {$employeeId}");
                            }
                        }
                    }
                    if (!$employeePartnerId) {
                        Log::error("Odoo: No valid partner ID for sheet {$expenseSheetId} (own_account)");
                        return [
                            'success' => false,
                            'message' => "No valid partner ID for sheet {$expenseSheetId} (own_account)",
                            'payment_id' => null
                        ];
                    }
                }
            }

            // Fetch draft payment
            $payments = $this->fetchData(
                'account.payment',
                ['id', 'state', 'amount', 'partner_id', 'journal_id', 'move_id'],
                [[['expense_sheet_id', '=', $expenseSheetId], ['state', '=', 'draft']]]
            );
            if (empty($payments)) {
                Log::warning("Odoo: No draft payment found for sheet {$expenseSheetId}");
                // Create journal entry if missing
                if (empty($sheet['account_move_ids'])) {
                    Log::info("Odoo: Creating journal entry for sheet {$expenseSheetId}");
                    $this->execute('hr.expense.sheet', 'action_sheet_move_create', [[$expenseSheetId]]);
                    $sheetUpdated = $this->fetchData(
                        'hr.expense.sheet',
                        ['account_move_ids'],
                        [[['id', '=', $expenseSheetId]]]
                    );
                    if (empty($sheetUpdated[0]['account_move_ids'])) {
                        Log::error("Odoo: Failed to create journal entry for sheet {$expenseSheetId}");
                        return [
                            'success' => false,
                            'message' => "Failed to create journal entry for sheet {$expenseSheetId}",
                            'payment_id' => null
                        ];
                    }
                }
                // Retry fetching payment
                $payments = $this->fetchData(
                    'account.payment',
                    ['id', 'state', 'amount', 'partner_id', 'journal_id', 'move_id'],
                    [[['expense_sheet_id', '=', $expenseSheetId], ['state', '=', 'draft']]]
                );
                if (empty($payments)) {
                    Log::error("Odoo: No draft payment created for sheet {$expenseSheetId}");
                    return [
                        'success' => false,
                        'message' => "No draft payment found or created for sheet {$expenseSheetId}",
                        'payment_id' => null
                    ];
                }
            }
            $payment = $payments[0];
            if ($payment['amount'] != $sheet['total_amount']) {
                Log::error("Odoo: Payment amount mismatch for sheet {$expenseSheetId}", [
                    'payment_amount' => $payment['amount'],
                    'sheet_amount' => $sheet['total_amount']
                ]);
                return [
                    'success' => false,
                    'message' => "Payment amount mismatch for sheet {$expenseSheetId}",
                    'payment_id' => $payment['id']
                ];
            }

            // Update payment with partner_id only for own_account
            if ($sheet['payment_mode'] === 'own_account' && (empty($payment['partner_id']))) {
                Log::info("Odoo: Updating partner_id for payment {$payment['id']}");
                $this->execute(
                    'account.payment',
                    'write',
                    [[$payment['id']], ['partner_id' => $employeePartnerId]]
                );
            }

            // Post payment
            Log::info("Odoo: Posting payment {$payment['id']} for sheet {$expenseSheetId}");
            $postResult = $this->execute('account.payment', 'action_post', [[$payment['id']]]);
            if (!$postResult) {
                Log::error("Odoo: Failed to post payment {$payment['id']} for sheet {$expenseSheetId}");
                return [
                    'success' => false,
                    'message' => "Failed to post payment {$payment['id']} for sheet {$expenseSheetId}",
                    'payment_id' => $payment['id']
                ];
            }

            // Verify sheet state
            $sheetVerified = $this->fetchData(
                'hr.expense.sheet',
                ['id', 'state', 'payment_state'],
                [[['id', '=', $expenseSheetId]]]
            );
            if (empty($sheetVerified) || $sheetVerified[0]['state'] !== 'done' || $sheetVerified[0]['payment_state'] !== 'paid') {
                Log::error("Odoo: Sheet {$expenseSheetId} state verification failed", ['sheetVerified' => $sheetVerified]);
                return [
                    'success' => false,
                    'message' => "Sheet {$expenseSheetId} state verification failed",
                    'payment_id' => $payment['id']
                ];
            }

            Log::info("Odoo: Successfully posted payment {$payment['id']} for sheet {$expenseSheetId}");
            return [
                'success' => true,
                'message' => "Payment for sheet {$expenseSheetId} posted successfully",
                'payment_id' => $payment['id']
            ];
        } catch (Exception $e) {
            Log::error("Odoo postDraftPayment Error: " . $e->getMessage(), [
                'expenseSheetId' => $expenseSheetId,
                'trace' => $e->getTraceAsString()
            ]);
            return [
                'success' => false,
                'message' => "Failed to post draft payment: {$e->getMessage()}",
                'payment_id' => null
            ];
        }
    }

    public function settleApprovedExpenses($journalId, $paymentMethodId, $date, $expenseSheetId = null)
    {
        try {
            Log::info("Odoo: Starting settleApprovedExpenses", [
                'journalId' => $journalId,
                'paymentMethodId' => $paymentMethodId,
                'date' => $date,
                'expenseSheetId' => $expenseSheetId
            ]);

            // Fetch expense account
            $accountResult = $this->getExpenseAccount($expenseSheetId);
            if (!$accountResult['success']) {
                Log::warning("Odoo: Failed to fetch expense account for sheet {$expenseSheetId}", ['message' => $accountResult['message']]);
                // Continue with payment posting even if account fetch fails, but include in response
            }

            $result = $this->postDraftPayment($expenseSheetId);
            $settledPayment = $result['success'] ? [[
                'expense_sheet_id' => $expenseSheetId,
                'payment_id' => $result['payment_id'],
                'account_id' => $accountResult['account_id'],
          //      'account_code' => $accountResult['account_code'],
                'account_name' => $accountResult['account_name']
            ]] : [];

            return [
                'success' => $result['success'],
                'message' => $result['message'],
                'settled_payments' => $settledPayment,
                'account_result' => $accountResult
            ];
        } catch (Exception $e) {
            Log::error("Odoo settleApprovedExpenses Error: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'expenseSheetId' => $expenseSheetId
            ]);
            return [
                'success' => false,
                'message' => "Failed to settle expenses: {$e->getMessage()}",
                'settled_payments' => [],
                'account_result' => [

                    'success' => false,
                    'expense_sheet_id'=>$expenseSheetId,
                    'message' => "Account fetch not attempted due to error",
                    'account_id' => null,
                  //  'account_code' => null,
                    'account_name' => null
                ]
            ];
        }
    }
}
?>
