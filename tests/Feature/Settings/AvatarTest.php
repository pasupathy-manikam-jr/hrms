<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_photo_is_stored_privately_and_replaced()
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email, 'photo' => UploadedFile::fake()->image('me.png'),
        ])->assertSessionHasNoErrors();

        $first = $user->fresh()->avatar_path;
        Storage::disk('local')->assertExists($first);
        $this->assertStringContainsString('/users/'.$user->id.'/avatar', (string) $user->fresh()->avatar);

        $this->patch(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email, 'photo' => UploadedFile::fake()->image('new.jpg'),
        ]);
        Storage::disk('local')->assertMissing($first);
    }

    public function test_photos_must_be_images_and_are_only_served_to_signed_in_users()
    {
        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user)->patch(route('profile.update'), [
            'name' => $user->name, 'email' => $user->email, 'photo' => UploadedFile::fake()->create('evil.php', 10, 'text/x-php'),
        ])->assertSessionHasErrors('photo');

        $user->replaceAvatar(UploadedFile::fake()->image('me.png'));
        $this->get(route('users.avatar', $user))->assertOk();

        auth()->logout();
        $this->get(route('users.avatar', $user))->assertRedirect(route('login'));
    }
}
