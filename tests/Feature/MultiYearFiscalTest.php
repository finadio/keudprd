<?php

namespace Tests\Feature;

use App\Models\AccountingPeriod;
use App\Models\MasterSetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BackupExportService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class MultiYearFiscalTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::first();
        $this->assertNotNull($user);
        $this->actingAs($user);
    }

    /**
     * Test switching fiscal year persists in session and isolates view data without altering 2026 archives.
     */
    public function test_switching_fiscal_year_persists_in_session_and_isolates_views(): void
    {
        $response = $this->post('/tahun-anggaran/pilih', [
            'year' => 2027,
            'current_url' => '/dashboard',
        ]);

        $response->assertRedirect('/dashboard?year=2027');
        $response->assertSessionHas('active_fiscal_year', 2027);

        // Visiting Dashboard without explicit ?year= follows session active year 2027
        $this->withSession(['active_fiscal_year' => 2027])
            ->get('/dashboard')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard/Index')
                ->where('year', 2027)
                ->where('fiscal_year.active', 2027)
                ->where('total_transactions', 0)
            );

        // Reses 2027 stays clean without mixing 2026 Reses rows
        $this->withSession(['active_fiscal_year' => 2027])
            ->get('/bku?tab=Reses+1')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Bku/Index')
                ->where('year', 2027)
                ->where('sheet_data.rows', [])
            );

        // 2026 Reses archive remains intact when explicitly queried
        $this->get('/bku?tab=Reses+1&year=2026')
            ->assertStatus(200)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Bku/Index')
                ->where('year', 2026)
                ->has('sheet_data.rows', 4)
            );
    }

    /**
     * Test opening a new fiscal year preserves all prior-year transactions and optionally locks prior-year periods.
     */
    public function test_opening_new_fiscal_year_preserves_prior_year_data_and_locks_periods(): void
    {
        $count2026Before = Transaction::where('period_year', 2026)->count();
        $this->assertGreaterThan(0, $count2026Before);

        $response = $this->post('/pengaturan/tahun-baru', [
            'new_year' => 2027,
            'lock_previous_year' => true,
        ]);

        $response->assertRedirect('/pengaturan?year=2027');
        $response->assertSessionHas('success');
        $response->assertSessionHas('active_fiscal_year', 2027);

        // Existing 2026 data must remain completely intact
        $this->assertSame($count2026Before, Transaction::where('period_year', 2026)->count());

        // All 12 periods of 2026 are locked
        $this->assertSame(
            12,
            AccountingPeriod::where('fiscal_year', 2026)->where('is_closed', true)->count()
        );

        // All 12 periods of 2027 are created and unlocked
        $this->assertSame(
            12,
            AccountingPeriod::where('fiscal_year', 2027)->where('is_closed', false)->count()
        );

        // Active MasterSetting is now 2027
        $activeSetting = MasterSetting::current();
        $this->assertNotNull($activeSetting);
        $this->assertSame(2027, $activeSetting->fiscal_year);
    }

    /**
     * Test opening new fiscal year validates reasonable year bounds.
     */
    public function test_opening_new_fiscal_year_validates_year_range(): void
    {
        $response = $this->post('/pengaturan/tahun-baru', [
            'new_year' => 1990,
        ]);

        $response->assertSessionHasErrors('new_year');
    }

    /**
     * Test auto-pruning removes older ZIP archives while keeping the newest N files.
     */
    public function test_backup_pruning_keeps_only_latest_n_zips(): void
    {
        $tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'keudprd_prune_test_'.uniqid();
        File::makeDirectory($tempDir, 0755, true);

        try {
            $baseTime = time() - 1000;
            for ($i = 1; $i <= 7; $i++) {
                $path = $tempDir.DIRECTORY_SEPARATOR."backup_{$i}.zip";
                File::put($path, "dummy zip {$i}");
                touch($path, $baseTime + ($i * 60));
            }

            $service = new BackupExportService;
            $deleted = $service->pruneOldBackups(5, $tempDir);

            $this->assertSame(2, $deleted);
            $remaining = collect(File::files($tempDir))
                ->map(fn (\SplFileInfo $file): string => $file->getFilename())
                ->sort()
                ->values()
                ->all();

            $this->assertSame([
                'backup_3.zip',
                'backup_4.zip',
                'backup_5.zip',
                'backup_6.zip',
                'backup_7.zip',
            ], $remaining);
        } finally {
            File::deleteDirectory($tempDir);
        }
    }
}
