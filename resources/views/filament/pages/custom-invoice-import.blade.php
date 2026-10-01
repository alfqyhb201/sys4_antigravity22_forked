<?php

use App\Livewire\InvoiceImporterComponent;

?>

<x-filament-panels::page>
    <div class="space-y-6">
        <!-- رأس الصفحة -->
        <div class="relative overflow-hidden bg-gradient-to-br from-custom-600 via-custom-700 to-custom-800 dark:from-custom-800 dark:via-custom-900 dark:to-custom-950 rounded-xl shadow-lg border border-brand-border">
            <div class="absolute inset-0 opacity-10">
                <div class="absolute -top-10 -right-10 w-40 h-40 bg-white rounded-full blur-3xl"></div>
                <div class="absolute -bottom-10 -left-10 w-60 h-60 bg-custom-300 rounded-full blur-3xl"></div>
                <div class="absolute top-0 left-0 w-1 h-full bg-gradient-to-b from-brand-orange to-brand-orange-light rounded-r-full"></div>
            </div>
            <div class="relative p-6 border-b border-brand-border">
                <div class="flex items-center gap-3">
                    <div class="p-2.5 bg-glow-purple backdrop-blur-sm rounded-lg">
                        <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5m-13.5-9L12 3m0 0l4.5 4.5M12 3v13.5" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-lg font-bold text-white">استيراد الفواتير من ملف</h2>
                        <p class="text-sm text-white/70 mt-0.5">
                            قم برفع ملف CSV أو Excel واستيراد الفواتير وبنودها إلى النظام بسهولة
                        </p>
                    </div>
                </div>
            </div>

            <!-- مكوّن Livewire للاستيراد -->
            <div class="p-0 bg-surface-dim/50 backdrop-blur-sm">
                @livewire(InvoiceImporterComponent::class)
            </div>
        </div>

        <!-- تعليمات الاستيراد -->
        <div class="bg-surface rounded-xl shadow-sm border border-brand-border p-6">
            <div class="flex items-start gap-3">
                <div class="p-2 bg-gradient-to-br from-brand-orange to-brand-orange-light rounded-lg shrink-0 shadow-sm shadow-glow-orange/20">
                    <svg class="w-5 h-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9 5.25h.008v.008H12v-.008z" />
                    </svg>
                </div>
                <div class="text-sm text-brand-muted">
                    <h3 class="font-bold text-brand-text mb-2 flex items-center gap-2">
                        <span class="bg-gradient-to-r from-custom-600 to-brand-orange bg-clip-text text-transparent">تعليمات الاستيراد</span>
                    </h3>
                    <ul class="space-y-1.5 list-disc list-inside text-brand-muted">
                        <li>الملفات المدعومة: <strong class="text-brand-text">CSV</strong> و <strong class="text-brand-text">Excel (.xlsx, .xls)</strong></li>
                        <li>الحد الأقصى لحجم الملف: <strong class="text-brand-text">10 ميجابايت</strong></li>
                        <li>يجب أن يحتوي الملف على <strong class="text-brand-text">صف عنوان</strong> (header row)</li>
                        <li>الحقول الإلزامية: <strong class="text-brand-text">العميل</strong> (اسم الشركة أو رقم العميل) و <strong class="text-brand-text">وصف البند</strong> و <strong class="text-brand-text">سعر الوحدة</strong></li>
                        <li>في حال عدم تحديد تاريخ الإصدار والاستحقاق، يتم تعيينهما تلقائياً إلى تاريخ الاستيراد نفسه</li>
                        <li>في حال عدم تحديد الحالة، تُحسب تلقائياً كـ <strong class="text-brand-text">مستحقة</strong></li>
                        <li>استخدم زر <strong class="text-brand-text">تحميل نموذج Excel</strong> للحصول على نموذج جاهز بكل الأعمدة</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
