<?php

namespace App\Jobs;

use App\Models\Conversation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Telegram\Bot\Api;
use Illuminate\Support\Facades\Log;

class SendAdminReplyToTelegramJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $conversation;
    protected $text;

    public function __construct(Conversation $conversation, $text)
    {
        $this->conversation = $conversation;
        $this->text = $text;
    }

    public function handle()
    {
        try {
            $telegram = new Api(config('services.telegram.bot_token'));
            $telegram->sendMessage([
                'chat_id' => config('services.telegram.group_id'),
                'message_thread_id' => $this->conversation->telegram_thread_id,
                'text' => "💬 پاسخ ادمین:\n" . $this->text
            ]);
        } catch (\Exception $e) {
            Log::error('SendAdminReplyToTelegramJob Error: ' . $e->getMessage());
            $this->release(10); // Retry after 10 seconds
        }
    }
}
