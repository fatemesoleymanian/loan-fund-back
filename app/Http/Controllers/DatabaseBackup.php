<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DatabaseBackup extends Controller
{
    public function download(Request $request){
        $providedToken = $request->query('access_token');
        $validToken = env('ACCESS_TOKEN');

        if ($providedToken !== $validToken) {
            abort(403, 'شما اجازه دسترسی به این فایل را ندارید!');
        }
        $dbPath = config('database.connections.sqlite.database');
        if (!file_exists($dbPath)) {
            abort(404, 'دیتابیس پیدا نشد.');
        }
         $backupDir = storage_path('app/backups');

        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = 'funddatabase_' . now()->format('Y-m-d_His') . '.sqlite';
        $backupPath = $backupDir . '/' . $filename;

        if (!copy($dbPath, $backupPath)) {
            abort(500, 'Failed to create backup copy.');
        }

        // Download and delete temp file
        return response()
            ->download($backupPath, 'بکاپ_صندوق.sqlite')
            ->deleteFileAfterSend(true);
    }
}
