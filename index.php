<?php
declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Provably\Repository\JsonSeedRepository;
use Provably\Repository\JsonBetRepository;
use Provably\Rng\HmacSha256Engine;
use Provably\SeedService;
use Provably\NonceService;
use Provably\GameRegistry;
use Provably\BetVerifier;
use Provably\Bet;
use Provably\Games\DiceGame;
use Provably\Games\CrashGame;
use Provably\Printer;

// ---------------------------------------------------------------------------
// Bootstrap
// ---------------------------------------------------------------------------

$seedRepo = new JsonSeedRepository(__DIR__ . '/data/seeds.json');
$betRepo  = new JsonBetRepository(__DIR__ . '/data/bets.json');
$rng      = new HmacSha256Engine();

$diceGame  = new DiceGame();
$crashGame = new CrashGame();

$registry = new GameRegistry();
$registry->register($diceGame);
$registry->register($crashGame);

$seedService  = new SeedService($seedRepo);
$nonceService = new NonceService($betRepo);
$verifier     = new BetVerifier($rng, $registry);

// Generate first seed on a fresh run
if ($seedRepo->getCurrent() === null) {
    $seedService->generateNewSeed();
}

$clientSeed = $argv[1] ?? 'player_' . substr(bin2hex(random_bytes(3)), 0, 6);

// ---------------------------------------------------------------------------
// Step 1 -- Public state before any bet
// ---------------------------------------------------------------------------

Printer::section('STEP 1 -- Public state before betting');
Printer::row('Client seed', $clientSeed);
Printer::row('Server hash (public)', $seedService->currentHash());
Printer::text(
    PHP_EOL .
    '  The operator publishes sha256(serverSeed) before any bet is placed.' . PHP_EOL .
    '  The plaintext seed stays secret. This means the casino has already fixed its secret input before the bet.' . PHP_EOL .
    '  It cannot quietly replace that seed afterward to force a better result.' . PHP_EOL .
    '  The final result is calculated from all relevant inputs together:' . PHP_EOL .
    '    serverSeed + clientSeed + nonce + game-specific mapping.' . PHP_EOL
);

// ---------------------------------------------------------------------------
// Step 2 -- Dice bet  (nonce = 0 for first bet in this session context)
//
// Context = (clientSeed, serverHash). nextNonce() returns 0 when no bets
// exist in that context yet. Each saved bet increments the nonce, so every
// HMAC input is unique even for the same player and game type.
// ---------------------------------------------------------------------------

Printer::section('STEP 2 -- Place dice bet  [target: under 49.50]');

$serverSeed = $seedService->currentSeed();
$serverHash = $seedService->currentHash();

$nonce   = $nonceService->nextNonce($clientSeed, $serverHash);  // 0 on first bet
$rng1    = $rng->deriveResult($serverSeed, $clientSeed, $nonce, 0);

$diceResult = $diceGame->mapToOutcome($rng1->unitFloat, ['target' => 49.5]);

$diceBet = $betRepo->save(new Bet(
    game:           $diceGame->gameId(),
    gameVersion:    $diceGame->gameVersion(),
    mappingVersion: $diceGame->mappingVersion(),
    rngVersion:     $rng->version(),
    serverHash:     $serverHash,
    clientSeed:     $clientSeed,
    nonce:          $nonce,
    round:          0,
    params:         ['target' => 49.5],
    result:         $diceResult,
));

Printer::row('Bet ID',           $diceBet->id);
Printer::row('Nonce',            (string) $nonce);
Printer::row('Canonical input',  $rng1->canonicalInput);
Printer::row('HMAC-SHA256 hex',  $rng1->hmacHex);
Printer::row('Float [0,1)',       number_format($rng1->unitFloat, 10));
Printer::row('Roll',             number_format($diceResult['roll'], 2) . '  (floor(float × 10000) / 100)');
Printer::row('Target',           number_format($diceResult['target'], 2));
Printer::row('Win',              $diceResult['win'] ? 'YES' : 'NO');
Printer::row('Mapping version',  $diceResult['mapping_version']);
Printer::row('Game version',     $diceResult['game_version']);
Printer::row('RNG version',      $rng->version());

// ---------------------------------------------------------------------------
// Step 3 -- Crash bet (same server seed, next nonce)
// ---------------------------------------------------------------------------

Printer::section('STEP 3 -- Place crash bet  [same seed context, nonce increments]');

$nonce2  = $nonceService->nextNonce($clientSeed, $serverHash);  // 1
$rng2    = $rng->deriveResult($serverSeed, $clientSeed, $nonce2, 0);

$crashResult = $crashGame->mapToOutcome($rng2->unitFloat, []);

