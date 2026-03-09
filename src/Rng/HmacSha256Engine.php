<?php
declare(strict_types=1);

namespace Provably\Rng;

use Provably\Contracts\IRngEngine;
use InvalidArgumentException;

class HmacSha256Engine implements IRngEngine
{
    public function version(): string
    {
        return 'hmac_sha256_v1';
    }

    /**
     * Run the full derivation pipeline and return a typed result.
     * The three internal steps — canonical-input construction, HMAC derivation,
     * and unit-float extraction — are private implementation details.
     */
    public function deriveResult(
        string $serverSeed,
        string $clientSeed,
        int    $nonce,
        int    $round,
    ): RngResult {
        $canonical = $this->canonicalInput($clientSeed, $nonce, $round);
        $hmacHex   = $this->derive($serverSeed, $canonical);
        $unitFloat = $this->extractUnitFloat($hmacHex);

        return new RngResult($canonical, $hmacHex, $unitFloat);
    }

    /**
     * Canonical format: "clientSeed:nonce:round"
     * All three components are explicit, making the input fully reproducible.
     */
    private function canonicalInput(string $clientSeed, int $nonce, int $round): string
    {
        return "{$clientSeed}:{$nonce}:{$round}";
    }

    /**
     * HMAC-SHA256 with the server seed as key and canonical input as message.
     * Returns the full 64-char hex output (256 bits).
     */
    private function derive(string $serverSeed, string $canonicalInput): string
    {
        return hash_hmac('sha256', $canonicalInput, $serverSeed);
    }

    /**
     * Extract a unit float in [0,1) from the HMAC hex output.
     * Uses the first 4 bytes (big-endian uint32) divided by 2^32.
     */
    private function extractUnitFloat(string $hmacHex): float
    {
        $bytes = hex2bin(substr($hmacHex, 0, 8));
        if ($bytes === false) {
            throw new InvalidArgumentException('Invalid HMAC hex in extractUnitFloat()');
        }
        $unpacked = unpack('N', $bytes); // N = big-endian unsigned long (32-bit)
        return ($unpacked[1] ?? 0) / 4294967296.0; // 2^32
    }
}
