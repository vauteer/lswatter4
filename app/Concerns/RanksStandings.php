<?php

namespace App\Concerns;

trait RanksStandings
{
    /**
     * Sorts a standings array in place: most games won first, then point
     * difference, then points won.
     *
     * @template T of array{won: int, pointsWon: int, pointsLost: int, ...}
     *
     * @param  array<int, T>  $standings
     */
    private static function rankStandings(array &$standings): void
    {
        $games = $pointsDifference = $pointsWon = [];
        foreach ($standings as $id => $ranking) {
            $games[$id] = $ranking['won'];
            $pointsDifference[$id] = $ranking['pointsWon'] - $ranking['pointsLost'];
            $pointsWon[$id] = $ranking['pointsWon'];
        }

        array_multisort($games, SORT_DESC, $pointsDifference, SORT_DESC, $pointsWon, SORT_DESC, $standings);
    }
}
