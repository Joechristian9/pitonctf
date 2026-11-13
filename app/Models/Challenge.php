<?php

namespace App\Models;

use Database\Factories\ChallengeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $game_id
 * @property int $number
 * @property string $category
 * @property string|null $unlocks_category Set on warm-ups: the category this one unlocks.
 * @property string $title
 * @property int $points
 * @property string $description
 * @property string|null $hint
 * @property array<int, string> $links
 * @property string $flag_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $solves_count Only set when loaded with withCount().
 */
#[Fillable(['game_id', 'number', 'category', 'unlocks_category', 'title', 'points', 'description', 'hint', 'links', 'flag_hash'])]
#[Hidden(['flag_hash'])]
class Challenge extends Model
{
    /** @use HasFactory<ChallengeFactory> */
    use HasFactory;

    /**
     * Flag submissions each student gets per challenge.
     */
    public const MAX_TRIES = 8;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'links' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return HasMany<Solve, $this>
     */
    public function solves(): HasMany
    {
        return $this->hasMany(Solve::class);
    }

    /**
     * Warm-ups score nothing and have no try limit; solving one unlocks its category.
     */
    public function isWarmUp(): bool
    {
        return $this->unlocks_category !== null;
    }

    /**
     * A category stays locked until the student's team solves the warm-up that unlocks it. Admins skip the warm-ups.
     */
    public function isLockedFor(User $user): bool
    {
        if ($user->isAdmin()) {
            return false;
        }

        return static::query()
            ->where('game_id', $this->game_id)
            ->where('unlocks_category', $this->category)
            ->whereDoesntHave('solves', fn ($solves) => $solves->where('team_id', $user->team_id))
            ->exists();
    }

    /**
     * Points taken off the reward when the student opens the hint before solving.
     */
    public function hintCost(): int
    {
        return intdiv($this->points, 2);
    }

    /**
     * Constant-time check of a submitted flag against the stored SHA-256 hash.
     */
    public function isCorrectFlag(string $submittedFlag): bool
    {
        return hash_equals($this->flag_hash, hash('sha256', trim($submittedFlag)));
    }
}
