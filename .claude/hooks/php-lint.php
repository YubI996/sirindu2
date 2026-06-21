<?php

/**
 * PostToolUse hook — lint file PHP yang baru diedit.
 *
 * Membaca payload hook (JSON) dari STDIN, mengambil file_path, dan bila itu file
 * .php menjalankan `php -l`. Jika ada syntax error (mis. duplikat import / kurung
 * tak seimbang), mengembalikan decision:"block" agar pesan error langsung diumpan
 * balik ke Claude untuk diperbaiki — bukan baru ketahuan saat runtime.
 *
 * Selalu exit 0; "block" disampaikan lewat JSON, bukan exit code, agar tidak
 * mengganggu hook lain.
 */

$raw  = stream_get_contents(STDIN) ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    exit(0);
}

$file = $data['tool_input']['file_path'] ?? '';
if ($file === '' || !preg_match('/\.php$/i', $file) || !is_file($file)) {
    exit(0); // bukan file PHP / tak ada → tidak ada yang dilint
}

$cmd = escapeshellarg(PHP_BINARY) . ' -l ' . escapeshellarg($file) . ' 2>&1';
exec($cmd, $out, $code);

if ($code !== 0) {
    $msg = trim(implode("\n", $out));
    echo json_encode([
        'decision' => 'block',
        'reason'   => "PHP syntax error terdeteksi di {$file}:\n{$msg}\nPerbaiki dulu sebelum lanjut.",
    ]);
}

exit(0);
