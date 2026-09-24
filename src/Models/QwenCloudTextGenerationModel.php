<?php

/**
 * QwenCloud OpenAI-compatible chat completions model.
 *
 * @package QwenCloud\AiProvider
 */

declare(strict_types=1);

namespace QwenCloud\AiProvider\Models;

use QwenCloud\AiProvider\Util\QwenCloudConfig;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleTextGenerationModel;

/**
 * Text generation model for QwenCloud chat models.
 *
 * Everything below the request body — message mapping, vision input, tool calls, response parsing,
 * `reasoning_content` thought parts and token usage — is handled by the SDK base class. Only
 * QwenCloud's request shape quirks need overriding.
 */
final class QwenCloudTextGenerationModel extends AbstractOpenAiCompatibleTextGenerationModel
{
    use QwenCloudRequestTrait;

    /**
     * {@inheritDoc}
     *
     * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt The prompt to generate text for.
     * @return array<string, mixed> The parameters for the API request.
     */
    protected function prepareGenerateTextParams(array $prompt): array
    {
        $params = parent::prepareGenerateTextParams($prompt);

        /*
         * An empty array signals "send no response_format" (see prepareResponseFormatParam()); leaving
         * the key in place would send `"response_format": []`, which is a 400.
         */
        if (isset($params['response_format']) && $params['response_format'] === []) {
            unset($params['response_format']);
        }

        return $params;
    }

    /**
     * {@inheritDoc}
     *
     * The SDK base class sends `{"type":"json_schema","json_schema":<schema>}`, but QwenCloud
     * documents the OpenAI shape, where the schema is wrapped in a named object with a `strict`
     * flag: `{"type":"json_schema","json_schema":{"name":...,"schema":{...},"strict":true}}`.
     * Without the wrapper the request is rejected with a 400 on `response_format`.
     *
     * @see https://docs.qwencloud.com/api-reference/chat/openai-chat
     *
     * @param array<string, mixed>|null $outputSchema The output schema, or null for plain JSON mode.
     * @return array<string, mixed> The response format parameter, or an empty array to send none.
     */
    protected function prepareResponseFormatParam(?array $outputSchema): array
    {
        $mode = QwenCloudConfig::getStructuredOutputMode();

        if ($mode === 'none') {
            return [];
        }

        if ($mode === 'json_object' || !is_array($outputSchema)) {
            return ['type' => 'json_object'];
        }

        return [
            'type' => 'json_schema',
            'json_schema' => [
                'name' => 'qwencloud_response',
                'schema' => $outputSchema,
                'strict' => true,
            ],
        ];
    }
}
