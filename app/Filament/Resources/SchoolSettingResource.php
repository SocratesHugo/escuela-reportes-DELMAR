<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SchoolSettingResource\Pages;
use App\Models\SchoolSetting;
use Filament\Forms;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SchoolSettingResource extends Resource
{
    protected static ?string $model = SchoolSetting::class;

    protected static ?string $navigationGroup = 'Configuración';
    protected static ?string $navigationLabel = 'Escuela (branding)';
    protected static ?string $navigationIcon  = 'heroicon-o-cog-6-tooth';
    protected static ?int    $navigationSort  = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Grid::make()
                    ->columns(12)
                    ->schema([
                        Section::make('Datos de la escuela')
                            ->columnSpan(4)
                            ->schema([
                                TextInput::make('school_name')
                                    ->label('Nombre de la escuela')
                                    ->required()
                                    ->maxLength(150)
                                    ->live(debounce: 300),

                                FileUpload::make('logo_path')
                                    ->label('Logo')
                                    ->image()
                                    ->directory('school-logos')
                                    ->disk('public')
                                    ->visibility('public')
                                    ->preserveFilenames()
                                    ->openable()
                                    ->downloadable()
                                    ->maxSize(2048) // 2 MB
                                    ->helperText('PNG, JPG o SVG. Máx. 2 MB.')
                                    ->live(),

                                TextInput::make('primary_color')
                                    ->label('Color primario')
                                    ->placeholder('#0ea5e9')
                                    ->live(debounce: 300),

                                TextInput::make('secondary_color')
                                    ->label('Color secundario')
                                    ->placeholder('#8b5cf6')
                                    ->live(debounce: 300),

                                TextInput::make('text_color')
                                    ->label('Color de texto')
                                    ->placeholder('#111827')
                                    ->live(debounce: 300),
                            ]),

                        // PREVIEW
                        Section::make('Vista previa')
                            ->columnSpan(8)
                            ->schema([
                                // ⚠️ Importante: NO pasamos viewData con closures.
                                // Este Blade recibirá $get para leer el estado.
                                Forms\Components\View::make('filament.components.branding-preview')
                                    ->reactive(),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('school_name')->label('Escuela')->searchable(),
                Tables\Columns\TextColumn::make('primary_color')->label('Primario'),
                Tables\Columns\TextColumn::make('secondary_color')->label('Secundario'),
                Tables\Columns\TextColumn::make('text_color')->label('Texto'),
                Tables\Columns\TextColumn::make('updated_at')->dateTime('Y-m-d H:i')->label('Actualizado'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->defaultSort('id', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListSchoolSettings::route('/'),
            'create' => Pages\CreateSchoolSetting::route('/create'),
            'edit'   => Pages\EditSchoolSetting::route('/{record}/edit'),
        ];
    }
    public static function shouldRegisterNavigation(): bool
{
    return auth()->user()?->hasAnyRole(['admin','director','coordinador']) ?? false;
}
}
