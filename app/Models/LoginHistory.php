<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'ip', 'user_agent', 'browser', 'os', 'device', 'logged_in_at'])]
class LoginHistory extends Model
{
    use MassPrunable;

    public const KEEP_DAYS = 90;

    public $timestamps = false;

    /**
     * Record a successful login with the browser, OS and device read from the user agent.
     */
    public static function record(User $user, ?string $ip, ?string $userAgent): self
    {
        return self::create([
            'user_id' => $user->id,
            'ip' => $ip,
            'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 512),
            ...self::parse((string) $userAgent),
            'logged_in_at' => now(),
        ]);
    }

    /**
     * A deliberately small user-agent parser: first match wins, so the order matters
     * (Edge and Opera also say "Chrome", Chrome also says "Safari", iOS also says "Mac OS X").
     *
     * @return array{browser: string, os: string, device: string}
     */
    public static function parse(string $userAgent): array
    {
        $first = function (array $patterns) use ($userAgent): string {
            foreach ($patterns as $name => $pattern) {
                if (preg_match($pattern, $userAgent)) {
                    return $name;
                }
            }

            return 'Unknown';
        };

        return [
            'browser' => $first(['Edge' => '/Edg(e|A|iOS)?\//', 'Opera' => '/OPR\/|Opera/', 'Firefox' => '/Firefox\/|FxiOS/', 'Chrome' => '/Chrome\/|CriOS/', 'Safari' => '/Safari\//']),
            'os' => $first(['iOS' => '/iPhone|iPad|iPod/', 'Android' => '/Android/', 'Windows' => '/Windows/', 'macOS' => '/Mac OS X|Macintosh/', 'Linux' => '/Linux|X11/']),
            'device' => $first(['Tablet' => '/iPad|Tablet/', 'Mobile' => '/Mobi|iPhone|Android/', 'Desktop' => '/Windows|Macintosh|X11|Linux/']),
        ];
    }

    /**
     * Records the user may see: everyone's with manage-login-history, otherwise their own.
     *
     * @param  Builder<static>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('manage-login-history')) {
            $query->where('user_id', $user->id);
        }
    }

    /**
     * @return Builder<static>
     */
    public function prunable(): Builder
    {
        return static::query()->where('logged_in_at', '<', now()->subDays(self::KEEP_DAYS));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'logged_in_at' => 'datetime',
        ];
    }
}
