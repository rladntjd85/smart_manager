# --- 1단계: Node.js를 이용한 Assets 빌드 (Vite/Mix 대응) ---
# 필라멘트와 라라벨의 CSS/JS를 컴파일하기 위해 Node.js 환경이 필요합니다.
FROM node:20-alpine AS asset_builder
WORKDIR /app
COPY . .
# 의존성 설치 및 빌드 (public/build 폴더 생성)
RUN npm install && npm run build

# --- 2단계: PHP 8.3 FPM 기반 최종 서버 이미지 ---
FROM php:8.3-fpm

# 필수 리눅스 패키지 및 PHP 확장 설치
# - gd: 이미지 처리 (상품 이미지 등)
# - pdo_mysql: MySQL 연결
# - zip, intl: 라라벨 및 필라멘트 필수 확장
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

# Composer 설치
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# 작업 디렉토리 설정
WORKDIR /var/www

# 전체 소스 복사
COPY . .

# [핵심] 1단계에서 빌드된 정적 파일(CSS/JS)을 최종 위치로 가져옵니다.
# 이 과정이 없으면 사이트 디자인이 깨집니다.
COPY --from=asset_builder /app/public/build /var/www/public/build

# 필수 디렉토리 생성 및 권한 부여 (www-data 유저 권한)
RUN mkdir -p /var/www/storage/app/public \
             /var/www/storage/framework/cache \
             /var/www/storage/framework/sessions \
             /var/www/storage/framework/views \
             /var/www/bootstrap/cache \
    && chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache \
    && chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# PHP 설정 최적화 (업로드 용량 등)
RUN echo "upload_max_filesize=32M" > /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size=32M" >> /usr/local/etc/php/conf.d/uploads.ini

# Nginx 설정 파일 복사
COPY docker/nginx.conf /etc/nginx/nginx.conf

# PHP 의존성 설치 (최적화 모드)
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 라라벨 저장소 심볼릭 링크 생성
RUN php artisan storage:link

# 실행 스크립트 복사 및 윈도우 줄바꿈 제거
COPY docker-entrypoint.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/docker-entrypoint.sh && \
    sed -i 's/\r$//' /usr/local/bin/docker-entrypoint.sh

# 포트 설정
EXPOSE 8080

# 최종 실행: PHP-FPM을 띄우고 Nginx를 실행합니다.
CMD php-fpm -D && sleep 2 && nginx -g "daemon off;"
