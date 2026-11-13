<?php

namespace App\Models;

use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A team of one to four students plays the game together and shares solves, tries and hints.
 *
 * @property int $id
 * @property string $name
 * @property string|null $codename Shown to students on the scoreboard instead of the name.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read int $members_count Only set when loaded with withCount().
 * @property-read int $solves_count Only set when loaded with withCount().
 * @property-read int|string|null $solves_sum_points Only set when loaded with withSum().
 */
#[Fillable(['name', 'codename'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    /**
     * The most members a team can have. A smaller team can still play.
     */
    public const SIZE = 4;

    /**
     * @return HasMany<User, $this>
     */
    public function members(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Solve, $this>
     */
    public function solves(): HasMany
    {
        return $this->hasMany(Solve::class);
    }

    /**
     * @return HasMany<Attempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    /**
     * @return HasMany<Guess, $this>
     */
    public function guesses(): HasMany
    {
        return $this->hasMany(Guess::class);
    }
}
