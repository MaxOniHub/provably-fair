<?php
declare(strict_types=1);

namespace Provably\Contracts;

use Provably\Rng\RngResult;

interface IRngEngine
{
    /** Unique version string stored with every bet for future verification dispatch. */
    public function version(): string;

    /**
     * Run the full derivation pipeline in one step and return a typed result.
     *
     * The engine is solely responsible for:
     *   1. building the canonical input string
     *   2. computing the HMAC
     *   3. extracting the unit float
     *
     * Callers receive an RngResult with all three values and never need
     * to know or orchestrate the internal sequence.
     */
    public function deriveResult(
        string $serverSeed,
        string $clientSeed,
        int    $nonce,
        int    $round,
    ): RngResult;
}
