<?php
declare(strict_types=1);

namespace Provably\Games;

use Provably\Contracts\IProvablyFairGame;

/**
 * Template-method base for provably-fair games.
 *
 * Subclass responsibilities (real variation points):
 *   __construct()    — passes id, mappingVersion, gameVersion to parent
 *   deriveFields()   — pure computation of outcome fields from the float
 *   outcomeMatches() — typed comparison of game-specific fields
 *
 * Everything else is sealed here and must not be duplicated in concrete classes.
 */
abstract class AbstractGame implements IProvablyFairGame
{
    public function __construct(
        private readonly string $id,
        private readonly string $mappingVersion,
        private readonly string $gameVersion,
    ) {}

    final public function gameId(): string         { return $this->id; }
    final public function mappingVersion(): string { return $this->mappingVersion; }
    final public function gameVersion(): string    { return $this->gameVersion; }

    /**
     * Compute game-specific outcome fields from a float in [0, 1).
     * Must NOT include mapping_version or game_version — those are appended by mapToOutcome().
     *
     * @throws \InvalidArgumentException if $params are invalid for this game.
     */
    abstract protected function deriveFields(float $float, array $params): array;

    /**
     * Compare only the game-specific fields of two outcome arrays.
     * Version strings are already verified before this is called. Never use loose ==.
     */
    abstract protected function outcomeMatches(array $stored, array $recomputed): bool;

    /**
     * Derives outcome and attaches versioning metadata.
     * Sealed: subclasses must not override this.
     */
    final public function mapToOutcome(float $float, array $params): array
    {
        return $this->deriveFields($float, $params) + [
            'mapping_version' => $this->mappingVersion(),
            'game_version'    => $this->gameVersion(),
        ];
    }

    /**
     * Checks version strings strictly, then delegates game-specific field comparison.
     * Sealed: subclasses must not override this.
     */
    final public function compareOutcomes(array $stored, array $recomputed): bool
    {
        return $stored['mapping_version'] === $recomputed['mapping_version']
            && $stored['game_version']    === $recomputed['game_version']
            && $this->outcomeMatches($stored, $recomputed);
    }
}
