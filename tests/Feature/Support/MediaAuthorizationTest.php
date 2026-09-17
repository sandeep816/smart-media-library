<?php

namespace FilamentMediaLibrary\Tests\Feature\Support;

use FilamentMediaLibrary\Models\Media;
use FilamentMediaLibrary\Support\MediaAuthorization;
use FilamentMediaLibrary\Tests\Fixtures\User;
use FilamentMediaLibrary\Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;

class MediaAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        MediaAuthorization::reset();
        parent::tearDown();
    }

    public function test_default_authorization_allows_authenticated_user_and_denies_guest(): void
    {
        // 1. Guest user
        auth()->logout();
        $this->assertFalse(MediaAuthorization::authorize('view-any'));
        $this->assertFalse(MediaAuthorization::can('upload'));

        // 2. Authenticated user
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->assertTrue(MediaAuthorization::authorize('view-any'));
        $this->assertTrue(MediaAuthorization::can('upload'));
        $this->assertTrue(MediaAuthorization::can('trash'));
        $this->assertTrue(MediaAuthorization::can('restore'));
        $this->assertTrue(MediaAuthorization::can('select'));
    }

    public function test_ability_specific_closure_callback_overrides_default(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Deny upload via closure
        MediaAuthorization::authorizeUsing('upload', fn ($u): bool => false);

        $this->assertFalse(MediaAuthorization::can('upload'));
        // Other abilities remain permitted
        $this->assertTrue(MediaAuthorization::can('view-any'));
        $this->assertTrue(MediaAuthorization::can('trash'));

        // Allow upload conditionally
        MediaAuthorization::authorizeUsing('upload', fn ($u): bool => $u->email === 'admin@example.com');
        $this->assertFalse(MediaAuthorization::can('upload'));

        $admin = User::factory()->create(['email' => 'admin@example.com']);
        $this->actingAs($admin);
        $this->assertTrue(MediaAuthorization::can('upload'));
    }

    public function test_general_closure_callback_evaluates_all_abilities(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        MediaAuthorization::authorizeWith(function ($u, string $ability, ?Media $media): bool {
            return in_array($ability, ['view-any', 'select'], true);
        });

        $this->assertTrue(MediaAuthorization::can('view-any'));
        $this->assertTrue(MediaAuthorization::can('select'));
        $this->assertFalse(MediaAuthorization::can('upload'));
        $this->assertFalse(MediaAuthorization::can('trash'));
    }

    public function test_gate_ability_takes_precedence_over_default(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Gate::define('filament-media-library.trash', fn ($u, ?Media $media = null): bool => false);

        $this->assertFalse(MediaAuthorization::can('trash'));
        $this->assertTrue(MediaAuthorization::can('upload'));
    }

    public function test_model_policy_is_honored_for_media_actions(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $policy = new class
        {
            public function viewAny($user): bool
            {
                return true;
            }

            public function create($user): bool
            {
                return false; // Deny uploads
            }

            public function delete($user, Media $media): bool
            {
                return $media->uploaded_by === (string) $user->getAuthIdentifier();
            }
        };

        Gate::policy(Media::class, $policy::class);

        $this->assertTrue(MediaAuthorization::can('view-any'));
        $this->assertFalse(MediaAuthorization::can('upload'));

        $ownMedia = Media::query()->create([
            'filename' => 'own.jpg',
            'original_filename' => 'own.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => 1024,
            'disk' => 'public',
            'uploaded_by' => (string) $user->getAuthIdentifier(),
        ]);

        $otherMedia = Media::query()->create([
            'filename' => 'other.jpg',
            'original_filename' => 'other.jpg',
            'mime_type' => 'image/jpeg',
            'extension' => 'jpg',
            'size' => 1024,
            'disk' => 'public',
            'uploaded_by' => '9999',
        ]);

        $this->assertTrue(MediaAuthorization::can('trash', $ownMedia));
        $this->assertFalse(MediaAuthorization::can('trash', $otherMedia));
    }
}
