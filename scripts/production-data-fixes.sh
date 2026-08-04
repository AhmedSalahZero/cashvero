#!/bin/bash
#
# production-data-fixes.sh
# ------------------------------------------------------------------
# بيشغّل إصلاحات البيانات بالترتيب الصح على الإنتاج.
#
# ⚠️ الترتيب هنا مش اختياري — كل خطوة بتعتمد على اللي قبلها:
#
#   ١. contracts:fix-duplicate-codes --fix
#        لازم قبل migrate، لأن هجرة الفهرس الفريد بترمي استثناء لو لسه
#        فيه كود عقد مكرر. ولو ده حصل جوه deploy.sh (اللي فيه set -e
#        و artisan down) الموقع هيفضل في وضع الصيانة.
#
#   ٢. migrate --force
#        بيضيف أعمدة excel_collected_amount / excel_paid_amount
#        والفهرس الفريد على (company_id, code).
#
#   ٣. run:sql
#        بيثبّت التريجرات. لازم بعد migrate لأن تريجرات الفواتير بتشير
#        لأعمدة excel_* — لو اتثبتت قبلها أول insert على الفواتير هيفشل.
#
#   ٤. statements:repair-balances --fix
#        لازم بعد run:sql — الأمر نفسه بيرفض الاشتغال لو التريجرات
#        القديمة (اللي بتسلسل بـ date) لسه متثبتة، عشان ما يكتبش نفس
#        القيم الغلط تاني.
#
#   ٥. debugging:truncate --force
#        تنضيف جدول debugging المتراكم. مستقل، ممكن في أي وقت.
#
# الاستخدام:
#   bash scripts/production-data-fixes.sh --dry     # فحص فقط، لا يكتب (ابدأ بده)
#   bash scripts/production-data-fixes.sh           # تنفيذ فعلي
#   PHP_BIN=/usr/local/bin/ea-php84 bash scripts/production-data-fixes.sh
#
set -euo pipefail

PHP_BIN="${PHP_BIN:-php}"
DRY=0
[[ "${1:-}" == "--dry" ]] && DRY=1

cd "$(dirname "$0")/.."

say()  { printf '\n\033[1;36m>>> %s\033[0m\n' "$1"; }
warn() { printf '\033[1;33m    %s\033[0m\n' "$1"; }
die()  { printf '\n\033[1;31m!!! %s\033[0m\n' "$1" >&2; exit 1; }

[[ -f artisan ]] || die "شغّل السكربت من جوه مجلد المشروع"

# ── ٠. نسخة احتياطية ──────────────────────────────────────────────
say "٠) نسخة احتياطية للجداول المتأثرة"
BACKUP_DIR="storage/app/backups"
mkdir -p "$BACKUP_DIR"
STAMP="$(date +%Y%m%d_%H%M%S)"
BACKUP_FILE="$BACKUP_DIR/pre-data-fixes-$STAMP.sql"

# بنقرا بيانات الاتصال من إعدادات لارافيل نفسها، مش من .env مباشرة،
# عشان أي override في البيئة يتاخد في الاعتبار
read -r DB_H DB_P DB_N DB_U DB_W <<<"$($PHP_BIN -r '
require "vendor/autoload.php"; $a = require "bootstrap/app.php";
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$c = config("database.connections.".config("database.default"));
echo $c["host"]," ",$c["port"]," ",$c["database"]," ",$c["username"]," ",$c["password"];
')"

TABLES="contracts customer_invoices supplier_invoices debugging
  shareholder_statements employee_statements other_partner_statements
  subsidiary_company_statements tax_statements cash_in_safe_statements
  current_account_bank_statements letter_of_credit_statements
  letter_of_credit_cash_cover_statements letter_of_guarantee_statements
  letter_of_guarantee_cash_cover_statements loan_statements"

# shellcheck disable=SC2086
MYSQL_PWD="$DB_W" mysqldump -h"$DB_H" -P"$DB_P" -u"$DB_U" --no-tablespaces \
  --single-transaction --quick "$DB_N" $TABLES > "$BACKUP_FILE" \
  || die "فشل أخذ النسخة الاحتياطية — توقف"

SIZE=$(du -h "$BACKUP_FILE" | cut -f1)
echo "    $BACKUP_FILE  ($SIZE)"
[[ -s "$BACKUP_FILE" ]] || die "ملف النسخة الاحتياطية فاضي — توقف"

if [[ $DRY -eq 1 ]]; then
  say "وضع الفحص — الخطوات الجاية هتتعرض من غير أي كتابة"
fi

# ── ١. أكواد العقود المكررة ───────────────────────────────────────
say "١) أكواد العقود المكررة"
if [[ $DRY -eq 1 ]]; then
  $PHP_BIN artisan contracts:fix-duplicate-codes
else
  $PHP_BIN artisan contracts:fix-duplicate-codes
  warn "لو فوق ظهر أي عقد 'يحتاج مراجعة' اوقف دلوقتي وراجعه يدويًا."
  read -r -p "    تكمّل بالإصلاح؟ [y/N] " ans
  [[ "$ans" =~ ^[Yy]$ ]] || die "اتلغى بناءً على طلبك"
  $PHP_BIN artisan contracts:fix-duplicate-codes --fix
fi

# ── ٢. الهجرات ────────────────────────────────────────────────────
say "٢) الهجرات"
if [[ $DRY -eq 1 ]]; then
  $PHP_BIN artisan migrate:status | tail -20
else
  $PHP_BIN artisan migrate --force
fi

# ── ٣. التريجرات ──────────────────────────────────────────────────
say "٣) تثبيت التريجرات"
if [[ $DRY -eq 1 ]]; then
  echo "    هيتنفّذ: artisan run:sql"
else
  $PHP_BIN artisan run:sql
fi

# ── ٤. أرصدة الكشوف ───────────────────────────────────────────────
say "٤) تسلسل أرصدة الكشوف"
if [[ $DRY -eq 1 ]]; then
  $PHP_BIN artisan statements:repair-balances --samples=2
else
  $PHP_BIN artisan statements:repair-balances --fix
fi

# ── ٥. جدول debugging ─────────────────────────────────────────────
say "٥) تنضيف جدول debugging"
if [[ $DRY -eq 1 ]]; then
  $PHP_BIN artisan debugging:truncate
else
  $PHP_BIN artisan debugging:truncate --force
fi

# ── ٦. التحقق النهائي ─────────────────────────────────────────────
say "٦) التحقق النهائي"
$PHP_BIN artisan contracts:fix-duplicate-codes | tail -3
$PHP_BIN artisan statements:repair-balances | tail -3

say "خلص. النسخة الاحتياطية: $BACKUP_FILE"
if [[ $DRY -eq 1 ]]; then
  warn "ده كان فحص فقط — مفيش أي بيانات اتغيّرت."
fi
