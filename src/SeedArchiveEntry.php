<?php
declare(strict_types=1);

namespace Provably;

/**
 * Immutable value object representing a server seed entry.
 * Used for both the active seed (revealedAt = null) and archived seeds (revealedAt = timestamp).
 */
final class SeedArchiveEntry
{
    public function __construct(
        public readonly string $seed,
        public readonly string $hash,
        public readonly ?int   $revealedAt = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            seed:       $data['seed'],
            hash:       $data['hash'],
            revealedAt: isset($data['revealed_at']) ? (int) $data['revealed_at'] : null,
        );
    }

    public function toArray(): array
    {
        return array_filter([
            'seed'        => $this->seed,
            'hash'        => $this->hash,
            'revealed_at' => $this->revealedAt,
        ], fn($v): bool => $v !== null);
    }

    public function isRevealed(): bool
    {
        return $this->revealedAt !== null;
    }
}

