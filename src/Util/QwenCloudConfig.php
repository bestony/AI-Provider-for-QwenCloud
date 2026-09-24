<?php

/**
 * QwenCloud provider configuration.
 *
 * The AI Client owns credentials. This class reads only non-secret configuration and asks the
 * registry whether a credential is available.
 *
 * Values are resolved as: environment variable > PHP constant > built-in default. There is no
 * database-backed settings page; the base URL is meant to be overridden by an operator when a
 * deployment needs a regional or Token Plan endpoint.
 *
 * @package QwenCloud\AiProvider
 */

declare(strict_types=1);

namespace QwenCloud\AiProvider\Util;

use WordPress\AiClient\AiClient;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;

final class QwenCloudConfig
{
    /**
     * The plugin version, reported in the User-Agent header.
     *
     * @var string
     */
    public const VERSION = '1.0.0';

    /**
     * The provider ID used by the SDK registry, the Connectors option name and the filter tuples.
     *
     * Frozen: it decides `connectors_ai_qwencloud_api_key`, `QWENCLOUD_API_KEY` and the value users
     * pass to model preference filters. Changing it drops every stored API key.
     *
     * @var string
     */
    public const PROVIDER_ID = 'qwencloud';

    /**
     * The default OpenAI-compatible API base URL.
     *
     * @var string
     */
    public const DEFAULT_BASE_URL = 'https://maas.qwencloudapi.com/compatible-mode/v1';

    /**
     * The model pushed to the front of the text and vision pickers.
     *
     * @var string
     */
    public const DEFAULT_MODEL = 'qwen3.7-plus';

    /**
     * The image model pushed to the front of the image generation picker.
     *
     * @var string
     */
    public const DEFAULT_IMAGE_MODEL = 'qwen-image-3.0-pro';

    /**
     * How structured output requests are shaped by default.
     *
     * @var string
     */
    public const DEFAULT_STRUCTURED_OUTPUT = 'json_schema';

    /**
     * The default timeout for model list and text generation requests, in seconds.
     *
     * @var float
     */
    public const DEFAULT_REQUEST_TIMEOUT = 120.0;

    /**
     * The default timeout for image generation requests, in seconds.
     *
     * Image generation routinely takes minutes; the API documentation recommends starting at 600
     * seconds, which is why this is separate from the text timeout.
     *
     * @var float
     */
    public const DEFAULT_IMAGE_REQUEST_TIMEOUT = 600.0;

    /**
     * The default connection timeout in seconds.
     *
     * @var float
     */
    public const DEFAULT_CONNECT_TIMEOUT = 10.0;

    /**
     * Resolve an environment variable or PHP constant.
     *
     * Environment variables take precedence over constants.
     *
     * @param string $name Configuration name.
     * @return string Resolved scalar value, or an empty string.
     */
    public static function env(string $name): string
    {
        $value = getenv($name);
        if (is_string($value) && $value !== '') {
            return $value;
        }

        if (defined($name)) {
            $constant = constant($name);
            if (is_scalar($constant)) {
                return (string) $constant;
            }
        }

        return '';
    }

    /**
     * Get the QwenCloud OpenAI-compatible API base URL.
     *
     * @return string Base URL without a trailing slash or operation suffix.
     */
    public static function getBaseUrl(): string
    {
        $configured = self::env('QWENCLOUD_BASE_URL');

        return self::normalizeBaseUrl($configured === '' ? self::DEFAULT_BASE_URL : $configured);
    }

    /**
     * Normalize an API base URL supplied by an operator.
     *
     * Strips a trailing slash and a pasted operation suffix, so an administrator who copies the full
     * endpoint URL from the documentation still gets a working base URL.
     *
     * @param string $url Base URL.
     * @return string Normalized base URL.
     */
    public static function normalizeBaseUrl(string $url): string
    {
        $url = rtrim(trim($url), '/');
        foreach (['/chat/completions', '/images/generations'] as $suffix) {
            if (substr($url, -strlen($suffix)) === $suffix) {
                $url = substr($url, 0, -strlen($suffix));
            }
        }

        return rtrim($url, '/');
    }

