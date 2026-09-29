<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <title>فاکتور سفارش</title>
    <style>
        body {
            font-family: 'shabnam', sans-serif;
            direction: rtl !important;
            text-align: right !important;
            unicode-bidi: bidi-override;
            margin: 0;
            padding: 0;
            font-size: 12px; /* فونت کلی کوچکتر */
        }
        .invoice-box {
            width: 450px;  /* کاهش عرض برای جایگیری بهتر در A5 */
            margin: 20px auto;
            padding: 10px;
            border: 1px solid #ddd;
            direction: rtl !important;
        }
        h1 {
            font-size: 18px; /* کوچکتر */
            text-align: right;
            margin-bottom: 15px;
        }
        h2 {
            font-size: 14px; /* کوچکتر */
            text-align: right;
            margin: 10px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            direction: rtl !important;
            text-align: right;
            font-size: 11px; /* کوچکتر */
        }
        th, td {
            border: 1px solid #ddd;
            padding: 6px 8px; /* کمتر padding */
            text-align: right;
            direction: rtl !important;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .order-items-table {
            margin-bottom: 15px;
        }
    </style>

</head>
<body>
<div class="invoice-box">
    <h1>فاکتور سفارش</h1>

    <h2>اطلاعات سفارش</h2>
    <table class="order-table">
        <tr>
            <th>شناسه سفارش</th>
            <td>{{ $order->id }}</td>
        </tr>
        <tr>
            <th>کد رهگیری</th>
            <td>{{ $order->tracking_code ?? '-' }}</td>
        </tr>
        <tr>
            <th>وضعیت</th>
            <td>{{ $order->status ?? '-' }}</td>
        </tr>
        <tr>
            <th>تاریخ سفارش</th>
            <td>{{ $order->created_at?->format('Y-m-d H:i') ?? '-' }}</td>
        </tr>
        <tr>
            <th>مجموع مبلغ سفارش</th>
            <td>{{ number_format($order->amount ?? 0) }} تومان</td>
        </tr>
    </table>

    <h2>آیتم‌های سفارش</h2>
    <table class="order-items-table">
        <thead>
        <tr>
            <th>نام محصول</th>
            <th>تعداد</th>
            <th>قیمت (تومان)</th>
            <th>رنگ</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($order->items as $item)
            <tr>
                <td>{{ $item->product?->name ?? '-' }}</td>
                <td>{{ $item->quantity ?? '-' }}</td>
                <td>{{ number_format($item->price ?? 0) }}</td>
                <td>{{ $item->color ?? '0' }}</td>

            </tr>
        @empty
            <tr>
                <td colspan="4">آیتمی یافت نشد.</td>
            </tr>
        @endforelse
        </tbody>
    </table>

    <h2>اطلاعات مشتری</h2>
    <table class="customer-table">
        <tr>
            <th>نام</th>
            <td>{{ $client->full_name ?? '-' }}</td>
        </tr>
        <tr>
            <th>ایمیل</th>
            <td>{{ $client->email ?? '-' }}</td>
        </tr>
        <tr>
            <th>شماره تلفن</th>
            <td>{{ $client->phone ?? '-' }}</td>
        </tr>
        <tr>
            <th>آدرس</th>
            <td>{{ $address->address ?? '-' }}</td>
        </tr>
        <tr>
            <th>کد پستی</th>
            <td>{{ $address->post_code ?? '-' }}</td>
        </tr>
    </table>
</div>
</body>
</html>
<?php
