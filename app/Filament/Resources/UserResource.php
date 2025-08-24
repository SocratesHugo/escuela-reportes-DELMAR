<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Forms\Form;      // v3
use Filament\Tables;
use Filament\Tables\Table;    // v3
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Usuarios';
    protected static ?string $navigationGroup = 'Usuarios';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),

            Forms\Components\TextInput::make('email')
                ->label('Correo')
                ->email()
                ->required()
                ->unique(ignoreRecord: true),

            Forms\Components\TextInput::make('password')
                ->label('Contraseña')
                ->password()
                ->revealable()
                ->dehydrateStateUsing(fn ($state) => filled($state) ? Hash::make($state) : null)
                ->dehydrated(fn ($state) => filled($state))
                ->required(fn (string $context) => $context === 'create'),

            // Asignación de múltiples roles (Spatie)
            Forms\Components\Select::make('roles')
                ->label('Roles')
                ->multiple()
                ->preload()
                ->relationship('roles', 'name')
                ->helperText('Puedes asignar varios: maestro, titular, preceptor, etc.'),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nombre')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('email')->label('Correo')->searchable(),
                Tables\Columns\TextColumn::make('roles.name')->label('Roles')->badge()->separator(', '),
            ])
            ->actions([Tables\Actions\EditAction::make()])
            ->bulkActions([Tables\Actions\DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit'   => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            \App\Filament\Resources\UserResource\RelationManagers\PreceptoredStudentsRelationManager::class,
        ];
    }

    public static function shouldRegisterNavigation(): bool
{
    return auth()->user()?->hasAnyRole(['admin','director','coordinador']) ?? false;
}
}
