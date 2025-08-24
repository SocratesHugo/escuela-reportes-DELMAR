<?php

namespace App\Filament\Resources;

use App\Filament\Resources\EmailSettingResource\Pages;
use App\Models\EmailSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class EmailSettingResource extends Resource
{
    protected static ?string $model = EmailSetting::class;       // <- ¡Asegúrate que NO diga EmailTemplate!
    protected static ?string $navigationIcon = 'heroicon-o-envelope-open';
    protected static ?string $navigationGroup = 'Reportes';
    protected static ?string $navigationLabel = 'Plantilla de correos';

    // Mantén la URL /admin/email-templates
    public static function getSlug(): string
    {
        return 'email-templates';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(2)->schema([
                Forms\Components\TextInput::make('from_name')->label('Remitente (nombre)')->maxLength(100),
                Forms\Components\TextInput::make('from_email')->label('Remitente (email)')->email()->maxLength(150),
            ]),

            Forms\Components\Textarea::make('subject_template')
                ->label('Asunto (plantilla)')
                ->rows(2)
                ->helperText('Vars: {{week_name}}, {{student_full_name}}, {{parent_name}}'),

            Forms\Components\RichEditor::make('body_template')
                ->label('Cuerpo (plantilla)')
                ->helperText('Usa {{link}} para el enlace firmado'),

            Forms\Components\Toggle::make('consolidate_by_parent')
                ->label('Unificar por padre/madre')
                ->default(true),

            Forms\Components\Toggle::make('include_students')
                ->label('Enviar también a alumnos')
                ->default(false),

            Forms\Components\TextInput::make('link_expires_days')
                ->label('Vigencia del enlace (días)')
                ->numeric()->minValue(1)->maxValue(30)->default(7),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('from_name')->label('De (nombre)')->limit(20),
            Tables\Columns\TextColumn::make('from_email')->label('De (email)')->limit(25),
            Tables\Columns\IconColumn::make('consolidate_by_parent')->boolean()->label('Unificar'),
            Tables\Columns\IconColumn::make('include_students')->boolean()->label('A alumnos'),
            Tables\Columns\TextColumn::make('link_expires_days')->label('Vigencia (d)'),
            Tables\Columns\TextColumn::make('updated_at')->dateTime('Y-m-d H:i'),
        ])->actions([
            Tables\Actions\EditAction::make(),
        ])->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListEmailSettings::route('/'),
            'create' => Pages\CreateEmailSetting::route('/create'),
            'edit'   => Pages\EditEmailSetting::route('/{record}/edit'),
        ];
    }

    // (Opcional) Forzar 1 sola fila
    public static function canCreate(): bool
    {
        return EmailSetting::count() === 0;
    }
}
