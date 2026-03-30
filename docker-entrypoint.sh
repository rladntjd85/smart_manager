#!/bin/sh

# 에러 발생 시 즉시 중단
set -e

echo "Starting PHP-FPM..."
php-fpm -D

echo "Starting Nginx..."
# Nginx를 포그라운드에서 실행하여 컨테이너가 종료되지 않게 함
nginx -g "daemon off;"
