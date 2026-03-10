<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ZScoreSeeder extends Seeder
{
    /**
     * Seed z_score table from the existing SQL dump file.
     */
    public function run(): void
    {
        if (DB::table('z_score')->count() > 0) {
            $this->command?->info('z_score table already has data, skipping.');
            return;
        }

        $sqlPath = database_path('insertDataSirindu.sql');

        if (!file_exists($sqlPath)) {
            $this->command?->warn('SQL file not found: ' . $sqlPath);
            return;
        }

        $this->command?->info('Seeding z_score from SQL file...');

        $sql = file_get_contents($sqlPath);

        // Extract only the z_score INSERT statement
        if (preg_match('/INSERT INTO `z_score`[^;]+;/s', $sql, $matches)) {
            DB::unprepared($matches[0]);
            $count = DB::table('z_score')->count();
            $this->command?->info("z_score seeded with {$count} rows.");
        } else {
            $this->command?->warn('No z_score INSERT statement found in SQL file.');
        }
    }
}
