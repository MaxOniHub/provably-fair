<?php
declare(strict_types=1);

namespace Provably\Repository;

use RuntimeException;

/**
 * Shared file-backed JSON persistence with flock-based concurrency control.
 *
 * Subclasses provide:
 *   label()      — human-readable name used in exception messages ("bets", "seed", …)
 *   emptyState() — the value written to a new / empty file on first access
 */
abstract class AbstractJsonRepository
{
    protected string $path;

    public function __construct(string $path)
    {
        $this->path = $path;
        if (!file_exists($path)) {
            $this->write($this->emptyState());
        }
    }

    /** Human-readable label for error messages, e.g. "bets" or "seed". */
    abstract protected function label(): string;

    /** Default structure written when the file does not yet exist or is empty. */
    abstract protected function emptyState(): array;

    protected function read(): array
    {
        $fp = fopen($this->path, 'c+');
        if ($fp === false) {
            throw new RuntimeException("Cannot open {$this->label()} file: {$this->path}");
        }
        flock($fp, LOCK_SH);
        $content = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($content === false || trim($content) === '') {
            return $this->emptyState();
        }
        $data = json_decode($content, true);
        if (!is_array($data)) {
            throw new RuntimeException("Malformed JSON in {$this->label()} file: {$this->path}");
        }
        return $data;
    }

    protected function write(array $data): void
    {
        $fp = fopen($this->path, 'c+');
        if ($fp === false) {
            throw new RuntimeException("Cannot open {$this->label()} file for writing: {$this->path}");
        }
        flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        flock($fp, LOCK_UN);
        fclose($fp);
    }
}
