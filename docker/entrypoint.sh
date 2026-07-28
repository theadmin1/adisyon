#!/bin/sh
set -eu

php artisan migrate --force
php artisan optimize

exec "$@"
