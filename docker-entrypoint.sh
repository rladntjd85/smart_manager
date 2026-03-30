#!/bin/sh

# 1. PHP-FPM을 백그라운드에서 실행 (-D 옵션)
php-fpm -D

# 2. Nginx를 포그라운드에서 실행 (컨테이너가 종료되지 않게 유지)
nginx -g "daemon off;"
