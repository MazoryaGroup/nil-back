<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>پرداخت ناموفق</title>
    <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;600&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Vazirmatn', sans-serif;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
            color: #000;
            text-align: center;
        }
        .card {
            background: #fff;
            padding: 25px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
            max-width: 340px;
            width: 90%;
        }
        .status-icon {
            font-size: 48px;
            color: red;
        }
        h1 {
            font-size: 20px;
            margin: 10px 0;
            font-weight: 600;
        }
        .details {
            margin-top: 15px;
            font-size: 14px;
        }
        .btn-home {
            display: inline-block;
            margin-top: 20px;
            padding: 10px 25px;
            background-color: black;
            color: #fff;
            text-decoration: none;
            border-radius: 8px;
            font-size: 14px;
            transition: background-color 0.3s ease;
        }
        .btn-home:hover {
            background-color: #333;
        }
        @media (max-width: 480px) {
            .card {
                padding: 20px;
            }
            h1 {
                font-size: 18px;
            }
            .btn-home {
                font-size: 13px;
                padding: 8px 18px;
            }
        }
    </style>
</head>
<body>
<div class="card">
    <div class="status-icon">❌</div>
    <h1>پرداخت لغو شد یا نامعتبر است</h1>
    <div class="details">
        <p>لطفاً دوباره تلاش کنید یا با پشتیبانی تماس بگیرید.</p>
    </div>
    <a href="https://youngstoring.com/" class="btn-home">بازگشت به خانه</a>
</div>
</body>
</html>
