<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthProtectionTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * Test all protected web routes redirect unauthenticated guests to the login page.
     */
    public function test_unauthenticated_users_are_redirected_to_login(): void
    {
        $protectedGetRoutes = [
            '/dashboard',
            '/bku',
            '/buku-bank',
            '/impor',
            '/rekonsiliasi',
            '/laporan',
            '/laporan/backup-zip',
            '/pengaturan',
        ];

        foreach ($protectedGetRoutes as $route) {
            $this->get($route)->assertRedirect('/login');
        }

        $this->post('/bku/transaksi', [])->assertRedirect('/login');
        $this->post('/pengaturan/tahun-baru', ['new_year' => 2027])->assertRedirect('/login');
        $this->getJson('/api/search?q=GAJI')->assertStatus(401);
    }

    /**
     * Test authenticated user can update password when current password matches.
     */
    public function test_authenticated_user_can_change_password(): void
    {
        $user = User::first();
        $this->assertNotNull($user);

        // Wrong current password is rejected
        $this->actingAs($user)
            ->post('/pengaturan/ganti-password', [
                'current_password' => 'wrong-password',
                'password' => 'NewSecurePass123',
                'password_confirmation' => 'NewSecurePass123',
            ])
            ->assertSessionHasErrors('current_password');

        // Matching current password succeeds
        $this->actingAs($user)
            ->post('/pengaturan/ganti-password', [
                'current_password' => 'password',
                'password' => 'NewSecurePass123',
                'password_confirmation' => 'NewSecurePass123',
            ])
            ->assertSessionHas('success');

        $user->refresh();
        $this->assertTrue(Hash::check('NewSecurePass123', $user->password));
    }
}

