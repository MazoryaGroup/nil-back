<?php

namespace App\Mail;

use App\Models\Contact;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;
use Illuminate\Contracts\Queue\ShouldQueue; // 👈 این خط را حتماً اضافه کن

class NewContactMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $contactData;

    public function __construct(Contact $contact)
    {
        $this->contactData = $contact;

        // لاگ هنگام ساخته شدن کلاس
        Log::info('Mail Constructor Fired', [
            'email' => $contact->email,
            'name' => $contact->name,
        ]);
    }

    public function build()
    {
        // تنظیم زبان
        App::setLocale($this->contactData->lang);

        return $this->subject(__('mail.subject'))
            ->view('emails.new-message')
            ->with([
                'messageData' => $this->contactData, // <-- اینو اضافه کردیم
            ]);
    }
}
