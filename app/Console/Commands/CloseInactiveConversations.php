<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Conversation;
use Carbon\Carbon;

class CloseInactiveConversations extends Command
{
    // نام دستوری که در کرون‌جاب صدا می‌زنیم
    protected $signature = 'chat:close-inactive';
    protected $description = 'بستن خودکار مکالماتی که ۱۰ دقیقه پیام نداشته‌اند';

    public function handle()
    {
        $limit = Carbon::now()->subMinutes(10);

        // پیدا کردن چت‌های باز که ۱۰ دقیقه از آخرین فعالیت‌شان گذشته
        $affected = Conversation::where('status', 'open')
            ->where('updated_at', '<', $limit)
            ->update(['status' => 'closed']);

        if ($affected > 0) {
            $this->info("تعداد {$affected} مکالمه به دلیل عدم فعالیت بسته شد.");
        }
    }
}
