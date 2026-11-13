<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A wrong flag a team member submitted.
 *
 * @property int $id
 * @property int $team_id
 * @property int $challenge_id
 * @property int|null $user_id The member who typed it.
 * @property string $flag
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['team_id', 'challenge_id', 'user_id', 'flag'])]
class Guess extends Model
{
    /**
     * How many of a team's latest wrong flags the challenge page shows per challenge.
     */
    public const SHOWN = 20;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
