<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One row per team per challenge: how many flag tries they used and whether they opened the hint.
 *
 * @property int $id
 * @property int $team_id
 * @property int $challenge_id
 * @property int $tries_used
 * @property bool $hint_used
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['team_id', 'challenge_id', 'hint_used'])]
class Attempt extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tries_used' => 'integer',
            'hint_used' => 'boolean',
        ];
    }
}
