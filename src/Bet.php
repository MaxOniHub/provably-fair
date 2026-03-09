<?php
declare(strict_types=1);

namespace Provably;

/**
 * Immutable value object representing a single placed bet.
 *
 * Hydrate from storage with Bet::fromArray(); serialize back with toArray().
 * After the repository assigns an id and timestamp, use withIdAndTimestamp()
 * to obtain a new instance — the original remains unchanged.
 */
class Bet
{
    public function __construct(
        public readonly string  $game,
        public readonly string  $gameVersion,
        public readonly string  $mappingVersion,
        public readonly string  $rngVersion,
        public readonly string  $serverHash,
        public readonly string  $clientSeed,
        public readonly int     $nonce,
        public readonly int     $round,
        public readonly array   $params,
        public readonly array   $result,
        public readonly ?string $id        = null,
        public readonly ?int    $timestamp = null,
    ) {}

    /** Hydrate from a raw associative array (e.g. decoded JSON). */
    public static function fromArray(array $data): self
    {
        return new self(
            game:           $data['game'],
            gameVersion:    $data['game_version'],
            mappingVersion: $data['mapping_version'],
            rngVersion:     $data['rng_version'],
            serverHash:     $data['server_hash'],
            clientSeed:     $data['client_seed'],
            nonce:          (int) $data['nonce'],
            round:          (int) ($data['round'] ?? 0),
            params:         $data['params'] ?? [],
            result:         $data['result'] ?? [],
            id:             $data['id'] ?? null,
            timestamp:      isset($data['timestamp']) ? (int) $data['timestamp'] : null,
        );
    }

    /** Serialize to an associative array suitable for JSON storage. */
    public function toArray(): array
    {
        return [
            'game'            => $this->game,
            'game_version'    => $this->gameVersion,
            'mapping_version' => $this->mappingVersion,
            'rng_version'     => $this->rngVersion,
            'server_hash'     => $this->serverHash,
            'client_seed'     => $this->clientSeed,
            'nonce'           => $this->nonce,
            'round'           => $this->round,
            'params'          => $this->params,
            'result'          => $this->result,
            'id'              => $this->id,
            'timestamp'       => $this->timestamp,
        ];
    }

    /**
     * Return a new instance with the repository-assigned id and timestamp.
     * The original object is not mutated.
     */
    public function withIdAndTimestamp(string $id, int $timestamp): self
    {
        return new self(
            game:           $this->game,
            gameVersion:    $this->gameVersion,
            mappingVersion: $this->mappingVersion,
            rngVersion:     $this->rngVersion,
            serverHash:     $this->serverHash,
            clientSeed:     $this->clientSeed,
            nonce:          $this->nonce,
            round:          $this->round,
            params:         $this->params,
            result:         $this->result,
            id:             $id,
            timestamp:      $timestamp,
        );
    }
}
