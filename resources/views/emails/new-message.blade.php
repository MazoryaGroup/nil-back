<!DOCTYPE html>
<html lang="{{ $messageData->lang }}" dir="{{ $messageData->lang == 'fa' ? 'rtl' : 'ltr' }}">
<head>
    @php
        $lineHeights = [
            'fa' => '1.8',
            'ar' => '1.8',
            'en' => '1.6',
            'es' => '1.6',
            'fr' => '1.6',
            'de' => '1.6',
        ];

        $textAligns = [
            'fa' => 'right',
            'ar' => 'right',
            'en' => 'left',
            'es' => 'left',
            'fr' => 'left',
            'de' => 'left',
        ];

        $currentLang = $messageData->lang ?? 'en';
    @endphp



    <meta charset="UTF-8">
    <title>{{ $messageData->lang == 'fa' ? 'به Mazorya Group خوش آمدید' : 'Welcome to Mazorya Group' }}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #f4f6f8;
            font-family: {{ $messageData->lang == 'fa' ? 'Tahoma, Arial, sans-serif' : 'Arial, Helvetica, sans-serif' }};
            /* اصلاح رنگ متن برای خوانایی روی سفید */
            color: #333333;
            direction: {{ $messageData->lang == 'fa' ? 'rtl' : 'ltr' }};
        }

        .email-wrapper {
            width: 100%;
            padding: 20px 0;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
        }

        .email-header {
            padding: 40px 30px;
            text-align: center; /* مرکزچین برای هر دو زبان شیک‌تر است */
            background: linear-gradient(135deg, #181818, #2a2a40);
            color: #ffffff;
        }

        .email-header h1 {
            margin: 0;
            font-size: 24px;
            color: #ffffff;
        }

        .email-body {
            padding: 35px 40px;
            line-height: {{ $lineHeights[$currentLang] ?? '1.6' }};
            text-align: {{ $textAligns[$currentLang] ?? 'left' }};
            font-size: 15px;
            color: #444444;
        }

        .button-container {
            text-align: center;
            padding: 20px 0;
        }

        .email-button {
            display: inline-block;
            padding: 14px 35px;
            background-color: #d74040;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 50px;
            font-weight: bold;
            font-size: 14px;
        }

        .email-footer {
            padding: 25px;
            text-align: center;
            font-size: 12px;
            color: #888;
            background-color: #fafafa;
            border-top: 1px solid #eeeeee;
        }
    </style>
</head>
<body>
<div class="email-wrapper">
    <div class="email-container">
        <div class="email-header">
            <h1>
                @if($messageData->lang == 'fa')
                    به Mazorya Group خوش آمدید
                @elseif($messageData->lang == 'en')
                    Welcome to Mazorya Group
                @elseif($messageData->lang == 'ar')
                    أهلا بكم في مجموعة مازوريا
                @elseif($messageData->lang == 'es')
                    Bienvenido al Grupo Mazorya
                @elseif($messageData->lang == 'fr')
                    Bienvenue dans le groupe Mazorya
                @elseif($messageData->lang == 'de')
                    Willkommen bei der Mazorya-Gruppe
                @else
                    Welcome to Mazorya Group
                @endif
            </h1>
        </div>


        <div class="email-body">
            @if($messageData->lang == 'fa')
                <p>سلام {{ $messageData->name ?? 'دوست عزیز' }}،</p>
                <p>از اینکه با <strong>Mazorya Group</strong> همراه شدید، بسیار خوشحالیم.</p>
                <p>ما به برندها کمک می‌کنیم تا در دنیای دیجیتال، هویت شفاف و تأثیر واقعی داشته باشند؛ از استراتژی و طراحی تا رشد و عملکرد.</p>

                <div class="button-container">
                    <a href="{{ url('/') }}" class="email-button">مشاهده وب‌سایت</a>
                </div>

                <p>اگر ایده یا چشم‌اندازی دارید، ما اینجا هستیم تا آن را به واقعیت تبدیل کنیم.</p>
                <p>با احترام،<br><strong>Mazorya Group</strong></p>

            @elseif($messageData->lang == 'en')
                <p>Hi {{ $messageData->name ?? 'there' }},</p>
                <p>Thank you for connecting with <strong>Mazorya Group</strong>. We’re excited to have you with us.</p>
                <p>We help brands find clarity, direction, and impact in today’s digital landscape — from strategy and design to performance.</p>

                <div class="button-container">
                    <a href="{{ url('/') }}" class="email-button">Explore Our Works</a>
                </div>

                <p>If you have a vision, we’re here to help you bring it to life.</p>
                <p>Warm regards,<br><strong>Mazorya Group</strong></p>

            @elseif($messageData->lang == 'ar')
                <p>مرحبًا {{ $messageData->name ?? 'عزيزي' }}،</p>
                <p>منذ مع <strong>مجموعة مازوريا</strong> نحن سعداء جداً بوجودك معنا.</p>
                <p>نساعد العلامات التجارية على امتلاك هوية واضحة وتأثير حقيقي في العالم الرقمي؛ بدءًا من الاستراتيجية والتصميم وصولاً إلى النمو والأداء.</p>

                <div class="button-container">
                    <a href="{{ url('/') }}" class="email-button">عرض الموقع الإلكتروني</a>
                </div>

                <p>إذا كانت لديك فكرة أو رؤية، فنحن هنا لتحويلها إلى حقيقة.</p>
                <p>مع الاحترام،<br><strong>مجموعة مازوريا</strong></p>

            @elseif($messageData->lang == 'es')
                <p>Hola {{ $messageData->name ?? 'amigo' }}!</p>
                <p>¡Gracias por conectarte con <strong>Mazorya Group</strong>!</p>
                <p>Ayudamos a las marcas a encontrar claridad, dirección e impacto en el mundo digital actual — desde la estrategia y el diseño hasta el rendimiento.</p>

                <div class="button-container">
                    <a href="{{ url('/') }}" class="email-button">Explorar nuestro sitio web</a>
                </div>

                <p>Si tienes una visión, estamos aquí para ayudarte a hacerla realidad.</p>
                <p>Saludos,<br><strong>Mazorya Group</strong></p>

            @elseif($messageData->lang == 'fr')
                <p>Bonjour {{ $messageData->name ?? 'ami' }} !</p>
                <p>Merci de vous être connecté avec <strong>Mazorya Group</strong>.</p>
                <p>Nous aidons les marques à trouver clarté, direction et impact dans le paysage numérique d’aujourd’hui — de la stratégie et du design à la performance.</p>

                <div class="button-container">
                    <a href="{{ url('/') }}" class="email-button">Découvrir notre site</a>
                </div>

                <p>Si vous avez une vision, nous sommes là pour la concrétiser.</p>
                <p>Cordialement,<br><strong>Mazorya Group</strong></p>

            @elseif($messageData->lang == 'de')
                <p>Hallo {{ $messageData->name ?? 'Freund' }}!</p>
                <p>Vielen Dank für die Verbindung mit <strong>Mazorya Group</strong>.</p>
                <p>Wir helfen Marken, Klarheit, Richtung und Wirkung in der heutigen digitalen Landschaft zu finden — von Strategie und Design bis hin zu Performance.</p>

                <div class="button-container">
                    <a href="{{ url('/') }}" class="email-button">Unsere Website erkunden</a>
                </div>

                <p>Wenn Sie eine Vision haben, sind wir hier, um sie zu verwirklichen.</p>
                <p>Mit freundlichen Grüßen,<br><strong>Mazorya Group</strong></p>

            @else
                {{-- Default to English --}}
                <p>Hi {{ $messageData->name ?? 'there' }},</p>
                <p>Thank you for connecting with <strong>Mazorya Group</strong>. We’re excited to have you with us.</p>
                <p>We help brands find clarity, direction, and impact in today’s digital landscape — from strategy and design to performance.</p>

                <div class="button-container">
                    <a href="{{ url('/') }}" class="email-button">Explore Our Works</a>
                </div>

                <p>If you have a vision, we’re here to help you bring it to life.</p>
                <p>Warm regards,<br><strong>Mazorya Group</strong></p>
            @endif
        </div>

    </div>

        <div class="email-footer">
            © {{ date('Y') }} Mazorya Group. {{ $messageData->lang == 'fa' ? 'کلیه حقوق محفوظ است.' : 'All rights reserved.' }}
        </div>
    </div>
</div>
</body>
</html>
