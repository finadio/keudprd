<?php

namespace Tests\Feature;

use App\Models\Pptk;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_user_can_login_with_short_username(): void
    {
        $user = User::factory()->create([
            'name' => 'Test Operator',
            'username' => 'testop',
            'email' => 'testop@dprd.banyumaskab.go.id',
            'password' => Hash::make('SecretPass#2026'),
            'role' => 'operator',
        ]);

        $response = $this->post('/login', [
            'email' => 'testop',
            'password' => 'SecretPass#2026',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_admin_can_create_user_with_role(): void
    {
        $admin = User::where('role', 'superadmin')->first() ?? User::factory()->create(['role' => 'superadmin']);

        $response = $this->actingAs($admin)
            ->post('/pengaturan/users', [
                'name' => 'Staff Keuangan Baru',
                'username' => 'stafbaru',
                'email' => 'stafbaru@dprd.banyumaskab.go.id',
                'role' => 'operator',
                'password' => 'StafBaru#2026',
            ]);

        $response->assertSessionHas('success');
        $this->assertDatabaseHas('users', [
            'username' => 'stafbaru',
            'role' => 'operator',
        ]);
    }

    public function test_admin_can_update_user_role_and_reset_password(): void
    {
        $admin = User::where('role', 'superadmin')->first() ?? User::factory()->create(['role' => 'superadmin']);

        $targetUser = User::factory()->create([
            'name' => 'Target User',
            'username' => 'targetuser',
            'role' => 'operator',
        ]);

        // Update role to bendahara
        $this->actingAs($admin)
            ->put("/pengaturan/users/{$targetUser->id}", [
                'name' => 'Target User Updated',
                'username' => 'targetuser',
                'role' => 'bendahara',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('users', [
            'id' => $targetUser->id,
            'role' => 'bendahara',
        ]);

        // Reset password
        $this->actingAs($admin)
            ->post("/pengaturan/users/{$targetUser->id}/reset-password", [
                'password' => 'NewResetPass#2026',
                'password_confirmation' => 'NewResetPass#2026',
            ])
            ->assertSessionHas('success');

        $targetUser->refresh();
        $this->assertTrue(Hash::check('NewResetPass#2026', $targetUser->password));
    }

    public function test_pptk_creation_with_auto_account(): void
    {
        $admin = User::where('role', 'superadmin')->first() ?? User::factory()->create(['role' => 'superadmin']);

        $this->actingAs($admin)
            ->post('/pengaturan/pptk', [
                'display_name' => 'Pak Bambang',
                'code' => 'BAMBANG_TEST',
                'create_login_account' => true,
                'username' => 'bambang',
                'password' => 'PptkBambang#2026',
            ])
            ->assertSessionHas('success');

        $pptk = Pptk::where('code', 'BAMBANG_TEST')->first();
        $this->assertNotNull($pptk);

        $this->assertDatabaseHas('users', [
            'username' => 'bambang',
            'role' => 'pptk',
            'pptk_id' => $pptk->id,
        ]);
    }
}
