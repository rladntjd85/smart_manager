# 1. PHP 8.3 FPM 기반 이미지 사용
FROM php:8.3-fpm

# 2. 필수 리눅스 패키지 및 PHP 확장 설치
# - nginx: 웹 서버
# - lib...: 이미지 처리 및 압축 관련 라이브러리
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
    && docker-php-ext-install gd pdo pdo_mysql bcmath zip intl \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# 3. Composer 설치
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 4. 작업 디렉토리 설정 및 소스 복사
WORKDIR /var/www
COPY . .

# 5. [중요] 필수 디렉토리 생성 및 권한 설정
# - Cloud Run은 쓰기 권한이 엄격하므로 실행 전 미리 권한을 부여합니다.
RUN mkdir -p /var/www/storage/app/public \
             /var/www/storage/app/livewire-tmp \
             /var/www/storage/framework/cache \
             /var/www/storage/framework/sessions \
             /var/www/storage/framework/views \
             /var/www/bootstrap/cache \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# 6. PHP 설정 최적화 (업로드 용량 및 에러 로그)
RUN echo "upload_max_filesize=20M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=20M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "error_log=/dev/stderr" >> /usr/local/etc/php/conf.d/docker-php-ext-xdebug.ini

# 7. Nginx 설정 파일 복사
# - 프로젝트 루트에 nginx.conf가 있어야 합니다.
COPY docker/nginx.conf /etc/nginx/nginx.conf

# 8. 의존성 설치 (Production 최적화)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 9. 라라벨 최적화 및 심볼릭 링크
RUN php artisan storage:link \
    && php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache

# 10. [핵심] 실행 스크립트 복사 및 권한 부여
# - 윈도우 환경에서의 권한 문제를 Docker 빌드 단계에서 강제로 해결합니다.
COPY docker-entrypoint.sh /usr/local/bin/

RUN chmod +x /usr/local/bin/docker-entrypoint.sh && \
    sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh

# 11. 포트 설정 및 실행
EXPOSE 8080
ENTRYPOINT ["docker-entrypoint.sh"]
