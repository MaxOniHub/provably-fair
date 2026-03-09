<?php
declare(strict_types=1);

namespace Provably;

use Provably\Repository\JsonSeedRepository;
use RuntimeException;

/**
 * Manages the server-seed lifecycle:
 *   generate → commit (hash published) → bets placed → rotate → reveal.
 */
class SeedService
{
    public function __construct(private readonly JsonSeedRepository $repo) {}

    /**
     * Archive the current seed (if any) and generate a fresh one.
     * Returns the SHA-256 hash of the new seed (safe to publish).
     */
    public function generateNewSeed(int $bytes = 32): string
    {
        $current = $this->repo->getCurrent();
        if ($current !== null) {
            $this->repo->archiveCurrent($current->seed, $current->hash);
        }

        $seed = bin2hex(random_bytes($bytes));
        $hash = hash('sha256', $seed);
        $this->repo->saveCurrent($seed, $hash);

        return $hash;
    }

    /** Rotate: archive current seed, generate a new one. */
    public function rotate(): string
    {
        return $this->generateNewSeed();
    }

    /**
     * SHA-256 hash of the active server seed — safe to share with players before bets.
     * @throws RuntimeException if no seed has been generated yet.
     */
    public function currentHash(): string
    {
        return $this->repo->getCurrent()?->hash
            ?? throw new RuntimeException('No active server seed. Call generateNewSeed() first.');
    }

    /**
     * Plaintext active server seed — used internally to derive RNG outputs.
     * Must never be shared with players before rotation.
     * @throws RuntimeException if no seed has been generated yet.
     */
    public function currentSeed(): string
    {
        return $this->repo->getCurrent()?->seed
            ?? throw new RuntimeException('No active server seed. Call generateNewSeed() first.');
    }

    /**
     * All previously revealed seeds in chronological order.
     *
     * @return SeedArchiveEntry[]
     */
    public function archive(): array
    {
        return $this->repo->getArchive();
    }
}

