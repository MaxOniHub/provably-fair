<?php
declare(strict_types=1);

namespace Provably\Results;

enum VerificationStatus: string
{
    case Passed   = 'PASS';
    case Mismatch = 'FAIL';
    case Error    = 'ERROR';

    public function isOk(): bool
    {
        return $this === self::Passed;
    }
}
