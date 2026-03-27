<?php

namespace App\Filament\Resources\Products;

use App\Filament\Resources\Products\Pages\CreateProduct;
use App\Filament\Resources\Products\Pages\EditProduct;
use App\Filament\Resources\Products\Pages\ListProducts;
use App\Filament\Resources\Products\Pages\ViewProduct;
use App\Models\Product;
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

// 입력 필드 컴포넌트 (vendor/filament/forms 에 있음)
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
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
                                        $set('name', $result['name']);
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
                    // ProductResource.php 내 FileUpload 부분
                    FileUpload::make('image_path')
                        ->label('상품 상세 이미지 업로드')
                        ->image()
                        ->directory('product-analysis')
                        // 1. 임시 파일이 아닌 즉시 저장을 위해 livewire의 업로드 완료를 보장합니다.
                        ->live()
                        ->afterStateUpdated(function ($state, $set) {
                            // 파일이 선택/수정되면 이전 분석 결과를 초기화하거나 자동 로직을 태울 수 있습니다.
                        })
                        ->hintAction(
                            Action::make('analyzeImage')
                                ->label('이미지 분석하기')
                                ->icon('heroicon-m-photo')
                                ->color('warning')
                                ->modalHidden(fn ($get) => !$get('image_path'))
                                // 분석 중 버튼 비활성화 및 로딩 표시
                                ->requiresConfirmation()
                                ->action(function ($get, $set, $component) { // $component 추가
                                    $imagePath = $get('image_path');

                                    if (!$imagePath) {
                                        Notification::make()->title('이미지가 감지되지 않았습니다.')->warning()->send();
                                        return;
                                    }

                                    // 1. Livewire 임시 파일 객체에서 직접 경로 추출 시도
                                    if ($imagePath instanceof \Livewire\Features\SupportFileUploads\TemporaryUploadedFile) {
                                        $fullPath = $imagePath->getRealPath();
                                    } else {
                                        // 이미 저장된 파일인 경우 (Edit 모드 등)
                                        $fullPath = storage_path('app/public/' . (is_array($imagePath) ? array_key_first($imagePath) : $imagePath));
                                    }

                                    // [중요] 파일이 물리적으로 존재하는지 한 번 더 체크
                                    if (!file_exists($fullPath)) {
                                        \Illuminate\Support\Facades\Log::error("파일 물리 경로 확인 실패: " . $fullPath);
                                        Notification::make()->title('파일 로드 실패')->body('임시 파일이 삭제되었을 수 있습니다. 다시 업로드해 주세요.')->danger()->send();
                                        return;
                                    }

                                    $cacheKey = 'prod_analysis_' . md5_file($fullPath);

                                    try {
                                        // [수정] 캐시에서 가져오되, 데이터가 확실히 있는지 체크
                                        $result = \Illuminate\Support\Facades\Cache::get($cacheKey);

                                        if (!$result) {
                                            \Illuminate\Support\Facades\Log::info("캐시 없음 - AI 분석 시작");

                                            // 이미지 리사이징 (v2/v3 대응)
                                            if (class_exists('\Intervention\Image\ImageManagerStatic')) {
                                                \Intervention\Image\ImageManagerStatic::make($fullPath)->resize(600, null, function ($c) { $c->aspectRatio(); })->save();
                                            } elseif (class_exists('\Intervention\Image\ImageManager')) {
                                                $manager = new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver());
                                                $manager->read($fullPath)->scale(width: 600)->save($fullPath);
                                            }

                                            // 서비스 호출
                                            $result = app(ProductAnalysisService::class)->analyzeImage($fullPath);

                                            // [체크 3] 서비스 결과값 로그로 찍어보기
                                            \Illuminate\Support\Facades\Log::info("AI 응답 데이터: ", (array) $result);

                                            if ($result) {
                                                \Illuminate\Support\Facades\Cache::put($cacheKey, $result, now()->addDays(30));
                                            }
                                        } else {
                                            \Illuminate\Support\Facades\Log::info("캐시 데이터 적중!");
                                        }

                                        if ($result) {
                                            $set('name', $get('name') ?: data_get($result, 'name'));
                                            $set('price', $get('price') ?: data_get($result, 'price', 0));
                                            $set('category_id', $get('category_id') ?: data_get($result, 'category_id'));

                                            $set('summary', $get('summary') ?: data_get($result, 'summary'));
                                            $set('tags', $get('tags') ?: data_get($result, 'tags', []));
                                            $set('temp_specs', $get('temp_specs') ?: json_encode(data_get($result, 'specs', [])));

                                            // 3. [중요] 이미지 검수 상태는 분석할 때마다 무조건 업데이트해야 함
                                            $set('image_match_status', data_get($result, 'image_match_status', 'success'));
                                            $set('image_match_message', data_get($result, 'image_match_message'));
                                            $set('raw_text', data_get($result, 'raw_text') ?? "이미지 분석을 통해 생성된 데이터입니다.");

                                            Notification::make()->title('이미지 분석 및 검수 완료')->success()->send();
                                        } else {
                                            Notification::make()->title('분석 결과가 비어있습니다.')->warning()->send();
                                        }

                                    } catch (\Exception $e) {
                                        // 할당량 초과 파싱 로직 유지
                                        if (str_contains(strtolower($e->getMessage()), 'quota')) {
                                            preg_match('/retry in ([\d\.]+)s/', $e->getMessage(), $matches);
                                            $seconds = isset($matches[1]) ? ceil((float)$matches[1]) : 60;
                                            Notification::make()->title('AI 한도 초과')->body("약 {$seconds}초 후 재시도")->danger()->send();
                                            return;
                                        }

                                        \Illuminate\Support\Facades\Log::error("AI 분석 예외: " . $e->getMessage());
                                        Notification::make()->title('오류 발생')->body($e->getMessage())->danger()->send();
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
                                        ->height(300),

                                    // 3. 이미지 매칭 결과 표시
                                    TextEntry::make('image_match_status')
                                        ->label('이미지 정합성 상태')
                                        ->badge()
                                        ->color(fn (string $state): string => match ($state) {
                                            'success' => 'success',
                                            'warning' => 'warning',
                                            'fail'    => 'danger',
                                            default   => 'gray',
                                        })
                                        ->formatStateUsing(fn (?string $state): string => strtoupper($state ?? 'PENDING')),

                                    TextEntry::make('image_match_message')
                                        ->label('검수 상세 메시지')
                                        ->hidden(fn ($record) => $record->image_match_status === 'success')
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

                // 4. 생성일 표시 (한국 시간 확인용)
                TextColumn::make('created_at')
                    ->label('등록일')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([ /* ... */ ])
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

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
