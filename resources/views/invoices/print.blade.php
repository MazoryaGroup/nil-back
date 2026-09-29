<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>برچسب پستی | MIM TEHRAN</title>
    <style>
        @font-face {
            font-family: 'Vazir';
            src: url('asset/fonts/Vazir.woff2') format('woff2');
            font-weight: normal;
            font-style: normal;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: #e0e0e0;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            font-family: 'Vazir', Tahoma, 'Segoe UI', sans-serif;
            padding: 10px;
        }

        .label {
            width: 10cm;
            height: auto;
            min-height: 15cm;
            background-color: white;
            box-sizing: border-box;
            color: black;
            display: flex;
            flex-direction: column;
            padding: 0.5cm;
            box-shadow: 0 0 5px rgba(0,0,0,0.1);
            page-break-after: avoid;
            break-inside: avoid;
        }

        .logo-section {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 0.5cm;
            margin-top: -1.3cm;
        }

        .logo-container {
            width: 10.5cm;
            height: 2.5cm;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .logo-container img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        /* نوار فرستنده - رنگ تغییر کرد */
        .sender-section {
            background-color: #8B5E3C;
            color: white;
            width: 100%;
            padding: 0.3cm 0.4cm;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 10pt;
            margin-bottom: 0.2cm;
            font-weight: bold;
            flex-wrap: wrap;
            gap: 5px;
            box-sizing: border-box;
            border-radius: 6px;
        }

        .separator {
            width: 100%;
            border-top: 1px dashed #ccc;
            margin: 0.2cm 0;
        }

        /* باکس اطلاعات - رنگ حاشیه و پس‌زمینه تغییر کرد */
        .info-box {
            width: 100%;
            border: 1px solid #8B5E3C;
            padding: 0.3cm 0.5cm;
            margin-bottom: 0.3cm;
            font-size: 11pt;
            text-align: right;
            font-weight: bold;
            line-height: 1.6;
            box-sizing: border-box;
            word-wrap: break-word;
            overflow-wrap: break-word;
            background-color: #fefaf5;
            border-radius: 6px;
        }

        .info-box:last-child {
            margin-bottom: 0;
        }

        .empty-field {
            color: #c62828;
            font-style: italic;
            font-weight: normal;
        }

        .barcode {
            margin-top: 0.5cm;
            text-align: center;
            border-top: 1px dashed #ccc;
            padding-top: 0.3cm;
            font-size: 8pt;
            color: #666;
        }

        .barcode img {
            max-width: 100%;
            height: auto;
        }

        @media print {
            @page {
                size: 10cm 15cm;
                margin: 0;
            }
            body {
                background-color: white;
                margin: 0;
                padding: 0;
            }
            .label {
                width: 10cm;
                min-height: 15cm;
                margin: 0;
                padding: 0.5cm;
                box-shadow: none;
                page-break-after: avoid;
                page-break-inside: avoid;
            }
            .sender-section {
                background-color: #8B5E3C !important;
                color: white !important;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            .info-box {
                background-color: #fefaf5 !important;
                print-color-adjust: exact;
                -webkit-print-color-adjust: exact;
            }
            .empty-field {
                color: #c62828 !important;
            }
        }

        @media (max-width: 600px) {
            .label {
                width: 100%;
                margin: 10px;
            }
            .sender-section {
                font-size: 9pt;
            }
            .info-box {
                font-size: 10pt;
            }
        }
    </style>
</head>
<body>
<div class="label">
    <div class="logo-section">
        <div class="logo-container">
            <img src="https://mimtehran.ir/asset/imag/logo.jpg"
                 alt="لوگو"
                 onerror="this.style.display='none'">
        </div>
    </div>

    <div class="sender-section">
        <span>📦 فرستنده: میم تهران</span>
        <span>09198401474</span>
    </div>

    <div class="separator"></div>

    <div class="info-box">
        👤 گیرنده:
        @if(isset($client) && !empty(trim(($client->first_name ?? '') . ' ' . ($client->last_name ?? ''))))
            {{ trim($client->first_name . ' ' . ($client->last_name ?? '')) }}
        @else
            <span class="empty-field">❌ نام ثبت نشده</span>
        @endif
    </div>

    <div class="info-box">
        📱 تلفن:
        @if(isset($client) && !empty($client->phone))
            {{ $client->phone }}
        @else
            <span class="empty-field">❌ تلفن ثبت نشده</span>
        @endif
    </div>

    <div class="info-box">
        📍 آدرس:
        @php
            $addressText = null;
            if (isset($address) && !empty($address->address)) {
                $addressText = $address->address;
            } elseif (isset($client) && $client && isset($client->addresses) && $client->addresses->isNotEmpty()) {
                $addressText = $client->addresses->first()->address;
            } elseif (isset($order) && isset($order->address) && !empty($order->address->address)) {
                $addressText = $order->address->address;
            }
        @endphp

        @if($addressText)
            {{ $addressText }}
        @else
            <span class="empty-field">❌ آدرس ثبت نشده</span>
        @endif
    </div>

    @if(isset($order) && !empty($order->tracking_code))
        <div class="info-box">
            🔢 کد رهگیری:
            {{ $order->tracking_code }}
        </div>
    @endif

    @if(isset($order) && !empty($order->tracking_code))
        <div class="barcode">
            <div style="font-family: monospace; font-size: 14pt; letter-spacing: 2px;">
                {{ $order->tracking_code }}
            </div>
            <div style="font-size: 8pt; margin-top: 5px;">
                * کد رهگیری سفارش *
            </div>
        </div>
    @endif
</div>
</body>
</html>
