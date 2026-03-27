<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductSpec extends Model
{
    protected $fillable = ['product_id', 'key', 'value'];

    // 상품과의 역관계 설정 (선택 사항)
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
