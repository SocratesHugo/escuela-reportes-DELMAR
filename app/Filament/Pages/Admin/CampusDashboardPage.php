<?php

namespace App\Filament\Pages\Admin;

use App\Models\Group;
use App\Models\Week;
use App\Models\Work;
use App\Models\GradeEntry;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

// IMPORTANTE: usa los Page de snapshot para generar URLs seguras
use App\Filament\Pages\Admin\GroupSnapshotPage;
use App\Filament\Pages\Admin\GroupSubjectSnapshotPage;

class CampusDashboardPage extends Page
{
    protected static ?string $navigationIcon  = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'Dashboard Dirección';
    protected static ?string $navigationLabel = 'Reportes (admin)';

    protected static string $view = 'filament.pages.admin.campus-dashboard'; // tu blade

    public ?int $weekId = null;

    /** @var array<int,array> */
    public array $groupsRows = [];

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->check() && auth()->user()->hasAnyRole(['admin','director','coordinador']);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Reportes (admin)';
    }

    public function mount(): void
    {
        // semana por defecto: la última
        $this->weekId ??= Week::query()->orderByDesc('id')->value('id');
        $this->loadData();
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('weekId')
                ->label('Semana')
                ->required()
                ->options(
                    Week::orderBy('id')
                        ->get()
                        ->mapWithKeys(fn (Week $w) => [
                            $w->id => "{$w->name} — {$w->starts_at} - {$w->ends_at}"
                        ])->toArray()
                )
                ->searchable()->preload()->reactive()
                ->afterStateUpdated(fn () => $this->loadData()),
        ])->columns(1);
    }

    protected function loadData(): void
    {
        $this->groupsRows = [];

        if (!$this->weekId) {
            return;
        }

        $week = Week::find($this->weekId);
        if (!$week) {
            return;
        }

        $groups = Group::withCount('students')->orderBy('name')->get();

        foreach ($groups as $group) {
            // trabajos de la semana para el grupo (vía relación del GST)
            $workIds = Work::query()
                ->where('week_id', $week->id)
                ->whereHas('groupSubjectTeacher', fn($q) => $q->where('group_id', $group->id))
                ->pluck('id');

            if ($workIds->isEmpty()) {
                $this->groupsRows[] = [
                    'group'     => $group,
                    'avg'       => null,
                    'delivered' => 0,
                    'pending'   => 0,
                    'missing'   => 0,
                    'total'     => 0,
                    // ENLACE SEGURO usando ::getUrl()
                    'link'      => GroupSnapshotPage::getUrl([
                        'groupId' => $group->id,
                        'weekId'  => $week->id,
                    ]),
                ];
                continue;
            }

            $grades = GradeEntry::whereIn('work_id', $workIds)->get();

            $sum = 0.0; $cnt = 0;
            $del = 0; $pen = 0; $mis = 0;

            foreach ($grades as $g) {
                if ($g->status === 'P') {
                    $pen++;
                    $sum += 0.0; $cnt++;
                    continue;
                }
                if ($g->status === 'J') {
                    $sum += 10.0; $cnt++;
                    $del++;
                    continue;
                }
                if (is_numeric($g->score)) {
                    $sum += (float) $g->score; $cnt++;
                    if ((float) $g->score == 0.0) $mis++;
                    else $del++;
                }
            }

            $avg = $cnt ? round($sum / $cnt, 2) : null;

            $this->groupsRows[] = [
                'group'     => $group,
                'avg'       => $avg,
                'delivered' => $del,
                'pending'   => $pen,
                'missing'   => $mis,
                'total'     => $grades->count(),
                // ENLACE SEGURO usando ::getUrl()
                'link'      => GroupSnapshotPage::getUrl([
                    'groupId' => $group->id,
                    'weekId'  => $week->id,
                ]),
            ];
        }
    }

    protected function getViewData(): array
    {
        return [
            'week'       => $this->weekId ? Week::find($this->weekId) : null,
            'groupsRows' => $this->groupsRows,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
