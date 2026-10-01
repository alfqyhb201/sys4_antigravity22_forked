<?php

namespace App\Http\Controllers;

use App\Services\CategoryImportService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class CategoryImportTemplateController extends Controller
{
    public function download(): BinaryFileResponse
    {
        return app(CategoryImportService::class)->downloadTemplate();
    }
}
