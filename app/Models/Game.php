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
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'description', 'status'])]
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
