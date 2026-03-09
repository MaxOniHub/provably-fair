<?php
declare(strict_types=1);

namespace Provably\Rng;

/**
 * Immutable result of one full RNG derivation pipeline.
 *
 * Produced by IRngEngine::deriveResult() and carries every value that
 * the derivation step produces:
 *
 *   canonicalInput — the message that was fed into HMAC (stored with the bet
 *                    so the full pipeline can be independently reproduced)
 *   hmacHex        — raw HMAC-SHA256 hex output (64 chars / 256 bits)
 *   unitFloat      — deterministic value in [0, 1) extracted from hmacHex,
 *                    used as the input to every game mapping function
 *
 * Keeping all three together means callers can never receive a float that
 * was extracted from a different HMAC, and never have to orchestrate the
 * internal sequence of derivation steps themselves.
 */
final class RngResult
{
    public function __construct(
        public readonly string $canonicalInput,
        public readonly string $hmacHex,
        public readonly float  $unitFloat,
    ) {}
}
