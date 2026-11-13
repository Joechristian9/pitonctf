<?php

namespace App\Models;

use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * The site runs exactly one game. Draft hides it from students, running is playable, locked is read-only.
 *
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property string $status
 * @property string|null $writeup_template Path on the "ctf" disk (public/ctf/uploads).
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description', 'status', 'writeup_template'])]
class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory;

    public const DRAFT = 'draft';

    public const RUNNING = 'running';

    public const LOCKED = 'locked';

    public const STATUSES = [self::DRAFT, self::RUNNING, self::LOCKED];

    /**
     * The one game, created as a draft the first time it is needed.
     */
    public static function current(): self
    {
        return static::query()->oldest('id')->firstOrCreate([], ['name' => 'PitonCTF', 'status' => self::DRAFT]);
    }

    /**
     * @return HasMany<Challenge, $this>
     */
    public function challenges(): HasMany
    {
        return $this->hasMany(Challenge::class);
    }

    /**
     * What the challenge pages show about the game.
     *
     * @return array{name: string, description: string|null, status: string, writeup_template_url: string|null}
     */
    public function forPage(): array
    {
        return [
            ...$this->only(['name', 'description', 'status']),
            // The version stops browsers from serving an old template after the admin replaces it.
            'writeup_template_url' => $this->writeup_template === null
                ? null
                : asset("ctf/uploads/{$this->writeup_template}").'?v='.$this->updated_at?->timestamp,
        ];
    }

    public function isRunning(): bool
    {
        return $this->status === self::RUNNING;
    }

    /**
     * Admins may preview a draft; students only see the challenges once the game has started.
     */
    public function isVisibleTo(User $user): bool
    {
        return $this->status !== self::DRAFT || $user->isAdmin();
    }
}
