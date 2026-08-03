<?php

namespace Tests\Feature\Epidemiologi;

use App\Http\Requests\Epidemiologi\StoreSurveillanceCaseRequest;
use App\Http\Requests\Epidemiologi\UpdateSurveillanceCaseRequest;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StatusLabValidationTest extends TestCase
{
    public function test_status_lab_rule_is_binary_on_both_requests(): void
    {
        $this->assertSame('nullable|in:diperiksa,tidak', (new StoreSurveillanceCaseRequest())->rules()['status_lab']);
        $this->assertSame('nullable|in:diperiksa,tidak', (new UpdateSurveillanceCaseRequest())->rules()['status_lab']);
    }

    public function test_binary_values_pass_and_old_values_fail(): void
    {
        $rule = ['status_lab' => (new StoreSurveillanceCaseRequest())->rules()['status_lab']];

        $this->assertTrue(Validator::make(['status_lab' => 'diperiksa'], $rule)->passes());
        $this->assertTrue(Validator::make(['status_lab' => 'tidak'], $rule)->passes());
        $this->assertTrue(Validator::make(['status_lab' => null], $rule)->passes());

        // Nilai lama 6-value tidak lagi valid.
        $this->assertFalse(Validator::make(['status_lab' => 'positif'], $rule)->passes());
        $this->assertFalse(Validator::make(['status_lab' => 'belum_diperiksa'], $rule)->passes());
    }
}
