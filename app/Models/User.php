<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser; // اضافه شد
use Filament\Panel; // اضافه شد
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class User extends Authenticatable implements FilamentUser // این Interface حتما اضافه شود
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'profile_image',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed', // در لاراول ۱۰ و ۱۱ این مورد الزامی است
    ];

    /**
     * تعیین سطح دسترسی برای ورود به پنل
     * در نسخه ۳، این متد برای امنیت الزامی است
     */
    public function canAccessPanel(Panel $panel): bool
    {
        // در اینجا می‌توانید شرط بگذارید، مثلا فقط ایمیل‌های خاص یا نقش ادمین
        // فعلاً برای تست روی true می‌گذاریم
        return true;
        // پیشنهاد: return str_ends_with($this->email, '@gmail.com');
    }

    /**
     * سیستم نوتیفیکیشن در نسخه ۳ کمی هوشمندتر شده
     */
    public function getFilamentNotifications(): \Illuminate\Support\Collection
    {
        return $this->unreadNotifications;
    }
    // این متد را داخل کلاس User اضافه کنید
    public function isAdmin(): bool
    {
        // اگر فیلد خاصی مثل 'is_admin' یا 'role' دارید، اینجا چک کنید
        // فعلاً برای اینکه ارور رفع شود و بتوانید وارد شوید، آن را روی true می‌گذاریم
        return true;

        // مثال واقعی در آینده:
        // return $this->role === 'admin';
    }
}
