<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use InteractsWithSockets, SerializesModels;

    public $message;
    public $conversationUuid;

    public function __construct(Message $message)
    {
        $this->message = $message;
        $this->conversationUuid = $message->conversation->uuid;
    }

    public function broadcastOn()
    {
        // این کانال عمومی برای ادمین‌هاست تا پیام‌های جدید رو بشنون
        return new Channel('admin-notifications');
    }

    public function broadcastWith()
    {
        return [
            'message' => $this->message->message,
            'visitor_name' => $this->message->conversation->visitor->name ?? 'مهمان',
            'conversation_uuid' => $this->message->conversation->uuid,
            'time' => $this->message->created_at->diffForHumans()
        ];
    }
}
