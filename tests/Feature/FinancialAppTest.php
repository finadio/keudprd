<?php

namespace Tests\Feature;

use App\Mail\BackupArchiveMail;
use App\Models\Pptk;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinancialAppTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $user = User::first();
        $this->assertNotNull($user);
        $this->actingAs($user);
    }

    /**
     * Test Dashboard page renders with financial metrics.
     */
    public function test_dashboard_renders_successfully(): void
    {
        $response = $this->get('/dashboard');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard/Index')
            ->has('bank_summary')
            ->has('hitungan_totals')
            ->has('pptk_recap')
        );
    }

    /**
     * Test BKU Sheet tabs render properly.
     */
    public function test_bku_sheets_render(): void
    {
        // 1. Hitungan
        $resHitungan = $this->get('/bku?tab=Hitungan');
        $resHitungan->assertStatus(200);
        $resHitungan->assertInertia(fn (Assert $page) => $page->component('Bku/Index')->where('active_tab', 'Hitungan'));

        // 2. UP
        $resUp = $this->get('/bku?tab=UP');
        $resUp->assertStatus(200);

        // 3. KK
        $resKk = $this->get('/bku?tab=KK');
        $resKk->assertStatus(200);

        // 4. Jml Deb Rek
        $resJml = $this->get('/bku?tab=Jml+Deb+Rek');
        $resJml->assertStatus(200);

        // 5. Deb Rek
        $resDeb = $this->get('/bku?tab=Deb+Rek');
        $resDeb->assertStatus(200);

        // 6. PPTK Individual Sheet
        $resPptk = $this->get('/bku?tab=Aul-Ety');
        $resPptk->assertStatus(200);

        // 7. Reses Sheet
        $resReses = $this->get('/bku?tab=Reses+1');
        $resReses->assertStatus(200);
    }

    /**
     * Test Buku Bank renders monthly sheets.
     */
    public function test_buku_bank_renders(): void
    {
        $response = $this->get('/buku-bank?month=1');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('BankBook/Index')
            ->where('current_month', 1)
            ->has('bank_data')
        );

        $responseFeb = $this->get('/buku-bank?month=2');
        $responseFeb->assertStatus(200);
    }

    /**
     * Test Smart Transaction Import parser endpoint.
     */
    public function test_smart_import_parse_endpoint(): void
    {
        $rawSample = "08/01/2026\t000006/LS/SETWAN\tPencairan UP 2026\t720.000.000\n".
                     "15/01/2026\t000010/LS/SETWAN\tDeb Rek pembelian snack Rita Pasaraya Suratman\t634.200";

        $response = $this->postJson('/impor/parse', [
            'mode' => 'text',
            'raw_text' => $rawSample,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);

        $data = $response->json();
        $this->assertGreaterThanOrEqual(1, $data['total_parsed']);
    }

    /**
     * Test Reconciliation view.
     */
    public function test_reconciliation_renders(): void
    {
        $response = $this->get('/rekonsiliasi?month=1');
        $response->assertStatus(200);
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Reconciliation/Index')
            ->has('items')
            ->has('stats')
        );
    }

    /**
     * Test Excel Export stream download.
     */
    public function test_excel_export_buku_bank(): void
    {
        $response = $this->get('/laporan/export-bank?month=1&year=2026');
        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'spreadsheetml'));
    }

    /**
     * Test Excel Export Hitungan stream download.
     */
    public function test_excel_export_hitungan(): void
    {
        $response = $this->get('/laporan/export-hitungan?year=2026');
        $response->assertStatus(200);
        $this->assertTrue(str_contains($response->headers->get('content-type'), 'spreadsheetml'));
    }

    /**
     * Test updating bank transaction nominal and description.
     */
    public function test_update_bank_transaction(): void
    {
        $tx = Transaction::first();
        $this->assertNotNull($tx);

        $newAmount = 888888;
        $response = $this->put("/buku-bank/transaksi/{$tx->id}", [
            'transaction_date' => $tx->transaction_date->format('Y-m-d'),
            'description' => 'Updated Description for Test',
            'flow_direction' => $tx->flow_direction,
            'amount_net' => $newAmount,
            'pptk_id' => $tx->pptk_id,
            'notes' => 'Updated Note',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('transactions', [
            'id' => $tx->id,
            'amount_net' => $newAmount,
            'description' => 'Updated Description for Test',
        ]);
    }

    /**
     * Test updating BKU transaction nominal and document number.
     */
    public function test_update_bku_transaction(): void
    {
        $tx = Transaction::first();
        $this->assertNotNull($tx);

        $newAmount = 777777;
        $response = $this->put("/bku/transaksi/{$tx->id}", [
            'transaction_date' => $tx->transaction_date->format('Y-m-d'),
            'document_number' => 'DOC/UPDATED/01',
            'description' => 'Updated BKU Description',
            'flow_direction' => $tx->flow_direction,
            'amount_net' => $newAmount,
            'pptk_id' => $tx->pptk_id,
            'notes' => 'Updated BKU Note',
        ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('transactions', [
            'id' => $tx->id,
            'amount_net' => $newAmount,
            'document_number' => 'DOC/UPDATED/01',
        ]);
    }

    /**
     * Test row verification status toggle.
     */
    public function test_toggle_verification(): void
    {
        $tx = Transaction::first();
        $this->assertNotNull($tx);
        $initialState = (bool) $tx->is_verified;

        $response = $this->postJson("/transaksi/{$tx->id}/toggle-verified");
        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'is_verified' => ! $initialState,
        ]);

        $this->assertDatabaseHas('transactions', [
            'id' => $tx->id,
            'is_verified' => ! $initialState,
        ]);
    }

    /**
     * Test retrieving audit trail history logs for a transaction.
     */
    public function test_get_audit_logs(): void
    {
        $tx = Transaction::first();
        $this->assertNotNull($tx);

        $response = $this->getJson("/transaksi/{$tx->id}/audit-logs");
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'transaction_id',
            'description',
            'logs',
        ]);
    }

    /**
     * Test period lock prevents mutation tampering and unlock restores access.
     */
    public function test_period_lock_protects_transactions(): void
    {
        // 1. Lock Period Bulan 1 Tahun 2026
        $this->post('/periode/2026/1/toggle-lock');
        $this->assertDatabaseHas('accounting_periods', [
            'fiscal_year' => 2026,
            'month' => 1,
            'is_closed' => true,
        ]);

        // 2. Attempt adding mutation to locked month -> should fail/redirect with error
        $response = $this->post('/buku-bank/transaksi', [
            'transaction_date' => '2026-01-15',
            'description' => 'Mutasi Terkunci Test',
            'flow_direction' => 'disbursement',
            'amount_net' => 500000,
        ]);
        $response->assertSessionHas('error');

        // 3. Unlock Period
        $this->post('/periode/2026/1/toggle-lock');
        $this->assertDatabaseHas('accounting_periods', [
            'fiscal_year' => 2026,
            'month' => 1,
            'is_closed' => false,
        ]);
    }

    /**
     * Test print view with internal memo draft mode.
     */
    public function test_print_views_render_with_internal_memo(): void
    {
        $resBank = $this->get('/buku-bank/cetak?month=1&year=2026&with_notes=1');
        $resBank->assertStatus(200);
        $resBank->assertSee('LEMBAR KERJA RAPAT INTERNAL');

        $resBku = $this->get('/bku/cetak?tab=Hitungan&year=2026&with_notes=1');
        $resBku->assertStatus(200);
        $resBku->assertSee('LEMBAR KERJA RAPAT INTERNAL');
    }

    /**
     * Test global search finds transactions across BKU and Bank Book.
     */
    public function test_global_search_returns_matched_transactions(): void
    {
        $response = $this->getJson('/api/search?q=GAJI');
        $response->assertStatus(200);
        $response->assertJsonStructure([
            'query',
            'count',
            'results' => [
                '*' => [
                    'id',
                    'date',
                    'description',
                    'amount_net',
                    'amount_formatted',
                    'bku_tab',
                    'bku_url',
                    'bank_month',
                    'bank_url',
                ],
            ],
        ]);
    }

    /**
     * Test full backup archive ZIP download.
     */
    public function test_backup_zip_can_be_downloaded(): void
    {
        $response = $this->get('/laporan/backup-zip?year=2026');
        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/zip');
        $this->assertStringContainsString('.zip', $response->headers->get('content-disposition', ''));
    }

    /**
     * Test backup archive can be emailed to designated address.
     */
    public function test_backup_zip_can_be_sent_via_email(): void
    {
        Mail::fake();

        $response = $this->post('/laporan/backup-email', [
            'email' => 'bendahara@dprd.banyumaskab.go.id',
            'year' => 2026,
        ]);

        $response->assertSessionHas('success');

        Mail::assertSent(BackupArchiveMail::class, function ($mail) {
            return $mail->hasTo('bendahara@dprd.banyumaskab.go.id') &&
                $mail->year === 2026 &&
                file_exists($mail->zipFilePath);
        });
    }
}
