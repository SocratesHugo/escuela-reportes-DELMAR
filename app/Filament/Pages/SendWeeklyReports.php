<?php

namespace App\Filament\Pages;

use App\Jobs\SendWeeklyReportsJob;
use App\Models\EmailSetting;
use App\Models\Week;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\Actions as FormActions;
use Filament\Forms\Components\Actions\Action as FormAction;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;

class SendWeeklyReports extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-paper-airplane';
    protected static ?string $navigationGroup = 'Envíos';
    protected static ?string $navigationLabel = 'Enviar reportes semanales';
    protected static string $view = 'filament.pages.send-weekly-reports';

    // Estado del formulario
    public ?int  $weekId              = null;
    public ?bool $consolidate         = null;
    public ?bool $includeStudents     = null;
    public ?int  $expiresDays         = null;

    // Datos de vista previa
    public string $previewSubject = '';
    public string $previewBody    = '';
    public string $previewNote    = '';

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin', 'director', 'coordinador']);
    }

    public function mount(): void
    {
        $cfg = EmailSetting::first();
        $this->consolidate     = $cfg?->consolidate_by_parent ?? true;
        $this->includeStudents = $cfg?->include_students       ?? false;
        $this->expiresDays     = $cfg?->link_expires_days      ?? 7;

        $this->weekId ??= Week::query()->orderByDesc('id')->value('id');
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Parámetros de envío')
                ->schema([
                    Forms\Components\Select::make('weekId')
                        ->label('Semana')
                        ->required()
                        ->options(
                            Week::orderByDesc('id')->get()->mapWithKeys(
                                fn (Week $w) => [
                                    $w->id => ($w->name ?? 'Semana')
                                        . ($w->starts_at && $w->ends_at
                                            ? ' — ' . Carbon::parse($w->starts_at)->format('Y-m-d')
                                              . ' - ' . Carbon::parse($w->ends_at)->format('Y-m-d')
                                            : ''
                                          )
                                ]
                            )->toArray()
                        )
                        ->searchable()
                        ->preload(),

                    Forms\Components\Toggle::make('consolidate')
                        ->label('Unificar por papá/mamá (un correo con todos sus hijos)')
                        ->inline(false),

                    Forms\Components\Toggle::make('includeStudents')
                        ->label('Enviar también a los alumnos')
                        ->inline(false),

                    Forms\Components\TextInput::make('expiresDays')
                        ->numeric()->minValue(1)->maxValue(30)
                        ->label('Vigencia del enlace (días)')
                        ->helperText('Días durante los que el enlace firmado será válido.'),
                ])
                ->columns(2),

            Forms\Components\Section::make()
                ->schema([
                    FormActions::make([
                        FormAction::make('preview')
                            ->label('Vista previa')
                            ->icon('heroicon-o-eye')
                            ->action('buildPreview')
                            ->modalHeading('Vista previa del correo')
                            ->modalSubmitAction(false)
                            ->modalContent(fn () => view('filament.pages.partials.weekly-mail-preview', [
                                'subject' => $this->previewSubject,
                                'body'    => $this->previewBody,
                                'note'    => $this->previewNote,
                            ])),

                        FormAction::make('sendNow')
                            ->label('Enviar ahora')
                            ->color('primary')
                            ->icon('heroicon-o-paper-airplane')
                            ->requiresConfirmation()
                            ->action('dispatchJob'),
                    ])->fullWidth(),
                ]),
        ]);
    }

    public function buildPreview(): void
    {
        $cfg = EmailSetting::first();

        $subjectTpl = $cfg?->subject_template ?? 'Reporte Semana {{week_name}} — {{student_full_name}}';
        $bodyTpl    = $cfg?->body_template    ?? "Hola {{student_or_parent}},\nRevisa el reporte de {{student_full_name}}: {{link}}";

        $weekName = optional(Week::find($this->weekId))->name ?? 'Semana';
        $fakeUrl  = url('/preview-link');

        $vars = [
            'week_name'         => $weekName,
            'parent_name'       => 'Nombre del padre/madre',
            'student_name'      => 'Nombre',
            'student_full_name' => 'Alumno Apellido',
            'student_or_parent' => 'Padres de familia',
            'link'              => $fakeUrl,
        ];

        $render = function (string $tpl, array $vars) {
            foreach ($vars as $k => $v) {
                $tpl = str_replace('{{'.$k.'}}', $v, $tpl);
            }
            return $tpl;
        };

        $this->previewSubject = $render($subjectTpl, $vars);
        $this->previewBody    = $render($bodyTpl, $vars);
        $this->previewNote    =
            '• Unificar por papá/mamá: ' . (($this->consolidate ?? true) ? 'Sí' : 'No') . "\n" .
            '• También a alumnos: ' . (($this->includeStudents ?? false) ? 'Sí' : 'No') . "\n" .
            '• Vigencia: ' . (int)($this->expiresDays ?? 7) . ' días';
    }

    public function dispatchJob(): void
    {
        if (!$this->weekId) {
            Notification::make()->title('Selecciona la semana.')->danger()->send();
            return;
        }

        dispatch(new SendWeeklyReportsJob(
            weekId: $this->weekId,
            consolidateByParent: (bool)$this->consolidate,
            expiresInDays: (int)$this->expiresDays,
            alsoSendToStudents: (bool)$this->includeStudents,
        ));

        Notification::make()
            ->title('Envío encolado')
            ->body('Se encoló el envío de correos para la semana seleccionada.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
