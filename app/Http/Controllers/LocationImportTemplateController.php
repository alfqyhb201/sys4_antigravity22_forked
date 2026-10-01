<?php

namespace App\Http\Controllers;

use App\Services\LocationImportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LocationImportTemplateController extends Controller
{
    public function download(): BinaryFileResponse
    {
        return app(LocationImportService::class)->downloadTemplate();
    }
}
