<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'category_id', 'raw_text', 'stock', 'price', 'status',
        'summary', 'tags', 'image_path', 'image_match_status', 'image_match_message',
        'user_id'
    ];

    /**
     * AI 분석 데이터(JSON)를 PHP 배열로 자동 변환
     */
    protected $casts = [
        'tags' => 'array',  // DB의 JSON을 배열로 변환하여 배지(Badge) 출력 가능하게 함
        'price' => 'integer',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function specs(): HasMany
    {
        // ProductSpec 모델이 존재한다고 가정합니다.
        return $this->hasMany(ProductSpec::class);
    }
}
