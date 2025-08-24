<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Week;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WeeklyReportLinksMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public User $parent,
        public Week $week,
        public array $links // [['student_name' => ..., 'url' => ...], ...]
    ) {}

    public function build()
    {
        return $this->subject("Reporte semanal - {$this->week->name}")
            ->view('emails.weekly_reports_links');
    }
}
