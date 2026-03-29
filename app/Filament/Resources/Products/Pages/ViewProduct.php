<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use Filament\Actions;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label('목록으로')
                ->color('gray')
                ->icon('heroicon-m-arrow-left') // 아이콘 추가 시 가독성 상승
                ->url(static::getResource()::getUrl('index')),

            EditAction::make(),
        ];
    }
}
