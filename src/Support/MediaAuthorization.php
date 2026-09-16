<?php

namespace FilamentMediaLibrary\Support;

use Closure;
use FilamentMediaLibrary\Models\Media;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;

class MediaAuthorization
{
    /**
     * @var array<string, Closure>
     */
    protected static array $callbacks = [];

    protected static ?Closure $generalCallback = null;

    /**
     * Authorize a specific media library ability.
     */
    public static function authorize(string $ability, ?Media $media = null, ?Authenticatable $user = null): bool
    {
        $user ??= auth()->user();

        // 1. Check general authorization callback if registered
        if (static::$generalCallback !== null) {
            return (bool) call_user_func(static::$generalCallback, $user, $ability, $media);
        }

        // 2. Check ability-specific callback if registered
        if (isset(static::$callbacks[$ability])) {
            return (bool) call_user_func(static::$callbacks[$ability], $user, $media);
        }

        // 3. Check native Laravel Model Policy on Media if defined
        $policy = Gate::getPolicyFor(Media::class);
        if ($policy !== null) {
            $policyMethod = match ($ability) {
                'view-any' => 'viewAny',
                'upload' => 'create',
                'update' => 'update',
                'trash' => 'delete',
                'restore' => 'restore',
                'delete' => 'forceDelete',
                'select' => 'view',
                default => $ability,
            };

            if (method_exists($policy, $policyMethod)) {
                $gate = $user ? Gate::forUser($user) : Gate::getFacadeRoot();

                return (bool) ($media !== null
                    ? $gate->allows($policyMethod, $media)
                    : $gate->allows($policyMethod, Media::class));
            }
        }

        // 4. Check prefixed Gate ability (e.g., 'filament-media-library.upload')
        $gateAbility = 'filament-media-library.'.$ability;
        if (Gate::has($gateAbility)) {
            $gate = $user ? Gate::forUser($user) : Gate::getFacadeRoot();

            return (bool) ($media !== null
                ? $gate->allows($gateAbility, $media)
                : $gate->allows($gateAbility));
        }

        // 5. Default backward-compatible fallback: allow any authenticated panel user
        return $user !== null || auth()->check();
    }

    /**
     * Check if the current authenticated user has the given ability.
     */
    public static function can(string $ability, ?Media $media = null): bool
    {
        return static::authorize($ability, $media);
    }

    /**
     * Register a callback for a specific ability.
     */
    public static function authorizeUsing(string $ability, Closure $callback): void
    {
        static::$callbacks[$ability] = $callback;
    }

    /**
     * Register a general callback for all abilities: fn (?Authenticatable $user, string $ability, ?Media $media): bool
     */
    public static function authorizeWith(?Closure $callback): void
    {
        static::$generalCallback = $callback;
    }

    /**
     * Reset all registered authorization callbacks (useful for tests).
     */
    public static function reset(): void
    {
        static::$callbacks = [];
        static::$generalCallback = null;
    }
}
