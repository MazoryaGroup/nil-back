<?php

namespace App\Jobs;

use App\Models\SendMessage;
use App\Models\WaitingList;
use App\Models\Client;
use App\Models\MessageLog;
use App\Sms\DropSms;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SendBulkSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $messageId;
    protected $phones;
    protected $template;
    protected $token;

    public function __construct($messageId, array $phones, string $template, $token = null)
    {
        $this->messageId = $messageId;
        $this->phones = $phones;
        $this->template = $template;
        $this->token = $token;
    }

    public function handle(): void
    {
        $message = SendMessage::find($this->messageId);

        if (!$message) {
            Log::error("SendMessage not found: {$this->messageId}");
            return;
        }

        foreach ($this->phones as $phone) {
            try {

                /*
                |--------------------------------------------------------------------------
                | Drop SMS Template
                |--------------------------------------------------------------------------
                */
                if ($this->template === 'dropsms') {

                    $user = WaitingList::where('phone', $phone)->first();

                    if (!$user) {
                        $user = Client::where('phone', $phone)->first();
                    }

                    if (!$user) {
                        Log::warning("کاربر با شماره {$phone} پیدا نشد");

                        MessageLog::create([
                            'send_message_id' => $message->id,
                            'recipient' => $phone,
                            'status' => 'failed',
                            'error' => 'کاربر پیدا نشد'
                        ]);

                        continue;
                    }

                    $sms = new DropSms($user);
                    $result = $sms->send();

                    MessageLog::create([
                        'send_message_id' => $message->id,
                        'recipient' => $phone,
                        'status' => $result ? 'success' : 'failed',
                        'error' => $result ? null : 'خطا در ارسال DropSms'
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Password Template
                |--------------------------------------------------------------------------
                */
                elseif ($this->template === 'password') {

                    Log::info("ارسال password sms به {$phone}");

                    // اینجا اگر کلاس PasswordSms داری اضافه کن
                    // مثال:
                    // $sms = new PasswordSms(...);
                    // $sms->send();

                    MessageLog::create([
                        'send_message_id' => $message->id,
                        'recipient' => $phone,
                        'status' => 'success',
                        'error' => null
                    ]);
                }

                /*
                |--------------------------------------------------------------------------
                | Unknown Template
                |--------------------------------------------------------------------------
                */
                else {
                    Log::warning("template ناشناخته: {$this->template}");

                    MessageLog::create([
                        'send_message_id' => $message->id,
                        'recipient' => $phone,
                        'status' => 'failed',
                        'error' => 'template ناشناخته'
                    ]);
                }

            } catch (\Exception $e) {
                Log::error("خطا در ارسال به {$phone}: " . $e->getMessage());

                MessageLog::create([
                    'send_message_id' => $message->id,
                    'recipient' => $phone,
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ]);
            }
        }
    }
}
