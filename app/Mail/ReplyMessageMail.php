<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReplyMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public $msg;
    public $reply;

    public function __construct($msg, $reply)
    {
        $this->msg = $msg;
        $this->reply = $reply;
    }

    public function build()
    {
        return $this
            ->subject('پاسخ پیام شما')
            ->view('emails.reply');
    }
}
