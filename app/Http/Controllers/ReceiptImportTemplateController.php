<?php

namespace App\Http\Controllers;

use App\Services\ReceiptImportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ReceiptImportTemplateController extends Controller
{
    public function download(): BinaryFileResponse
    {
        return app(ReceiptImportService::class)->downloadTemplate();
    }
}
