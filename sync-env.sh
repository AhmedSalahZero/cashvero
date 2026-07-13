#!/bin/bash
# sync-env.sh
set -e

SOURCE_ENV=".env.sync-source"
TARGET_ENV=".env"
IGNORE_FILE="env-ignore-keys.txt"

if [ ! -f "$SOURCE_ENV" ]; then
    echo "❌ Source file $SOURCE_ENV not found"
    exit 1
fi

echo ">>> Syncing .env (excluding ignored keys)..."

# اقرا كل الـ keys الموجودة في المصدر
while IFS= read -r line; do
    # تجاهل السطور الفاضية أو الكومنتات
    [[ -z "$line" || "$line" == \#* ]] && continue

    key=$(echo "$line" | cut -d '=' -f1)
    new_value=$(echo "$line" | cut -d '=' -f2-)

    # لو الـ key موجود في ملف الاستثناء، تخطاه
    if grep -qx "$key" "$IGNORE_FILE"; then
        echo "⏭️  Skipped (ignored): $key"
        continue
    fi

    if grep -q "^${key}=" "$TARGET_ENV"; then
        sed -i "s|^${key}=.*|${key}=${new_value}|" "$TARGET_ENV"
        echo "✓ Updated: $key"
    else
        echo "${key}=${new_value}" >> "$TARGET_ENV"
        echo "✓ Added: $key"
    fi
done < "$SOURCE_ENV"

echo ">>> Env sync completed."
