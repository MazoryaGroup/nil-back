<?php

namespace App\Filament\Resources\BookingResource\RelationManagers;

use App\Models\Booking;
use App\Models\BookingService;
use App\Models\PosPaymentRequest;
use App\Services\PaymentService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\Staff;
use App\Models\StaffService;
use App\Services\BookingAvailabilityService;
use Carbon\Carbon;

class BookingServicesRelationManager extends RelationManager
{
    protected static string $relationship = 'bookingServices';

    protected static ?string $title = 'خدمات رزرو';

    protected static ?string $recordTitleAttribute = 'id';

    public function form(Form $form): Form
    {
        return $form->schema([

            Forms\Components\Section::make('اصلاح قیمت سرویس')
                ->description(
                    'قیمت اولیه و بیعانه ثابت می‌مانند. فقط قیمت نهایی سرویس قابل تغییر است.'
                )
                ->schema([

                    Forms\Components\Placeholder::make('service_name')
                        ->label('نام سرویس')
                        ->content(
                            fn (?BookingService $record) =>
                                $record?->service?->name ?? '-'
                        ),

                    Forms\Components\Placeholder::make('staff_name')
                        ->label('پرسنل')
                        ->content(
                            fn (?BookingService $record) =>
                                $record?->staff?->name ?? '-'
                        ),

                    Forms\Components\Placeholder::make('original_price')
                        ->label('قیمت اولیه')
                        ->content(
                            fn (?BookingService $record) =>
                            $record
                                ? number_format((float) $record->price) . ' تومان'
                                : '-'
                        ),

                    Forms\Components\Placeholder::make('original_deposit')
                        ->label('بیعانه سرویس')
                        ->content(
                            fn (?BookingService $record) =>
                            $record
                                ? number_format((float) $record->deposit_amount) . ' تومان'
                                : '-'
                        ),

                    Forms\Components\TextInput::make('final_price')
                        ->label('قیمت نهایی سرویس')
                        ->numeric()
                        ->minValue(0)
                        ->suffix('تومان')
                        ->nullable()
                        ->helperText(
                            'اگر خالی باشد، قیمت اولیه سرویس محاسبه می‌شود.'
                        ),

                    Forms\Components\Textarea::make('price_adjustment_reason')
                        ->label('دلیل تغییر قیمت')
                        ->rows(3)
                        ->maxLength(2000)
                        ->nullable()
                        ->columnSpanFull(),

                ])
                ->columns(2),

        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([

                Tables\Columns\TextColumn::make('service.name')
                    ->label('سرویس')
                    ->formatStateUsing(
                        fn ($state, BookingService $record) =>
                        $state ?: 'سرویس #' . $record->service_id
                    ),

                Tables\Columns\TextColumn::make('staff.name')
                    ->label('پرسنل'),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('شروع'),

                Tables\Columns\TextColumn::make('end_time')
                    ->label('پایان'),

                Tables\Columns\TextColumn::make('duration')
                    ->label('مدت')
                    ->suffix(' دقیقه'),

                Tables\Columns\TextColumn::make('price')
                    ->label('قیمت اولیه')
                    ->formatStateUsing(
                        fn ($state) =>
                            number_format((float) $state) . ' تومان'
                    ),

                Tables\Columns\TextColumn::make('final_price')
                    ->label('قیمت اصلاح‌شده')
                    ->formatStateUsing(
                        fn ($state) =>
                        $state === null
                            ? 'بدون تغییر'
                            : number_format((float) $state) . ' تومان'
                    ),

                Tables\Columns\TextColumn::make('effective_price')
                    ->label('قیمت قابل محاسبه')
                    ->state(
                        fn (BookingService $record) =>
                        $record->effective_price
                    )
                    ->formatStateUsing(
                        fn ($state) =>
                            number_format((float) $state) . ' تومان'
                    ),

                Tables\Columns\TextColumn::make('deposit_amount')
                    ->label('بیعانه')
                    ->formatStateUsing(
                        fn ($state) =>
                            number_format((float) $state) . ' تومان'
                    ),

                Tables\Columns\TextColumn::make('price_adjustment_reason')
                    ->label('دلیل تغییر')
                    ->limit(35)
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),

            ])
            ->headerActions([])
            ->actions([

                Tables\Actions\EditAction::make()
                    ->label('اصلاح قیمت')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('تعیین قیمت نهایی سرویس')
                    ->modalSubmitActionLabel('ذخیره قیمت')
                    ->using(function (
                        BookingService $record,
                        array $data
                    ): BookingService {

                        DB::transaction(function () use ($record, $data) {

                            // 1. Lock Booking

                            $booking = Booking::query()
                                ->whereKey($record->booking_id)
                                ->lockForUpdate()
                                ->firstOrFail();

                            // 2. Validate Booking Status

                            if (in_array($booking->status, [
                                'cancelled',
                                'rejected',
                            ], true)) {
                                throw ValidationException::withMessages([
                                    'final_price' =>
                                        'امکان تغییر قیمت رزرو لغوشده یا ردشده وجود ندارد.',
                                ]);
                            }

                            // 3. Check Active Online Payments

                            $hasActiveOnlinePayment = $booking->payments()
                                ->where('status', 'pending')
                                ->where(function ($query) {
                                    $query
                                        ->whereNotNull('initiation_token')
                                        ->orWhere(function ($query) {
                                            $query
                                                ->where('gateway', 'zarinpal')
                                                ->whereNotNull('authority');
                                        });
                                })
                                ->exists();

                            if ($hasActiveOnlinePayment) {
                                throw ValidationException::withMessages([
                                    'final_price' =>
                                        'پرداخت آنلاین این رزرو در حال انجام است. ابتدا وضعیت پرداخت را مشخص کنید.',
                                ]);
                            }

                            // 4. Check Unresolved POS Requests

                            $hasUnresolvedPosPayment = PosPaymentRequest::query()
                                ->whereHas('payment', function ($query) use ($booking) {
                                    $query->where('booking_id', $booking->id);
                                })
                                ->whereNull('completed_at')
                                ->exists();

                            if ($hasUnresolvedPosPayment) {
                                throw ValidationException::withMessages([
                                    'final_price' =>
                                        'درخواست کارت‌خوان تعیین‌تکلیف‌نشده برای این رزرو وجود دارد. ابتدا وضعیت آن را بررسی کنید.',
                                ]);
                            }

                            // 5. Lock and Update Service

                            $service = BookingService::query()
                                ->whereKey($record->id)
                                ->where('booking_id', $booking->id)
                                ->lockForUpdate()
                                ->firstOrFail();

                            $finalPrice = $data['final_price'] ?? null;

                            $service->update([
                                'final_price' =>
                                    $finalPrice === null || $finalPrice === ''
                                        ? null
                                        : $finalPrice,

                                'price_adjustment_reason' =>
                                    $data['price_adjustment_reason'] ?? null,
                            ]);

                            // 6. Recalculate Subtotal

                            $newSubtotal = (float) $booking
                                ->bookingServices()
                                ->get()
                                ->sum(
                                    fn (BookingService $item) =>
                                    $item->effective_price
                                );

                            // 7. Keep Current Discount

                            $discountAmount = (float) $booking->discount_amount;

                            $newTotal = max(
                                0,
                                round($newSubtotal - $discountAmount, 2)
                            );

                            // 8. Update Booking Amounts

                            $booking->update([
                                'subtotal' => round($newSubtotal, 2),
                                'total_amount' => $newTotal,
                            ]);

                            // 9. Recalculate Payment Status

                            app(PaymentService::class)
                                ->recalculateBookingFinancialStatus(
                                    $booking->refresh()
                                );

                            // Existing payments, refunds and deposits
                            // are not modified.

                        }, 3);

                        Notification::make()
                            ->title('قیمت سرویس با موفقیت اصلاح شد')
                            ->success()
                            ->send();

                        return $record->refresh();
                    }),


                Tables\Actions\Action::make('assignStaff')
                    ->label('تخصیص پرسنل')
                    ->icon('heroicon-o-user-plus')
                    ->color('primary')
                    ->modalHeading('تخصیص یا تغییر پرسنل')
                    ->modalSubmitActionLabel('ثبت تخصیص')
                    ->form([
                        Forms\Components\Select::make('staff_id')
                            ->label('پرسنل')
                            ->options(function (BookingService $record): array {
                                $staffIds = StaffService::query()
                                    ->where('service_id', $record->service_id)
                                    ->where('is_active', true)
                                    ->pluck('staff_id');

                                return Staff::query()
                                    ->whereIn('id', $staffIds)
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->toArray();
                            })
                            ->default(fn (BookingService $record) => $record->staff_id)
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (
                        BookingService $record,
                        array $data
                    ): void {

                        try {

                            DB::transaction(function () use ($record, $data) {

                            // Lock the parent booking.
                            $booking = Booking::query()
                                ->whereKey($record->booking_id)
                                ->lockForUpdate()
                                ->firstOrFail();

                            if (in_array($booking->status, [
                                'cancelled',
                                'rejected',
                                'completed',
                                'no_show',
                            ], true)) {
                                throw ValidationException::withMessages([
                                    'staff_id' => 'وضعیت این رزرو اجازه تخصیص پرسنل را نمی‌دهد.',
                                ]);
                            }

                            // Lock the booking service.
                            $bookingService = BookingService::query()
                                ->whereKey($record->id)
                                ->where('booking_id', $booking->id)
                                ->lockForUpdate()
                                ->firstOrFail();

                            // Booking date is stored in Gregorian format.
                            $bookingDate = Carbon::parse(
                                $booking->booking_date
                            )->format('Y-m-d');

                            $bookingStart = Carbon::parse(
                                $bookingDate . ' ' . $bookingService->start_time,
                                'Asia/Tehran'
                            );

                            // Assignment must happen at least 24 hours in advance.
                            if ($bookingStart->lt(
                                Carbon::now('Asia/Tehran')->addHours(24)
                            )) {
                                throw ValidationException::withMessages([
                                    'staff_id' => 'مهلت تخصیص پرسنل به پایان رسیده است. تخصیص باید بیش از ۲۴ ساعت قبل از شروع نوبت انجام شود.',
                                ]);
                            }

                            $staffId = (int) $data['staff_id'];

                            // Serialize assignments targeting the same staff.
                            $staff = Staff::query()
                                ->whereKey($staffId)
                                ->lockForUpdate()
                                ->firstOrFail();

                            if (!$staff->is_active) {
                                throw ValidationException::withMessages([
                                    'staff_id' => 'پرسنل انتخاب‌شده غیرفعال است.',
                                ]);
                            }

                            // Verify that staff can perform this service.
                            $canPerformService = StaffService::query()
                                ->where('staff_id', $staffId)
                                ->where('service_id', $bookingService->service_id)
                                ->where('is_active', true)
                                ->exists();

                            if (!$canPerformService) {
                                throw ValidationException::withMessages([
                                    'staff_id' => 'این پرسنل مجاز به ارائه خدمت انتخاب‌شده نیست.',
                                ]);
                            }

                            // Prevent overlapping assignments.
                            $isAvailable = app(BookingAvailabilityService::class)
                                ->isTimeAvailable(
                                    staffId: $staffId,
                                    date: $bookingDate,
                                    startTime: $bookingService->start_time,
                                    endTime: $bookingService->end_time,
                                    ignoreBookingServiceId: $bookingService->id
                                );

                            if (!$isAvailable) {
                                throw ValidationException::withMessages([
                                    'staff_id' => 'پرسنل در این ساعت ظرفیت ندارد یا برنامه کاری، استراحت، مرخصی یا رزرو دیگری با این زمان تداخل دارد.',
                                ]);
                            }

                            // Only change the staff assignment.
                            $bookingService->update([
                                'staff_id' => $staffId,
                            ]);


                            }, 3);

                        } catch (ValidationException $e) {

                            $messages = collect($e->errors())
                                ->flatten()
                                ->implode("\n");

                            Notification::make()
                                ->title('تخصیص پرسنل انجام نشد')
                                ->body($messages ?: 'اطلاعات انتخاب‌شده معتبر نیست.')
                                ->danger()
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title('پرسنل با موفقیت تخصیص داده شد')
                            ->success()
                            ->send();
                    }),



            ])
            ->bulkActions([]);
    }
}
