<?php

/**
 * Shared request creation for QwenCloud models.
 *
 * @package QwenCloud\AiProvider
 */

declare(strict_types=1);

namespace QwenCloud\AiProvider\Models;

use QwenCloud\AiProvider\Provider\QwenCloudProvider;
use QwenCloud\AiProvider\Util\QwenCloudConfig;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;

/**
 * Builds requests against the QwenCloud OpenAI-compatible API.
 *
 * Every endpoint authenticates with `Authorization: Bearer <key>`, which the SDK's default API key
 * authentication already applies, so no custom authentication class is needed. Request options come
 * from the model instance, which lets the provider give image requests a longer timeout than text.
 */
trait QwenCloudRequestTrait
{
    /**
     * Create a request against the QwenCloud OpenAI-compatible API.
     *
     * @param HttpMethodEnum $method The HTTP method.
     * @param string $path The API endpoint path, relative to the base URL.
     * @param array<string, string|list<string>> $headers The request headers.
     * @param string|array<string, mixed>|null $data The request data.
     * @return Request The request object.
     */
    protected function createRequest(
        HttpMethodEnum $method,
        string $path,
        array $headers = [],
        $data = null
    ): Request {
        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] = 'application/json';
        $headers['User-Agent'] = QwenCloudConfig::getUserAgent();

        return new Request(
            $method,
            QwenCloudProvider::url($path),
            $headers,
            $data,
            $this->getRequestOptions()
        );
    }
}
