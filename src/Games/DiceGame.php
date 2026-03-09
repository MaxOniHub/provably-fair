<?php
declare(strict_types=1);

namespace Provably\Games;

use InvalidArgumentException;

class DiceGame extends AbstractGame
{
    public function __construct()
    {
        parent::__construct('dice', 'dice_v1', '1.0');
    }

    /**
     * Maps a float in [0, 1) to a roll in [0.00, 99.99].
     *
     * Formula: floor(float × 10000) / 100
     *
     * Produces exactly 10 000 equally-probable outcomes: 0.00, 0.01, …, 99.99.
     * The player wins if roll < target (strictly less than).
     * Target must be in the open interval (0.00, 100.00).
     */
    protected function deriveFields(float $float, array $params): array
    {
        $target = $params['target'] ?? null;
        if ($target === null) {
            throw new InvalidArgumentException("Dice game requires a 'target' param.");
        }
        $target = (float) $target;
        if ($target <= 0.0 || $target >= 100.0) {
            throw new InvalidArgumentException('Dice target must be in the open interval (0.00, 100.00).');
        }

        $roll = floor($float * 10000) / 100;

        return [
            'roll'   => $roll,
            'target' => $target,
            'win'    => $roll < $target,
        ];
    }

    /**
     * Typed comparison of game-specific fields.
     * Version strings are already verified by AbstractGame::compareOutcomes().
     */
    protected function outcomeMatches(array $stored, array $recomputed): bool
    {
        return (float) $stored['roll']   === (float) $recomputed['roll']
            && (float) $stored['target'] === (float) $recomputed['target']
            && (bool)  $stored['win']    === (bool)  $recomputed['win'];
    }
}
