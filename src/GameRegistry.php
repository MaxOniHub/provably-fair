<?php
declare(strict_types=1);

namespace Provably;

use Provably\Contracts\IProvablyFairGame;
use RuntimeException;

/**
 * Registry that resolves ProvablyFairGameInterface implementations
 * by (gameId, mappingVersion). Throws explicitly on any unknown combination —
 * no silent fallback behaviour.
 */
class GameRegistry
{
    /**
    * @var array<string, array<string, IProvablyFairGame>>
     *   game_id => mapping_version => implementation
     */
    private array $registry = [];

    public function register(IProvablyFairGame $game): void
    {
        $this->registry[$game->gameId()][$game->mappingVersion()] = $game;
    }

    /**
     * @throws RuntimeException if the game or mapping version is not registered.
     */
    public function resolve(string $gameId, string $mappingVersion): IProvablyFairGame
    {
        if (!isset($this->registry[$gameId])) {
            throw new RuntimeException(
                "Unknown game '{$gameId}'. Register it with GameRegistry::register() before use."
            );
        }

        if (!isset($this->registry[$gameId][$mappingVersion])) {
            $known = implode(', ', array_keys($this->registry[$gameId]));
            throw new RuntimeException(
                "Unknown mapping version '{$mappingVersion}' for game '{$gameId}'. "
                . "Known versions: {$known}. Cannot verify."
            );
        }

        return $this->registry[$gameId][$mappingVersion];
    }
}
