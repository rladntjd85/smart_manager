<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    // 대량 할당 허용 필드
    protected $fillable = ['name'];

    /**
     * 상품과의 1:N 관계 정의
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
