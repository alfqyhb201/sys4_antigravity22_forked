<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    {{-- 
    @block: Meta & Configuration
    @desc: This block sets up the document metadata, viewport settings, and imports necessary fonts for the minimalist design.
    @inputs: 
        - charset: UTF-8
        - viewport: Responsive settings
        - fonts: Tajawal (Arabic) and Inter (English numbers/UI)
    @outputs: Renders the head section with correct encoding and typography resources.
    --}}
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة العملاء والمصممين | TrueERP</title>
    <meta name="description" content="منصة احترافية لإدارة الموارد والعملاء">

    <!-- Favicon -->
    <link rel="icon" href="{{ asset('images/true-nav.png') }}">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600&family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">

    <style>
        /*
        @block: Design Tokens & Reset
        @desc: Defines CSS variables for the dark minimalist theme and resets default browser styles.
        @inputs: 
            - --bg-main: Deep dark background (#0f172a) for the body.
            - --glass: Semi-transparent white for glassmorphism.
            - --primary-text: High contrast text (#f8fafc).
            - --muted-text: Low contrast text (#94a3b8).
            - --accent: Subtle accent color (#38bdf8).
        @outputs: Global styles and variables available for all elements.
        */
        :root {
            --bg-main: #020617;
            --bg-gradient: radial-gradient(circle at top right, #1e293b, #020617 60%);
            --glass-bg: rgba(30, 41, 59, 0.4);
            --glass-border: rgba(255, 255, 255, 0.05);
            --primary-text: #f8fafc;
            --muted-text: #94a3b8;
            --accent-glow: rgba(56, 189, 248, 0.15);
            --accent-color: #38bdf8;
            --royal-purple: #441188;
            --royal-purple-dark: #350d6b;
            --font-ar: 'Tajawal', sans-serif;
            --font-en: 'Inter', sans-serif;
            --ease-out: cubic-bezier(0.16, 0.75, 0.3, 1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-ar);
            background-color: var(--bg-main);
            background-image: var(--bg-gradient);
            color: var(--primary-text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            overflow: hidden;
            position: relative;
        }

        /*
        @block: Background Aesthetics
        @desc: Creates subtle background elements like the glow effect and grain texture to add depth without clutter.
        @inputs: 
            - .glow-spot: Positioned radial gradient.
            - .grid-pattern: Subtle svg grid overlay.
        @outputs: A dynamic but minimal background environment.
        */
        .glow-spot {
            position: absolute;
            width: 600px;
            height: 600px;
            background: var(--accent-glow);
            filter: blur(120px);
            border-radius: 50%;
            z-index: 0;
            opacity: 0.6;
            animation: pulse 8s ease-in-out infinite alternate;
        }

        .top-right { top: -200px; right: -100px; }
        .bottom-left { bottom: -200px; left: -100px; background: rgba(99, 102, 241, 0.1); }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 0.5; }
            100% { transform: scale(1.1); opacity: 0.7; }
        }

        @media (prefers-reduced-motion: reduce) {
            .glow-spot {
                animation: none;
                opacity: 0.4;
            }
        }

        /*
        @block: Main Container
        @desc: The central container holding the content. Modified to be transparent (no card style).
        @inputs: .main-card class
        @outputs: A centered, transparent container hosting the UI.
        */
        .main-card {
            position: relative;
            z-index: 10;
            width: 100%;
            max-width: 600px;
            padding: 2rem;
            text-align: center;
            transform: translateY(20px);
            opacity: 0;
            animation: slideUpFade 0.8s 0.2s var(--ease-out) forwards;
        }

        @keyframes slideUpFade {
            to { transform: translateY(0); opacity: 1; }
        }

        @media (prefers-reduced-motion: reduce) {
            .main-card {
                transform: none;
                opacity: 1;
                animation: none;
            }
        }

        /*
        @block: Typography & Content
        @desc: Styles for the logo, headings, and description text focusing on readability and hierarchy.
        @inputs: .logo, h1, p classes
        @outputs: Styled textual content with proper spacing and color.
        */
        .logo {
            height: 64px;
            width: auto;
            margin-bottom: 2.5rem;
            filter: drop-shadow(0 0 15px rgba(255,255,255,0.1));
        }

        h1 {
            font-size: clamp(1.5rem, 5vw, 2rem);
            font-weight: 900;
            letter-spacing: -0.02em;
            margin-bottom: 1rem;
            color: var(--primary-text);
            text-wrap: balance;
        }

        h1 span {
            color: var(--accent-color);
            position: relative;
            display: inline-block;
        }

        p.subtitle {
            font-size: 1rem;
            line-height: 1.7;
            color: var(--muted-text);
            margin-bottom: 3rem;
            font-weight: 300;
            text-wrap: pretty;
        }

        /*
        @block: Action Buttons
        @desc: Minimalist button styles with subtle hover states.
        @inputs: .btn-group, .btn class
        @outputs: Interactive buttons for navigation.
        */
        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            width: 100%;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.95rem;
            transition: background 0.25s var(--ease-out), transform 0.25s var(--ease-out), box-shadow 0.25s var(--ease-out);
            width: 100%;
            min-height: 44px;
            position: relative;
            overflow: hidden;
        }

        .btn-primary {
            background: var(--royal-purple);
            color: #ffffff;
            border: 1px solid transparent;
        }

        .btn-primary:hover {
            background: var(--royal-purple-dark);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(68, 17, 136, 0.3);
        }

        .btn-primary:active {
            transform: translateY(0);
            box-shadow: none;
        }

        .btn-primary:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px rgba(68, 17, 136, 0.5);
        }

        .btn-secondary {
            background: transparent;
            color: var(--muted-text);
            border: 1px solid var(--glass-border);
        }

        .btn-secondary:hover {
            border-color: var(--muted-text);
            color: var(--primary-text);
            background: rgba(255, 255, 255, 0.03);
        }

        .btn-secondary:focus-visible {
            outline: none;
            box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.4);
        }

        /*
        @block: Footer Elements
        @desc: Small footer details like copyright or status indicators.
        @inputs: .footer-meta
        @outputs: Subtle footer text.
        */
        .footer-meta {
            margin-top: 3rem;
            font-size: 0.8rem;
            color: var(--muted-text);
        }
        
        .footer-meta span {
            display: inline-block;
            margin: 0 8px;
            opacity: 0.5;
        }

    </style>
