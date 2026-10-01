<?php

return [

    /*
    |--------------------------------------------------------------------------
    | أوزان نظام النقاط
    |--------------------------------------------------------------------------
    |
    | هذه الأوزان تحدد أهمية كل عامل في حساب النقاط النهائية للمصمم.
    | المجموع يجب أن يساوي 100.
    |
    */

    'weights' => [
        'specialization' => env('DISTRIBUTION_WEIGHT_SPECIALIZATION', 25),
        'rating' => env('DISTRIBUTION_WEIGHT_RATING', 15),
        'continuity' => env('DISTRIBUTION_WEIGHT_CONTINUITY', 15),
        'experience' => env('DISTRIBUTION_WEIGHT_EXPERIENCE', 10),
        'capacity_balance' => env('DISTRIBUTION_WEIGHT_CAPACITY_BALANCE', 35),
    ],

    /*
    |--------------------------------------------------------------------------
    | إعدادات السعة
    |--------------------------------------------------------------------------
    |
    | إعدادات متعلقة بسعة المصممين وحساب الحمل.
    |
    */

    'capacity' => [
        // هل يتم التحقق من إجمالي عدد التصاميم؟
        'check_total_designs' => env('DISTRIBUTION_CHECK_TOTAL_DESIGNS', true),

        // عدد التصاميم التي يمكن للمصمم إنجازها في الساعة
        'designs_per_hour' => env('DISTRIBUTION_DESIGNS_PER_HOUR', 2),

        // الحد الأدنى الافتراضي للسعة (إذا لم يكن محدد)
        'default_min_capacity' => env('DISTRIBUTION_DEFAULT_MIN_CAPACITY', 0),

        // الحد الأقصى الافتراضي للسعة (إذا لم يكن محدد)
        'default_max_capacity' => env('DISTRIBUTION_DEFAULT_MAX_CAPACITY', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | إعدادات التقييم
    |--------------------------------------------------------------------------
    |
    | إعدادات متعلقة بنظام التقييم.
    |
    */

    'rating' => [
        // الحد الأقصى للتقييم
        'max_rate' => env('DISTRIBUTION_MAX_RATE', 10),

        // الحد الأدنى للتقييم المقبول
        'min_acceptable_rate' => env('DISTRIBUTION_MIN_ACCEPTABLE_RATE', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | إعدادات الخبرة
    |--------------------------------------------------------------------------
    |
    | إعدادات متعلقة بحساب نقاط الخبرة.
    |
    */

    'experience' => [
        // عدد التصاميم التي تعتبر خبرة عالية
        'high_experience_threshold' => env('DISTRIBUTION_HIGH_EXPERIENCE', 1000),

        // عدد التصاميم الذي يعتبر العميل "كبير"
        'large_client_threshold' => env('DISTRIBUTION_LARGE_CLIENT', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | إعدادات الاستمرارية
    |--------------------------------------------------------------------------
    |
    | إعدادات متعلقة بالاستمرارية مع العملاء.
    |
    */

    'continuity' => [
        // هل يتم تفعيل الاستمرارية؟
        'enabled' => env('DISTRIBUTION_CONTINUITY_ENABLED', true),

        // عدد الأسابيع للبحث عن المصمم السابق
        'lookback_weeks' => env('DISTRIBUTION_CONTINUITY_LOOKBACK', 1),
    ],

    /*
    |--------------------------------------------------------------------------
    | إعدادات التوزيع
    |--------------------------------------------------------------------------
    |
    | إعدادات عامة للتوزيع.
    |
    */

    'distribution' => [
        // ترتيب العملاء (priority_high_to_low أو priority_low_to_high)
        'client_sorting' => env('DISTRIBUTION_CLIENT_SORTING', 'priority_high_to_low'),

        // هل يتم حذف التعيينات السابقة تلقائياً؟
        'auto_clear_previous' => env('DISTRIBUTION_AUTO_CLEAR', false),

        // الحد الأقصى لعدد المحاولات لإيجاد مصمم
        'max_attempts' => env('DISTRIBUTION_MAX_ATTEMPTS', 3),
    ],

    /*
    |--------------------------------------------------------------------------
    | إعدادات التقارير
    |--------------------------------------------------------------------------
    |
    | إعدادات متعلقة بالتقارير والإحصائيات.
    |
    */

    'reporting' => [
        // هل يتم حفظ سجل التوزيع؟
        'log_distributions' => env('DISTRIBUTION_LOG_ENABLED', true),

        // مسار ملف السجل
        'log_channel' => env('DISTRIBUTION_LOG_CHANNEL', 'daily'),

        // هل يتم إرسال إشعارات؟
        'send_notifications' => env('DISTRIBUTION_SEND_NOTIFICATIONS', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | إعدادات الأداء
    |--------------------------------------------------------------------------
    |
    | إعدادات لتحسين الأداء.
    |
    */

    'performance' => [
        // استخدام Cache للبيانات
        'use_cache' => env('DISTRIBUTION_USE_CACHE', false),

        // مدة Cache بالدقائق
        'cache_duration' => env('DISTRIBUTION_CACHE_DURATION', 60),

        // حجم الدفعة للمعالجة
        'batch_size' => env('DISTRIBUTION_BATCH_SIZE', 100),
    ],

    /*
    |--------------------------------------------------------------------------
    | قواعد مخصصة
    |--------------------------------------------------------------------------
    |
    | قواعد إضافية يمكن استخدامها في الخوارزمية.
    |
    */

    'custom_rules' => [
        // استبعاد مصممين معينين
        'excluded_designers' => env('DISTRIBUTION_EXCLUDED_DESIGNERS', ''),

        // إعطاء أولوية لمصممين معينين
        'priority_designers' => env('DISTRIBUTION_PRIORITY_DESIGNERS', ''),

        // استبعاد عملاء معينين
        'excluded_clients' => env('DISTRIBUTION_EXCLUDED_CLIENTS', ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | إعدادات التطوير والاختبار
    |--------------------------------------------------------------------------
    |
    | إعدادات للتطوير والاختبار فقط.
    |
    */

    'debug' => [
        // تفعيل وضع التصحيح
        'enabled' => env('DISTRIBUTION_DEBUG', false),

        // عرض تفاصيل حساب النقاط
        'show_score_details' => env('DISTRIBUTION_SHOW_SCORES', false),

        // حفظ نتائج التوزيع في ملف
        'save_results_to_file' => env('DISTRIBUTION_SAVE_RESULTS', false),
    ],

];
