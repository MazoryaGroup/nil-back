<?php
//
//namespace App\Filament\Widgets;
//
//use App\Models\Conversation;
//use App\Models\Message;
//use App\Models\Order;
//use App\Models\Product;
//use App\Models\Service;
//use App\Models\Project;
//use Filament\Widgets\StatsOverviewWidget as BaseWidget;
//use Filament\Widgets\StatsOverviewWidget\Stat; // تغییر به Stat
//
//class RecentMessagesWidget extends BaseWidget
//{
//    // فواصل زمانی در نسخه ۳ به صورت string ساده هم پذیرفته می‌شوند
//    protected static ?string $pollingInterval = '3s';
//
//    protected static ?int $sort = 0; // عدد کمتر = اولویت بالاتر (بالاتر نمایش داده می‌شود)
//
//    protected int | string | array $columnSpan = 'full';
//    protected function getStats(): array // تغییر نام متد
//    {
//        $unreadCount = Message::where('sender', 'visitor')->where('is_read', false)->count();
//
//        return [
//            Stat::make('پیام‌های جدید چت', $unreadCount)
//                ->description('تعداد کل پیام‌های پاسخ داده نشده')
//                // تغییر آیکون به نسخه ۲ (chat-bubble-left-right معادل جدید chat است)
//                ->descriptionIcon($unreadCount > 0 ? 'heroicon-m-chat-bubble-left-right' : 'heroicon-m-check-circle')
//                ->color($unreadCount > 0 ? 'danger' : 'success')
//                ->extraAttributes([
//                    'class' => $unreadCount > 0 ? 'animate-pulse' : '',
//                ]),
//            Stat::make('تعداد فروش', Order::where('status', 'paid')->count())
//                ->description('محصول فروخته شده ')
//                ->descriptionIcon('heroicon-s-shopping-cart')
//                ->color('primary'),
//
//            Stat::make('تعداد مجصول', Product::count())
//                ->description('تعداد مجصول')
//                ->descriptionIcon('heroicon-s-shopping-bag')
//                ->color('primary'),
//        ];
//    }
//}
