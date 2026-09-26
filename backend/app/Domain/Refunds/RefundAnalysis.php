<?php

namespace App\Domain\Refunds;

use InvalidArgumentException;

readonly class RefundAnalysis
{
    public function __construct(
        public RefundReason $classifiedReason,
        public string $summary,
        public bool $suspicious,
        public bool $conflictingClaims,
        public float $confidence,
        public string $suggestedResponse,
    ) {
        if (trim($this->summary) === '' || mb_strlen($this->summary) > 500) {
            throw new InvalidArgumentException('Summary must contain between 1 and 500 characters.');
        }

        if (! is_finite($this->confidence) || $this->confidence < 0 || $this->confidence > 1) {
            throw new InvalidArgumentException('Confidence must be between 0 and 1.');
        }

        if (trim($this->suggestedResponse) === '' || mb_strlen($this->suggestedResponse) > 1000) {
            throw new InvalidArgumentException('Suggested response must contain between 1 and 1000 characters.');
        }
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $keys = [
            'classified_reason',
            'summary',
            'suspicious',
            'conflicting_claims',
            'confidence',
            'suggested_response',
        ];

        if (array_diff($keys, array_keys($data)) !== [] || array_diff(array_keys($data), $keys) !== []) {
            throw new InvalidArgumentException('Analysis fields do not match the expected schema.');
        }

        if (! is_string($data['classified_reason']) || ! is_string($data['summary'])
            || ! is_bool($data['suspicious']) || ! is_bool($data['conflicting_claims'])
            || (! is_int($data['confidence']) && ! is_float($data['confidence']))
            || ! is_string($data['suggested_response'])) {
            throw new InvalidArgumentException('Analysis fields have invalid types.');
        }

        $reason = RefundReason::tryFrom($data['classified_reason']);

        if ($reason === null) {
            throw new InvalidArgumentException('Analysis classification is unsupported.');
        }

        return new self(
            classifiedReason: $reason,
            summary: $data['summary'],
            suspicious: $data['suspicious'],
            conflictingClaims: $data['conflicting_claims'],
            confidence: (float) $data['confidence'],
            suggestedResponse: $data['suggested_response'],
        );
    }

    /** @return array{classified_reason: string, summary: string, suspicious: bool, conflicting_claims: bool, confidence: float, suggested_response: string} */
    public function toArray(): array
    {
        return [
            'classified_reason' => $this->classifiedReason->value,
            'summary' => $this->summary,
            'suspicious' => $this->suspicious,
            'conflicting_claims' => $this->conflictingClaims,
            'confidence' => $this->confidence,
            'suggested_response' => $this->suggestedResponse,
        ];
    }
}
