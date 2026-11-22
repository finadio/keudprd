<?php

namespace Tests\Feature;

use App\Models\Gaji\GajiBudgetAllocation;
use App\Models\Gaji\GajiPayrollPeriod;
use App\Models\Gaji\GajiRecipient;
use App\Models\User;
use App\Services\Gaji\GajiBudgetControlService;
use App\Services\Gaji\GajiCalculatorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GajiModuleTest extends TestCase
{
    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::firstOrCreate(
            ['email' => 'admin_gaji@example.com'],
            [
                'name' => 'Admin Keuangan Test',
                'role' => 'admin',
                'password' => bcrypt('password'),
            ]
        );
    }

    public function test_can_access_gaji_dashboard(): void
    {
        $response = $this->actingAs($this->admin)->get('/gaji');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Gaji/Index'));
    }

    public function test_can_access_recipients_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/gaji/penerima');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Gaji/Penerima/Index'));
    }

    public function test_can_access_simulation_page(): void
    {
        $response = $this->actingAs($this->admin)->get('/gaji/simulasi?year=2026&month=11');
        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Gaji/Simulasi/Index'));
    }

    public function test_calculator_service_handles_asn_and_dprd(): void
    {
        $calc = app(GajiCalculatorService::class);

        // Test DPRD Ketua
        $ketua = GajiRecipient::where('sub_category', 'ketua_dprd')->first();
        if ($ketua) {
            $res = $calc->calculateForRecipient($ketua);
            $this->assertEquals(2100000.0, $res['basic_salary_or_rep']);
            $this->assertEquals(210000.0, $res['package_allowance']);
            $this->assertEquals(3045000.0, $res['position_allowance']);
            $this->assertEquals(14700000.0, $res['tki_allowance']);
            $this->assertEquals(42625000.0, $res['housing_allowance']);
            $this->assertGreaterThan(50000000.0, $res['gross_amount']);
        }

        // Test PPPK Paruh Waktu: Dikecualikan dari TPP
        $pppkParuh = GajiRecipient::where('sub_category', 'pppk_paruh_waktu')->first();
        if ($pppkParuh) {
            $resParuh = $calc->calculateForRecipient($pppkParuh);
            $this->assertEquals(0.0, $resParuh['tpp_amount']);
        }
    }

    public function test_can_commit_payroll_simulation(): void
    {
        $response = $this->actingAs($this->admin)->post('/gaji/simulasi/commit', [
            'year' => 2026,
            'month' => 11,
            'productivity_pct' => 95,
            'discipline_pct' => 100,
            'is_reses' => false,
        ]);

        $response->assertRedirect('/gaji/laporan?year=2026&month=11');

        $this->assertDatabaseHas('gaji_payroll_periods', [
            'period_year' => 2026,
            'period_month' => 11,
        ]);
    }
}
