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
        // 카테고리 테이블
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // 상품 테이블 (비정형 데이터 포함)
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained();
            $table->string('name');
            $table->unsignedInteger('price')->default(0);
            $table->text('raw_text'); // Gemini가 분석할 원본 데이터
            $table->integer('stock')->default(0);
            $table->timestamps();
        });

        // 상세 스펙 테이블 (엔티티 레이어 강조용 Key-Value 구조)
        Schema::create('product_specs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->onDelete('cascade');
            $table->string('key');   // 예: 전압, 무게
            $table->string('value'); // 예: 220V, 1.5kg
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products_tables');
    }
};
