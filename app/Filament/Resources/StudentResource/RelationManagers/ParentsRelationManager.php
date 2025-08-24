<?php

namespace App\Filament\Resources\StudentResource\RelationManagers;

use App\Models\User;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class ParentsRelationManager extends RelationManager
{
    protected static string $relationship = 'parents';
    protected static ?string $title = 'Padres / Tutores';

    public function form(Forms\Form $form): Forms\Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nombre')->required(),
            Forms\Components\TextInput::make('email')->label('Email')->email()->required(),
        ]);
    }

    public function table(Tables\Table $table): Tables\Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nombre')->searchable(),
                Tables\Columns\TextColumn::make('email')->searchable(),
                Tables\Columns\TextColumn::make('created_at')->dateTime()->label('Vinculado'),
            ])
            ->headerActions([
                // Adjuntar padre existente (sólo usuarios con rol "padre")
                Tables\Actions\AttachAction::make()
                    ->label('Adjuntar existente')
                    ->recordSelectOptionsQuery(fn ($q) => $q->role('padre')),

                // Crear nuevo usuario padre y vincular
                Tables\Actions\Action::make('crearPadre')
                    ->label('Crear y vincular padre')
                    ->icon('heroicon-o-user-plus')
                    ->form([
                        Forms\Components\TextInput::make('name')->label('Nombre')->required(),
                        Forms\Components\TextInput::make('email')->label('Email')->email()->required(),
                        Forms\Components\Toggle::make('enviar_reset')->label('Enviar link de contraseña')->default(true),
                    ])
                    ->action(function (array $data) {
                        $user = User::firstOrCreate(
                            ['email' => $data['email']],
                            [
                                'name'     => $data['name'],
                                'password' => Hash::make(Str::random(16)),
                            ]
                        );

                        if (! $user->hasRole('padre')) {
                            $user->assignRole('padre');
                        }

                        $this->ownerRecord->parents()->syncWithoutDetaching([$user->id]);

                        if (!empty($data['enviar_reset'])) {
                            Password::sendResetLink(['email' => $user->email]);
                        }
                    }),
            ])
            ->actions([
                Tables\Actions\DetachAction::make(),
            ]);
    }
}
