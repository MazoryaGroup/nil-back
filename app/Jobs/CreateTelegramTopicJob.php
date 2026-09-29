<?php

namespace App\Jobs;

use App\Models\Conversation;
use App\Models\Visitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Telegram\Bot\Api;
use Illuminate\Support\Facades\Log;

class CreateTelegramTopicJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $conversation;
    protected $visitor;

    public function __construct(Conversation $conversation, Visitor $visitor)
    {
        $this->conversation = $conversation;
        $this->visitor = $visitor;
    }

    public function handle()
    {
        try {
            $telegram = new Api(config('services.telegram.bot_token'));
            $topicName = "CID-{$this->conversation->id} | " . ($this->visitor->name ?? $this->visitor->email);

            $response = $telegram->createForumTopic([
                'chat_id' => config('services.telegram.group_id'),
                'name'    => $topicName
            ]);

            $this->conversation->update([
                'telegram_thread_id' => $response->getMessageThreadId()
            ]);
        } catch (\Exception $e) {
            Log::error('Telegram Topic Job Error: ' . $e->getMessage());
            // Retry after 10 seconds
            $this->release(10);
        }
    }
}
