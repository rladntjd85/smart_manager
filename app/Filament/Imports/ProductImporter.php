<?php

namespace App\Filament\Imports;

use App\Models\Product;
use App\Models\ProductSpec;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Facades\DB;
use App\Jobs\AnalyzeProductWithVertexAI;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;

class ProductImporter extends Importer
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            // 1. 부모 테이블(products)용 컬럼 매핑
            ImportColumn::make('name')
                ->label('상품명')
                ->requiredMapping()
                ->rules(['required', 'max:255']),

            ImportColumn::make('price')
                ->label('판매가')
                ->rules(['nullable']),

            ImportColumn::make('created_at')
                ->label('등록일자'), // CSV: '등록일자'

            ImportColumn::make('status')
                ->label('판매상태'), // CSV: '판매상태'

//            ImportColumn::make('stock')
//                ->label('재고')
//                ->rules(['integer', 'min:0']),
//
//            // 엑셀에 'image_name.jpg' 형태로 기입한다고 가정
//            ImportColumn::make('image_path')
//                ->label('이미지 파일명')
//                ->rules(['nullable', 'max:255']),
//
//            // 2. [핵심] 자식 테이블(product_specs)용 가상 컬럼
//            // 엑셀의 '색상' 컬럼 데이터를 specs 테이블에 넣기 위해 잠시 받습니다.
//            ImportColumn::make('spec_color')
//                ->label('스펙_색상')
//                ->rules(['nullable']),
        ];
    }

    public function resolveRecord(): ?Product
    {
        // 1. 기존 레코드를 찾거나 새로 생성
        $product = $this->record ?? new Product();

        // 2. 한글 라벨 키값으로 데이터 매핑 (label을 '상품명'으로 했으므로 키도 '상품명')
        $product->fill([
            'name' => $this->data['상품명'] ?? '미지정 상품',
            'category_id' => 1,
            'user_id' => auth()->id() ?? 1,
            'raw_text' => $this->data['상품명'] ?? '',
            'status' => $this->data['판매상태'] ?? null,
        ]);

        // 3. 가격 숫자만 추출 (세탁 로직은 아주 좋습니다)
        $rawPrice = preg_replace('/[^0-9]/', '', $this->data['판매가'] ?? '0');
        $product->price = (int) ($rawPrice ?: 0);

        return $product;
    }

    protected function afterSave(): void
    {
        $product = $this->record;
        $data = $this->data;

        if (!empty($this->data['판매상태'])) {
            \App\Models\ProductSpec::updateOrCreate(
                ['product_id' => $this->record->id, 'key' => 'status'],
                ['value' => $this->data['판매상태']]
            );
        }

        // DB 트랜잭션으로 안전하게 저장
        DB::transaction(function () use ($product, $data) {
            // 색상 정보가 있다면 specs 테이블에 저장
            if (!empty($data['color'])) {
                ProductSpec::updateOrCreate(
                    ['product_id' => $product->id, 'key' => 'color'],
                    ['value' => $data['color']]
                );
            }

            // 사이즈 정보가 있다면 specs 테이블에 저장
            if (!empty($data['size'])) {
                ProductSpec::updateOrCreate(
                    ['product_id' => $product->id, 'key' => 'size'],
                    ['value' => $data['size']]
                );
            }
        });

        \Log::emergency("저장 완료! 상품 ID: " . $this->record->id);
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your product import has completed and ' . Number::format($import->successful_rows) . ' ' . str('row')->plural($import->successful_rows) . ' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to import.';
        }

        return $body;
    }

    protected function afterMapping(): void
    {

    }
}
