<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    protected function makeUser(array $overrides = []): User
    {
        $company = Company::query()->create([
            'code' => 'TST'.fake()->unique()->numerify('###'),
            'name' => 'Test Company',
            'is_active' => true,
        ]);

        return User::factory()->create(array_merge([
            'company_id' => $company->id,
            'username' => 'deskuser',
            'password' => 'password',
        ], $overrides));
    }

    public function test_profile_page_is_displayed(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertSee('My Profile')
            ->assertSee('User ID');
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user);

        Volt::test('pages.profile')
            ->set('name', 'Test User')
            ->set('username', 'testuser')
            ->call('save')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('testuser', $user->username);
    }

    public function test_password_can_be_updated(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user);

        Volt::test('pages.profile')
            ->set('name', $user->name)
            ->set('username', $user->username)
            ->set('current_password', 'password')
            ->set('password', 'new-password-123')
            ->set('password_confirmation', 'new-password-123')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertTrue(Hash::check('new-password-123', $user->refresh()->password));
    }

    public function test_avatar_can_be_uploaded(): void
    {
        Storage::fake('public');

        $user = $this->makeUser();
        $this->actingAs($user);

        $png = base64_encode(hex2bin(
            '89504e470d0a1a0a0000000d49484452000000010000000108060000001f15c4890000000a49444154789a63000100000500010d0a2db40000000049454e44ae426082'
        ));

        Volt::test('pages.profile')
            ->call('uploadAvatar', 'data:image/png;base64,'.$png, 'avatar.png')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);
    }
}
