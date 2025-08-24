<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GroupResource\Pages;
use App\Models\Group;
use Filament\Resources\Resource;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GroupResource extends Resource
{
    protected static ?string $model = Group::class;

    protected static ?string $navigationIcon  = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Grupos';

    public static function form(Form $form): Form
    {
        // Titular/Preceptor no deben poder editar — el propio framework ya bloqueará
        // al ocultar acciones; adicionalmente, ponemos el form en "solo lectura"
        // cuando esos roles acceden /force/**edit** por URL.
        $isReadOnly = auth()->user()?->hasAnyRole(['titular','preceptor']) ?? false;

        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('Nombre del grupo')
                ->required()
                ->maxLength(50)
                ->disabled($isReadOnly),

            Forms\Components\TextInput::make('grade')
                ->label('Grado')
                ->numeric()
                ->required()
                ->disabled($isReadOnly),

            Forms\Components\Select::make('school_year_id')
                ->label('Ciclo escolar')
                ->relationship('schoolYear', 'name')
                ->required()
                ->disabled($isReadOnly),

            Forms\Components\Toggle::make('active')
                ->label('Activo')
                ->default(true)
                ->helperText('Puedes desactivar grupos no vigentes.')
                ->disabled($isReadOnly),

            Forms\Components\Select::make('titular_id')
                ->label('Titular')
                ->relationship('titular', 'name')
                ->searchable()
                ->preload()
                ->modifyQueryUsing(fn (Builder $q) => $q->role('titular'))
                ->disabled($isReadOnly),
        ]);
    }

    public static function table(Table $table): Table
    {
        $user = auth()->user();

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Grupo')->sortable()->searchable(),
                Tables\Columns\TextColumn::make('grade')->label('Grado')->sortable(),
                Tables\Columns\TextColumn::make('schoolYear.name')->label('Ciclo'),
                Tables\Columns\TextColumn::make('titular.name')->label('Titular')->placeholder('—')->toggleable(),
                Tables\Columns\IconColumn::make('active')->label('Activo')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')->label('Solo activos'),
                Tables\Filters\SelectFilter::make('school_year_id')->label('Ciclo')->relationship('schoolYear','name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\ViewAction::make()
                    ->visible(fn(Group $record) => auth()->user()->can('view', $record)),

                Tables\Actions\EditAction::make()
                    ->visible(fn(Group $record) => auth()->user()->can('update', $record)),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn(Group $record) => auth()->user()->can('delete', $record)),

                // Editar/Eliminar solo para no-titular/preceptor (y opcionalmente por permiso)
                Tables\Actions\EditAction::make()
                    ->visible(fn () => ! $user?->hasAnyRole(['titular','preceptor'])),

                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => ! $user?->hasAnyRole(['titular','preceptor'])),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => ! $user?->hasAnyRole(['titular','preceptor'])),
                ]),
            ]);
    }

    /**
     * Visibilidad de registros:
     * - Titular: solo ve grupos donde es titular
     * - Preceptor: puede ver todos los grupos (ajústalo si quieres acotar)
     * - Resto: sin restricción
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['schoolYear','titular']);
        $user  = auth()->user();

        if ($user?->hasRole('titular')) {
            $query->where('titular_id', $user->id);
        }

        return $query;
    }

    /** Oculta el botón "Crear" arriba de la tabla para titular/preceptor */
    public static function canCreate(): bool
        {
            return auth()->user()?->can('create', Group::class) ?? false;
        }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListGroups::route('/'),
            'create' => Pages\CreateGroup::route('/create'),
            'edit'   => Pages\EditGroup::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $name = \App\Models\SchoolYear::query()->orderByDesc('id')->value('name');
        return $name ? "Ciclo: {$name}" : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'info';
    }
    public static function shouldRegisterNavigation(): bool
{
    return auth()->user()?->hasAnyRole(['admin','director','coordinador']) ?? false;
}
}
