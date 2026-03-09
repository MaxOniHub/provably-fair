<?php
declare(strict_types=1);

namespace Provably\Contracts;

interface IProvablyFairGame
{
    /** Unique game identifier stored with every bet. */
    public function gameId(): string;

    /** Mapping version string stored with every bet. Used for version-aware dispatch during verification. */
    public function mappingVersion(): string;

    /** Game version string stored with every bet. */
    public function gameVersion(): string;

    /**
     * Map a float in [0, 1) to a game outcome.
     * Must be deterministic and side-effect free.
     * $params contains game-specific parameters (e.g. ['target' => 49.5] for dice).
     */
    public function mapToOutcome(float $float, array $params): array;

    /**
     * Compare a stored outcome against a recomputed outcome.
     * Must compare specific typed fields, not arbitrary nested arrays.
     * Never use loose comparison (==) for integrity checks.
     */
    public function compareOutcomes(array $stored, array $recomputed): bool;
}
