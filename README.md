# Provably Fair Demo (Plain PHP)

An educational PHP demo of a complete provably-fair casino system — server-seed lifecycle, HMAC-SHA256 RNG derivation, game outcome mapping, seed rotation, and independent bet verification. No framework required.

## Quick start

```bash
composer install
echo '{"current":null,"archive":[]}' > data/seeds.json
echo '[]' > data/bets.json
php index.php my_seed
```

---

## Architecture

```
````markdown
# Provably Fair Demo (Plain PHP)

An educational PHP demo of a complete provably-fair casino system — server-seed lifecycle, HMAC-SHA256 RNG derivation, game outcome mapping, seed rotation, and independent bet verification. No framework required.

## Quick start

```bash
composer install
echo '{"current":null,"archive":[]}' > data/seeds.json
echo '[]' > data/bets.json
php index.php my_seed
```

---

## Architecture

```
src/
  Contracts/                       – Core interfaces
    IRngEngine.php
    IProvablyFairGame.php
  Repository/                      – JSON + flock implementations
    AbstractJsonRepository.php     – shared read/write + locking
    JsonSeedRepository.php         – active seed + archive (uses SeedArchiveEntry)
    JsonBetRepository.php          – stores `Bet` objects
  Rng/
    HmacSha256Engine.php           – IRngEngine, version = hmac_sha256_v1
    RngResult.php                  – immutable derivation result (canonicalInput, hmacHex, unitFloat)
  Games/
    AbstractGame.php               – template-method base (mapToOutcome, compareOutcomes sealed)
    DiceGame.php                   – mapping_version = dice_v1
    CrashGame.php                  – mapping_version = crash_v1, 1% house edge
  Results/
    VerificationStatus.php         – backed enum: Passed | Mismatch | Error
    VerificationResult.php         – typed verify() return value with named constructors
    VerificationDetails.php        – full recomputation trace (only on Passed / Mismatch)
  Bet.php                          – immutable value object for a placed bet
  SeedArchiveEntry.php             – immutable VO for current/archive entries
  GameRegistry.php                 – resolves IProvablyFairGame by (gameId, mappingVersion)
  SeedService.php                  – seed lifecycle façade (uses JsonSeedRepository)
  NonceService.php                 – next-nonce policy (uses JsonBetRepository)
  BetVerifier.php                  – verify(Bet, revealedSeed): VerificationResult
  Printer.php                      – CLI output helpers
data/
  seeds.json                       – active seed + revealed archive (see SeedArchiveEntry)
  bets.json                        – all placed Bet records
index.php                          – CLI demo wiring everything together
```

---

## Lifecycle concepts

### 1. Seed lifecycle

```
generate → commit (publish hash only) → bets placed → rotate → reveal
```

1. **Generate** – operator creates a random server seed: `bin2hex(random_bytes(32))`.
2. **Commit** – only `sha256(serverSeed)` is published. The plaintext seed is kept secret.
3. **Bets placed** – players see the hash and supply their own client seed. The operator uses the hidden seed to derive outcomes; the player cannot predict them.
4. **Rotate** – the current seed is archived with a `revealed_at` timestamp and a fresh seed is generated.
5. **Reveal** – the archived plaintext seed is now publicly available for independent verification of every bet placed under it.

> **Fairness property**: because the hash was published *before* any bet, the operator cannot choose a different seed after seeing player bets. Once the seed is revealed, every result can be reproduced from the stored inputs alone.

---

### 2. Nonce lifecycle

The nonce is scoped to the `(clientSeed, serverHash)` pair (`NonceService`).

- `nextNonce()` returns **0** when no bets exist in this context yet.
- Each saved bet increments the nonce by 1.
- Rotating the server seed starts a **new context**: the nonce sequence resets to 0 independently for every client seed.

No two bets ever share the same canonical input, even across multiple client seeds or after seed rotation.

---

### 3. RNG derivation (`HmacSha256Engine`)

```
canonicalInput = "{clientSeed}:{nonce}:{round}"
hmacHex        = HMAC-SHA256(key=serverSeed, data=canonicalInput)   → 64 hex chars (256 bits)
unitFloat      = unpack('N', hex2bin(substr(hmacHex, 0, 8)))[1] / 4_294_967_296.0
                 ↑ first 4 bytes as big-endian uint32 ÷ 2³² → [0, 1)
