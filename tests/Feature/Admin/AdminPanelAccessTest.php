<?php

namespace Tests\Feature\Admin;

use App\Domain\Shared\Enums\UserProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_the_panel(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => true]);

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_non_admin_cannot_access_the_panel(): void
    {
        $professional = User::factory()->professional()->create(['is_active' => true]);

        $this->actingAs($professional)->get('/admin')->assertForbidden();
    }

    public function test_blocked_admin_cannot_access_the_panel(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => false]);

        $this->actingAs($admin)->get('/admin')->assertForbidden();
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }
}