    /**
     * Get the model placed first in the text and vision preference filters.
     *
     * @return string Model ID.
     */
    public static function getDefaultModelId(): string
    {
        $configured = trim(self::env('QWENCLOUD_DEFAULT_MODEL'));

        return $configured === '' ? self::DEFAULT_MODEL : $configured;
    }

    /**
     * Get the model placed first in the image generation preference filter.
     *
     * @return string Model ID.
     */
    public static function getImageModelId(): string
    {
        $configured = trim(self::env('QWENCLOUD_IMAGE_MODEL'));

        return $configured === '' ? self::DEFAULT_IMAGE_MODEL : $configured;
    }

    /**
     * Get the structured output wire-format mode.
     *
     * @return string One of json_schema, json_object, or none.
     */
    public static function getStructuredOutputMode(): string
    {
        $configured = strtolower(trim(self::env('QWENCLOUD_STRUCTURED_OUTPUT')));

        return in_array($configured, ['json_schema', 'json_object', 'none'], true)
            ? $configured
            : self::DEFAULT_STRUCTURED_OUTPUT;
    }

    /**
     * Get the total request timeout in seconds.
     *
     * @return float Request timeout.
     */
    public static function getRequestTimeout(): float
    {
        return self::timeout('QWENCLOUD_REQUEST_TIMEOUT', self::DEFAULT_REQUEST_TIMEOUT);
    }

    /**
     * Get the image request timeout in seconds.
     *
     * @return float Image request timeout.
     */
    public static function getImageRequestTimeout(): float
    {
        return self::timeout('QWENCLOUD_IMAGE_REQUEST_TIMEOUT', self::DEFAULT_IMAGE_REQUEST_TIMEOUT);
    }

    /**
     * Get the connection timeout in seconds.
     *
     * @return float Connection timeout.
     */
    public static function getConnectTimeout(): float
    {
        return self::timeout('QWENCLOUD_CONNECT_TIMEOUT', self::DEFAULT_CONNECT_TIMEOUT);
    }

    /**
     * Resolve a positive timeout from an environment variable or constant.
     *
     * @param string $name Configuration name.
     * @param float $default Default timeout in seconds.
     * @return float Timeout, never below one second.
     */
    private static function timeout(string $name, float $default): float
    {
        $configured = self::env($name);

        return $configured === '' ? $default : max(1.0, (float) $configured);
    }

    /**
     * Create options used by model list and text generation requests.
     *
     * @return RequestOptions Request options.
     */
    public static function createRequestOptions(): RequestOptions
    {
        return self::createOptions(self::getRequestTimeout());
    }

    /**
     * Create options used by image generation requests.
     *
     * @return RequestOptions Request options.
     */
    public static function createImageRequestOptions(): RequestOptions
    {
        return self::createOptions(self::getImageRequestTimeout());
    }

    /**
     * Build request options with the shared connection timeout.
     *
     * @param float $timeout Total request timeout in seconds.
     * @return RequestOptions Request options.
     */
    private static function createOptions(float $timeout): RequestOptions
    {
        $options = new RequestOptions();
        $options->setTimeout($timeout);
        $options->setConnectTimeout(self::getConnectTimeout());

        return $options;
    }

    /**
     * Check whether the AI Client registry has a QwenCloud credential.
     *
     * This is a local registry lookup. It never reads the connector option and never calls QwenCloud.
     *
     * @return bool Whether credentials are configured.
     */
    public static function hasCredentials(): bool
    {
        if (!class_exists(AiClient::class)) {
            return false;
        }

        $registry = AiClient::defaultRegistry();
        if (!$registry->hasProvider(self::PROVIDER_ID)) {
            return false;
        }

        return $registry->getProviderRequestAuthentication(self::PROVIDER_ID) !== null;
    }

    /**
     * Get the User-Agent sent to QwenCloud.
     *
     * @return string User-Agent value.
     */
    public static function getUserAgent(): string
    {
        $configured = trim(self::env('QWENCLOUD_USER_AGENT'));
        if ($configured === '') {
            return 'ai-provider-for-qwencloud/' . self::VERSION;
        }

        return preg_replace('/[\r\n]+/', ' ', $configured) ?: 'ai-provider-for-qwencloud/' . self::VERSION;
    }
}