```

All three values are returned together in an `RngResult` and stored with every `Bet`, so any third party can reproduce the full pipeline from the revealed seed alone.

---

### 4. Bet value object (`Bet`)

Bets are typed immutable objects, not raw associative arrays.

```php
$bet = new Bet(
    game:           'dice',
    gameVersion:    '1.0',
    mappingVersion: 'dice_v1',
    rngVersion:     'hmac_sha256_v1',
    serverHash:     $hash,
    clientSeed:     $clientSeed,
    nonce:          0,
    round:          0,
    params:         ['target' => 49.5],
    result:         $outcome,
);

$saved = $betRepo->save($bet);   // returns Bet with ->id and ->timestamp set
```

`Bet::fromArray()` / `toArray()` handle JSON hydration and serialization. `withIdAndTimestamp()` returns a new instance (original is never mutated).

---

### 5. Seed archive entry (`SeedArchiveEntry`)

`SeedArchiveEntry` is the immutable VO used for both the active server seed and previously revealed entries. Its fields:

- `seed: string` — plaintext server seed (present only when revealed/archive, not published while active)
- `hash: string` — `sha256(seed)` used to commit
- `revealedAt: ?int` — `null` for the active/unrevealed seed; integer timestamp when archived/revealed

The repository stores the active entry under `current` (or `null`) and a chronological array under `archive`.

`JsonSeedRepository::getCurrent()` returns `?SeedArchiveEntry`.

---

### 6. Game outcome mapping (`AbstractGame`)

Each game extends `AbstractGame` and implements two methods:

| Method | Responsibility |
|---|---|
| `deriveFields(float, params)` | Pure outcome computation; no versioning fields |
| `outcomeMatches(stored, recomputed)` | Typed field comparison for verification |

`AbstractGame` seals `mapToOutcome()` (appends `mapping_version` / `game_version`) and `compareOutcomes()` (checks version strings first, then delegates).

**Dice (`dice_v1`)**

```
roll = floor(float × 10000) / 100   → [0.00, 99.99]  (10 000 equally probable outcomes)
win  = roll < target                 (target must be in the open interval (0.00, 100.00))
```

**Crash (`crash_v1`)**

```
if float < 0.01  →  bust at 1.00×                          (1% house-edge instant-bust)
otherwise        →  floor(1 / (1 − float) × 100) / 100     (Pareto-shaped, truncated)
                    capped at 100.00×
```

Old bets remain verifiable after a formula change because `mapping_version` is stored with every bet and `GameRegistry` dispatches to the exact registered implementation.

---

### 7. Seed reveal and verification (`BetVerifier`)

`BetVerifier::verify(Bet $bet, string $revealedSeed): VerificationResult` runs these steps in order:

1. `sha256(revealedSeed) === $bet->serverHash` — confirms the seed was committed before the bet.
2. `$bet->rngVersion` matches the registered engine — no silent fallback.
3. Rebuild `canonicalInput` from `$bet->clientSeed`, `$bet->nonce`, `$bet->round`.
4. Recompute `HMAC-SHA256`.
5. Recompute `unitFloat`.
6. Resolve game via `GameRegistry::resolve($bet->game, $bet->mappingVersion)` — throws on unknown.
7. Recompute outcome via `$game->mapToOutcome()`.
8. Compare via `$game->compareOutcomes()` — typed field comparison, never loose `==`.

Returns a `VerificationResult` with a `VerificationStatus` enum:

| Status | Meaning |
|---|---|
| `Passed` / `'PASS'` | Seed commitment valid and outcome matches |
| `Mismatch` / `'FAIL'` | Computation ran but outcomes differ |
| `Error` / `'ERROR'` | Aborted — seed hash mismatch or unknown RNG version |

On `Passed` or `Mismatch` a `VerificationDetails` object is attached with the full intermediate trace (`canonicalInput`, `hmacHex`, `unitFloat`, `storedOutcome`, `recomputedOutcome`).

---

## Storage

Both JSON files are protected with `flock(LOCK_EX)` on writes and `flock(LOCK_SH)` on reads. Malformed JSON throws a `RuntimeException` rather than silently returning an empty result.

In production the plaintext server seed must be stored in a secure secret store (HSM, Vault, etc.) and never in a web-accessible file.

---

## Running the demo

```bash
php index.php [clientSeed]
```

The script is idempotent: re-running accumulates bets and seeds persist across runs. To start fresh:

```bash
echo '{"current":null,"archive":[]}' > data/seeds.json
echo '[]' > data/bets.json
```

````

