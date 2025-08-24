<?php

namespace App\Mail;

use App\Models\EmailSetting;
use App\Models\Week;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WeeklyReportsConsolidatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public $parentOrStudent,            // User (papá/mamá) o Student
        public Week $week,
        public array $items,                // [['student' => Student, 'url' => string], ...]
        public ?EmailSetting $settings = null,
        public bool $isParent = true        // true = papá/mamá, false = alumno
    ) {}

    public function build()
    {
        $s = $this->settings;

        if ($s?->from_email) {
            $this->from($s->from_email, $s->from_name ?: config('app.name'));
        }

        $subject = $s?->subject_template ?? 'Reporte Semana {{week_name}}';
        $firstStudentName = data_get($this->items, '0.student.full_name');

        $repl = [
            '{{week_name}}'         => $this->week->name,
            '{{student_full_name}}' => $firstStudentName ?: '',
            '{{group_name}}'        => data_get($this->items, '0.student.group.name', ''),
        ];

        foreach ($repl as $k => $v) {
            $subject = str_replace($k, $v, $subject);
        }

        return $this->subject($subject)
            ->view('emails.weekly_reports_consolidated', [
                's'       => $s,
                'week'    => $this->week,
                'items'   => $this->items,
                'who'     => $this->parentOrStudent,
                'isParent'=> $this->isParent,
            ]);
    }
}
