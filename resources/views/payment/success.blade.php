<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پرداخت موفق | MIM TEHRAN</title>

    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        *{
            margin:0;
            padding:0;
            box-sizing:border-box;
        }

        body{
            font-family:'Vazirmatn', sans-serif;
            background: linear-gradient(135deg,#f8f4ef,#f1e8df);
            min-height:100vh;
            display:flex;
            justify-content:center;
            align-items:center;
            padding:20px;
        }

        .card{
            width:100%;
            max-width:450px;
            background:#fff;
            border-radius:25px;
            padding:35px 25px;
            text-align:center;
            box-shadow:0 15px 40px rgba(0,0,0,0.08);
        }

        .success-icon{
            width:85px;
            height:85px;
            background:#4CAF50;
            border-radius:50%;
            margin:0 auto 20px;
            display:flex;
            justify-content:center;
            align-items:center;
        }

        .success-icon svg{
            width:45px;
            height:45px;
            color:white;
        }

        h1{
            font-size:24px;
            color:#222;
            margin-bottom:10px;
        }

        .desc{
            color:#777;
            font-size:14px;
            margin-bottom:25px;
        }

        .order-box{
            background:#f8f4ef;
            padding:18px;
            border-radius:15px;
            margin-bottom:20px;
        }

        .order-box h3{
            color:#8B5E3C;
            font-size:14px;
            margin-bottom:10px;
        }

        .order-id{
            font-size:22px;
            font-weight:bold;
            color:#222;
        }

        .details{
            text-align:right;
            margin-top:20px;
            border-top:1px solid #eee;
            padding-top:20px;
        }

        .row{
            display:flex;
            justify-content:space-between;
            margin-bottom:12px;
            font-size:14px;
        }

        .label{
            color:#777;
        }

        .value{
            font-weight:bold;
            color:#222;
        }

        .success-message{
            background:#e8f5e9;
            color:#2e7d32;
            padding:14px;
            border-radius:12px;
            font-size:13px;
            margin-top:20px;
        }

        .btn-home{
            display:inline-block;
            margin-top:25px;
            text-decoration:none;
            background:#8B5E3C;
            color:#fff;
            padding:12px 30px;
            border-radius:40px;
            font-size:14px;
            transition:.3s;
        }

        .btn-home:hover{
            background:#6d472b;
        }

        @media(max-width:480px){
            .card{
                padding:25px 18px;
            }

            h1{
                font-size:20px;
            }

            .order-id{
                font-size:18px;
            }
        }
    </style>
</head>
<body>

<div class="card">

    <div class="success-icon">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round"
                  stroke-linejoin="round"
                  stroke-width="2"
                  d="M5 13l4 4L19 7"/>
        </svg>
    </div>

    <h1>پرداخت با موفقیت انجام شد 🎉</h1>

    <p class="desc">
        سفارش شما با موفقیت ثبت شد و در حال پردازش است.
    </p>

    <div class="order-box">
        <h3>شماره سفارش</h3>
        <div class="order-id">
            {{ $order->tracking_code }}
        </div>
    </div>

    <div class="details">
        <div class="row">
            <span class="label">کد پیگیری بانک</span>
            <span class="value">
                {{ $order->ref_id ?? '---' }}
            </span>
        </div>

        <div class="row">
            <span class="label">مبلغ پرداختی</span>
            <span class="value">
                {{ number_format($order->amount) }} تومان
            </span>
        </div>

        <div class="row">
            <span class="label">وضعیت سفارش</span>
            <span class="value" style="color:green;">
                پرداخت شده
            </span>
        </div>


    </div>

    @if(session('success_message'))
        <div class="success-message">
            {{ session('success_message') }}
        </div>
    @endif

    <a href="https://mimtehran.ir" class="btn-home">
        بازگشت به فروشگاه
    </a>

</div>

</body>
</html>
