<?php

namespace App\Filament\Widgets;

use App\Models\Event;
use Filament\Forms\Form;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\DateTimePicker;
use Saade\FilamentFullCalendar\Actions\EditAction;
use Saade\FilamentFullCalendar\Actions\CreateAction;
use Saade\FilamentFullCalendar\Actions\DeleteAction;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class EventsCalendarWidget extends FullCalendarWidget
{
    protected static ?int $sort = 5;

    protected int | string | array $columnSpan = 'full';

    public Model | string | null $model = Event::class;

    public function config(): array
    {
        return [
            'height' => '600px',
            'selectable' => true, // فعال کردن قابلیت کلیک و کشیدن روی تقویم
            'headerToolbar' => [
                'left' => 'prev,next today',
                'center' => 'title',
                'right' => 'dayGridMonth,timeGridWeek,timeGridDay'
            ],
            'locale' => 'fa',
            'firstDay' => 6, // شروع از شنبه
        ];
    }

    /**
     * وقتی روی یک روز یا ساعت کلیک می‌شود
     */
    public function onSelect(array $arguments): void
    {
        // این کد به جای جایگزینی، مستقیماً اکشن را صدا می‌زند
        $this->mountAction('create', [
            'start' => $arguments['start'] ?? null,
            'end' => $arguments['end'] ?? null,
        ]);
    }

    /**
     * دریافت داده‌ها برای نمایش روی تقویم
     */
    public function fetchEvents(array $fetchInfo): array
    {
        return Event::query()
            ->where('end_date', '>=', $fetchInfo['start'])
            ->where('start_date', '<=', $fetchInfo['end'])
            ->get()
            ->map(fn (Event $event) => [
                'id'    => $event->id,
                'title' => $event->title,
                'start' => $event->start_date->toIso8601String(),
                'end'   => $event->end_date->toIso8601String(),
                'color' => $event->type === 'meeting' ? '#f59e0b' : '#3b82f6',
            ])->toArray();
    }

    /**
     * فرم ساخت و ویرایش
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('title')
                    ->label('عنوان رویداد')
                    ->required()
                    ->placeholder('مثلاً: جلسه فنی'),

                Select::make('type')
                    ->label('نوع رویداد')
                    ->options([
                        'meeting' => 'جلسه',
                        'task' => 'تسک',
                        'holiday' => 'تعطیلات',
                        'etc' => 'غیره'
                    ])
                    ->default('meeting')
                    ,

                DateTimePicker::make('start_date')
                    ->label('تاریخ و زمان شروع')
                    ->required()
                    ->seconds(false),

                DateTimePicker::make('end_date')
                    ->label('تاریخ و زمان پایان')
                    ->required()
                    ->seconds(false),

                Textarea::make('description')
                    ->label('توضیحات تکمیلی')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    /**
     * دکمه ساخت (Header)
     */
    protected function headerActions(): array
    {
        return [
            CreateAction::make()
                ->label('رویداد جدید')
                ->form(fn (Form $form) => $this->form($form)->getComponents()) // 👈 اصلاح شد
                ->mountUsing(function (array $arguments) {
                    return [
                        'start_date' => isset($arguments['start']) ? Carbon::parse($arguments['start']) : now(),
                        'end_date' => isset($arguments['end']) ? Carbon::parse($arguments['end']) : now()->addHour(),
                    ];
                }),
        ];
    }

    protected function modalActions(): array
    {
        return [
            EditAction::make()
                ->form(fn (Form $form) => $this->form($form)->getComponents()) // 👈 اصلاح شد
                ->mountUsing(function (Event $record) {
                    return [
                        'title' => $record->title,
                        'type' => $record->type,
                        'start_date' => $record->start_date,
                        'end_date' => $record->end_date,
                        'description' => $record->description,
                    ];
                }),
            DeleteAction::make(),
        ];
    }
}
