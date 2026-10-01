<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 | الصفحة غير موجودة | TrueERP</title>
    <meta name="description" content="عذراً، الصفحة التي تبحث عنها غير موجودة">

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('images/true-nav.png') }}">

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;600;700;900&display=swap" rel="stylesheet">

    <style>
        /* ── Design Tokens ── */
        :root {
            --bg-main: #020617;
            --bg-gradient: radial-gradient(circle at top right, #1e293b, #020617 60%);
            --primary-text: #f8fafc;
            --muted-text: #94a3b8;
            --accent-glow: rgba(68, 17, 136, 0.15);
            --royal-purple: #441188;
            --font-ar: 'Cairo', sans-serif;
            --ease-out: cubic-bezier(0.16, 0.75, 0.3, 1);
        }

        /* ── Reset ── */
        *,
        *::before,
        *::after {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* ── Body ── */
        body {
            font-family: var(--font-ar);
            background-color: var(--bg-main);
            background-image: var(--bg-gradient);
            color: var(--primary-text);
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            direction: rtl;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }

        /* ── Background glow (responsive) ── */
        .glow-spot {
            position: fixed;
            border-radius: 50%;
            filter: blur(120px);
            z-index: 0;
            opacity: 0.5;
            animation: float 12s ease-in-out infinite alternate;
            pointer-events: none;
            /* responsive size */
            width: min(500px, 80vw);
            height: min(500px, 80vw);
        }

        .glow-spot.purple {
            background: var(--accent-glow);
            top: -20%;
            right: -15%;
        }

        .glow-spot.red {
            background: rgba(239, 68, 68, 0.08);
            bottom: -20%;
            left: -15%;
            animation-delay: -6s;
        }

        @keyframes float {
            0% {
                transform: scale(1) translate(0, 0);
            }

            100% {
                transform: scale(1.1) translate(30px, -20px);
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .glow-spot {
                animation: none;
                opacity: 0.25;
            }
        }

        /* ── Main container (fully responsive) ── */
        .main-container {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 460px;
            padding: clamp(1.25rem, 4vw, 2.5rem);
            text-align: center;
            transform: translateY(20px);
            opacity: 0;
            animation: slideUpFade 0.8s 0.2s var(--ease-out) forwards;
        }

        @keyframes slideUpFade {
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .main-container {
                transform: none;
                opacity: 1;
                animation: none;
            }
        }

        /* ── Logo ── */
        .logo {
            max-height: clamp(44px, 10vw, 64px);
            width: auto;
            margin-bottom: clamp(1.5rem, 5vw, 3rem);
            filter: drop-shadow(0 0 12px rgba(255, 255, 255, 0.08));
        }

        /* ── Error code ── */
        .error-code {
            font-size: clamp(5rem, 25vw, 10rem);
            font-weight: 900;
            line-height: 1;
            margin-bottom: 0.35rem;
            background: linear-gradient(135deg, var(--primary-text) 30%, var(--royal-purple) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            letter-spacing: -0.04em;
            animation: fadeScale 0.6s 0.4s var(--ease-out) both;
        }

        @keyframes fadeScale {
            0% {
                transform: scale(0.85);
                opacity: 0;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .error-code {
                animation: none;
            }
        }

        /* ── Divider ── */
        .divider {
            width: clamp(60px, 15vw, 100px);
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--royal-purple), transparent);
            border-radius: 2px;
            margin: clamp(0.8rem, 2vw, 1.4rem) auto;
            opacity: 0.7;
        }

        /* ── Dots ── */
        .dots {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: clamp(0.5rem, 2vw, 0.9rem);
            margin-bottom: clamp(1.2rem, 4vw, 2.2rem);
        }

        .dots span {
            width: clamp(7px, 2vw, 11px);
            height: clamp(7px, 2vw, 11px);
            border-radius: 50%;
            background: var(--royal-purple);
            opacity: 0.6;
            animation: dotPulse 2s ease-in-out infinite;
        }

        .dots span:nth-child(2) {
            animation-delay: 0.3s;
        }

        .dots span:nth-child(3) {
            animation-delay: 0.6s;
        }

        @keyframes dotPulse {

            0%,
            100% {
                transform: scale(1);
                opacity: 0.35;
            }

            50% {
                transform: scale(1.35);
                opacity: 0.8;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            .dots span {
                animation: none;
                opacity: 0.45;
            }
        }

        /* ── Message ── */
        .message {
            font-size: clamp(0.9rem, 2.8vw, 1.15rem);
            font-weight: 400;
            color: var(--muted-text);
            line-height: 1.9;
            margin-bottom: 0;
            padding: 0;
            text-wrap: pretty;
            word-break: break-word;
        }

        .message strong {
            color: var(--primary-text);
            font-weight: 600;
        }

        /* ── Footer ── */
        .footer {
            margin-top: clamp(2rem, 6vw, 3.5rem);
            font-size: clamp(0.7rem, 1.8vw, 0.8rem);
            color: var(--muted-text);
            opacity: 0.55;
            padding-bottom: env(safe-area-inset-bottom, 0);
        }

        .footer .sep {
            margin: 0 6px;
            opacity: 0.35;
        }

        /* ── Tablet (481px – 768px) ── */
        @media (min-width: 481px) and (max-width: 768px) {
            .main-container {
                max-width: 420px;
            }

            .error-code {
                font-size: clamp(6rem, 18vw, 8rem);
            }
        }

        /* ── Small phone (≤ 380px) ── */
        @media (max-width: 380px) {
            .main-container {
                padding: 1rem 0.75rem;
            }

            .error-code {
                font-size: clamp(3.5rem, 22vw, 5rem);
            }

            .logo {
                max-height: 36px;
                margin-bottom: 1.25rem;
            }

            .message {
                font-size: 0.85rem;
                line-height: 1.7;
            }
        }

        /* ── Landscape phone ── */
        @media (max-height: 500px) and (orientation: landscape) {
            body {
                min-height: 100svh;
                justify-content: flex-start;
                padding-top: 1rem;
            }

            .main-container {
                padding: 0.75rem 1rem;
            }

            .logo {
                max-height: 32px;
                margin-bottom: 0.75rem;
            }

            .error-code {
                font-size: clamp(2.5rem, 12vh, 4.5rem);
            }

            .dots {
                margin-bottom: 0.6rem;
            }

            .footer {
                margin-top: 1rem;
            }
        }

        /* ── Large screens (≥ 1200px) ── */
        @media (min-width: 1200px) {
            .main-container {
                max-width: 520px;
            }

            .error-code {
                font-size: 10rem;
            }
        }

        /* ── High-DPI / Retina ── */
        @media (-webkit-min-device-pixel-ratio: 2),
        (min-resolution: 192dpi) {
            .logo {
                filter: drop-shadow(0 0 16px rgba(255, 255, 255, 0.06));
            }
        }
    </style>
</head>

<body>
    <!-- Background decorations -->
    <div class="glow-spot purple"></div>
    <div class="glow-spot red"></div>

    <!-- Main content -->
    <div class="main-container">
        <!-- Logo -->
        <img src="{{ asset('images/true-logo.png') }}" alt="TrueERP" class="logo">

        <!-- Error code -->
        <div class="error-code">404</div>

        <div class="divider"></div>

        <!-- Decorative dots -->
        <div class="dots">
            <span></span>
            <span></span>
            <span></span>
        </div>

        <!-- Message -->
        <p class="message">
            عذراً، الصفحة التي تبحث عنها <strong>غير موجودة</strong>.<br>
            قد يكون الرابط غير صحيح أو الصفحة قد تم نقلها أو إزالتها.
        </p>

        <!-- Footer -->
        <div class="footer">
            &copy; {{ date('Y') }} جميع الحقوق محفوظة
            <span class="sep">•</span>
            TrueERP v1.0.0
        </div>
    </div>
</body>

</html>