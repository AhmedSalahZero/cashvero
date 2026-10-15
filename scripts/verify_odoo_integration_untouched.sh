#!/usr/bin/env bash
# verify_odoo_integration_untouched.sh
# ------------------------------------------------------------------
# Diff gate for the Odoo integration surface. Pagination (and any
# other non-Odoo work) must leave these paths identical to HEAD —
# or to a commit you pass as $1 — so a silent edit to execute_kw /
# AuthTrait / payment create cannot ride along unnoticed.
#
# Usage:
#   ./scripts/verify_odoo_integration_untouched.sh
#   ./scripts/verify_odoo_integration_untouched.sh HEAD
#   ./scripts/verify_odoo_integration_untouched.sh origin/master
#
# Exit 0 = surface untouched. Exit 1 = unexpected diff.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

BASE="${1:-HEAD}"

PATHS=(
  app/Services/Api
  public/apis
  app/Http/Controllers/ReadOdooPartners.php
  app/Http/Controllers/ReadOdooContracts.php
  app/Http/Controllers/ReadOdooInvoices.php
  app/Http/Controllers/SendOdooCollectionOrPayment.php
  app/Http/Controllers/OdooSettingController.php
  app/OdooSetting.php
  app/Traits/Models/HasOdooMoneyTransfer.php
  app/Traits/Models/HasDeleteOdoo.php
  app/Traits/HasOdooPaymentMethod.php
)

echo "Odoo integration diff gate vs ${BASE}"
echo "------------------------------------"

DIFF="$(git diff "$BASE" -- "${PATHS[@]}")"
STAT="$(git diff "$BASE" --stat -- "${PATHS[@]}")"

if [[ -n "$STAT" ]]; then
  echo "$STAT"
fi

# Adjacent controllers often import Odoo classes for unlink/create on
# unrelated actions. Their pagination hunks must not rewrite execute paths.
ADJACENT=(
  app/Http/Controllers/BankStatementController.php
  app/Http/Controllers/CompanyController.php
  app/Models/User.php
  app/Http/Controllers/LetterOfGuaranteeIssuanceController.php
)
ADJACENT_HITS="$(git diff "$BASE" -- "${ADJACENT[@]}" | grep -E 'execute_kw|new OdooService|new OdooPayment|new CashExpenseOdooService\(|->authenticate\(|action_post|action_create_payments' || true)"

if [[ -z "$DIFF" && -z "$ADJACENT_HITS" ]]; then
  echo "PASS — Odoo integration surface identical to ${BASE}"
  echo "PASS — adjacent pagination diffs contain no Odoo execute paths"
  exit 0
fi

if [[ -n "$DIFF" ]]; then
  echo "FAIL — Odoo integration surface differs from ${BASE}:"
  echo "$DIFF"
fi

if [[ -n "$ADJACENT_HITS" ]]; then
  echo "FAIL — adjacent file diffs touch Odoo execute paths:"
  echo "$ADJACENT_HITS"
fi

exit 1
