<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('filament.admin.auth.login');
});

Route::get('/login', function () {
    return redirect()->route('filament.admin.auth.login');
})->name('login');

Route::middleware(['web', 'auth'])->get('/admin/notifications/unread-count', function () {
    /** @var \App\Models\User $user */
    $user = Auth::user();
    if (! $user) {
        return response()->json(['count' => 0, 'notifications' => []], 401);
    }

    $unreadNotifications = $user->unreadNotifications()
        ->latest()
        ->limit(5)
        ->get()
        ->map(function ($notification) {
            $data = $notification->data ?? [];
            $url = $data['url'] ?? $data['viewUrl'] ?? null;

            if (! $url && ! empty($data['actions']) && is_array($data['actions'])) {
                foreach ($data['actions'] as $action) {
                    if (! empty($action['url'])) {
                        $url = $action['url'];
                        break;
                    }
                }
            }

            return [
                'id' => $notification->id,
                'title' => $data['title'] ?? 'إشعار جديد 🔔',
                'body' => $data['body'] ?? '',
                'message' => $data['message'] ?? '',
                'url' => $url,
                'icon' => $data['icon'] ?? null,
                'created_at' => $notification->created_at->toIso8601String(),
            ];
        });

    return response()->json([
        'count' => $user->unreadNotifications()->count(),
        'notifications' => $unreadNotifications,
    ]);
});

// نموذج استيراد العملاء (Excel) — محمي بالدخول وصلاحية إنشاء العملاء
// ملاحظة: المسار خارج /admin/clients/ لأن مسار Filament (admin/clients/{record})
// يسجَّل أولاً بدون قيد رقمي ويلتقط أي مقطع تحت /admin/clients/ مما يسبب 404.
Route::middleware(['web', 'auth'])->get('/admin/client-import-template', function () {
    return app(\App\Http\Controllers\ClientImportTemplateController::class)->download();
})->name('clients.import.template');

Route::middleware(['web', 'auth'])->get('/admin/invoice-import-template', function () {
    return app(\App\Http\Controllers\InvoiceImportTemplateController::class)->download();
})->name('invoices.import.template');

Route::middleware(['web', 'auth'])->get('/admin/receipt-import-template', function () {
    return app(\App\Http\Controllers\ReceiptImportTemplateController::class)->download();
})->name('receipts.import.template');

Route::middleware(['web', 'auth'])->get('/admin/location-import-template', function () {
    return app(\App\Http\Controllers\LocationImportTemplateController::class)->download();
})->name('locations.import.template');

Route::middleware(['web', 'auth'])->get('/admin/category-import-template', function () {
    return app(\App\Http\Controllers\CategoryImportTemplateController::class)->download();
})->name('categories.import.template');

// تقرير مديونيات العملاء المالي (PDF / طباعة ورق)
Route::middleware(['web', 'auth'])->get('/admin/reports/debtors', [\App\Http\Controllers\Reports\ClientDebtorsReportController::class, 'index'])
    ->name('reports.client-debtors');

// كشف حساب العميل المالي (PDF / طباعة ورق)
Route::middleware(['web', 'auth'])->get('/admin/reports/client-statement/{client}', [\App\Http\Controllers\Reports\ClientStatementReportController::class, 'show'])
    ->name('reports.client-statement');

// تقرير المهام غير المنجزة للمشرف (طباعة وتصوير)
Route::middleware(['web', 'auth'])->get('/admin/reports/uncompleted-tasks', \App\Http\Controllers\Reports\UncompletedTasksReportController::class)
    ->name('reports.uncompleted-tasks');

// تنزيل ملف ZIP المجهّز لواجهة الإرسال (خاص بصاحب الطلب فقط)
Route::middleware(['web', 'auth'])->get('/admin/designs-zip/{token}', function (string $token) {
    $data = \App\Jobs\BuildDesignsZipJob::progress($token);
    abort_unless($data && (int) $data['user_id'] === (int) auth()->id() && $data['status'] === 'done' && ! empty($data['file']), 404);

    $path = \Illuminate\Support\Facades\Storage::disk('local')->path($data['file']);
    abort_unless(is_file($path), 404);

    $fileName = 'designs-'.now()->format('Y-m-d_H-i-s').'.zip';

    return response()->download($path, $fileName, [
        'Content-Type' => 'application/zip',
    ])->deleteFileAfterSend();
})->where('token', '[A-Za-z0-9]+')->name('designs-zip.download');

// تنزيل ملف ZIP المجهّز من واجهة الإرسال دون مغادرة الصفحة
Route::middleware(['web', 'auth'])->get('/admin/sending-follow-up-zip/{token}', function (string $token) {
    $cacheKey = "sending_zip:{$token}";
    $data = \Illuminate\Support\Facades\Cache::get($cacheKey);
    abort_unless($data && (int) $data['user_id'] === (int) auth()->id(), 404);

    $path = \Illuminate\Support\Facades\Storage::disk('local')->path($data['file']);
    abort_unless(is_file($path), 404);

    \Illuminate\Support\Facades\Cache::forget($cacheKey);

    return response()->download($path, $data['name'], [
        'Content-Type' => 'application/zip',
    ])->deleteFileAfterSend();
})->where('token', '[A-Za-z0-9]+')->name('sending-follow-up-zip.download');
