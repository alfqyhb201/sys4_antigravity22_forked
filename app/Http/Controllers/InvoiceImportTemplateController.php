<?php

namespace App\Http\Controllers;

use App\Services\InvoiceImportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class InvoiceImportTemplateController extends Controller
{
    public function download(): BinaryFileResponse
    {
        return app(InvoiceImportService::class)->downloadTemplate();
    }
}
