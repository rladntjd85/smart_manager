<?php

namespace App\Filament\Exports;

use App\Models\Product;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class ProductExporter extends Exporter
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('name')
                ->label('상품명'),

            // [포폴 포인트] 가격에 '원'을 붙이거나 천 단위 콤마 추가
            ExportColumn::make('price')
                ->label('판매가')
                ->formatStateUsing(fn ($state) => number_format($state) . '원'),

            // [포폴 포인트] 등록일을 읽기 쉬운 한글 포맷으로 변경
            ExportColumn::make('created_at')
                ->label('등록일자')
                ->formatStateUsing(fn ($state) => $state->format('Y-m-d')),

            // DB에 없는 '상태' 컬럼을 로직으로 생성
            ExportColumn::make('status')
                ->label('판매상태')
                ->state(function (Product $record): string {
                    return $record->price > 0 ? '판매중' : '품절/가격미정';
                }),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your product export has completed and ' . Number::format($export->successful_rows) . ' ' . str('row')->plural($export->successful_rows) . ' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' ' . Number::format($failedRowsCount) . ' ' . str('row')->plural($failedRowsCount) . ' failed to export.';
        }

        return $body;
    }

    public function getXlsxHeaderStyle(): ?\OpenSpout\Common\Entity\Style\Style
    {
        return (new \OpenSpout\Common\Entity\Style\Style())
            ->setFontBold()
            ->setFontSize(12)
            ->setBackgroundColor('FFFF00'); // 노란색 배경
    }
}
