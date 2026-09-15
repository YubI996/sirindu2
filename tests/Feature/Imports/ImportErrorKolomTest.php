<?php

namespace Tests\Feature\Imports;

use App\Models\Anak;
use App\Models\ImportLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImportErrorKolomTest extends TestCase
{
    use RefreshDatabase;

    public function test_impor_hp_40_karakter_dan_detail_kolom_gagal_sampai_notifikasi(): void
    {
        Storage::fake('local');
        config(['queue.default' => 'sync']);
        $this->actingAs(User::factory()->create(['type' => 0]));

        $column = DB::selectOne("SELECT CHARACTER_MAXIMUM_LENGTH AS panjang, IS_NULLABLE AS nullable_column
            FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'anak' AND COLUMN_NAME = 'no_hp'");
        $this->assertSame(40, (int) $column->panjang);
        $this->assertSame('YES', $column->nullable_column);

        $lama = Anak::create([
            'nik' => '6474010101249002', 'nama' => 'Anak Lama Uji', 'jk' => 1,
            'tgl_lahir' => '2024-01-01', 'status' => 1, 'no_hp' => '081234567890',
        ]);
        $hp40 = '081234567890 /082345678901 /083456789012';
        $this->assertSame(40, mb_strlen($hp40));
        $csv = "nik,nama,jk,tgl_lahir,no_hp\n"
            ."6474010101249001,Anak Uji Empat Puluh,L,2024-01-01,{$hp40}\n"
            ."6474010101249002,Anak Uji Terlalu Panjang,L,2024-01-01,{$hp40}4\n"
            .'6474010101249003,'.str_repeat('A', 256).",L,2024-01-01,081234567890\n"
            ."6474010101249004,Anak Uji Berikutnya,L,2024-01-01,081234567890 / 082345678901\n";

        $response = $this->postJson(route('admin.importCsv.anak'), [
            'file_anak' => UploadedFile::fake()->createWithContent('uji-kolom.csv', $csv),
        ])->assertOk()->assertJsonPath('ok', true);

        $log = ImportLog::findOrFail($response->json('log_id'));
        $this->assertSame('done', $log->status, implode('; ', $log->failures ?? []));
        $this->assertSame(2, (int) $log->success_count);
        $this->assertSame(2, (int) $log->failure_count);
        $this->assertSame($hp40, Anak::where('nik', '6474010101249001')->sole()->no_hp);
        $this->assertSame('081234567890 / 082345678901', Anak::where('nik', '6474010101249004')->sole()->no_hp);
        $this->assertSame('081234567890', $lama->refresh()->no_hp);
        $this->assertSame('Anak Lama Uji', $lama->nama);
        $this->assertDatabaseMissing('anak', ['nik' => '6474010101249003']);

        $failures = $this->getJson(route('admin.importCsv.status', ['type' => 'anak']))
            ->assertOk()->assertJsonPath('0.failure_count', 2)->json('0.failures');
        $this->assertCount(2, $failures);
        $this->assertStringContainsString('Baris 3', $failures[0]);
        $this->assertStringContainsString('Data terlalu panjang (kolom: no_hp)', $failures[0]);
        $this->assertStringContainsString('Baris 4', $failures[1]);
        $this->assertStringContainsString('Data terlalu panjang (kolom: nama)', $failures[1]);
        $this->assertStringNotContainsString('SQL:', implode(' ', $failures));
        $this->get(route('admin.importCsv.index'))->assertOk()
            ->assertSee('Data terlalu panjang (kolom: no_hp)')
            ->assertSee('Data terlalu panjang (kolom: nama)');
    }
}
