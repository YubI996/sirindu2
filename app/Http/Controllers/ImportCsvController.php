<?php

namespace App\Http\Controllers;

use App\Jobs\ImportAnakJob;
use App\Jobs\ImportHasilLabJob;
use App\Jobs\ImportImunisasiJob;
use App\Jobs\ImportPengukuranJob;
use App\Models\ImportLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Controller untuk import data via CSV terstandarisasi.
 *
 * Modul yang ditangani: Anak, Pengukuran Berkala (DataAnak), Imunisasi.
 * Setiap import berjalan async melalui queue job.
 * Status import dapat di-polling via endpoint importStatus().
 */
class ImportCsvController extends Controller
{
    /** Tipe import yang valid dan label tampilan-nya. */
    private const VALID_TYPES = [
        'anak'       => 'Data Anak',
        'pengukuran' => 'Pengukuran Berkala',
        'imunisasi'  => 'Imunisasi',
        'hasil_lab'  => 'Hasil Laboratorium PD3I',
    ];

    /** Nama file template per tipe. */
    private const TEMPLATE_FILES = [
        'anak'       => 'template_anak.csv',
        'pengukuran' => 'template_pengukuran_berkala.csv',
        'imunisasi'  => 'template_imunisasi.csv',
    ];

    // =========================================================================
    // Halaman utama Import Data
    // =========================================================================

    public function index()
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403);

        $logs = ImportLog::where('user_id', auth()->id())
            ->whereIn('type', array_keys(self::VALID_TYPES))
            ->latest()
            ->take(10)
            ->get();

        return view('admin.import.index', compact('logs'));
    }

    // =========================================================================
    // Upload — satu method generik, dipanggil dari 3 route berbeda
    // =========================================================================

    public function uploadAnak(Request $request)
    {
        return $this->handleUpload($request, 'anak', 'file_anak');
    }

    public function uploadPengukuran(Request $request)
    {
        return $this->handleUpload($request, 'pengukuran', 'file_pengukuran');
    }

    public function uploadImunisasi(Request $request)
    {
        return $this->handleUpload($request, 'imunisasi', 'file_imunisasi');
    }

    public function uploadHasilLab(Request $request)
    {
        return $this->handleUpload($request, 'hasil_lab', 'file_hasil_lab');
    }

    protected function handleUpload(Request $request, string $type, string $inputName)
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403, 'Hanya superadmin yang dapat mengimpor data.');

        $request->validate([
            $inputName => 'required|file|mimes:csv,txt|max:10240',
        ], [
            "{$inputName}.mimes" => 'File harus berformat CSV (.csv).',
            "{$inputName}.max"   => 'Ukuran file maksimal 10 MB.',
        ]);

        $file     = $request->file($inputName);
        $filename = $file->getClientOriginalName();
        $path     = $file->store("imports/{$type}");

        $log = ImportLog::create([
            'user_id'   => auth()->id(),
            'filename'  => $filename,
            'file_path' => $path,
            'type'      => $type,
            'status'    => 'pending',
        ]);

        $jobClass = match ($type) {
            'anak'       => ImportAnakJob::class,
            'pengukuran' => ImportPengukuranJob::class,
            'imunisasi'  => ImportImunisasiJob::class,
            'hasil_lab'  => ImportHasilLabJob::class,
        };

        $jobClass::dispatch($log);

        $label = self::VALID_TYPES[$type];

        if ($request->expectsJson()) {
            return response()->json([
                'ok'      => true,
                'log_id'  => $log->id,
                'message' => "File \"{$filename}\" ({$label}) diterima dan sedang diproses.",
            ]);
        }

        return back()->with(
            'import_queued',
            "File \"{$filename}\" ({$label}) telah diterima dan sedang diproses di latar belakang. Cek status import di bawah."
        );
    }

    // =========================================================================
    // Reimport — jalankan ulang import dari file yang sudah ada
    // =========================================================================

    public function reimport(ImportLog $log)
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403);
        abort_if(!array_key_exists($log->type, self::VALID_TYPES), 404);

        if (!Storage::exists($log->file_path)) {
            return back()->with('error', "File \"{$log->filename}\" sudah tidak tersedia. Silakan upload ulang.");
        }

        $newLog = ImportLog::create([
            'user_id'   => auth()->id(),
            'filename'  => $log->filename,
            'file_path' => $log->file_path,
            'type'      => $log->type,
            'status'    => 'pending',
        ]);

        $jobClass = match ($log->type) {
            'anak'       => ImportAnakJob::class,
            'pengukuran' => ImportPengukuranJob::class,
            'imunisasi'  => ImportImunisasiJob::class,
            'hasil_lab'  => ImportHasilLabJob::class,
        };

        $jobClass::dispatch($newLog);

        $label = self::VALID_TYPES[$log->type];

        if (request()->expectsJson()) {
            return response()->json(['ok' => true, 'log_id' => $newLog->id]);
        }

        return back()->with('import_queued', "Mengulang import \"{$log->filename}\" ({$label}) di latar belakang.");
    }

    // =========================================================================
    // Status polling — dipanggil AJAX, filter by type
    // =========================================================================

    public function importStatus(Request $request)
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403);

        $type = $request->query('type');

        $query = ImportLog::where('user_id', auth()->id())
            ->whereIn('type', array_keys(self::VALID_TYPES))
            ->latest()
            ->take(10);

        if ($type && array_key_exists($type, self::VALID_TYPES)) {
            $query->where('type', $type);
        }

        $logs = $query->get([
            'id', 'filename', 'type', 'status',
            'success_count', 'failure_count', 'failures',
            'started_at', 'completed_at', 'created_at',
        ]);

        return response()->json($logs);
    }

    // =========================================================================
    // Hapus log import
    // =========================================================================

    public function destroyLog(ImportLog $log)
    {
        abort_if(!auth()->user()->isSuperAdmin(), 403);
        abort_if(!array_key_exists($log->type, self::VALID_TYPES), 404);
        abort_if(
            in_array($log->status, ['pending', 'processing']),
            422,
            'Tidak bisa menghapus log yang sedang diproses.'
        );

        Storage::delete($log->file_path);
        $log->delete();

        if (request()->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    // =========================================================================
    // Download template CSV
    // =========================================================================

    public function downloadTemplate(string $type)
    {
        abort_if(!array_key_exists($type, self::TEMPLATE_FILES), 404);

        $filename    = self::TEMPLATE_FILES[$type];
        $storagePath = "imports/{$filename}";

        if (!Storage::exists($storagePath)) {
            abort(404, "Template {$filename} tidak ditemukan di server.");
        }

        return Storage::download($storagePath, $filename, [
            'Content-Type' => 'text/csv; charset=utf-8',
        ]);
    }
}
