<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\PaguRealization;
use App\Models\Pptk;
use App\Models\User;
use Tests\TestCase;

class PaguRealizationTest extends TestCase
{
    public function test_pagu_dashboard_page_is_accessible(): void
    {
        $user = User::first() ?? User::factory()->create();

        $response = $this->actingAs($user)->get('/pagu');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Pagu/Index'));
    }

    public function test_can_record_pencairan_and_pengembalian_transactions(): void
    {
        $user = User::first() ?? User::factory()->create();
        $pptk = Pptk::first();
        $activity = Activity::where('pptk_id', $pptk->id)->first();

        // 1. Pencairan kas kegiatan
        $response = $this->actingAs($user)->post('/pagu/transaksi', [
            'pptk_id' => $pptk->id,
            'activity_id' => $activity?->id,
            'transaction_date' => '2026-11-19',
            'type' => 'pencairan',
            'amount' => 15000000,
            'recipient' => 'Komisi A DPRD',
            'description' => 'Uang Muka Kegiatan Kunjungan Kerja Komisi A',
            'source_fund' => 'up_gu',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('pagu_realizations', [
            'pptk_id' => $pptk->id,
            'type' => 'pencairan',
            'amount' => 15000000,
            'recipient' => 'Komisi A DPRD',
        ]);

        $realization = PaguRealization::where('pptk_id', $pptk->id)
            ->where('type', 'pencairan')
            ->latest('id')
            ->first();

        // 2. Pengembalian sisa kas kegiatan
        $responseReturn = $this->actingAs($user)->post('/pagu/transaksi', [
            'pptk_id' => $pptk->id,
            'activity_id' => $activity?->id,
            'transaction_date' => '2026-11-20',
            'type' => 'pengembalian',
            'amount' => 2000000,
            'recipient' => 'Komisi A DPRD',
            'description' => 'Pengembalian Sisa Kas Kunjungan Kerja Komisi A',
            'source_fund' => 'up_gu',
        ]);

        $responseReturn->assertRedirect();
        $this->assertDatabaseHas('pagu_realizations', [
            'pptk_id' => $pptk->id,
            'type' => 'pengembalian',
            'amount' => 2000000,
        ]);

        // 3. Toggle SPJ SIPD status
        $responseToggle = $this->actingAs($user)->post("/pagu/transaksi/{$realization->id}/toggle-spj", [
            'is_spj_submitted' => true,
            'spj_document_number' => '0099/SPJ/SETWAN/2026',
        ]);

        $responseToggle->assertRedirect();
        $this->assertDatabaseHas('pagu_realizations', [
            'id' => $realization->id,
            'is_spj_submitted' => 1,
            'spj_document_number' => '0099/SPJ/SETWAN/2026',
        ]);
    }
}
