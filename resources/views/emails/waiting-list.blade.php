<!DOCTYPE html>
<html lang="{{ $waiting->lang }}" dir="{{ $waiting->lang == 'fa' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <title>{{ $waiting->lang == 'fa' ? 'در خبرنامه ما مشترک شدید 🎉' : 'You joined our Newsletter 🎉' }}</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: {{ $waiting->lang == 'fa' ? 'Tahoma, Arial, sans-serif' : 'Arial, Helvetica, sans-serif' }};
            direction: {{ $waiting->lang == 'fa' ? 'rtl' : 'ltr' }};
            color: #333;
            background-color: #f4f6f8;
        }
        .container {
            max-width: 600px;
            margin: 40px auto;
            background-color: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
        }
        .header {
            text-align: {{ $waiting->lang == 'fa' ? 'right' : 'center' }};
            background: linear-gradient(135deg, #181818, #2a2a40);
            color: #fff;
            padding: 20px;
            border-radius: 12px 12px 0 0;
        }
        .header h1 { margin: 0; font-size: 22px; }
        .body {
            padding: 25px 0;
            text-align: {{ $waiting->lang == 'fa' ? 'right' : 'left' }};
            line-height: {{ $waiting->lang == 'fa' ? '2' : '1.7' }};
        }
        .body p { margin-bottom: 18px; font-size: 14px; }
        .button {
            display: inline-block;
            margin-top: 20px;
            padding: 12px 30px;
            background-color: #d74040;
            color: #fff !important;
            text-decoration: none;
            border-radius: 25px;
            float: {{ $waiting->lang == 'fa' ? 'right' : 'none' }};
        }
        .footer {
            text-align: center;
            color: #777;
            font-size: {{ $waiting->lang == 'fa' ? '12px' : '13px' }};
            padding: 20px 0 0;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="header">
        <h1>{{ $waiting->lang == 'fa' ? 'در خبرنامه ما مشترک شدید 🎉' : 'You joined our Newsletter 🎉' }}</h1>
    </div>

    <div class="body">
        @if($waiting->lang == 'fa')
            <p>سلام {{ $waiting->email }},</p>
            <p>از اینکه در خبرنامه <strong>Mazorya Group</strong> عضو شدید، بسیار خوشحالیم!</p>
            <p>به زودی تازه‌ترین اخبار، مقالات و فرصت‌های ویژه برای شما ارسال خواهد شد.</p>
            <a href="{{ url('/') }}" class="button">مشاهده Mazorya Group</a>
        @else
            <p>Hi {{ $waiting->email }},</p>
            <p>We are excited that you joined the <strong>Mazorya Group</strong> newsletter!</p>
            <p>You will soon receive the latest updates, articles, and special offers.</p>
            <a href="{{ url('/') }}" class="button">Explore Mazorya Group</a>
        @endif
    </div>

    <div class="footer">
        © {{ date('Y') }} Mazorya Group. {{ $waiting->lang == 'fa' ? 'کلیه حقوق محفوظ است.' : 'All rights reserved.' }}
    </div>
</div>
</body>
</html>
