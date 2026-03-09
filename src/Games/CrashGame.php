<?php
declare(strict_types=1);

namespace Provably\Games;

class CrashGame extends AbstractGame
{
    /** Fraction of floats that produce an instant bust (house edge). */
    private const HOUSE_EDGE = 0.01;   // 1 %

    /** Cap to prevent unbounded multipliers near float → 1. */
    private const MAX_MULTIPLIER = 100.00;

    public function __construct()
    {
        parent::__construct('crash', 'crash_v1', '1.0');
    }

    /**
     * Maps a float in [0, 1) to a crash bust multiplier using a house-edge model.
     *
     * Formula
     * -------
     *   if float < HOUSE_EDGE  →  bust immediately at 1.00× (house wins)
     *   otherwise              →  floor(1 / (1 − float) × 100) / 100
     *                              capped at MAX_MULTIPLIER
     *
     * Why this formula?
     *   1 / (1 − float) maps the uniform RNG output to a Pareto-like distribution:
     *   small floats produce low multipliers (common), large floats produce high
     *   multipliers (rare). This matches the shape real crash games target.
     *
     *   HOUSE_EDGE = 0.01 (1 %) means the game busts immediately ~1 % of the time
     *   before any player could cash out, giving the house its edge.
     *
     *   floor(… × 100) / 100 truncates (not rounds) to 2 decimal places so the
     *   result is always reproducible without floating-point rounding ambiguity.
     *
     * Range: 1.00× (bust) to MAX_MULTIPLIER (100.00×).
     *
     * This is the same structural model used by real provably-fair crash games.
     */
    protected function deriveFields(float $float, array $params): array
    {
        if ($float < self::HOUSE_EDGE) {
            return ['multiplier' => 1.00];
        }

        $raw        = 1.0 / (1.0 - $float);              // Pareto-shaped, unbounded
        $truncated  = floor($raw * 100) / 100;            // truncate to 2 dp (no rounding)
        $multiplier = min($truncated, self::MAX_MULTIPLIER);

        return ['multiplier' => $multiplier];
    }

    /**
     * Typed comparison of game-specific fields.
     * Version strings are already verified by AbstractGame::compareOutcomes().
     */
    protected function outcomeMatches(array $stored, array $recomputed): bool
    {
        return (float) $stored['multiplier'] === (float) $recomputed['multiplier'];
    }
}
