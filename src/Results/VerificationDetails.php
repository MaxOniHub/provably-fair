<?php
declare(strict_types=1);

namespace Provably\Results;

/**
 * Immutable trace of every recomputed value produced during verification.
 * Only present on a VerificationResult when the full computation ran
 * (i.e. seed-commitment and RNG-version checks already passed).
 */
final class VerificationDetails
{
    public function __construct(
        public readonly bool   $hashMatched,
        public readonly string $rngVersion,
        public readonly string $mappingVersion,
        public readonly string $gameVersion,
        public readonly string $canonicalInput,
        public readonly string $hmacHex,
        public readonly float  $unitFloat,
        public readonly array  $storedOutcome,
        public readonly array  $recomputedOutcome,
    ) {}
}
