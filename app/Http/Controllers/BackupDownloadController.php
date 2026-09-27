<?php

namespace App\Http\Controllers;

use App\Services\BackupService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupDownloadController extends Controller
{
    public function __invoke(string $name, BackupService $backups): BinaryFileResponse
    {
        activity()->causedBy(auth()->user())->log("downloaded backup {$name}");

        return response()->download($backups->path($name));
    }
}
