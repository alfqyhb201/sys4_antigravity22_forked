<?php

namespace App\Http\Controllers;

use App\Services\ClientImportService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * يوفّر تنزيل نموذج استيراد العملاء (Excel) الجاهز للتعبئة.
 */
class ClientImportTemplateController extends Controller
{
    public function download(): BinaryFileResponse
    {
        abort_unless(Auth::check() && Auth::user()?->can('create_client'), 403);

        return app(ClientImportService::class)->downloadTemplate();
    }
}
