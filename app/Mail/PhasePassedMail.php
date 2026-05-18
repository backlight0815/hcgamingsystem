<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PhasePassedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $passdetails;

    public function __construct($passdetails)
    {
        $this->passdetails = $passdetails;
    }

    public function build()
    {
        return $this->subject($this->passdetails['subject'] ?? '🎉 Phase Passed Notification')
                    ->view('emails.phase_passed')
                    ->with(['passdetails' => $this->passdetails]);
    }
}
