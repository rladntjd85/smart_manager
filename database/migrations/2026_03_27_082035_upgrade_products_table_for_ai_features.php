<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            // 1. 상품 요약 (길 수 있으므로 text)
            $table->text('summary')->nullable()->after('name');

            // 2. 검색 키워드 태그 (JSON 배열로 저장)
            $table->json('tags')->nullable()->after('summary');

            // 3. 이미지 매칭 결과 (성공/실패/경고 상태 및 메시지)
            $table->string('image_match_status')->default('pending')->after('tags'); // pending, success, warning, fail
            $table->text('image_match_message')->nullable()->after('image_match_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['summary', 'tags', 'image_match_status', 'image_match_message']);
        });
    }
};
