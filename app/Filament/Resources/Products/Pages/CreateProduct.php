<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * 상품(부모)이 DB에 생성된 직후 호출됨
     */
    protected function afterCreate(): void
    {
        $product = $this->record; // 방금 생성된 상품 객체

        // ProductResource의 Hidden 필드나 State에서 specs 데이터를 가져옴
        // (서비스가 리턴한 $result['specs']를 temp_specs 등에 담았다고 가정)
        $specs = json_decode($this->data['temp_specs'] ?? '[]', true);

        if (!empty($specs)) {
            foreach ($specs as $key => $value) {
                $product->specs()->create([
                    'key' => (string) $key,
                    'value' => (string) $value,
                ]);
            }
        }
    }
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id(); // 현재 로그인한 유저 ID 강제 삽입
        return $data;
    }
}
