<?php

namespace Tests\Unit\Models;

use App\Models\JenisKasusEpidemiologi;
use App\Models\SurveillanceCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class JenisKasusEpidemiologiTest extends TestCase
{
    use DatabaseTransactions;

    public function test_can_create_jenis_kasus()
    {
        $jenis = JenisKasusEpidemiologi::factory()->create([
            'kode_penyakit' => 'DBD',
            'nama_penyakit' => 'Demam Berdarah Dengue',
            'kategori' => 'vector_borne',
        ]);

        $this->assertDatabaseHas('jenis_kasus_epidemiologi', [
            'kode_penyakit' => 'DBD',
            'nama_penyakit' => 'Demam Berdarah Dengue',
            'kategori' => 'vector_borne',
        ]);
    }

    public function test_scope_active_returns_only_active_records()
    {
        JenisKasusEpidemiologi::factory()->count(3)->create(['is_active' => true]);
        JenisKasusEpidemiologi::factory()->count(2)->create(['is_active' => false]);

        $this->assertCount(3, JenisKasusEpidemiologi::active()->get());
    }

    public function test_scope_by_category_filters_correctly()
    {
        JenisKasusEpidemiologi::factory()->create(['kategori' => 'PD3I']);
        JenisKasusEpidemiologi::factory()->create(['kategori' => 'PD3I']);
        JenisKasusEpidemiologi::factory()->create(['kategori' => 'vector_borne']);

        $this->assertCount(2, JenisKasusEpidemiologi::byCategory('PD3I')->get());
        $this->assertCount(1, JenisKasusEpidemiologi::byCategory('vector_borne')->get());
    }

    public function test_has_many_surveillance_cases()
    {
        $disease = JenisKasusEpidemiologi::factory()->create();

        $this->assertCount(0, $disease->surveillanceCases);
    }

    public function test_is_active_is_cast_to_boolean()
    {
        $jenis = JenisKasusEpidemiologi::factory()->create(['is_active' => 1]);

        $this->assertTrue($jenis->is_active);
        $this->assertIsBool($jenis->is_active);
    }
}
