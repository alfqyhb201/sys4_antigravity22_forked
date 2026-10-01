<x-filament-panels::page>
    <style>
        /* توسيع حقل البحث الافتراضي الخاص بالجدول */
        .fi-ta-search-field {
            max-width: 100% !important;
            width: 450px !important;
        }
    </style>
    {{ $this->table }}
</x-filament-panels::page>