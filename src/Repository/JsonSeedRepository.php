<?php
declare(strict_types=1);

namespace Provably\Repository;

use Provably\SeedArchiveEntry;

class JsonSeedRepository extends AbstractJsonRepository
{
    protected function label(): string { return 'seed'; }

    protected function emptyState(): array { return ['current' => null, 'archive' => []]; }

    public function getCurrent(): ?SeedArchiveEntry
    {
        $raw = $this->read()['current'] ?? null;
        return $raw !== null ? SeedArchiveEntry::fromArray($raw) : null;
    }

    public function saveCurrent(string $seed, string $hash): void
    {
        $data = $this->read();
        $data['current'] = (new SeedArchiveEntry($seed, $hash))->toArray();
        $this->write($data);
    }

    public function archiveCurrent(string $seed, string $hash): void
    {
        $data = $this->read();
        $data['archive'][] = (new SeedArchiveEntry($seed, $hash, time()))->toArray();
        $data['current'] = null;
        $this->write($data);
    }

    /** @return SeedArchiveEntry[] */
    public function getArchive(): array
    {
        $raw = $this->read()['archive'] ?? [];
        return array_map(fn(array $entry) => SeedArchiveEntry::fromArray($entry), $raw);
    }
}


