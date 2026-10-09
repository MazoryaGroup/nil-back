<?php

namespace App\Filament\Resources\BookingResource\RelationManagers;

use App\Helpers\JalaliHelper;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PosTerminal;
use App\Services\PaymentService;
use App\Services\PosPaymentService;
use App\Services\SmsService;
use App\Services\ZarinPalService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use RuntimeException;
use Throwable;
use App\Services\RefundService;
use App\Services\ShortPaymentLinkService;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'پرداخت‌ها و تسویه حساب';

    protected static ?string $recordTitleAttribute = 'id';

    protected function booking(): Booking
    {
        return $this->getOwnerRecord();
    }

    protected function remainingAmount(): float
    {
        return (float) app(PaymentService::class)
            ->getBookingRemainingAmount(
                $this->booking()->fresh()
            );
    }

    protected function canSettle(): bool
    {
        $booking = $this->booking()->fresh();

        return $booking !== null
            && $booking->status !== 'cancelled'
            && app(PaymentService::class)
                ->getBookingRemainingAmount($booking) > 0;
    }

    /**
     * Check if an existing ZarinPal link
     * can still be shown or sent.
     *
     * This validates local state only.
     * It does not query the gateway.
     */
    protected function validateOnlinePayment(
        Payment $payment
    ): Booking {

        $payment = $payment->fresh();

        if (
            !$payment
            || $payment->type !== 'remaining'
            || $payment->payment_method !== 'online'
            || $payment->gateway !== 'zarinpal'
            || $payment->status !== 'pending'
            || empty($payment->authority)
            || !empty($payment->initiation_token)
        ) {
            throw new RuntimeException(
                'این پرداخت آنلاین در وضعیت قابل استفاده نیست.'
            );
        }

        $booking = $payment->booking?->fresh();

        if (!$booking || $booking->status === 'cancelled') {
            throw new RuntimeException(
                'رزرو لغو شده یا پیدا نشد.'
            );
        }

        if ((int) $payment->client_id !== (int) $booking->client_id) {
            throw new RuntimeException(
                'مشتری پرداخت با مشتری رزرو مطابقت ندارد.'
            );
        }

        $remaining = (float) app(PaymentService::class)
            ->getBookingRemainingAmount($booking);

        if ($remaining <= 0) {
            throw new RuntimeException(
                'مانده قابل پرداختی وجود ندارد.'
            );
        }

        if (abs((float) $payment->amount - $remaining) > 0.001) {
            throw new RuntimeException(
                'مبلغ لینک پرداخت با مانده فعلی رزرو مطابقت ندارد.'
            );
        }

        return $booking;
    }

    /**
     * Rebuild the gateway URL from the saved Authority.
     *
     * IMPORTANT:
     * ZarinPalService::getPaymentUrl() must use the
     * same sandbox/production environment as requestPayment().
     */
    protected function paymentUrl(Payment $payment): string
    {
        return app(ZarinPalService::class)
            ->getPaymentUrl((string) $payment->authority);
    }

    protected function paymentLinkAvailable(Payment $payment): bool
    {
        return $payment->type === 'remaining'
            && $payment->payment_method === 'online'
            && $payment->gateway === 'zarinpal'
            && $payment->status === 'pending'
            && !empty($payment->authority);
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('پرداخت‌ها و تسویه حساب')

            ->description(function (): HtmlString {

                $booking = $this->booking()->fresh();

                $paymentService = app(PaymentService::class);

                $remaining = $paymentService
                    ->getBookingRemainingAmount($booking);

                $total = (float) $booking->total_amount;

                $grossPaid = (float) Payment::query()
                    ->where('booking_id', $booking->id)
                    ->where('status', 'paid')
                    ->whereIn('type', ['deposit', 'remaining'])
                    ->sum('amount');

                $refunded = (float) Payment::query()
                    ->where('booking_id', $booking->id)
                    ->where('type', 'refund')
                    ->whereIn('status', ['paid', 'refunded'])
                    ->sum('amount');

                $netPaid = max(0, $grossPaid - $refunded);

                $overpaid = max(0, $netPaid - $total);

                $format = fn ($amount) =>
                    number_format((float) $amount) . ' تومان';

                return new HtmlString(
                    '<div style="display:flex;gap:25px;flex-wrap:wrap;
        padding:15px 0;direction:rtl">

            <div>
                <strong>مبلغ نهایی:</strong>
                ' . $format($total) . '
            </div>

            <div>
                <strong>پرداخت خالص:</strong>
                ' . $format($netPaid) . '
            </div>

            <div>
                <strong>مانده بدهی:</strong>
                ' . $format($remaining) . '
            </div>

            <div>
                <strong>اضافه‌پرداخت:</strong>
                <span style="font-weight:bold;color:#d97706">
                    ' . $format($overpaid) . '
                </span>
            </div>

        </div>'
                );
            })

            ->columns([

                Tables\Columns\TextColumn::make('id')
                    ->label('#')
                    ->sortable(),

                Tables\Columns\TextColumn::make('client.name')
                    ->label('مشتری'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('مبلغ')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' تومان'),

                Tables\Columns\TextColumn::make('type')
                    ->label('نوع پرداخت')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'deposit' => 'بیعانه',
                        'remaining' => 'تسویه',
                        'refund' => 'بازپرداخت',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('payment_method')
                    ->label('روش پرداخت')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'cash' => 'نقدی',
                        'pos' => 'کارت‌خوان',
                        'online' => 'آنلاین',
                        'bank_transfer' => 'کارت‌به‌کارت',
                        default => $state ?: '-',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('وضعیت')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending' => 'در انتظار',
                        'paid' => 'پرداخت‌شده',
                        'failed' => 'ناموفق',
                        'refunded' => 'بازپرداخت‌شده',
                        default => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'paid' => 'success',
                        'pending' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('reference_number')
                    ->label('شماره پیگیری')
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('paid_at')
                    ->label('زمان پرداخت')
                    ->formatStateUsing(
                        fn ($state) => $state
                            ? JalaliHelper::dateTime(
                                $state,
                                'Y/m/d H:i'
                            )
                            : '-'
                    ),
            ])

            ->headerActions([

                /*
                |--------------------------------------------------------------------------
                | Cash settlement
                |--------------------------------------------------------------------------
                */

                Tables\Actions\Action::make('settleCash')
                    ->label('تسویه نقدی')
                    ->icon('heroicon-o-banknotes')
                    ->color('primary')
                    ->visible(fn () => $this->canSettle())

                    ->form([
                        Forms\Components\Placeholder::make('remaining')
                            ->label('مبلغ قابل تسویه')
                            ->content(fn () =>
                                number_format($this->remainingAmount())
                                . ' تومان'
                            ),

                        Forms\Components\Placeholder::make('method')
                            ->label('روش پرداخت')
                            ->content('نقدی'),
                    ])

                    ->requiresConfirmation()
                    ->modalHeading('تأیید تسویه نقدی')
                    ->modalDescription(
                        'فقط پس از دریافت وجه نقد، پرداخت را تأیید کنید.'
                    )
                    ->modalSubmitActionLabel('تأیید دریافت وجه')

                    ->action(function () {

                        try {

                            $payment = app(PaymentService::class)
                                ->settleRemainingByCash(
                                    booking: $this->booking(),
                                    createdBy: auth()->id()
                                );

                            Notification::make()
                                ->title('تسویه نقدی ثبت شد')
                                ->body(
                                    'مبلغ ' .
                                    number_format((float) $payment->amount) .
                                    ' تومان تسویه شد.'
                                )
                                ->success()
                                ->send();

                        } catch (Throwable $e) {

                            report($e);

                            Notification::make()
                                ->title('خطا در تسویه نقدی')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                /*
                |--------------------------------------------------------------------------
                | Manual POS settlement
                |--------------------------------------------------------------------------
                */

                Tables\Actions\Action::make('settlePos')
                    ->label('تسویه با کارت‌خوان')
                    ->icon('heroicon-o-credit-card')
                    ->color('primary')
                    ->visible(fn () => $this->canSettle())

                    ->form([

                        Forms\Components\Placeholder::make('remaining_pos')
                            ->label('مبلغ قابل تسویه')
                            ->content(fn () =>
                                number_format($this->remainingAmount())
                                . ' تومان'
                            ),

                        Forms\Components\Select::make('pos_terminal_id')
                            ->label('دستگاه کارت‌خوان')
                            ->options(fn () =>
                            PosTerminal::query()
                                ->where('is_active', true)
                                ->where('connection_type', 'manual')
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray()
                            )
                            ->searchable()
                            ->required(),

                        Forms\Components\TextInput::make('reference_number')
                            ->label('شماره پیگیری تراکنش')
                            ->placeholder('شماره پیگیری روی رسید کارت‌خوان')
                            ->required()
                            ->maxLength(100),
                    ])

                    ->requiresConfirmation()
                    ->modalHeading('تأیید تسویه با کارت‌خوان')
                    ->modalDescription(
                        'فقط پس از دریافت وجه و تأیید تراکنش موفق روی کارت‌خوان، پرداخت را ثبت کنید.'
                    )
                    ->modalSubmitActionLabel('ثبت پرداخت کارت‌خوان')

                    ->action(function (array $data) {

                        try {

                            $payment = app(PaymentService::class)
                                ->settleRemainingByPos(
                                    booking: $this->booking(),
                                    terminalId: (int) $data['pos_terminal_id'],
                                    referenceNumber: trim(
                                        (string) $data['reference_number']
                                    ),
                                    createdBy: auth()->id()
                                );

                            Notification::make()
                                ->title('پرداخت کارت‌خوان ثبت شد')
                                ->body(
                                    'مبلغ ' .
                                    number_format((float) $payment->amount) .
                                    ' تومان تسویه شد.'
                                )
                                ->success()
                                ->send();

                        } catch (Throwable $e) {

                            report($e);

                            Notification::make()
                                ->title('خطا در پرداخت کارت‌خوان')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                /*
                |--------------------------------------------------------------------------
                | ZarinPal payment link + automatic SMS
                |--------------------------------------------------------------------------
                */

                Tables\Actions\Action::make('onlinePaymentLink')
                    ->label('لینک پرداخت آنلاین')
                    ->icon('heroicon-o-link')
                    ->color('primary')
                    ->visible(fn () => $this->canSettle())

                    ->form([

                        Forms\Components\Placeholder::make('online_amount')
                            ->label('مبلغ قابل پرداخت')
                            ->content(fn () =>
                                number_format($this->remainingAmount())
                                . ' تومان'
                            ),

                        Forms\Components\Placeholder::make('gateway')
                            ->label('درگاه پرداخت')
                            ->content('زرین‌پال'),
                    ])

                    ->modalHeading('ایجاد لینک پرداخت زرین‌پال')
                    ->modalDescription(
                        'پس از ایجاد لینک، در صورت فعال بودن قالب SMS.ir، لینک برای مشتری پیامک می‌شود.'
                    )
                    ->modalSubmitActionLabel('ایجاد لینک پرداخت')

                    ->action(function () {

                        $paymentService = app(PaymentService::class);
                        $payment = null;
                        $authority = null;

                        try {

                            $booking = $this->booking()->fresh();

                            if ($booking->status === 'cancelled') {
                                throw new RuntimeException(
                                    'رزرو لغو شده است.'
                                );
                            }

                            $client = $booking->client;

                            if (!$client) {
                                throw new RuntimeException(
                                    'مشتری رزرو پیدا نشد.'
                                );
                            }

                            /*
                            | 1. Reserve payment initiation
                            */

                            $reservation = $paymentService
                                ->reserveRemainingPaymentInitiation(
                                    $booking,
                                    $client
                                );

                            $payment = $reservation['payment'];
                            $token = $reservation['token'];

                            /*
                            | 2. Request payment from ZarinPal
                            */

                            $paymentData = app(ZarinPalService::class)
                                ->requestPayment(
                                    amount: (float) $payment->amount,
                                    callbackUrl: config(
                                        'services.zarinpal.callback_url'
                                    ),
                                    description:
                                    'NIL booking remaining payment #'
                                    . $booking->id,
                                    email: $client->email,
                                    mobile: $client->phone
                                );

                            $authority = trim(
                                (string) ($paymentData['authority'] ?? '')
                            );

                            $paymentUrl = trim(
                                (string) ($paymentData['payment_url'] ?? '')
                            );

                            if ($authority === '' || $paymentUrl === '') {
                                throw new RuntimeException(
                                    'زرین‌پال اطلاعات معتبر پرداخت برنگرداند.'
                                );
                            }

                            /*
                            | 3. Save Authority
                            */

                            $payment = $paymentService
                                ->completeRemainingPaymentInitiation(
                                    payment: $payment,
                                    token: $token,
                                    authority: $authority
                                );
                            $shortPaymentUrl = app(ShortPaymentLinkService::class)
                                ->getOrCreate($payment);

                            /*
                            | 4. Send SMS independently
                            */

                            $smsSent = false;
                            $smsError = null;

                            try {

                                $templateId = (int) config(
                                    'services.smsir.templates.payment_link'
                                );

                                if ($templateId <= 0) {
                                    throw new RuntimeException(
                                        'قالب پیامکی هنوز تنظیم نشده است.'
                                    );
                                }

                                app(SmsService::class)
                                    ->sendPaymentLink(
                                        booking: $booking,
                                        paymentUrl: $shortPaymentUrl
                                    );

                                $smsSent = true;

                            } catch (Throwable $smsException) {

                                $smsError = $smsException->getMessage();

                                Log::warning('Payment link SMS failed', [
                                    'booking_id' => $booking->id,
                                    'payment_id' => $payment->id,
                                    'error' => $smsError,
                                ]);
                            }

                            if ($smsSent) {

                                Notification::make()
                                    ->title('لینک پرداخت ایجاد و ارسال شد')
                                    ->body(
                                        'لینک زرین‌پال به شماره مشتری پیامک شد.'
                                    )
                                    ->success()
                                    ->send();

                            } else {

                                Notification::make()
                                    ->title('لینک پرداخت ایجاد شد')
                                    ->body(
                                        'پرداخت ایجاد شد، اما پیامک ارسال نشد. '
                                        . ($smsError ?? '')
                                    )
                                    ->warning()
                                    ->send();
                            }

                        } catch (Throwable $e) {

                            Log::error(
                                'Filament ZarinPal payment initiation failed',
                                [
                                    'booking_id' => $this->booking()->id,
                                    'payment_id' => $payment?->id,
                                    'authority' => $authority,
                                    'error' => $e->getMessage(),
                                ]
                            );

                            Notification::make()
                                ->title('خطا در ایجاد لینک پرداخت')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])

            ->actions([

                Tables\Actions\Action::make('refundOverpayment')
                    ->label('ثبت استرداد')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')

                    ->visible(function (Payment $record): bool {

                        if (
                            $record->status !== 'paid' ||
                            !in_array($record->type, ['deposit', 'remaining'], true)
                        ) {
                            return false;
                        }

                        $booking = $record->booking;

                        if (!$booking) {
                            return false;
                        }

                        $grossPaid = (float) $booking->payments()
                            ->where('status', 'paid')
                            ->whereIn('type', ['deposit', 'remaining'])
                            ->sum('amount');

                        $refunded = (float) $booking->payments()
                            ->where('type', 'refund')
                            ->whereIn('status', ['paid', 'refunded'])
                            ->sum('amount');

                        $overpaid = max(
                            0,
                            round(
                                $grossPaid
                                - $refunded
                                - (float) $booking->total_amount,
                                2
                            )
                        );

                        $alreadyRefunded = (float) $record->refunds()
                            ->whereIn('status', ['paid', 'refunded'])
                            ->sum('amount');

                        $paymentRefundable = max(
                            0,
                            (float) $record->amount - $alreadyRefunded
                        );

                        return $overpaid > 0 && $paymentRefundable > 0;
                    })

                    ->form([

                        Forms\Components\Placeholder::make('refund_amount_available')
                            ->label('اضافه‌پرداخت رزرو')
                            ->content(function (Payment $record) {

                                $booking = $record->booking;

                                $grossPaid = (float) $booking->payments()
                                    ->where('status', 'paid')
                                    ->whereIn('type', ['deposit', 'remaining'])
                                    ->sum('amount');

                                $refunded = (float) $booking->payments()
                                    ->where('type', 'refund')
                                    ->whereIn('status', ['paid', 'refunded'])
                                    ->sum('amount');

                                $overpaid = max(
                                    0,
                                    $grossPaid
                                    - $refunded
                                    - (float) $booking->total_amount
                                );

                                $paymentRefundable = max(
                                    0,
                                    (float) $record->amount
                                    - (float) $record->refunds()
                                        ->whereIn('status', ['paid', 'refunded'])
                                        ->sum('amount')
                                );

                                return number_format(
                                        min($overpaid, $paymentRefundable)
                                    ) . ' تومان';
                            }),

                        Forms\Components\TextInput::make('amount')
                            ->label('مبلغ استرداد')
                            ->numeric()
                            ->required()
                            ->gt(0)
                            ->suffix('تومان')
                            ->helperText(
                                'مبلغ استرداد نمی‌تواند از اضافه‌پرداخت و مانده قابل استرداد این تراکنش بیشتر باشد.'
                            ),

                        Forms\Components\Select::make('payment_method')
                            ->label('روش استرداد وجه')
                            ->options([
                                'cash' => 'نقدی',
                                'pos' => 'کارت‌خوان / دستگاه بانکی',
                                'bank_transfer' => 'انتقال بانکی',
                                'other' => 'سایر',
                            ])
                            ->required()
                            ->live(),

                        Forms\Components\TextInput::make('reference_number')
                            ->label('شماره پیگیری استرداد')
                            ->maxLength(100)
                            ->required(
                                fn (Forms\Get $get): bool =>
                                    $get('payment_method') !== 'cash'
                            )
                            ->helperText(
                                'برای استرداد غیرنقدی شماره پیگیری انتقال واقعی وجه را وارد کنید.'
                            ),

                        Forms\Components\Textarea::make('description')
                            ->label('توضیحات استرداد')
                            ->rows(3)
                            ->maxLength(1000)
                            ->required(),

                        Forms\Components\Checkbox::make('refund_confirmed')
                            ->label('تأیید می‌کنم که وجه واقعاً به مشتری برگشت داده شده است.')
                            ->accepted()
                            ->required(),

                    ])

                    ->modalHeading('ثبت استرداد اضافه‌پرداخت')
                    ->modalDescription(
                        'این عملیات انتقال بانکی انجام نمی‌دهد؛ فقط وجهی را که واقعاً مسترد شده، در پرداخت‌ها و حسابداری ثبت می‌کند.'
                    )
                    ->modalSubmitActionLabel('ثبت نهایی استرداد')
                    ->modalWidth('lg')

                    ->action(function (Payment $record, array $data) {

                        try {

                            if (empty($data['refund_confirmed'])) {
                                throw new RuntimeException(
                                    'تأیید بازگشت واقعی وجه الزامی است.'
                                );
                            }

                            $method = (string) $data['payment_method'];

                            $referenceNumber = trim(
                                (string) ($data['reference_number'] ?? '')
                            );

                            if (
                                $method !== 'cash'
                                && $referenceNumber === ''
                            ) {
                                throw new RuntimeException(
                                    'شماره پیگیری برای استرداد غیرنقدی الزامی است.'
                                );
                            }

                            $refund = app(RefundService::class)
                                ->createOverpaymentRefund(
                                    payment: $record,
                                    amount: (float) $data['amount'],
                                    paymentMethod: $method,
                                    referenceNumber: $referenceNumber ?: null,
                                    description: trim(
                                        (string) $data['description']
                                    ),
                                    createdBy: auth()->id()
                                );

                            Notification::make()
                                ->title('استرداد با موفقیت ثبت شد')
                                ->body(
                                    'مبلغ '
                                    . number_format((float) $refund->amount)
                                    . ' تومان در سیستم مالی ثبت شد.'
                                )
                                ->success()
                                ->send();

                        } catch (Throwable $e) {

                            report($e);

                            Notification::make()
                                ->title('خطا در ثبت استرداد')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
                /*
                |--------------------------------------------------------------------------
                | Show existing payment link
                |--------------------------------------------------------------------------
                */

                Tables\Actions\Action::make('showPaymentLink')
                    ->label('نمایش لینک')
                    ->icon('heroicon-o-link')
                    ->color('info')
                    ->visible(
                        fn (Payment $record): bool =>
                        $this->paymentLinkAvailable($record)
                    )

                    ->modalHeading('لینک پرداخت زرین‌پال')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('بستن')

                    ->modalContent(function (Payment $record): HtmlString {

                        $this->validateOnlinePayment($record);

                        $url = $this->paymentUrl($record);

                        $safeUrl = e($url);

                        return new HtmlString(
                            '<div style="direction:ltr;word-break:break-all;
                            padding:15px;border:1px solid #ddd;
                            border-radius:8px">

                                <a href="' . $safeUrl . '"
                                   target="_blank"
                                   rel="noopener noreferrer">
                                    ' . $safeUrl . '
                                </a>

                            </div>'
                        );
                    }),

                /*
                |--------------------------------------------------------------------------
                | Resend payment link SMS
                |--------------------------------------------------------------------------
                */

                Tables\Actions\Action::make('resendPaymentLink')
                    ->label('ارسال مجدد پیامک')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('warning')
                    ->visible(
                        fn (Payment $record): bool =>
                        $this->paymentLinkAvailable($record)
                    )

                    ->requiresConfirmation()
                    ->modalHeading('ارسال مجدد لینک پرداخت')
                    ->modalDescription(
                        'همان لینک پرداخت قبلی برای مشتری پیامک می‌شود. درخواست پرداخت جدیدی ایجاد نخواهد شد.'
                    )
                    ->modalSubmitActionLabel('ارسال پیامک')

                    ->action(function (Payment $record) {

                        try {

                            $booking = $this->validateOnlinePayment(
                                $record
                            );

                            $url = app(ShortPaymentLinkService::class)
                                ->getOrCreate($record);

                            app(SmsService::class)
                                ->sendPaymentLink(
                                    booking: $booking,
                                    paymentUrl: $url
                                );

                            Notification::make()
                                ->title('پیامک ارسال شد')
                                ->body(
                                    'لینک پرداخت مجدداً برای مشتری ارسال شد.'
                                )
                                ->success()
                                ->send();

                        } catch (Throwable $e) {

                            report($e);

                            Notification::make()
                                ->title('خطا در ارسال مجدد پیامک')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ])

            ->bulkActions([]);
    }
}
