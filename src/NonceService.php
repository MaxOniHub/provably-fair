<?php
declare(strict_types=1);

namespace Provably;

use Provably\Repository\JsonBetRepository;

/**
 * Resolves the next nonce for a provably-fair betting context.
 *
 * A context is uniquely identified by the (clientSeed, serverHash) pair.
 * Rotating the server seed starts a new context; the nonce sequence restarts
 * from 0 independently for every client seed.
 */
class NonceService
{
    public function __construct(private readonly JsonBetRepository $repo) {}

    /**
     * Returns the next nonce to use for the given context.
     * Returns 0 when no bets exist in this context yet.
     */
    public function nextNonce(string $clientSeed, string $serverHash): int
    {
        $bets = $this->repo->findByContext($clientSeed, $serverHash);
        if (empty($bets)) {
            return 0;
        }
        return max(array_map(fn(Bet $b) => $b->nonce, $bets)) + 1;
    }
}


