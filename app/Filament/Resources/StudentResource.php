<?php

namespace App\Filament\Resources;

use App\Filament\Resources\StudentResource\Pages;
use App\Models\GroupSubjectTeacher;
use App\Models\Student;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StudentResource extends Resource
{
    protected static ?string $model = Student::class;

    protected static ?string $navigationIcon  = 'heroicon-o-academic-cap';
    protected static ?string $navigationLabel = 'Alumnos';
    protected static ?string $modelLabel      = 'Alumno';
    protected static ?string $pluralLabel     = 'Alumnos';
    protected static ?string $navigationGroup = 'Académico';

    /** Mostrar en menú a staff + maestro + titular + preceptor */
    public static function shouldRegisterNavigation(): bool
    {
        $u = auth()->user();
        if (! $u) {
            return false;
        }

        return $u->hasAnyRole([
            'admin','director','coordinador',
            'maestro','titular','preceptor',
        ]);
    }

    /** Solo staff crea alumnos */
    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['admin','director','coordinador']) ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('paternal_lastname')->label('Apellido paterno')->required(),
            Forms\Components\TextInput::make('maternal_lastname')->label('Apellido materno'),
            Forms\Components\TextInput::make('names')->label('Nombres')->required(),
            Forms\Components\TextInput::make('email')->label('Correo')->email(),
            Forms\Components\Select::make('group_id')->label('Grupo')->relationship('group', 'name')->searchable()->preload(),
            Forms\Components\Toggle::make('active')->label('Activo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        $user = auth()->user();
        $isTeacher = $user?->hasRole('maestro') ?? false;

        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Alumno')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('group.name')
                    ->label('Grupo')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('schoolYear.name')
                    ->label('Ciclo'),

                // 👇 Ocultar “Activo” solo para maestros
                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean()
                    ->visible(fn () => ! (auth()->user()?->hasRole('maestro') ?? false)),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')->label('Solo activos')
                    ->visible(fn () => ! (auth()->user()?->hasRole('maestro') ?? false)),
            ])
            ->actions([
                // 👇 Botón para abrir el reporte semanal (columna “Acciones”)
                Tables\Actions\Action::make('reporte')
                    ->label('Reporte semanal')
                    ->icon('heroicon-o-calendar-days')
                    ->color('primary')
                    ->url(fn (Student $record) =>
                        \App\Filament\Pages\StudentWeeklyReport::getUrl([
                            'student_id' => $record->id,
                        ])
                    )
                    ->openUrlInNewTab(), // si prefieres dentro del panel, quita esta línea

                // 👇 Ocultar “Ver” SOLO al maestro
                Tables\Actions\ViewAction::make()
                    ->label('Ver')
                    ->visible(fn () => ! (auth()->user()?->hasRole('maestro') ?? false)),

                // Edición solo para staff
                Tables\Actions\EditAction::make()
                    ->label('Editar')
                    ->visible(fn () => auth()->user()?->hasAnyRole(['admin','director','coordinador']) ?? false),

                // Eliminación solo para admin/director
                Tables\Actions\DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn () => auth()->user()?->hasAnyRole(['admin','director']) ?? false),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->visible(fn () => auth()->user()?->hasAnyRole(['admin','director']) ?? false),
                ]),
            ]);
    }

    /** Limitar registros por rol */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['group','schoolYear']);
        $u     = auth()->user();

        if (! $u) {
            return $query->whereRaw('1=0');
        }

        // Staff ve todo
        if ($u->hasAnyRole(['admin','director','coordinador'])) {
            return $query;
        }

        // Maestro → alumnos de los grupos que imparte
        if ($u->hasRole('maestro')) {
            $groupIds = GroupSubjectTeacher::query()
                ->where('teacher_id', $u->id)
                ->pluck('group_id');

            return $query->whereIn('group_id', $groupIds);
        }

        // Titular → alumnos del grupo donde es titular
        if ($u->hasRole('titular')) {
            return $query->whereHas('group', fn($q) => $q->where('titular_id', $u->id));
        }

        // Preceptor → alumnos asignados en la pivote preceptor_student
        if ($u->hasRole('preceptor')) {
            return $query->whereIn('id', function ($sub) use ($u) {
                $sub->from('preceptor_student')
                    ->select('student_id')
                    ->where('preceptor_id', $u->id);
            });
        }

        // Otros roles no ven alumnos
        return $query->whereRaw('1=0');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListStudents::route('/'),
            'view'  => Pages\ViewStudent::route('/{record}'),
        ];
    }
}
