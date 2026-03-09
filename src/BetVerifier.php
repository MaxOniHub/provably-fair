<?php
declare(strict_types=1);

namespace Provably;

use Provably\Contracts\IRngEngine;
use Provably\Results\VerificationDetails;
use Provably\Results\VerificationResult;

/**
 * Verifies previously placed bets against a revealed server seed.
 *
 * Verification steps (all must pass):
 *   1. sha256(revealedSeed) === bet->serverHash   — proves seed was committed before the bet
 *   2. rng_version matches the registered engine  — exact version, no fallback
 *   3. Rerun full RNG pipeline (canonical input → HMAC → unit float) via engine
 *   4. Resolve game implementation by (game, mappingVersion) — fails loudly on unknown
 *   5. Recompute outcome
 *   6. Compare via game::compareOutcomes() — typed field comparison, no loose ==
 */
class BetVerifier
{
    private IRngEngine $rng;
    private GameRegistry $registry;

    public function __construct(IRngEngine $rng, GameRegistry $registry)
    {
        $this->rng      = $rng;
        $this->registry = $registry;
    }

    public function verify(Bet $bet, string $revealedSeed): VerificationResult
    {
        // Step 1 — Seed commitment check
        $recomputedHash = hash('sha256', $revealedSeed);
        if ($recomputedHash !== $bet->serverHash) {
            return VerificationResult::error(
                'Hash mismatch: sha256(revealedSeed) does not equal stored server_hash. '
                    . "Expected: {$bet->serverHash}, got: {$recomputedHash}."
            );
        }

        // Step 2 — RNG version check
        if ($bet->rngVersion !== $this->rng->version()) {
            return VerificationResult::error(
                "Unknown rng_version '{$bet->rngVersion}'. "
                . "This verifier uses '{$this->rng->version()}'. Cannot verify."
            );
        }

        // Step 3 — Rerun full RNG pipeline via engine (canonical input → HMAC → unit float)
        $rng = $this->rng->deriveResult(
            $revealedSeed,
            $bet->clientSeed,
            $bet->nonce,
            $bet->round,
        );

        // Step 4 — Resolve game (fails loudly; no fallback)
        $game = $this->registry->resolve($bet->game, $bet->mappingVersion);

        // Step 5 — Recompute outcome
        $recomputed = $game->mapToOutcome($rng->unitFloat, $bet->params);

        // Step 6 — Typed field comparison (no loose ==)
        $match = $game->compareOutcomes($bet->result, $recomputed);

        $details = new VerificationDetails(
            hashMatched:       true,
            rngVersion:        $bet->rngVersion,
            mappingVersion:    $bet->mappingVersion,
            gameVersion:       $bet->gameVersion,
            canonicalInput:    $rng->canonicalInput,
            hmacHex:           $rng->hmacHex,
            unitFloat:         $rng->unitFloat,
            storedOutcome:     $bet->result,
            recomputedOutcome: $recomputed,
        );

        return $match
            ? VerificationResult::passed($details)
            : VerificationResult::mismatch($details);
    }
}

