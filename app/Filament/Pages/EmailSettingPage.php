<?php

namespace App\Filament\Pages;

use App\Models\EmailSetting;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Filament\Notifications\Notification;

class EmailSettingPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-cog-6-tooth';
    protected static ?string $navigationGroup = 'Envíos';
    protected static ?string $navigationLabel = 'Configuración de correos';
    protected static string $view = 'filament.pages.email-setting';

    public ?string $from_name            = null;
    public ?string $from_email           = null;
    public ?string $subject_template     = null;
    public ?string $body_template        = null;
    public bool $consolidate_by_parent   = true;
    public bool $include_students        = false;
    public int $link_expires_days        = 7;

    public function mount(): void
    {
        $s = EmailSetting::query()->first() ?? EmailSetting::create();
        $this->fill($s->toArray());
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('from_name')->label('Remitente (nombre)'),
            Forms\Components\TextInput::make('from_email')->label('Remitente (email)')->email(),
            Forms\Components\Textarea::make('subject_template')
                ->label('Asunto (template)')
                ->rows(2)
                ->helperText('Variables: {{week_name}}, {{student_full_name}}, {{group_name}}'),
            Forms\Components\Textarea::make('body_template')
                ->label('Cuerpo del correo (HTML/Texto)')
                ->rows(10)
                ->helperText('Usa {{link}} para insertar el botón de ver reporte. Var: {{parent_name}}, {{student_full_name}}, {{week_name}}'),
            Forms\Components\Toggle::make('consolidate_by_parent')->label('Unificar por papá/mamá'),
            Forms\Components\Toggle::make('include_students')->label('Enviar también al alumno'),
            Forms\Components\TextInput::make('link_expires_days')->numeric()->minValue(1)->maxValue(30)->label('Días de expiración del enlace'),
            Forms\Components\Actions::make([
                Forms\Components\Actions\Action::make('save')
                    ->label('Guardar configuración')
                    ->action(function () {
                        $s = EmailSetting::query()->first() ?? EmailSetting::create();
                        $s->fill([
                            'from_name'            => $this->from_name,
                            'from_email'           => $this->from_email,
                            'subject_template'     => $this->subject_template,
                            'body_template'        => $this->body_template,
                            'consolidate_by_parent'=> $this->consolidate_by_parent,
                            'include_students'     => $this->include_students,
                            'link_expires_days'    => $this->link_expires_days,
                        ])->save();

                        Notification::make()->title('Guardado')->success()->send();
                    }),
            ])->columnSpanFull(),
        ])->columns(2);
    }
}
