<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ParentAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $subjectLine, public string $bodyText) {}

    public function build()
    {
        return $this->subject($this->subjectLine)
            ->markdown('emails.parent_alert', [
                'bodyText' => $this->bodyText,
            ]);
    }
}
