<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use Spatie\GoogleCalendar\Event as GoogleEvent;
use Morilog\Jalali\Jalalian;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Event extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'start_date',
        'end_date',
        'description',
        'type',
        'google_event_id',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date'   => 'datetime',
    ];
    // در مدل Event.php



    protected static function booted()
    {
        // این تابع چک می‌کند که آیا اجازه ارسال به گوگل را داریم یا خیر
        $shouldSync = function () {
            // اگر دستور از طریق ترمینال یا وب‌هوک (Artisan::call) اجرا شده، به گوگل نفرست
            if (app()->runningInConsole()) return false;
            return true;
        };

        static::created(function ($event) use ($shouldSync) {
            if ($shouldSync()) {
                try {
                    $googleEvent = \Spatie\GoogleCalendar\Event::create([
                        'name' => $event->title,
                        'startDateTime' => $event->start_date,
                        'endDateTime' => $event->end_date,
                        'description' => $event->description,
                    ]);

                    // ذخیره آی‌دی بدون صدا زدن مجدد رویدادهای مدل
                    $event->updateQuietly(['google_event_id' => $googleEvent->id]);
                } catch (\Exception $e) {
                    \Log::error("Sync Error (Created): " . $e->getMessage());
                }
            }
        });

        static::updated(function ($event) use ($shouldSync) {
            if ($shouldSync() && $event->google_event_id) {
                try {
                    $googleEvent = \Spatie\GoogleCalendar\Event::find($event->google_event_id);
                    $googleEvent->name = $event->title;
                    $googleEvent->startDateTime = $event->start_date;
                    $googleEvent->endDateTime = $event->end_date;
                    $googleEvent->description = $event->description;
                    $googleEvent->save();
                } catch (\Exception $e) {
                    \Log::error("Sync Error (Updated): " . $e->getMessage());
                }
            }
        });

        static::deleted(function ($event) use ($shouldSync) {
            if ($shouldSync() && $event->google_event_id) {
                try {
                    $googleEvent = \Spatie\GoogleCalendar\Event::find($event->google_event_id);
                    $googleEvent->delete();
                } catch (\Exception $e) {
                    \Log::error("Sync Error (Deleted): " . $e->getMessage());
                }
            }
        });
    }


    protected function startDate(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => is_string($value) && str_contains($value, '/')
                ? Jalalian::fromFormat('Y/m/d H:i:s', $value)->toCarbon()
                : $value,
        );
    }

    protected function endDate(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => is_string($value) && str_contains($value, '/')
                ? Jalalian::fromFormat('Y/m/d H:i:s', $value)->toCarbon()
                : $value,
        );
    }

}
