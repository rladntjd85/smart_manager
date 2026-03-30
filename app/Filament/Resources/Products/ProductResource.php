<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Filament\Exports\ProductExporter;
use App\Models\Product;
use App\Filament\Imports\ProductImporter;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;

use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\ImageEntry;

// 지원 클래스들은 그대로 유지될 수 있습니다.
use Filament\Support\Enums\FontWeight;
use Filament\Infolists\Components\TextEntry;

use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DatePicker;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Actions\ExportAction;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\ImportAction;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ImageColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
// ------------------------------------
use Filament\Notifications\Notification;

use App\Services\ProductAnalysisService;


class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // Components\ 를 모두 제거하고 직접 호출합니다.
            Section::make('AI 상품 분석 엔진')
                ->schema([
                    Textarea::make('raw_text')
                        ->label('상품 원문 데이터')
                        ->rows(8)
                        ->hintAction(
                            Action::make('analyzeContent')
                                ->label('Gemini AI로 분석하기')
                                ->icon('heroicon-m-sparkles')
                                ->color('success')
                                ->action(function ($get, $set) {
                                    $rawContent = $get('raw_text');
                                    if (blank($rawContent)) return;

                                    $service = app(ProductAnalysisService::class);
                                    $result = $service->analyzeAndSave($rawContent);

                                    if ($result) {
                                        $set('name', $get('name') ?: $result['name']);
                                        $set('category_id', $result['category_id']);
                                        $set('price', $result['price'] ?? 0);
                                        $set('summary', $result['summary'] ?? '');
                                        $set('tags', $result['tags'] ?? []);

                                        // products 테이블의 price 컬럼과 매핑된 필드에 주입
                                        $set('temp_specs', json_encode($result['specs'] ?? []));

                                        Notification::make()
                                            ->title('분석 완료!')
                                            ->body('상품 정보가 필드에 자동 입력되었습니다.')
                                            ->success()
                                            ->send();
                                    }
                                })
                        ),
                ]),

            Section::make('AI 상품 이미지 분석')
                ->schema([
                    FileUpload::make('image_path')
                        ->label('상품 상세 이미지 업로드')
                        ->image()
                        ->disk('gcs')
                        ->directory('product-analysis')
                        ->visibility('public')
                        ->live()
                        ->hintAction(
                            Action::make('analyzeImage')
                                ->label('이미지 분석하기')
                                ->icon('heroicon-m-photo')
                                ->color('warning')
                                ->requiresConfirmation()
                                ->action(function ($get, $set) {
                                    $imagePath = $get('image_path');
                                    if (!$imagePath) {
                                        Notification::make()->title('이미지가 없습니다.')->warning()->send();
                                        return;
                                    }

                                    $fullPath = null;
                                    $tempPath = null;

                                    // 1. GCS 또는 임시 파일 처리 (배열 구조 완벽 대응)
                                    if ($imagePath instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                                        $fullPath = $imagePath->getRealPath();
                                    } else {
                                        // Filament 특유의 배열 구조에서 실제 파일 경로 추출
//                                        $actualPath = is_array($imagePath) ? (array_key_first($imagePath) ?: reset($imagePath)) : $imagePath;
                                        if (is_array($imagePath)) {
                                            $actualPath = collect($imagePath)->first();
                                        } else {
                                            $actualPath = $imagePath;
                                        }

                                        $disk = \Illuminate\Support\Facades\Storage::disk('gcs');
                                        if ($actualPath && $disk->exists($actualPath)) {
                                            $tempPath = tempnam(sys_get_temp_dir(), 'gcs_ai_');
                                            stream_copy_to_stream(
                                                $disk->readStream($actualPath),
                                                fopen($tempPath, 'w')
                                            );
                                            $fullPath = $tempPath;
                                        }
                                    }

                                    if (!$fullPath || !file_exists($fullPath)) {
                                        Notification::make()->title('파일 준비 실패')->danger()->send();
                                        return;
                                    }

                                    try {
                                        // 2. 서비스 호출 및 데이터 주입
                                        $result = app(ProductAnalysisService::class)->analyzeImage($fullPath);

                                        if ($result) {
                                            $set('name', $get('name') ?: data_get($result, 'name'));
                                            $set('price', $get('price') ?: data_get($result, 'price'));
                                            $set('category_id', $get('category_id') ?: data_get($result, 'category_id'));
                                            $set('summary', data_get($result, 'summary'));
                                            $set('tags', data_get($result, 'tags', []));
                                            $set('temp_specs', json_encode(data_get($result, 'specs', [])));
                                            $set('image_match_status', data_get($result, 'image_match_status', 'success'));
                                            $set('image_match_message', data_get($result, 'image_match_message'));
                                            $set('raw_text', "[AI 이미지 분석]\n" . data_get($result, 'summary'));

                                            Notification::make()->title('이미지 분석 성공')->success()->send();
                                        } else {
                                            Notification::make()->title('분석 결과가 비어있습니다.')->warning()->send();
                                        }
                                    } catch (\Exception $e) {
                                        Log::error("Resource 분석 에러: " . $e->getMessage());
                                        Notification::make()->title('에러 발생')->body($e->getMessage())->danger()->send();
                                    } finally {
                                        // 임시 파일 즉시 삭제
                                        if ($tempPath && file_exists($tempPath)) {
                                            unlink($tempPath);
                                        }
                                    }
                                })
                        ),
                ]),

            Section::make('기본 정보')
                ->schema([
                    TextInput::make('name')->required(),
                    TextInput::make('summary')->label('요약'),
                    \Filament\Forms\Components\TagsInput::make('tags')
                        ->label('검색 키워드')
                        ->placeholder('엔터로 입력')
                        ->separator(','),
                    Select::make('category_id')
                        ->relationship('category', 'name')
                        ->required(),
                    TextInput::make('price')
                        ->label('가격')
                        ->numeric()
                        ->prefix('₩')
                        ->default(0),
                    TextInput::make('stock')
                        ->label('재고')
                        ->numeric()
                        ->default(0)
                        ->required(),

                    // [추가] 분석된 스펙 데이터를 임시로 저장할 히든 필드
                    \Filament\Forms\Components\Hidden::make('temp_specs'),
                ])->columns(2),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([ // v5에서는 schema() 대신 components()를 최상위에서 주로 사용합니다.
                // 메인 섹션: 상품명, 요약, 태그
                Section::make('AI 분석 핵심 요약')
                    ->schema([
                        TextEntry::make('name')
                            ->label('상품명')
                            ->weight(FontWeight::Bold)
                            ->size('lg'),

                        // 1. 요약(Summary) 표시
                        TextEntry::make('summary')
                            ->label('AI 한 줄 요약')
                            ->color('primary')
                            ->icon('heroicon-m-sparkles'),

                        // 2. 태그(Tags) 표시
                        TextEntry::make('tags')
                            ->label('검색 키워드 (SEO)')
                            ->badge()
                            ->color('success')
                            ->separator(','),
                    ]),

                Grid::make(2)
                    ->schema([
                        // 왼쪽 컬럼
                        Group::make([
                            Section::make('기본 정보')
                                ->schema([
                                    TextEntry::make('category.name')->label('카테고리'),
                                    TextEntry::make('price')->label('가격')->money('KRW'),
                                    TextEntry::make('stock')->label('재고')->numeric(),
                                ]),

                            Section::make('상세 스펙')
                                ->schema([
                                    RepeatableEntry::make('specs')
                                        ->label('상세 스펙')
                                        ->schema([
                                            TextEntry::make('key')->weight(FontWeight::Bold),
                                            TextEntry::make('value'),
                                        ])
                                        ->columns(2),
                                ]),
                        ]),

                        // 오른쪽 컬럼
                        Group::make([
                            Section::make('상품 이미지 및 AI 검수')
                                ->schema([
                                    ImageEntry::make('image_path')
                                        ->label('등록된 상세 이미지')
                                        ->disk('gcs')
                                        ->defaultImageUrl('https://placehold.jp/24/333333/ffffff/400x300.png?text=No%20Image%20Found')
                                        ->extraImgAttributes([
                                            'style' => 'width: 100%; height: auto; object-fit: contain;',
                                        ])
                                        ->extraAttributes([
                                            'style' => 'max-height: 600px; overflow-y: auto; border: 1px solid #333; padding: 5px; border-radius: 8px;',
                                            'class' => 'custom-image-scroll', // 필요시 커스텀 클래스 추가
                                            'onerror' => "https://placehold.jp/24/333333/ffffff/400x300.png?text=No%20Image%20Found",
                                        ])
                                        ->action(
                                            Action::make('viewOriginalImage')
                                                ->label('원본 이미지 보기')
                                                ->modalHeading('상세 원본 이미지')
                                                ->modalContent(function ($record) {
                                                    // 이미지가 존재하면 GCS URL을, 없으면 대체(No Image) URL을 할당하여 Null 에러를 방지합니다.
                                                    $imageUrl = $record->image_path
                                                        ? \Illuminate\Support\Facades\Storage::disk('gcs')->url($record->image_path)
                                                        : 'https://placehold.jp/24/333333/ffffff/400x300.png?text=No%20Image%20Found';

                                                    return new \Illuminate\Support\HtmlString("
                                                        <div style='text-align: center;'>
                                                            <img src='{$imageUrl}' style='max-width: 100%; height: auto; border-radius: 8px;'>
                                                        </div>
                                                    ");
                                                })
                                                ->modalSubmitAction(false)
                                                ->modalCancelAction(false)
                                                ->modalWidth('7xl')
                                        ),

                                    // 3. 이미지 매칭 결과 표시
                                    TextEntry::make('image_match_status')
                                        ->label('이미지 정합성 상태')
                                        ->badge()
                                        ->color(fn(string $state): string => match ($state) {
                                            'success' => 'success',
                                            'warning' => 'warning',
                                            'fail' => 'danger',
                                            default => 'gray',
                                        })
                                        ->formatStateUsing(fn(?string $state): string => strtoupper($state ?? 'PENDING')),

                                    TextEntry::make('image_match_message')
                                        ->label('검수 상세 메시지')
                                        ->hidden(fn($record) => $record->image_match_status === 'success')
                                        ->color('danger'),
                                ]),
                        ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('이미지')
                    ->disk('gcs') // [필수] 조회/업로드와 동일한 디스크 설정
                    ->visibility('public')
                    ->imageSize(40) // 썸네일 크기 (픽셀 단위, 기본 40)
                    ->circular() // 원형으로 보여주고 싶다면 추가 (선택 사항)
                    ->defaultImageUrl('https://placehold.jp/24/333333/ffffff/100x100.png?text=No%20Img')
                    ->extraImgAttributes([
                        'onerror' => "this.src='https://placehold.jp/24/333333/ffffff/100x100.png?text=Error'; this.onerror=null;",
                    ]),
                // 1. 상품명 표시
                TextColumn::make('name')
                    ->label('상품명')
                    ->searchable()
                    ->sortable(),

                // 2. 카테고리 표시 (관계 설정 시)
                TextColumn::make('category.name')
                    ->label('카테고리'),

                // 3. 재고 표시
                TextColumn::make('stock')
                    ->label('재고')
                    ->numeric(),

                // [추가] 검색 키워드(SEO) 컬럼
                TextColumn::make('tags')
                    ->label('검색 키워드')
                    ->badge()
                    ->searchable()
                    ->separator(','),

                // 4. 생성일 표시 (한국 시간 확인용)
                TextColumn::make('created_at')
                    ->label('등록일')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([
                ExportAction::make()
                    ->exporter(ProductExporter::class)
                    ->label('엑셀 다운로드'),
                ImportAction::make()
                    ->importer(ProductImporter::class) // 위에서 가져온 임포터 클래스 연결
                    ->label('엑셀 업로드')
                    ->icon('heroicon-o-arrow-up-tray') // 아이콘 (선택 사항)
                    ->color('primary') // 버튼 색상 (선택 사항)
            ])
            ->filters([
                // 예: AI 분석 결과가 있는 것만 보기
                Tables\Filters\TernaryFilter::make('has_analysis')
                    ->label('AI 분석 여부')
                    ->placeholder('전체')
                    ->trueLabel('분석 완료')
                    ->falseLabel('미분석')
                    ->queries(
                        true: fn($query) => $query->whereNotNull('raw_text'),
                        false: fn($query) => $query->whereNull('raw_text'),
                    ),

                // 예: 등록일자 범위 필터
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')->label('시작일'),
                        Forms\Components\DatePicker::make('created_until')->label('종료일'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['created_from'], fn($query, $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['created_until'], fn($query, $date) => $query->whereDate('created_at', '<=', $date));
                    })
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'view' => ViewProduct::route('/{record}'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        // 1. 부모 클래스의 쿼리를 가져옵니다.
        // 만약 Policy에서 viewAny를 true로 했다면 여기서 모든 데이터가 와야 합니다.
        $query = parent::getEloquentQuery();

        // 2. 만약 모델에 Global Scope(상태 필터 등)가 걸려 있다면 강제로 해제합니다.
        // $query->withoutGlobalScopes();

        // 3. 검색어가 있다면 검색 조건만 추가합니다.
        $search = request()->query('tableSearch') ?? request()->query('search');
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        // 4. 캐시 없이 즉시 반환하여 DB 데이터를 실시간으로 확인합니다.
        return $query;
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
