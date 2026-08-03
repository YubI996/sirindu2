<?php

namespace Tests\Unit\Support;

use App\Support\LabStatus;
use PHPUnit\Framework\TestCase;

class LabStatusBinaryTest extends TestCase
{
    public function test_maps_old_values_to_binary(): void
    {
        foreach (['positif', 'negatif', 'proses', 'diperiksa_lab'] as $v) {
            $this->assertSame('diperiksa', LabStatus::toBinary($v));
        }
        foreach (['belum_diperiksa', 'tidak_diperiksa_lab', '', null] as $v) {
            $this->assertSame('tidak', LabStatus::toBinary($v));
        }
        // sudah biner → idempoten
        $this->assertSame('diperiksa', LabStatus::toBinary('diperiksa'));
        $this->assertSame('tidak', LabStatus::toBinary('tidak'));
        // toleran spasi/kapital
        $this->assertSame('diperiksa', LabStatus::toBinary(' Positif '));
    }
}
