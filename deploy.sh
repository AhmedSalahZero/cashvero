#!/bin/bash
set -e
echo ">>> Maintenance mode ON"
/usr/local/bin/ea-php84 artisan down

echo ">>> Fix Permissions"
chown -R cfostools:cfostools storage bootstrap/cache

echo ">>> Pulling latest code"
git status
git stash
git pull origin master


echo ">>> Installing dependencies"
/usr/local/bin/ea-php84 $(which composer) install --no-interaction --prefer-dist --optimize-autoloader

echo ">>> Clearing cache"
/usr/local/bin/ea-php84 artisan optimize:clear

echo ">>> Fixing permissions"
chmod -R 775 storage
chmod -R 775 bootstrap/cache

echo ">>> Running migrations"
/usr/local/bin/ea-php84 artisan migrate --force

# Permissions are held per user: sync only fills role templates (for future
# users); rollout hands each NEW key, once, to the users who already hold the
# screen it came from. See docs/permissions.md.
echo ">>> Syncing permissions"
/usr/local/bin/ea-php84 artisan permissions:sync

echo ">>> Rolling out new permissions"
/usr/local/bin/ea-php84 artisan permissions:rollout --force

echo ">>> Running Sql"
/usr/local/bin/ea-php84 artisan run:sql

echo ">>> Linking storage"
/usr/local/bin/ea-php84 artisan storage:link

echo ">>> Restarting queue workers"
sudo supervisorctl restart all

echo ">>> Maintenance mode OFF"
/usr/local/bin/ea-php84 artisan up

echo ">>> Deployment finished successfully!"
