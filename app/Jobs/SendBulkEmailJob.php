<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Models\SendMessage;
use App\Models\MessageLog;

class SendBulkEmailJob implements ShouldQueue
{
    use Queueable;

    public $timeout = 300;
    public $tries = 3;

    public function __construct(
        protected int $messageId,
        protected array $recipients,
        protected ?string $htmlContent = null
    ) {}

    public function handle(): void
    {
        $message = SendMessage::find($this->messageId);
        if (!$message) return;

        $subject = $message->subject ?? $this->getDefaultSubject($message->email_subject_type);
        $success = 0;
        $failed = 0;

        foreach ($this->recipients as $recipient) {
            if (!filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
                $failed++;
                $this->logResult($message->id, $recipient, 'failed', 'ایمیل نامعتبر');
                continue;
            }

            try {
                if ($this->htmlContent) {
                    Mail::send([], [], function ($m) use ($recipient, $subject) {
                        $m->to($recipient)
                            ->subject($subject)
                            ->setBody($this->htmlContent, 'text/html');
                    });
                } else {
                    $template = "emails.{$message->email_subject_type}";
                    if (!view()->exists($template)) {
                        throw new \Exception("قالب {$template} یافت نشد");
                    }
                    Mail::send($template, ['data' => $message], function ($m) use ($recipient, $subject) {
                        $m->to($recipient)->subject($subject);
                    });
                }

                $success++;
                $this->logResult($message->id, $recipient, 'success');
            } catch (\Exception $e) {
                $failed++;
                $this->logResult($message->id, $recipient, 'failed', $e->getMessage());
            }
        }

        Log::info("Email Job completed: message_id={$this->messageId}, success={$success}, failed={$failed}");
    }

    private function getDefaultSubject(?string $type): string
    {
        return match ($type) {
            'add_to_waitlist' => 'اضافه شدن به لیست انتظار',
            'drop_start' => 'شروع دراپ',
            'drop_report' => 'گزارش دراپ',
            'password' => 'رمز عبور جدید',
            'submit_order' => 'تایید سفارش',
            'welcome' => 'خوش آمدید',
            default => 'پیام جدید',
        };
    }

    private function logResult(int $messageId, string $recipient, string $status, ?string $error = null): void
    {
        MessageLog::create([
            'send_message_id' => $messageId,
            'recipient' => $recipient,
            'type' => 'email',
            'status' => $status,
            'error' => $error,
        ]);
    }
}
