<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Visitor;
use App\Models\Message;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Telegram\Bot\Api;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class SendMessageToTelegramJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $conversation;
    protected $visitor;
    protected $message;

    public function __construct(Conversation $conversation, Visitor $visitor, Message $message)
    {
        $this->conversation = $conversation;
        $this->visitor = $visitor;
        $this->message = $message;
    }

    public function handle()
    {
        try {
            $telegram = new Api(config('services.telegram.bot_token'));
            $groupId = config('services.telegram.group_id');

            // 🔴 اصلاح اصلی: چک کردن وجود Thread ID
            $threadId = $this->conversation->telegram_thread_id;

            if (!$threadId) {
                // اگر هنوز تاپیک ساخته نشده، جاب را ۵ ثانیه دیگر دوباره امتحان کن
                Log::info("Topic not ready for Conv: {$this->conversation->id}. Retrying...");
                return $this->release(5);
            }

            $caption = "🆕 پیام جدید از: {$this->visitor->email}\n";
            // ... باقی کد قبلی شما برای ارسال فایل یا متن ...

            if ($this->message->file_path) {
                // منطق ارسال فایل
            } else {
                // مطمئن شو که کپشن یا متن پیام مقدار دارد
                $messageText = $this->message->message ?? 'بدون متن';

                $telegram->sendMessage([
                    'chat_id' => $groupId,
                    'message_thread_id' => $threadId,
                    'text' => $messageText, // از متغیر درست استفاده کن
                    'parse_mode' => 'HTML' // اضافه کردن این خط کمک می‌کند ایموجی‌ها و فرمت‌ها بهتر نمایش داده شوند
                ]);
            }
        } catch (\Exception $e) {
            Log::error('SendMessageToTelegramJob Error: ' . $e->getMessage());
            $this->release(10);
        }
    }
}
