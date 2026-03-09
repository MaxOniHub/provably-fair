<?php
declare(strict_types=1);

namespace Provably\Repository;

use Provably\Bet;

class JsonBetRepository extends AbstractJsonRepository
{
    protected function label(): string { return 'bets'; }

    protected function emptyState(): array { return []; }

    public function save(Bet $bet): Bet
    {
        $saved = $bet->withIdAndTimestamp(uniqid('bet_', true), time());
        $bets   = $this->read();
        $bets[] = $saved->toArray();
        $this->write($bets);
        return $saved;
    }

    /** @return Bet[] */
    public function findAll(): array
    {
        return array_map(fn(array $data) => Bet::fromArray($data), $this->read());
    }

    /**
     * Return all bets placed under the given (clientSeed, serverHash) context.
     *
     * @return Bet[]
     */
    public function findByContext(string $clientSeed, string $serverHash): array
    {
        return array_values(array_filter(
            $this->findAll(),
            fn(Bet $bet) => $bet->clientSeed === $clientSeed
                         && $bet->serverHash === $serverHash,
        ));
    }
}

