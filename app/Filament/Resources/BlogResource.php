<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BlogResource\Pages;
use App\Models\Artist;
use App\Models\Blog;
use App\Models\Client;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BlogResource extends Resource
{
    protected static ?string $model = Blog::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'وبلاگ';

    protected static ?string $modelLabel = 'وبلاگ';

    protected static ?string $pluralModelLabel = 'وبلاگ';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                /*
                |--------------------------------------------------------------------------
                | Blog Information
                |--------------------------------------------------------------------------
                */

                Forms\Components\Section::make('Blog Information')
                    ->schema([

                        Forms\Components\DatePicker::make('date')
                            ->label('Date')
                            ->required()
                            ->native(false),

                        Forms\Components\TextInput::make('reading_time')
                            ->label('Reading Time')
                            ->placeholder('5 min')
                            ->maxLength(100),

                        Forms\Components\Select::make('status')
                            ->label('Status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
                            ])
                            ->default('draft')
                            ->required(),

                    ])
                    ->columns(3)
                    ->columnSpanFull(),

                /*
                |--------------------------------------------------------------------------
                | Author
                |--------------------------------------------------------------------------
                */

                Forms\Components\Section::make('Author')
                    ->schema([

                        Forms\Components\Select::make('author_type')
                            ->label('Author')
                            ->options([
                                'wachaar' => 'Wachaar',
                                Artist::class => 'Artist',
                            ])
                            ->live()
                            ->required()
                            ->afterStateUpdated(function ($state, callable $set) {
                                $set('author_id', null);
                            }),

                        Forms\Components\Select::make('author_id')
                            ->label('Author Name')
                            ->searchable()
                            ->preload()
                            ->required(fn (callable $get) => $get('author_type') === Artist::class)
                            ->visible(fn (callable $get) => $get('author_type') === Artist::class)
                            ->options(function (callable $get) {

                                $type = $get('author_type');

                                if ($type !== Artist::class) {
                                    return [];
                                }

                                return Artist::query()
                                    ->get()
                                    ->mapWithKeys(function ($artist) {

                                        $name = trim(
                                            ($artist->name ?? '') . ' ' .
                                            ($artist->family ?? '')
                                        );

                                        return [
                                            $artist->id => $name ?: 'Artist #' . $artist->id,
                                        ];
                                    })
                                    ->toArray();
                            }),

                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                /*
                |--------------------------------------------------------------------------
                | Blog Images
                |--------------------------------------------------------------------------
                */

                Forms\Components\Section::make('Blog Images')
                    ->schema([

                        Forms\Components\FileUpload::make('image_1')
                            ->label('Image 1')
                            ->image()
                            ->disk('api_public')
                            ->directory('blogs')
                            ->acceptedFileTypes([
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->maxSize(5120)
                            ->imagePreviewHeight('300')
                            ->downloadable()
                            ->openable(),

                        Forms\Components\FileUpload::make('image_2')
                            ->label('Image 2')
                            ->image()
                            ->disk('api_public')
                            ->directory('blogs')
                            ->acceptedFileTypes([
                                'image/jpeg',
                                'image/png',
                                'image/webp',
                            ])
                            ->maxSize(5120)
                            ->imagePreviewHeight('300')
                            ->downloadable()
                            ->openable(),

                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                /*
                |--------------------------------------------------------------------------
                | Translations
                |--------------------------------------------------------------------------
                */

                Forms\Components\Tabs::make('Translations')
                    ->tabs([

                        /*
                        |--------------------------------------------------------------------------
                        | Persian
                        |--------------------------------------------------------------------------
                        */

                        Forms\Components\Tabs\Tab::make('فارسی')
                            ->schema([

                                Forms\Components\TextInput::make(
                                    'translation_fa.title_1'
                                )
                                    ->label('عنوان ۱')
                                    ->maxLength(255),

                                Forms\Components\Textarea::make(
                                    'translation_fa.description_1'
                                )
                                    ->label('توضیحات ۱')
                                    ->rows(7)
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make(
                                    'translation_fa.title_2'
                                )
                                    ->label('عنوان ۲')
                                    ->maxLength(255),

                                Forms\Components\Textarea::make(
                                    'translation_fa.description_2'
                                )
                                    ->label('توضیحات ۲')
                                    ->rows(7)
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make(
                                    'translation_fa.title_3'
                                )
                                    ->label('عنوان ۳')
                                    ->maxLength(255),

                                Forms\Components\Textarea::make(
                                    'translation_fa.description_3'
                                )
                                    ->label('توضیحات ۳')
                                    ->rows(7)
                                    ->columnSpanFull(),

                            ])
                            ->columns(2),

                        /*
                        |--------------------------------------------------------------------------
                        | English
                        |--------------------------------------------------------------------------
                        */

                        Forms\Components\Tabs\Tab::make('English')
                            ->schema([

                                Forms\Components\TextInput::make(
                                    'translation_en.title_1'
                                )
                                    ->label('Title 1')
                                    ->maxLength(255),

                                Forms\Components\Textarea::make(
                                    'translation_en.description_1'
                                )
                                    ->label('Description 1')
                                    ->rows(7)
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make(
                                    'translation_en.title_2'
                                )
                                    ->label('Title 2')
                                    ->maxLength(255),

                                Forms\Components\Textarea::make(
                                    'translation_en.description_2'
                                )
                                    ->label('Description 2')
                                    ->rows(7)
                                    ->columnSpanFull(),

                                Forms\Components\TextInput::make(
                                    'translation_en.title_3'
                                )
                                    ->label('Title 3')
                                    ->maxLength(255),

                                Forms\Components\Textarea::make(
                                    'translation_en.description_3'
                                )
                                    ->label('Description 3')
                                    ->rows(7)
                                    ->columnSpanFull(),

                            ])
                            ->columns(2),

                    ])
                    ->columnSpanFull(),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([



                Tables\Columns\TextColumn::make('id')
                    ->label('شناسه')
                    ->sortable(),
                Tables\Columns\TextColumn::make('translations.title')
                    ->label('موضوع')
                    ->sortable(),

                Tables\Columns\TextColumn::make('date')
                    ->label('تاریخ')
                    ->date('Y-m-d')
                    ->sortable(),



                Tables\Columns\TextColumn::make('author_type')
                    ->label('نویسنده')
                    ->formatStateUsing(function ($state) {
                        return match ($state) {
                            Artist::class => 'Artist',
                            Client::class => 'Client',
                            default => $state,
                        };
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'draft' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('date', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBlogs::route('/'),
            'create' => Pages\CreateBlog::route('/create'),
            'edit' => Pages\EditBlog::route('/{record}/edit'),
        ];
    }
}
