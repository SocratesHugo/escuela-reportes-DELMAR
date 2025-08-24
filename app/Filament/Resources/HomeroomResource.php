<?php

namespace App\Filament\Resources;

use App\Filament\Resources\HomeroomResource\Pages;
use App\Models\Group;
use App\Models\Homeroom;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class HomeroomResource extends Resource
{
    protected static ?string $model = Homeroom::class;

    protected static ?string $navigationIcon  = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Homerooms';
    protected static ?string $pluralLabel     = 'Homerooms';
    protected static ?string $modelLabel      = 'Homeroom';
    protected static ?string $navigationGroup = 'Académico';

    /**
     * ¿Quién ve el recurso en el menú?
     * (ajusta a tus roles/permiso)
     */
    public static function shouldRegisterNavigation(): bool
    {
        $u = auth()->user();

        return $u?->hasAnyRole(['admin', 'director', 'coordinador']) === true;
        // O con permiso:
        // return $u?->can('titulares.gestionar') === true;
    }

    /**
     * ¿Quién puede crear?
     */
    public static function canCreate(): bool
    {
        $u = auth()->user();

        return $u?->hasAnyRole(['admin', 'director', 'coordinador']) === true;
        // O con permiso:
        // return $u?->can('titulares.gestionar') === true;
    }

    /**
     * ¿Quién puede editar / eliminar?
     * (Ajusta según tu necesidad: aquí igual que crear.)
     */
    public static function canEdit($record): bool
    {
        return self::canCreate();
    }

    public static function canDelete($record): bool
    {
        return self::canCreate();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Grid::make(12)->schema([
                // Grupo (único, no permitir elegir uno ya asignado)
                Forms\Components\Select::make('group_id')
                    ->label('Grupo')
                    ->columnSpan(6)
                    ->required()
                    ->searchable()
                    ->preload()
                    ->options(function () {
                        // Excluir grupos que ya tengan homeroom
                        $usedGroupIds = Homeroom::query()->pluck('group_id')->all();

                        return Group::query()
                            ->when(!empty($usedGroupIds), fn ($q) => $q->whereNotIn('id', $usedGroupIds))
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    }),

                // Maestro titular (filtrado por rol 'maestro')
                Forms\Components\Select::make('teacher_id')
                    ->label('Maestro titular')
                    ->columnSpan(6)
                    ->required()
                    ->searchable()
                    ->preload()
                    ->options(function () {
                        return User::query()
                            ->whereHas('roles', fn (Builder $q) => $q->where('name', 'maestro'))
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->helperText('El usuario debe tener rol "maestro".'),
            ]),

            Forms\Components\Toggle::make('active')
                ->label('Activo')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('group.name')
                    ->label('Grupo')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('teacher.name')
                    ->label('Titular')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')->label('Solo activos'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->visible(fn () => static::canCreate()),
                Tables\Actions\DeleteAction::make()->visible(fn () => static::canCreate()),
            ])
            ->bulkActions([
                Tables\Actions\DeleteBulkAction::make()->visible(fn () => static::canCreate()),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListHomerooms::route('/'),
            'create' => Pages\CreateHomeroom::route('/create'),
            'edit'   => Pages\EditHomeroom::route('/{record}/edit'),
        ];
    }
}
