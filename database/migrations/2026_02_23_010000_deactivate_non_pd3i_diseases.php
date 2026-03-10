<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Deactivate non-PD3I diseases, keeping only the 5 priority surveillance diseases.
     */
    public function up()
    {
        DB::table('jenis_kasus_epidemiologi')
            ->whereNotIn('kode_penyakit', [
                'CAMPAK_RUBELLA',
                'DIFTERI_OBS',
                'AFP',
                'PERTUSIS',
                'TETANUS_NEO',
            ])
            ->update(['is_active' => false]);
    }

    /**
     * Re-activate all diseases.
     */
    public function down()
    {
        DB::table('jenis_kasus_epidemiologi')
            ->update(['is_active' => true]);
    }
};
