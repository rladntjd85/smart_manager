FROM php:8.3-fpm

# 1. 필수 라이브러리 설치
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libicu-dev \
    zip \
    unzip \
    git \
    curl \
    nginx \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd pdo pdo_mysql bcmath zip intl

# 2. Composer 복사
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 3. 작업 디렉토리 설정 및 소스 복사
WORKDIR /var/www
COPY . .

# 4. [핵심] 권한 및 폴더 설정 최적화
# - 필요한 모든 하위 디렉토리를 미리 생성합니다.
# - storage/app/public과 livewire-tmp는 업로드에 필수입니다.
# 1. 모든 필수 폴더 생성 및 권한 설정
RUN mkdir -p /var/www/storage/app/public \
             /var/www/storage/app/livewire-tmp \
             /var/www/storage/framework/cache \
             /var/www/storage/framework/sessions \
             /var/www/storage/framework/views \
             /var/www/bootstrap/cache \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 777 /var/www/storage /var/www/bootstrap/cache

# 2. PHP 용량 제한 설정 (2.6MB 에러 해결용)
RUN echo "display_errors=On" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "display_startup_errors=On" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "error_reporting=E_ALL" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "log_errors=On" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "error_log=/dev/stderr" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini \
    && echo "upload_max_filesize=20M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=20M" >> /usr/local/etc/php/conf.d/uploads.ini

# 5. 의존성 설치 (권한 설정 이후 수행)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 6. 라라벨 최적화 및 심볼릭 링크 설정
# - storage:link는 파일 업로드 후 브라우저 노출을 위해 필수입니다.
RUN php artisan storage:link \
    && php artisan config:clear \
    && php artisan route:clear \
    && php artisan view:clear \
    && php artisan cache:clear

# 7. 포트 설정 및 실행
EXPOSE 8080

# Cloud Run 환경에서 가장 안정적인 artisan serve 방식 유지
CMD php artisan serve --host=0.0.0.0 --port=8080
