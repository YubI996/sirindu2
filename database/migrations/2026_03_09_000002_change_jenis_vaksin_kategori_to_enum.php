<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Data migration: map existing free-text values to closest enum value
        $mapping = [
            'wajib' => 'Wajib',
            'tambahan' => 'Tambahan',
            'booster' => 'Booster',
        ];

        $existing = DB::table('jenis_vaksin')->select('id', 'kategori')->get();
        foreach ($existing as $row) {
            $lower = strtolower(trim($row->kategori));
            $mapped = $mapping[$lower] ?? 'Wajib';
            DB::table('jenis_vaksin')->where('id', $row->id)->update(['kategori' => $mapped]);
        }

        // Change column type from varchar to enum
        DB::statement("ALTER TABLE jenis_vaksin MODIFY kategori ENUM('Wajib','Tambahan','Booster') NOT NULL DEFAULT 'Wajib'");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE jenis_vaksin MODIFY kategori VARCHAR(100) NOT NULL");
    }
};
