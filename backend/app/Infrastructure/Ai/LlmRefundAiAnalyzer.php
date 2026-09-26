<?php

namespace App\Infrastructure\Ai;

use App\Contracts\Ai\Exceptions\LlmInvalidResponseException;
use App\Contracts\Ai\JsonSchema;
use App\Contracts\Ai\LlmClient;
use App\Contracts\RefundAiAnalyzer as RefundAiAnalyzerContract;
use App\Domain\Refunds\RefundAnalysis;
use App\Domain\Refunds\RefundAnalysisInput;
use App\Infrastructure\Ai\Prompts\RefundPromptBuilder;
use JsonException;

class LlmRefundAiAnalyzer implements RefundAiAnalyzerContract
{
    public function __construct(
        private readonly LlmClient $llmClient,
        private readonly RefundPromptBuilder $promptBuilder,
    ) {}

    public function analyze(RefundAnalysisInput $input): RefundAnalysis
    {
        $response = $this->llmClient->generateStructured(
            $this->promptBuilder->build($input),
            new JsonSchema('refund_analysis', [
                'type' => 'object',
                'properties' => [
                    'classified_reason' => [
                        'type' => 'string',
                        'enum' => ['DAMAGED', 'INCORRECT_ITEM', 'OTHER'],
                    ],
                    'summary' => ['type' => 'string'],
                    'suspicious' => ['type' => 'boolean'],
                    'conflicting_claims' => ['type' => 'boolean'],
                    'confidence' => ['type' => 'number', 'minimum' => 0, 'maximum' => 1],
                    'suggested_response' => ['type' => 'string'],
                ],
                'required' => [
                    'classified_reason',
                    'summary',
                    'suspicious',
                    'conflicting_claims',
                    'confidence',
                    'suggested_response',
                ],
                'additionalProperties' => false,
            ]),
        );

        try {
            $data = json_decode($response->text, true, 512, JSON_THROW_ON_ERROR);

            if (! is_array($data)) {
                throw new LlmInvalidResponseException;
            }

            return RefundAnalysis::fromArray($data);
        } catch (JsonException|\InvalidArgumentException $exception) {
            throw new LlmInvalidResponseException;
        }
    }
}