</head>
<body>

    {{-- 
    @block: Background Decoration
    @desc: Renders the ambient light glowing orbs in the background.
    @inputs: .glow-spot elements
    @outputs: Visual depth and atmosphere.
    --}}
    <div class="glow-spot top-right"></div>
    <div class="glow-spot bottom-left"></div>

    {{-- 
    @block: Main UI Container
    @desc: The central interactive area of the welcome page.
    @inputs: .main-card
    @outputs: Contains Logo, Text, and Action Buttons.
    --}}
    <div class="main-card">
        {{-- Logo Section --}}
        <img src="{{ asset('images/true-logo.png') }}" alt="TrueERP" class="logo">

        {{-- Text Content --}}
        <h1>
            لوحة تحكم <span>المصممين</span>
        </h1>
        <p class="subtitle">
            نظام إدارة مركزي متقدم وسلس لتحسين سير العمل<br>
            وتعزيز كفاءة التواصل مع العملاء.
        </p>

        {{-- Actions --}}
        <div class="btn-group">
            <a href="/admin" class="btn btn-primary">
                الدخول للنظام
            </a>
            <!-- Optional Secondary Action -->
            <!-- <a href="#" class="btn btn-secondary">وثائق المساعدة</a> -->
        </div>

        {{-- Footer/Copyright --}}
        <div class="footer-meta">
            &copy; {{ date('Y') }} جميع الحقوق محفوظة
            <span>•</span>
            v1.0.0
        </div>
    </div>

</body>
</html>
