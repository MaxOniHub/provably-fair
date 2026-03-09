<?php
declare(strict_types=1);

namespace Provably\Results;

/**
 * Typed result returned by BetVerifier::verify().
 *
 * Three explicit domain states — one named constructor per state:
 *
 *   error()    – Seed-commitment or RNG-version check failed.
 *                Computation was aborted before reaching the game layer.
 *                $error is set; $details is null.
 *
 *   passed()   – Full recomputation ran and outcomes match.
 *                $details holds the full trace; $error is null.
 *
 *   mismatch() – Full recomputation ran but outcomes differ.
 *                $details holds the full trace; $error is null.
 */
final class VerificationResult
{
    private function __construct(
        public readonly VerificationStatus   $status,
        public readonly ?string              $error,
        public readonly ?VerificationDetails $details,
    ) {}

    /** Seed-commitment or RNG-version check failed; computation was aborted. */
    public static function error(string $reason): self
    {
        return new self(status: VerificationStatus::Error, error: $reason, details: null);
    }

    /** Full computation ran and stored outcome matches the recomputed one. */
    public static function passed(VerificationDetails $details): self
    {
        return new self(status: VerificationStatus::Passed, error: null, details: $details);
    }

    /** Full computation ran but stored outcome does not match the recomputed one. */
    public static function mismatch(VerificationDetails $details): self
    {
        return new self(status: VerificationStatus::Mismatch, error: null, details: $details);
    }
}
