<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Support\Enums\IconSize;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $unclassifiedCount = Product::whereNull('category_id')->count();

        return [
            Stat::make('전체 상품 수', Product::count() . ' 개')
                ->description('DB 내 전체 상품 데이터')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->color('info')
                ->url(route('filament.admin.resources.products.index')),

            Stat::make('AI 분석 완료', Product::whereNotNull('summary')->count() . ' 건')
                ->description('Gemini 2.5 Flash Lite 처리')
                ->descriptionIcon('heroicon-m-cpu-chip')
                ->color('success'),

            Stat::make('미분류 상품', $unclassifiedCount . ' 건')
                ->url(route('filament.admin.resources.products.index', [
                    'tableFilters[category][value]' => null // 클릭 시 카테고리가 없는 상품만 필터링해서 보여줌
                ]))
                ->description($unclassifiedCount > 0 ? '카테고리 매핑이 필요함' : '모든 상품 분류 완료')
                ->descriptionIcon($unclassifiedCount > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($unclassifiedCount > 0 ? 'danger' : 'success'),
        ];
    }
}
