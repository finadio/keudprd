<?php

namespace Tests\Feature;

use App\Models\Pptk;
use App\Models\Subunit;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PptkManagementTest extends TestCase
{
    use DatabaseTransactions;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::first() ?? User::factory()->create();
    }

    public function test_setting_page_renders_with_pptks_and_subunits(): void
    {
        $response = $this->actingAs($this->user)->get('/pengaturan');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Setting/Index')
            ->has('pptks')
            ->has('subunits')
        );
    }

    public function test_user_can_create_new_pptk(): void
    {
        $subunit = Subunit::first();

        $payload = [
            'name' => 'DRS. HIDAYAT SANTOSO, M.M.',
            'display_name' => 'Pak Hidayat',
            'code' => 'HDYT',
            'nip' => '19820101 200501 1 005',
            'subunit_id' => $subunit?->id,
            'sheet_name' => 'Hidayat',
            'color_tag' => '#0d9488',
            'sort_order' => 15,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)
            ->post('/pengaturan/pptk', $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('pptks', [
            'code' => 'HDYT',
            'display_name' => 'Pak Hidayat',
            'name' => 'DRS. HIDAYAT SANTOSO, M.M.',
            'is_active' => true,
        ]);
    }

    public function test_user_can_update_existing_pptk(): void
    {
        $pptk = Pptk::create([
            'code' => 'TEST_PPTK',
            'name' => 'PEJABAT UJI COBA LAMA',
            'display_name' => 'Test Lama',
            'sort_order' => 99,
            'is_active' => true,
        ]);

        $payload = [
            'code' => 'TEST_PPTK',
            'name' => 'PEJABAT UJI COBA PERIODE BARU, M.Si.',
            'display_name' => 'Test Baru',
            'nip' => '19850202 201001 2 002',
            'sheet_name' => 'TestBaru',
            'color_tag' => '#7c3aed',
            'sort_order' => 10,
            'is_active' => true,
        ];

        $response = $this->actingAs($this->user)
            ->put("/pengaturan/pptk/{$pptk->id}", $payload);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('pptks', [
            'id' => $pptk->id,
            'name' => 'PEJABAT UJI COBA PERIODE BARU, M.Si.',
            'display_name' => 'Test Baru',
            'sheet_name' => 'TestBaru',
        ]);
    }

    public function test_user_can_toggle_pptk_active_status(): void
    {
        $pptk = Pptk::create([
            'code' => 'TOGGLE_TEST',
            'name' => 'PEJABAT TOGGLE',
            'display_name' => 'Toggle Test',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->post("/pengaturan/pptk/{$pptk->id}/toggle-active");

        $response->assertRedirect();
        $this->assertDatabaseHas('pptks', [
            'id' => $pptk->id,
            'is_active' => false,
        ]);

        // Toggle back to active
        $this->actingAs($this->user)
            ->post("/pengaturan/pptk/{$pptk->id}/toggle-active");

        $this->assertDatabaseHas('pptks', [
            'id' => $pptk->id,
            'is_active' => true,
        ]);
    }

    public function test_deleting_pptk_without_transactions_hard_deletes(): void
    {
        $pptk = Pptk::create([
            'code' => 'DEL_UNUSED',
            'name' => 'PEJABAT BELUM DIGUNAKAN',
            'display_name' => 'Unused PPTK',
            'is_active' => true,
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/pengaturan/pptk/{$pptk->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('pptks', [
            'id' => $pptk->id,
        ]);
    }

    public function test_deleting_pptk_with_transactions_deactivates_safely(): void
    {
        $pptk = Pptk::create([
            'code' => 'DEL_USED',
            'name' => 'PEJABAT DENGAN TRANSAKSI',
            'display_name' => 'Used PPTK',
            'is_active' => true,
        ]);

        Transaction::create([
            'batch_id' => null,
            'period_year' => 2026,
            'period_month' => 1,
            'transaction_date' => '2026-01-15',
            'transaction_type' => 'general',
            'flow_direction' => 'disbursement',
            'amount_gross' => 500000,
            'amount_tax' => 0,
            'amount_net' => 500000,
            'description' => 'Transaksi uji coba perlindungan riwayat PPTK',
            'pptk_id' => $pptk->id,
            'status' => 'posted',
        ]);

        $response = $this->actingAs($this->user)
            ->delete("/pengaturan/pptk/{$pptk->id}");

        $response->assertRedirect();
        $response->assertSessionHas('success');

        // Still exists in database but deactivated
        $this->assertDatabaseHas('pptks', [
            'id' => $pptk->id,
            'is_active' => false,
        ]);
    }
}
