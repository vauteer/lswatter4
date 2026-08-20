<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tournament_id
 * @property int $team1_id
 * @property int $team2_id
 * @property-read Team $team1
 * @property-read Team $team2
 * @property int $round
 * @property int $table_number
 * @property string|null $score
 * @property int $team1_won
 * @property int $team2_won
 * @property int $team1_points
 * @property int $team2_points
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['tournament_id', 'team1_id', 'team2_id', 'round', 'table_number', 'score', 'team1_won', 'team2_won', 'team1_points', 'team2_points'])]
class Fixture extends Model
{
    /** @use HasFactory<Factory<Fixture>> */
    use HasFactory;

    const SCORE_REGEX = '(\d{1,2})[-:](\d{1,2})';

    /**
     * @return BelongsTo<Tournament, $this>
     */
    public function tournament(): BelongsTo
    {
        return $this->belongsTo(Tournament::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team1(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * @return BelongsTo<Team, $this>
     */
    public function team2(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function calculate(?string $newScore, bool $persist): true|string
    {
        $wonHome = $wonAway = $pointsHome = $pointsAway = 0;
        $requiredGames = $this->tournament->games;
        $winPoints = $this->tournament->winpoints;
        $normalizedScore = '';

        if ($newScore) {
            preg_match_all('|'.self::SCORE_REGEX.'|', $newScore, $matches);
            $gamesCount = count($matches[0]);
            if ($gamesCount !== $requiredGames) {
                return "{$gamesCount} statt {$requiredGames} Spiele eingegeben";
            }

            for ($i = 0; $i < $gamesCount; $i++) {
                $points1 = intval($matches[1][$i]);
                $points2 = intval($matches[2][$i]);

                if (self::pointsValid($points1, $winPoints) && self::pointsValid($points2, $winPoints) &&
                    $points1 !== $points2 && ($points1 === $winPoints || $points2 == $winPoints)) {
                    $pointsHome += $points1;
                    $pointsAway += $points2;
                    ($points1 === $winPoints) ? $wonHome++ : $wonAway++;
                } else {
                    return "{$matches[0][$i]} ist ungültig";
                }

                $normalizedScore .= "{$points1}-{$points2} ";
            }
        }

        if ($persist) {
            $this->team1_won = $wonHome;
            $this->team2_won = $wonAway;
            $this->team1_points = $pointsHome;
            $this->team2_points = $pointsAway;
            $this->score = $normalizedScore === '' ? null : rtrim($normalizedScore);
            $this->save();
        }

        return true;
    }

    private static function pointsValid(int $points, int $winPoints): bool
    {
        return $points === 0 || ($points > 1 && $points <= $winPoints);
    }

    public static function calculateAll(): void
    {
        $fixtures = Fixture::all();
        foreach ($fixtures as $fixture) {
            $fixture->calculate($fixture->score, true);
        }
    }
}
