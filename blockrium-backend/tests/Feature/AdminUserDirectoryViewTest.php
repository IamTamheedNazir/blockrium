<?php

namespace Tests\Feature;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\MessageBag;
use Tests\TestCase;

class AdminUserDirectoryViewTest extends TestCase
{
    public function test_user_directory_renders_compact_identity_status_and_safe_avatar_fallback(): void
    {
        $users = new LengthAwarePaginator([
            (object) [
                'id' => 52,
                'username' => 'blockrium_member',
                'name' => 'Blockrium Member',
                'email' => 'member@example.test',
                'phone_number' => '+123456789',
                'country_name' => null,
                'refferal_code' => 'BRC-52',
                'user_image' => 'member.png',
                'is_active' => 1,
            ],
        ], 1, 10, 1, ['path' => '/admin/users']);

        $html = view('users.index', [
            'customer_data' => $users,
            'search' => '',
            'errors' => new MessageBag(),
        ])->render();

        $this->assertStringContainsString('User directory', $html);
        $this->assertStringContainsString('1 accounts', $html);
        $this->assertStringContainsString('ID 52 · member@example.test', $html);
        $this->assertStringContainsString('Not set', $html);
        $this->assertStringContainsString('user-status-active', $html);
        $this->assertStringContainsString('width: 44px !important', $html);
        $this->assertStringContainsString('onerror="this.hidden=true"', $html);
        $this->assertStringContainsString('Deactivate', $html);
    }

    public function test_user_directory_shows_a_clear_empty_search_state(): void
    {
        $users = new LengthAwarePaginator([], 0, 10, 1, ['path' => '/admin/users']);

        $html = view('users.index', [
            'customer_data' => $users,
            'search' => 'no-match',
            'errors' => new MessageBag(),
        ])->render();

        $this->assertStringContainsString('No users match this search.', $html);
        $this->assertStringContainsString('Reset', $html);
    }
}