$crashBet = $betRepo->save(new Bet(
    game:           $crashGame->gameId(),
    gameVersion:    $crashGame->gameVersion(),
    mappingVersion: $crashGame->mappingVersion(),
    rngVersion:     $rng->version(),
    serverHash:     $serverHash,
    clientSeed:     $clientSeed,
    nonce:          $nonce2,
    round:          0,
    params:         [],
    result:         $crashResult,
));

Printer::row('Bet ID',          $crashBet->id);
Printer::row('Nonce',           (string) $nonce2);
Printer::row('Canonical input', $rng2->canonicalInput);
Printer::row('HMAC-SHA256 hex', $rng2->hmacHex);
Printer::row('Float [0,1)',      number_format($rng2->unitFloat, 10));
Printer::row('Multiplier',      $crashResult['multiplier'] . 'x  (floor(1÷(1−float)×100)/100, 1% house-edge bust threshold)');
Printer::row('Mapping version', $crashResult['mapping_version']);
Printer::row('Game version',    $crashResult['game_version']);
Printer::row('RNG version',     $rng->version());

// ---------------------------------------------------------------------------
// Step 4 -- Rotate seed  (archive + reveal old seed, generate new one)
// ---------------------------------------------------------------------------

Printer::section('STEP 4 -- Rotate server seed  (old seed publicly revealed)');

$seedService->rotate();

Printer::row('Revealed seed',        $serverSeed);
Printer::row('Expected hash',        $serverHash);
Printer::row('sha256(revealedSeed)', hash('sha256', $serverSeed));
Printer::row('Hash match',           hash('sha256', $serverSeed) === $serverHash ? 'PASS' : 'FAIL');
Printer::row('New server hash',      $seedService->currentHash());
Printer::text(
    PHP_EOL .
    '  The operator now publishes the server seed for the rotated context.' . PHP_EOL .
    '  Any party can check: sha256(revealedSeed) must equal the server hash that' . PHP_EOL .
    '  was published before bets. Once confirmed, every bet under that hash can' . PHP_EOL .
    '  be recomputed from its stored inputs (clientSeed, nonce, round).' . PHP_EOL .
    '  The nonce sequence for (clientSeed, newServerHash) starts fresh at 0.' . PHP_EOL
);

// ---------------------------------------------------------------------------
// Step 5 -- Verification of bets placed this session
//
// "This session" = bets whose server_hash matches the seed we just rotated.
// Historical bets from prior runs are counted separately to avoid noise.
// ---------------------------------------------------------------------------

Printer::section('STEP 5 -- Verification of ALL bets with a revealed seed');

// Build a lookup: server_hash => revealedSeed from the archive.
// Every bet whose hash appears here can and MUST be verified.
$archive = $seedService->archive();
$seedByHash = [];
foreach ($archive as $entry) {
    $seedByHash[$entry->hash] = $entry->seed;
}

$allBets = $betRepo->findAll();
$pass    = 0;
$fail    = 0;
$pending = 0;

foreach ($allBets as $bet) {
    $label = "[{$bet->game}]  nonce={$bet->nonce}  id={$bet->id}";
    $tag   = $bet->serverHash === $serverHash ? '  (this session)' : '  (historical)';
    Printer::text(PHP_EOL . "  {$label}{$tag}" . PHP_EOL);

    if (!isset($seedByHash[$bet->serverHash])) {
        Printer::row('  Status', 'PENDING — seed not yet revealed (active session)');
        $pending++;
        continue;
    }

    $result = $verifier->verify($bet, $seedByHash[$bet->serverHash]);

    if ($result->error !== null) {
        Printer::row('  Error',  $result->error);
        Printer::row('  Status', $result->status->value);
        $fail++;
        continue;
    }

    Printer::row('  Seed commitment',    ($result->details->hashMatched ? 'PASS' : 'FAIL') . '  sha256(revealedSeed) === storedHash');
    Printer::row('  Canonical input',    $result->details->canonicalInput);
    Printer::row('  Recomputed HMAC',    $result->details->hmacHex);
    Printer::row('  Recomputed float',   number_format($result->details->unitFloat, 10));
    Printer::row('  Stored outcome',     json_encode($result->details->storedOutcome));
    Printer::row('  Recomputed outcome', json_encode($result->details->recomputedOutcome));
    Printer::row('  Match',              $result->status->value);

    $result->status->isOk() ? $pass++ : $fail++;
}

// ---------------------------------------------------------------------------
// Summary
// ---------------------------------------------------------------------------

Printer::section('SUMMARY');
Printer::row('Total bets in store',  (string) count($allBets));
Printer::row('Verified',             (string) ($pass + $fail));
Printer::row('Passed',               (string) $pass);
Printer::row('Failed',               (string) $fail);
Printer::row('Pending (active seed)', (string) $pending);
