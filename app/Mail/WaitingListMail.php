<?php

namespace App\Mail;

use App\Models\WaitingList;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WaitingListMail extends Mailable
{
    use Queueable, SerializesModels;

    public $waiting;

    public function __construct(WaitingList $waiting)
    {
        $this->waiting = $waiting;
    }

    public function build()
    {
        $subject = $this->waiting->lang === 'fa'
            ? 'شما در لیست انتظار هستید 🎉'
            : 'You are on the waiting list 🎉';

        return $this->subject($subject)
            ->view('emails.waiting-list'); // Blade ایمیل با شرط lang
    }
}
