<?php

namespace App\Domain\Refunds;

use InvalidArgumentException;

readonly class Money
{
    private function __construct(public int $cents) {}

    public static function fromDecimal(string $amount): self
    {
        if (! preg_match('/\A\d+(?:\.\d{1,2})?\z/', $amount)) {
            throw new InvalidArgumentException('Money must be a non-negative decimal with at most two fractional digits.');
        }

        [$whole, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        $normalizedWhole = ltrim($whole, '0');
        $normalizedWhole = $normalizedWhole === '' ? '0' : $normalizedWhole;

        if (strlen($normalizedWhole) > 8) {
            throw new InvalidArgumentException('Money exceeds the supported precision.');
        }

        return new self(((int) $normalizedWhole * 100) + (int) str_pad($fraction, 2, '0'));
    }

    public function toDecimal(): string
    {
        return sprintf('%d.%02d', intdiv($this->cents, 100), $this->cents % 100);
    }
}
