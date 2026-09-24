<?php

// phpcs:ignoreFile -- dev-only CLI harness; .gitattributes export-ignores it from releases.

/**
 * Runnable self-check for the QwenCloud provider plugin.
 *
 * Covers the plugin's own logic — model classification, sort order, capability declarations, request
 * building, and the metadata requirements the AI plugin's features actually send — without needing
 * WordPress, composer or a live API key. One file, no test framework, because this is the smallest
 * thing that fails when the logic breaks.
 *
 * Usage:
 *   php scripts/selfcheck.php
 *   php scripts/selfcheck.php --sdk=/path/to/php-ai-client/src   # extra live-SDK checks
 *
 * @package QwenCloud\AiProvider
 */

declare(strict_types=1);

$root = dirname(__DIR__);

/*
 * The plugin's autoloader refuses to run outside WordPress (Plugin Check requires a direct-access
 * guard on it), so this harness defines ABSPATH the way a WordPress test bootstrap does.
 */
if (!defined('ABSPATH')) {
    define('ABSPATH', $root . '/');
}

$GLOBALS['qwencloud_plugins_dir'] = dirname($root);
$GLOBALS['qwencloud_plugin_dir'] = $root;

require $root . '/src/autoload.php';

use QwenCloud\AiProvider\Util\QwenCloudConfig;
use QwenCloud\AiProvider\Util\QwenCloudModelCatalog;

$failures = 0;
$checks = 0;

/**
 * Asserts a condition and records the outcome.
 *
 * @param bool $condition The condition to check.
 * @param string $description What is being checked.
 * @return void
 */
function check(bool $condition, string $description): void
{
    global $failures, $checks;
    $checks++;

    if (!$condition) {
        $failures++;
        fwrite(STDERR, "FAIL  {$description}\n");
        return;
    }

    fwrite(STDOUT, "ok    {$description}\n");
}

// A stale environment must not leak into the checks.
foreach (
    [
    'QWENCLOUD_BASE_URL',
    'QWENCLOUD_DEFAULT_MODEL',
    'QWENCLOUD_IMAGE_MODEL',
    'QWENCLOUD_STRUCTURED_OUTPUT',
    'QWENCLOUD_REQUEST_TIMEOUT',
    'QWENCLOUD_IMAGE_REQUEST_TIMEOUT',
    'QWENCLOUD_CONNECT_TIMEOUT',
    'QWENCLOUD_USER_AGENT',
    'QWENCLOUD_SIZE_SQUARE',
    'QWENCLOUD_SIZE_LANDSCAPE',
    'QWENCLOUD_SIZE_PORTRAIT',
    ] as $name
) {
    putenv($name);
}

// --- Text classification. ---------------------------------------------------------------------
foreach (
    [
    'qwen3.8-max',
    'qwen3.7-plus',
    'qwen3.8-flash',
    'qwen3.7-max',
    'qwen3.6-flash',
    'qwen3.5-plus',
    'deepseek-v4.1-flash',
    'deepseek-v4-pro-0813',
    'kimi-k3',
    'kimi-k2.7-code',
    'glm-5.3',
    'qwen3-vl-plus',
    'qwen3.8-omni-flash',
    'qwen2.5-vl-72b-instruct',
    'qvq-max',
    'qwen-mt-plus',
    ] as $modelId
) {
    check(QwenCloudModelCatalog::isTextModel($modelId), "{$modelId} generates text");
}

foreach (
    [
    'qwen3.7-text-embedding',
    'qwen3-rerank',
    'wan2.7-image-pro',
    'wan3.0-video',
    'happyhorse-1.1-t2v',
    'happyoyster-1.0-adventure',
    'qwen-audio-3.0-tts-plus',
    'qwen-audio-3.1-asr-flash-filetrans',
    'qwen3.5-omni-plus-realtime',
    'decision-model-preview',
    'qwen-mt-image',
    'qwen-image-2.0',
    'z-image-turbo',
    'gpt-4o',
    ] as $modelId
) {
    check(!QwenCloudModelCatalog::isTextModel($modelId), "{$modelId} is not a chat model");
}

// --- Image classification. --------------------------------------------------------------------
foreach (['qwen-image-3.0', 'qwen-image-3.0-pro'] as $modelId) {
    check(QwenCloudModelCatalog::isImageModel($modelId), "{$modelId} generates images");
}
foreach (['qwen3.8-max', 'qwen-image-2.0', 'wan2.7-image-pro', 'z-image-turbo'] as $modelId) {
    check(!QwenCloudModelCatalog::isImageModel($modelId), "{$modelId} is not an image model");
}

// --- Vision classification. -------------------------------------------------------------------
foreach (
    [
    'qwen3.8-max',
    'qwen3.7-plus',
    'qwen3.8-flash',
    'qwen3-vl-plus',
    'qwen-vl-max',
    'qvq-max',
    'qwen3.8-omni-flash',
    'qwen3.5-omni-plus',
    'qwen2.5-vl-72b-instruct',
    'deepseek-v4.1-flash',
    ] as $modelId
) {
    check(QwenCloudModelCatalog::supportsImageInput($modelId), "{$modelId} accepts image input");
}
foreach (['deepseek-v4-pro-0813', 'kimi-k3', 'glm-5.3', 'qwen-mt-plus', 'qwen3.8-2.4t-a95b'] as $modelId) {
    check(!QwenCloudModelCatalog::supportsImageInput($modelId), "{$modelId} is text-only");
}

// --- Unsupported models carry no capability at all. -------------------------------------------
foreach (
    [
    'wan3.0-video',
    'happyhorse-1.1-t2v',
    'happyoyster-1.0-directing',
    'qwen3.7-text-embedding',
    'qwen3-rerank',
    'qwen-audio-3.0-tts-plus',
    'qwen3.5-omni-plus-realtime',
    'decision-model-preview',
    'qwen-mt-image',
    ] as $modelId
) {
    check(QwenCloudModelCatalog::isUnsupported($modelId), "{$modelId} is declared with no capability");
}

// --- Capability family rules. -----------------------------------------------------------------
check(QwenCloudModelCatalog::supportsTools('qwen3.8-max'), 'qwen3.8-max supports function calling');
check(!QwenCloudModelCatalog::supportsTools('qwen-mt-plus'), 'the translation model declares no tools');
check(!QwenCloudModelCatalog::supportsTools('qvq-max'), 'the reasoning-only model declares no tools');

check(QwenCloudModelCatalog::supportsJsonSchema('qwen3.8-max'), 'qwen3.8-max supports JSON Schema');
check(QwenCloudModelCatalog::supportsJsonSchema('qwen3.7-plus'), 'qwen3.7-plus supports JSON Schema');
check(QwenCloudModelCatalog::supportsJsonSchema('qwen3.8-flash'), 'qwen3.8-flash supports JSON Schema');
check(!QwenCloudModelCatalog::supportsJsonSchema('deepseek-v4-pro-0813'), 'deepseek-v4-pro-0813 has JSON Object only');
check(QwenCloudModelCatalog::supportsStructuredOutput('deepseek-v4-pro-0813'), 'deepseek-v4-pro-0813 supports JSON Object');
check(!QwenCloudModelCatalog::supportsStructuredOutput('qvq-max'), 'qvq-max declares no structured output');
check(!QwenCloudModelCatalog::supportsStructuredOutput('deepseek-v4-flash-0731'), 'deepseek-v4-flash-0731 declares no structured output');

check(QwenCloudModelCatalog::supportsSampling('qwen3.8-max'), 'qwen3.8-max accepts sampling parameters');
check(!QwenCloudModelCatalog::supportsSampling('qvq-max'), 'qvq-max rejects sampling parameters');
check(!QwenCloudModelCatalog::supportsSampling('qwen-mt-plus'), 'the translation model rejects sampling parameters');

check(QwenCloudModelCatalog::supportsCandidateCount('qwen3-max'), 'qwen3-max accepts a candidate count');
check(!QwenCloudModelCatalog::supportsCandidateCount('qwen3-vl-plus'), 'qwen3-vl-plus accepts no candidate count');
check(!QwenCloudModelCatalog::supportsCandidateCount('qwen3.8-max'), 'qwen3.8-max declares no candidate count');

// --- Preview and snapshot classification. -----------------------------------------------------
check(QwenCloudModelCatalog::isPreview('qwen3.7-max-preview'), 'a preview model is classified');
check(!QwenCloudModelCatalog::isPreview('qwen3.8-max'), 'a stable model is not a preview');
check(QwenCloudModelCatalog::isSnapshot('qwen3.7-max-2026-06-08'), 'a dated snapshot is classified');
check(QwenCloudModelCatalog::isSnapshot('qwen3.8-max-0902'), 'a short-MMDD snapshot is classified');
check(!QwenCloudModelCatalog::isSnapshot('qwen3.8-max'), 'a stable model is not a snapshot');

// --- Sort order. ------------------------------------------------------------------------------
check(
    QwenCloudModelCatalog::compareModelIds('qwen3.8-max', 'wan3.0-video') < 0,
    'a usable model sorts before a capability-less one'
);
check(
    QwenCloudModelCatalog::compareModelIds('qwen3.8-max', 'qwen-image-3.0') < 0,
    'text models sort before image models'
);
check(
    QwenCloudModelCatalog::compareModelIds('qwen3.8-max', 'qwen3.8-max-0902') < 0,
    'a stable model sorts before its snapshot'
);
check(
    QwenCloudModelCatalog::compareModelIds('qwen3.7-plus', 'qwen3.8-max') < 0,
    'the configured default model sorts first'
);
check(
    QwenCloudModelCatalog::compareModelIds('qwen3.8-max', 'qwen3.8-max') === 0,
    'comparing a model with itself is neutral'
);

// --- Size mapping. ----------------------------------------------------------------------------
check(QwenCloudModelCatalog::sizeForOrientation('square') === '1024x1024', 'square maps to 1024x1024');
check(QwenCloudModelCatalog::sizeForOrientation('landscape') === '1664x928', 'landscape maps to 1664x928');
check(QwenCloudModelCatalog::sizeForOrientation('portrait') === '928x1664', 'portrait maps to 928x1664');
check(QwenCloudModelCatalog::sizeForOrientation(null) === '1024x1024', 'no orientation falls back to square');
check(QwenCloudModelCatalog::sizeForOrientation('nonsense') === '1024x1024', 'an unknown orientation falls back to square');

// --- Configuration defaults. ------------------------------------------------------------------
check(QwenCloudConfig::getBaseUrl() === QwenCloudConfig::DEFAULT_BASE_URL, 'default base URL');
check(
    QwenCloudConfig::normalizeBaseUrl('https://example.test/compatible-mode/v1/chat/completions')
        === 'https://example.test/compatible-mode/v1',
    'a pasted chat completions URL is trimmed back to the base URL'
);
check(
    QwenCloudConfig::normalizeBaseUrl('https://example.test/compatible-mode/v1/images/generations')
        === 'https://example.test/compatible-mode/v1',
    'a pasted images URL is trimmed back to the base URL'
);
check(QwenCloudConfig::getDefaultModelId() === 'qwen3.7-plus', 'default text model');
check(QwenCloudConfig::getImageModelId() === 'qwen-image-3.0-pro', 'default image model');
check(QwenCloudConfig::getStructuredOutputMode() === 'json_schema', 'structured output defaults to json_schema');
check(QwenCloudConfig::getRequestTimeout() >= 60.0, 'text timeout is long enough for an LLM call');
check(
    QwenCloudConfig::getImageRequestTimeout() > QwenCloudConfig::getRequestTimeout(),
    'the image timeout is longer than the text timeout'
);
check(QwenCloudConfig::getConnectTimeout() >= 5.0, 'the connect timeout is long enough');
check(
    QwenCloudConfig::getUserAgent() === 'ai-provider-for-qwencloud/' . QwenCloudConfig::VERSION,
    'the User-Agent identifies the plugin and its version'
);
check(!QwenCloudConfig::hasCredentials(), 'no credentials unless one is configured');

// The base URL, model IDs and size table are the values a deployment is most likely to override.
putenv('QWENCLOUD_BASE_URL=https://maas.example.test/compatible-mode/v1/');
putenv('QWENCLOUD_DEFAULT_MODEL=qwen3.8-max');
putenv('QWENCLOUD_IMAGE_MODEL=qwen-image-3.0');
putenv('QWENCLOUD_STRUCTURED_OUTPUT=json_object');
putenv('QWENCLOUD_REQUEST_TIMEOUT=180');
putenv('QWENCLOUD_IMAGE_REQUEST_TIMEOUT=900');
putenv('QWENCLOUD_CONNECT_TIMEOUT=15');
putenv('QWENCLOUD_USER_AGENT=qwencloud-selfcheck/1');
putenv('QWENCLOUD_SIZE_LANDSCAPE=1184x896');
check(
    QwenCloudConfig::getBaseUrl() === 'https://maas.example.test/compatible-mode/v1',
    'QWENCLOUD_BASE_URL overrides the base URL'
);
check(QwenCloudConfig::getDefaultModelId() === 'qwen3.8-max', 'QWENCLOUD_DEFAULT_MODEL override');
check(QwenCloudConfig::getImageModelId() === 'qwen-image-3.0', 'QWENCLOUD_IMAGE_MODEL override');
check(QwenCloudConfig::getStructuredOutputMode() === 'json_object', 'QWENCLOUD_STRUCTURED_OUTPUT override');
check(QwenCloudConfig::getRequestTimeout() === 180.0, 'QWENCLOUD_REQUEST_TIMEOUT override');
check(QwenCloudConfig::getImageRequestTimeout() === 900.0, 'QWENCLOUD_IMAGE_REQUEST_TIMEOUT override');
check(QwenCloudConfig::getConnectTimeout() === 15.0, 'QWENCLOUD_CONNECT_TIMEOUT override');
check(QwenCloudConfig::getUserAgent() === 'qwencloud-selfcheck/1', 'QWENCLOUD_USER_AGENT override');
check(
    QwenCloudModelCatalog::sizeForOrientation('landscape') === '1184x896',
    'QWENCLOUD_SIZE_LANDSCAPE overrides the landscape size'
);

foreach (
    [
    'QWENCLOUD_BASE_URL',
    'QWENCLOUD_DEFAULT_MODEL',
    'QWENCLOUD_IMAGE_MODEL',
    'QWENCLOUD_STRUCTURED_OUTPUT',
    'QWENCLOUD_REQUEST_TIMEOUT',
    'QWENCLOUD_IMAGE_REQUEST_TIMEOUT',
    'QWENCLOUD_CONNECT_TIMEOUT',
    'QWENCLOUD_USER_AGENT',
    'QWENCLOUD_SIZE_LANDSCAPE',
    ] as $name
) {
    putenv($name);
}

// --- Request building, against the real SDK when one is available. ----------------------------
$sdkPath = null;
foreach ($argv as $argument) {
    if (strpos($argument, '--sdk=') === 0) {
        $sdkPath = substr($argument, 6);
    }
}

if (load_sdk($sdkPath)) {
    use_qwencloud_sdk_checks();
} else {
    check(!QwenCloudConfig::hasCredentials(), 'without the AI Client there are no credentials to report');
    fwrite(STDOUT, "skip  SDK-dependent checks (pass --sdk=<path to php-ai-client/src> to run them)\n");
}

/**
 * Loads the PHP AI Client SDK from a checkout, if one was given.
 *
 * Accepts either the SDK root (which ships a generated `autoload.php` covering both the client and
 * its scoped dependencies) or a `src/` directory next to a `polyfills.php`, which is what a
 * composer-based checkout exposes.
 *
 * @param string|null $sdkPath The path passed with --sdk.
 * @return bool Whether the SDK was loaded.
 */
function load_sdk(?string $sdkPath): bool
{
    if ($sdkPath === null || $sdkPath === '') {
        return false;
    }

    // The bundled WordPress SDK: <root>/autoload.php.
    if (is_file($sdkPath . '/autoload.php')) {
        require $sdkPath . '/autoload.php';
        return true;
    }

    // A composer checkout pointed at its src directory: <root>/src with <root>/autoload.php.
    if (is_file(dirname($sdkPath) . '/autoload.php')) {
        require dirname($sdkPath) . '/autoload.php';
        return true;
    }

    // A bare src directory with a polyfills.php bootstrap.
    if (is_file($sdkPath . '/polyfills.php')) {
        require $sdkPath . '/polyfills.php';
        spl_autoload_register(static function (string $class) use ($sdkPath): void {
            $prefix = 'WordPress\\AiClient\\';
            if (strpos($class, $prefix) !== 0) {
                return;
            }
            $file = $sdkPath . '/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
            if (is_file($file)) {
                require $file;
            }
        });

        return true;
    }

    return false;
}

/**
 * Checks that need the real SDK classes: declared options, built requests and the plugin entry file.
 *
 * @return void
 */
function use_qwencloud_sdk_checks(): void
{
    $response = new \WordPress\AiClient\Providers\Http\DTO\Response(
        200,
        [],
        json_encode([
            'object' => 'list',
            'data' => array_map(
                static fn(string $id): array => ['id' => $id, 'object' => 'model', 'created' => 1788339600],
                [
                    'qwen3.8-max',
                    'qwen3.7-plus',
                    'qwen3.8-flash',
                    'qwen3.8-max-0902',
                    'deepseek-v4-pro-0813',
                    'deepseek-v4.1-flash',
                    'kimi-k3',
                    'glm-5.3',
                    'qwen3-vl-plus',
                    'qwen3.8-omni-flash',
                    'qvq-max',
                    'qwen-mt-plus',
                    'qwen-image-3.0-pro',
                    'qwen-image-3.0',
                    'qwen3.7-text-embedding',
                    'qwen3-rerank',
                    'wan2.7-image-pro',
                    'qwen-audio-3.0-tts-plus',
                    'qwen3.5-omni-plus-realtime',
                    'decision-model-preview',
                ]
            ),
        ])
    );

    // parseResponseToModelMetadataList() is protected: reach it through a subclass.
    $parser = new class extends \QwenCloud\AiProvider\Metadata\QwenCloudModelMetadataDirectory {
        /**
         * Exposes the protected parser.
         *
         * @param \WordPress\AiClient\Providers\Http\DTO\Response $response The model list response.
         * @return list<\WordPress\AiClient\Providers\Models\DTO\ModelMetadata> The parsed models.
         */
        public function parse(\WordPress\AiClient\Providers\Http\DTO\Response $response): array
        {
            return $this->parseResponseToModelMetadataList($response);
        }
    };

    $models = $parser->parse($response);
    check(count($models) === 20, 'every model in the list response is parsed');

    $byId = [];
    $orderedIds = [];
    foreach ($models as $model) {
        $byId[$model->getId()] = $model;
        $orderedIds[] = $model->getId();
    }

    check($orderedIds[0] === 'qwen3.7-plus', 'the configured model is sorted first');
    check(
        array_search('qwen-image-3.0-pro', $orderedIds, true) > array_search('qwen3.8-max', $orderedIds, true),
        'image models sort after text models'
    );
    check(
        array_search('qwen3.7-text-embedding', $orderedIds, true) > array_search('qwen-image-3.0-pro', $orderedIds, true),
        'capability-less models sort last'
    );

    // Option names come back as camelCase for ModelConfig-derived options (e.g. `inputModalities`).
    $optionNames = static function (\WordPress\AiClient\Providers\Models\DTO\ModelMetadata $model): array {
        return array_map(
            static fn($option): string => $option->getName()->value,
            $model->getSupportedOptions()
        );
    };
    $optionValues = static function (
        \WordPress\AiClient\Providers\Models\DTO\ModelMetadata $model,
        string $optionName
    ): ?array {
        foreach ($model->getSupportedOptions() as $option) {
            if ($option->getName()->value === $optionName) {
                return $option->getSupportedValues();
            }
        }
        return null;
    };
    $capabilityValues = static function (\WordPress\AiClient\Providers\Models\DTO\ModelMetadata $model): array {
        return array_map(static fn($capability): string => $capability->value, $model->getSupportedCapabilities());
    };

    // Capabilities.
    check($capabilityValues($byId['wan2.7-image-pro']) === [], 'the video model declares no capability');
    check($capabilityValues($byId['qwen3.7-text-embedding']) === [], 'the embedding model declares no capability');
    check(
        $capabilityValues($byId['qwen-image-3.0-pro']) === ['image_generation'],
        'the image model declares image generation'
    );

    // Input modalities: vision models offer two combinations, text-only models one.
    check(
        count((array) $optionValues($byId['qwen3.8-max'], 'inputModalities')) === 2,
        'a vision model declares two input modality combinations'
    );
    check(
        count((array) $optionValues($byId['deepseek-v4-pro-0813'], 'inputModalities')) === 1,
        'a text-only model declares one input modality combination'
    );

    // Structured output: schema-capable, JSON-object-only and none.
    check(
        in_array('outputSchema', $optionNames($byId['qwen3.8-max']), true),
        'qwen3.8-max declares a JSON schema option'
    );
    check(
        in_array('outputMimeType', $optionNames($byId['deepseek-v4-pro-0813']), true)
            && !in_array('outputSchema', $optionNames($byId['deepseek-v4-pro-0813']), true),
        'deepseek-v4-pro-0813 declares JSON Object only'
    );
    check(
        !in_array('outputMimeType', $optionNames($byId['qvq-max']), true),
        'qvq-max declares no structured output'
    );

    // Tools and sampling.
    check(
        in_array('functionDeclarations', $optionNames($byId['qwen3.8-max']), true),
        'qwen3.8-max declares function declarations'
    );
    check(
        !in_array('functionDeclarations', $optionNames($byId['qwen-mt-plus']), true),
        'the translation model declares no function declarations'
    );
    check(in_array('temperature', $optionNames($byId['qwen3.8-max']), true), 'chat models declare temperature');
    check(
        !in_array('temperature', $optionNames($byId['qwen-mt-plus']), true),
        'the translation model declares no sampling options'
    );

    // Image model: remote output only, and a candidate count of one to six.
    $imageOutputModalities = $optionValues($byId['qwen-image-3.0-pro'], 'outputModalities');
    check(
        is_array($imageOutputModalities)
            && count($imageOutputModalities) === 1
            && count($imageOutputModalities[0]) === 1
            && $imageOutputModalities[0][0]->isImage(),
        'the image model declares image output'
    );
    check(
        $optionValues($byId['qwen-image-3.0-pro'], 'candidateCount') === [1, 2, 3, 4, 5, 6],
        'the image model accepts one to six images per request'
    );
    $imageFileTypes = $optionValues($byId['qwen-image-3.0-pro'], 'outputFileType');
    check(
        is_array($imageFileTypes)
            && count($imageFileTypes) === 1
            && $imageFileTypes[0]->isRemote(),
        'the image model declares remote output only'
    );
    check(
        !in_array('temperature', $optionNames($byId['qwen-image-3.0-pro']), true),
        'the image model declares no sampling options'
    );

    // --- Provider metadata and URL building. ----------------------------------------------------
    $provider = \QwenCloud\AiProvider\Provider\QwenCloudProvider::class;
    check($provider::metadata()->getId() === QwenCloudConfig::PROVIDER_ID, 'the provider ID is qwencloud');
    check(
        $provider::url('chat/completions') === QwenCloudConfig::DEFAULT_BASE_URL . '/chat/completions',
        'the provider joins the base URL and path'
    );
    check(
        $provider::url('/models') === QwenCloudConfig::DEFAULT_BASE_URL . '/models',
        'a leading slash on the path does not produce a double slash'
    );

    // --- Requests, end to end through a fake HTTP transporter. ---------------------------------
    $providerMetadata = $provider::metadata();

    /**
     * Records the request it is handed and replays a canned response.
     */
    $transporter = new class implements \WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface {
        /** @var \WordPress\AiClient\Providers\Http\DTO\Request|null */
        public $request = null;

        /** @var array<string, mixed> */
        public $body = [];

        /** @var array<string, mixed> */
        public $queue = [];

        /**
         * Sends a request and records it.
         *
         * @param \WordPress\AiClient\Providers\Http\DTO\Request $request The request.
         * @param \WordPress\AiClient\Providers\Http\DTO\RequestOptions|null $options Transport options.
         * @return \WordPress\AiClient\Providers\Http\DTO\Response The canned response.
         */
        public function send(
            \WordPress\AiClient\Providers\Http\DTO\Request $request,
            ?\WordPress\AiClient\Providers\Http\DTO\RequestOptions $options = null
        ): \WordPress\AiClient\Providers\Http\DTO\Response {
            $this->request = $request;
            $this->body = (array) $request->getData();

            return new \WordPress\AiClient\Providers\Http\DTO\Response(200, [], json_encode($this->queue));
        }
    };

    /**
     * Wires a model to the fake transporter with a test credential.
     *
     * @param object $model The model instance.
     * @param object $transporter The fake transporter.
     * @param bool $image Whether this is an image model (longer timeout).
     * @return void
     */
    $bind = static function ($model, $transporter, bool $image = false): void {
        $model->setHttpTransporter($transporter);
        $model->setRequestAuthentication(
            new \WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication('test-key')
        );
        $model->setRequestOptions(
            $image ? QwenCloudConfig::createImageRequestOptions() : QwenCloudConfig::createRequestOptions()
        );
    };

    $message = static fn(string $text) => new \WordPress\AiClient\Messages\DTO\Message(
        \WordPress\AiClient\Messages\Enums\MessageRoleEnum::user(),
        [new \WordPress\AiClient\Messages\DTO\MessagePart($text)]
    );

    // Chat: request shape, structured output wrapper, tools and custom options.
    $chatModel = new \QwenCloud\AiProvider\Models\QwenCloudTextGenerationModel(
        $byId['qwen3.8-max'],
        $providerMetadata
    );
    $bind($chatModel, $transporter);
    $chatModel->setConfig(\WordPress\AiClient\Providers\Models\DTO\ModelConfig::fromArray([
        'systemInstruction' => 'Be terse.',
        'maxTokens' => 256,
        'temperature' => 0.2,
        'outputMimeType' => 'application/json',
        'outputSchema' => [
            'type' => 'object',
            'properties' => ['suggestions' => ['type' => 'array']],
        ],
        'functionDeclarations' => [[
            'name' => 'lookup',
            'description' => 'Look up a value.',
            'parameters' => ['type' => 'object'],
        ]],
        'customOptions' => [
            'enable_thinking' => true,
            'thinking_budget' => 512,
            'top_k' => 20,
            'enable_search' => true,
        ],
    ]));

    $transporter->queue = [
        'id' => 'chatcmpl-1',
        'choices' => [[
            'message' => ['role' => 'assistant', 'content' => 'Hi there'],
            'finish_reason' => 'stop',
        ]],
        'usage' => ['prompt_tokens' => 7, 'completion_tokens' => 3, 'total_tokens' => 10],
    ];
    $chatResult = $chatModel->generateTextResult([$message('hi')]);

    check(
        $transporter->request->getUri() === QwenCloudConfig::DEFAULT_BASE_URL . '/chat/completions',
        'the chat request targets /chat/completions'
    );
    check($transporter->body['model'] === 'qwen3.8-max', 'the chat request carries the model ID');
    check($transporter->body['max_tokens'] === 256, 'max_tokens is forwarded');
    check($transporter->body['temperature'] === 0.2, 'temperature is forwarded');
    check($transporter->body['messages'][0]['role'] === 'system', 'the system instruction is prepended');
    check(
        $transporter->request->getHeaders()['User-Agent'][0] === QwenCloudConfig::getUserAgent(),
        'the chat request identifies the plugin'
    );
    check(
        $transporter->request->getHeaders()['Authorization'][0] === 'Bearer test-key',
        'the chat request carries the Bearer token'
    );
    check(
        ($transporter->body['enable_thinking'] ?? null) === true
            && ($transporter->body['thinking_budget'] ?? null) === 512
            && ($transporter->body['top_k'] ?? null) === 20
            && ($transporter->body['enable_search'] ?? null) === true,
        'custom QwenCloud options are forwarded unchanged'
    );
    check(
        isset($transporter->body['tools'][0]['function']['name'])
            && $transporter->body['tools'][0]['function']['name'] === 'lookup',
        'tool declarations use the OpenAI-compatible wire format'
    );

    /*
     * Regression: the SDK base class emitted {"type":"json_schema","json_schema":<schema>}, which
     * QwenCloud rejects. The schema must be wrapped in a named object with `strict`.
     */
    $responseFormat = $transporter->body['response_format'] ?? null;
    check(
        is_array($responseFormat) && ($responseFormat['type'] ?? null) === 'json_schema',
        'structured output requests use the json_schema response format'
    );
    check(
        isset($responseFormat['json_schema']['name'], $responseFormat['json_schema']['schema'])
            && ($responseFormat['json_schema']['strict'] ?? null) === true,
        'the JSON schema is wrapped in a named, strict json_schema object'
    );
    check(
        ($responseFormat['json_schema']['schema']['type'] ?? null) === 'object',
        'the schema wrapper carries the caller schema unchanged'
    );
    check(
        ($responseFormat['json_schema']['schema']['properties']['suggestions']['type'] ?? null) === 'array',
        'nested schema properties survive the wrapping'
    );
    check(
        $chatResult->getCandidates()[0]->getMessage()->getParts()[0]->getText() === 'Hi there',
        'the chat response text is parsed'
    );
    check($chatResult->getTokenUsage()->getPromptTokens() === 7, 'prompt tokens are counted');

    // JSON Object mode sends no schema.
    $jsonObjectModel = new \QwenCloud\AiProvider\Models\QwenCloudTextGenerationModel(
        $byId['deepseek-v4-pro-0813'],
        $providerMetadata
    );
    $bind($jsonObjectModel, $transporter);
    $jsonObjectModel->setConfig(\WordPress\AiClient\Providers\Models\DTO\ModelConfig::fromArray([
        'outputMimeType' => 'application/json',
    ]));
    $transporter->queue = [
        'choices' => [[
            'message' => ['role' => 'assistant', 'content' => '{"ok":true}'],
            'finish_reason' => 'stop',
        ]],
    ];
    $jsonObjectModel->generateTextResult([$message('json please')]);
    check(
        ($transporter->body['response_format'] ?? null) === ['type' => 'json_object'],
        'a schema-less JSON request uses json_object'
    );

    // Disabling structured output omits response_format entirely.
    putenv('QWENCLOUD_STRUCTURED_OUTPUT=none');
    $chatModel->generateTextResult([$message('plain')]);
    check(!isset($transporter->body['response_format']), 'disabled structured output omits response_format');
    putenv('QWENCLOUD_STRUCTURED_OUTPUT');

    // Vision input: an inline image must become an image_url content part.
    $transporter->queue = [
        'choices' => [[
            'message' => ['role' => 'assistant', 'content' => 'A cat'],
            'finish_reason' => 'stop',
        ]],
    ];
    $visionModel = new \QwenCloud\AiProvider\Models\QwenCloudTextGenerationModel(
        $byId['qwen3.8-max'],
        $providerMetadata
    );
    $bind($visionModel, $transporter);
    $visionModel->generateTextResult([new \WordPress\AiClient\Messages\DTO\Message(
        \WordPress\AiClient\Messages\Enums\MessageRoleEnum::user(),
        [
            new \WordPress\AiClient\Messages\DTO\MessagePart('what is this'),
            new \WordPress\AiClient\Messages\DTO\MessagePart(
                new \WordPress\AiClient\Files\DTO\File('data:image/png;base64,iVBORw0KGgo=', 'image/png')
            ),
        ]
    )]);
    $contentTypes = array_column($transporter->body['messages'][0]['content'], 'type');
    check(in_array('image_url', $contentTypes, true), 'an inline image becomes an image_url content part');

    // Reasoning content is parsed into a thought part by the base class.
    $transporter->queue = [
        'choices' => [[
            'message' => ['role' => 'assistant', 'reasoning_content' => 'pondering', 'content' => 'Answer'],
            'finish_reason' => 'stop',
        ]],
    ];
    $reasoned = $chatModel->generateTextResult([$message('hi')]);
    $channels = [];
    foreach ($reasoned->getCandidates()[0]->getMessage()->getParts() as $part) {
        if ($part->getType()->isText()) {
            $channels[$part->getChannel()->value] = $part->getText();
        }
    }
    check(($channels['thought'] ?? null) === 'pondering', 'reasoning_content becomes a thought part');
    check(($channels['content'] ?? null) === 'Answer', 'the visible answer is a separate content part');

    // Tool call response parsing.
    $transporter->queue = [
        'choices' => [[
            'message' => [
                'role' => 'assistant',
                'content' => null,
                'tool_calls' => [[
                    'type' => 'function',
                    'id' => 'call_1',
                    'function' => ['name' => 'get_weather', 'arguments' => '{"city":"Paris"}'],
                ]],
            ],
            'finish_reason' => 'tool_calls',
        ]],
    ];
    $toolResult = $chatModel->generateTextResult([$message('weather?')]);
    $functionCall = null;
    foreach ($toolResult->getCandidates()[0]->getMessage()->getParts() as $part) {
        if ($part->getType()->isFunctionCall()) {
            $functionCall = $part->getFunctionCall();
        }
    }
    check(
        $functionCall !== null
            && $functionCall->getName() === 'get_weather'
            && ($functionCall->getArgs()['city'] ?? null) === 'Paris',
        'a tool call response is parsed into a function call part'
    );

    // Image generation: request shape and URL parsing.
    $imageModel = new \QwenCloud\AiProvider\Models\QwenCloudImageGenerationModel(
        $byId['qwen-image-3.0-pro'],
        $providerMetadata
    );
    $bind($imageModel, $transporter, true);
    $imageModel->setConfig(\WordPress\AiClient\Providers\Models\DTO\ModelConfig::fromArray([
        'candidateCount' => 2,
        'outputFileType' => 'remote',
        'outputMediaOrientation' => 'landscape',
    ]));
    $transporter->queue = [
        'created' => 1788339600,
        'data' => [['url' => 'https://dashscope-result.example.com/example.png']],
    ];
    $imageResult = $imageModel->generateImageResult([$message('a cat')]);

    check(
        $transporter->request->getUri() === QwenCloudConfig::DEFAULT_BASE_URL . '/images/generations',
        'the image request targets /images/generations'
    );
    check(!isset($transporter->body['response_format']), 'response_format is stripped from the image request');
    check(!isset($transporter->body['output_format']), 'output_format is stripped from the image request');
    check(($transporter->body['n'] ?? null) === 2, 'the image request forwards the candidate count');
    check(($transporter->body['size'] ?? null) === '1664x928', 'the landscape size comes from the catalog');
    check($transporter->body['model'] === 'qwen-image-3.0-pro', 'the image request carries the model ID');
    check($imageResult->toImageFile()->isRemote(), 'the url response is parsed into a remote image');
    check($imageResult->getId() === 'img-1788339600', 'the created timestamp becomes the result ID');

    // A non-HTTP(S) URL must be rejected rather than treated as base64 data.
    $transporter->queue = [
        'created' => 1788339600,
        'data' => [['url' => 'not-a-url']],
    ];
    $rejected = false;
    try {
        $imageModel->generateImageResult([$message('a cat')]);
    } catch (\WordPress\AiClient\Providers\Http\Exception\ResponseException $exception) {
        $rejected = true;
    }
    check($rejected, 'a non-HTTP(S) image URL is rejected');

    use_qwencloud_requirements_checks($byId, $message);
    use_qwencloud_plugin_checks();
}

/**
 * Checks that the declared metadata satisfies the requirements the AI plugin actually sends.
 *
 * This is the failure mode that matters most: metadata is the single source of truth, so a missing
 * capability or option makes a feature report "no available model" with no obvious cause.
 *
 * @param array<string, \WordPress\AiClient\Providers\Models\DTO\ModelMetadata> $byId Models by ID.
 * @param callable $message Builds a single-text user message.
 * @return void
 */
function use_qwencloud_requirements_checks(array $byId, callable $message): void
{
    $meets = static function (string $capability, array $messages, array $config, string $modelId) use ($byId): bool {
        $capabilityEnum = $capability === 'imageGeneration'
            ? \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::imageGeneration()
            : \WordPress\AiClient\Providers\Models\Enums\CapabilityEnum::textGeneration();

        return \WordPress\AiClient\Providers\Models\DTO\ModelRequirements::fromPromptData(
            $capabilityEnum,
            $messages,
            \WordPress\AiClient\Providers\Models\DTO\ModelConfig::fromArray($config)
        )->areMetBy($byId[$modelId]);
    };

    $plainConfig = ['systemInstruction' => 'Be terse.'];

    // Plain text generation (Title Generation, Summarization, Excerpt Generation, …).
    check(
        $meets('textGeneration', [$message('generate a title')], $plainConfig, 'qwen3.7-plus'),
        'plain text generation is supported by the configured chat model'
    );
    // Chat history (two or more messages) must map onto the chatHistory capability.
    check(
        $meets('textGeneration', [$message('hi'), $message('and again')], $plainConfig, 'qwen3.7-plus'),
        'a multi-message prompt is supported (chat history is declared)'
    );

    // Structured output, exactly as the AI plugin's as_json_response() call sites build it.
    $jsonSchemaConfig = [
        'outputMimeType' => 'application/json',
        'outputSchema' => ['type' => 'object', 'properties' => ['suggestions' => ['type' => 'array']]],
    ];
    check(
        $meets('textGeneration', [$message('suggest notes')], $jsonSchemaConfig, 'qwen3.8-max'),
        'a JSON schema request is supported by qwen3.8-max'
    );
    check(
        !$meets('textGeneration', [$message('suggest notes')], $jsonSchemaConfig, 'deepseek-v4-pro-0813'),
        'a JSON schema request is rejected for a JSON-object-only model'
    );
    check(
        $meets('textGeneration', [$message('json please')], ['outputMimeType' => 'application/json'], 'deepseek-v4-pro-0813'),
        'a JSON object request is supported by a JSON-object-only model'
    );

    // Vision: Alt Text Generation sends text plus an image file.
    $visionMessage = new \WordPress\AiClient\Messages\DTO\Message(
        \WordPress\AiClient\Messages\Enums\MessageRoleEnum::user(),
        [
            new \WordPress\AiClient\Messages\DTO\MessagePart('describe this'),
            new \WordPress\AiClient\Messages\DTO\MessagePart(
                new \WordPress\AiClient\Files\DTO\File('data:image/png;base64,iVBORw0KGgo=', 'image/png')
            ),
        ]
    );
    check(
        $meets('textGeneration', [$visionMessage], $plainConfig, 'qwen3.8-max'),
        'alt text generation is supported by the vision model'
    );
    check(
        !$meets('textGeneration', [$visionMessage], $plainConfig, 'deepseek-v4-pro-0813'),
        'the text-only model is correctly rejected for an image prompt'
    );

    // Tool calling: the requirement is inferred from a function response in the prompt.
    $toolMessage = new \WordPress\AiClient\Messages\DTO\Message(
        \WordPress\AiClient\Messages\Enums\MessageRoleEnum::user(),
        [new \WordPress\AiClient\Messages\DTO\MessagePart(new \WordPress\AiClient\Tools\DTO\FunctionResponse(
            'call_1',
            'get_weather',
            ['temperature' => 21]
        ))]
    );
    check(
        $meets('textGeneration', [$toolMessage], $plainConfig, 'qwen3.8-max'),
        'tool calling is supported (function declarations are declared)'
    );
    check(
        !$meets('textGeneration', [$toolMessage], $plainConfig, 'qwen-mt-plus'),
        'the translation model is correctly rejected for a tool prompt'
    );

    // Image generation, exactly as the AI plugin's Generate Image ability builds it.
    check(
        $meets('imageGeneration', [$message('a cat')], ['outputFileType' => 'remote'], 'qwen-image-3.0-pro'),
        'the Generate Image ability is supported (remote output)'
    );
    check(
        !$meets('imageGeneration', [$message('a cat')], ['outputFileType' => 'remote'], 'qwen3.8-max'),
        'the chat model is correctly rejected for image generation'
    );
    check(
        !$meets('imageGeneration', [$message('a cat')], ['outputFileType' => 'inline'], 'qwen-image-3.0-pro'),
        'an inline image request is correctly rejected (only remote output is declared)'
    );
    check(
        $meets('imageGeneration', [$message('a cat')], ['candidateCount' => 6], 'qwen-image-3.0-pro'),
        'asking for six images is supported'
    );
    check(
        !$meets('imageGeneration', [$message('a cat')], ['candidateCount' => 7], 'qwen-image-3.0-pro'),
        'asking for seven images is correctly rejected'
    );

    // A capability-less model must never be selected for anything.
    check(
        !$meets('textGeneration', [$message('hi')], $plainConfig, 'wan2.7-image-pro'),
        'the video model is never selected for text generation'
    );
}

/**
 * Checks the plugin's entry file: registration guards and the model preference filters.
 *
 * The plugin file is not part of the autoloader, so it is loaded here with the few WordPress
 * functions it touches stubbed out. The preference filters are the reason this exists: they rewrite
 * a list owned by other plugins, and an off-by-one there silently drops another provider's model.
 *
 * @return void
 */
function use_qwencloud_plugin_checks(): void
{
    // WordPress stubs, defined only when WordPress is not actually present.
    if (!function_exists('add_action')) {
        /**
         * Records an action registration.
         *
         * @param string $hook The hook name.
         * @param callable $callback The callback.
         * @param int $priority The priority.
         * @return void
         */
        function add_action(string $hook, $callback, int $priority = 10): void
        {
            $GLOBALS['qwencloud_actions'][$hook][$priority][] = $callback;
        }

        /**
         * Records a filter registration.
         *
         * @param string $hook The hook name.
         * @param callable $callback The callback.
         * @param int $priority The priority.
         * @return void
         */
        function add_filter(string $hook, $callback, int $priority = 10): void
        {
            $GLOBALS['qwencloud_filters'][$hook][$priority][] = $callback;
        }

        /**
         * Returns the string unchanged; translation is a WordPress concern.
         *
         * @param string $text The text.
         * @param string|null $domain The text domain.
         * @return string The text.
         */
        function __(string $text, ?string $domain = null): string
        {
            return $text;
        }

        /**
         * Returns the escaped string unchanged.
         *
         * @param string $text The text.
         * @param string|null $domain The text domain.
         * @return string The text.
         */
        function esc_html__(string $text, ?string $domain = null): string
        {
            return $text;
        }

        /**
         * Returns the escaped string unchanged.
         *
         * @param string $text The text.
         * @return string The text.
         */
        function esc_html(string $text): string
        {
            return $text;
        }

        /**
         * Returns the plugin file's path relative to the plugins directory, as WordPress does.
         *
         * @param string $file The plugin file path.
         * @return string The path relative to the plugins directory.
         */
        function plugin_basename(string $file): string
        {
            $dir = $GLOBALS['qwencloud_plugins_dir'] ?? null;
            if (is_string($dir) && strpos($file, $dir) === 0) {
                return ltrim(substr($file, strlen($dir)), '/');
            }

            return basename($file);
        }

        /**
         * Records that translations were loaded; the harness has no .mo files to load.
         *
         * @param string $domain The text domain.
         * @param bool $deprecated Unused.
         * @param string|null $path The languages directory.
         * @return bool Always true.
         */
        function load_plugin_textdomain(string $domain, bool $deprecated = false, ?string $path = null): bool
        {
            $GLOBALS['qwencloud_textdomain'] = [$domain, $path];

            return true;
        }
    }

    require dirname(__DIR__) . '/ai-provider-for-qwencloud.php';

    /*
     * The SDK resolves a PSR-18 client through HTTPlug discovery when a provider is registered. That
     * works in WordPress (the AI plugin ships the discovery package) but not against a bare SDK
     * checkout, so inject a no-op transporter first and the registration path never reaches discovery.
     */
    \WordPress\AiClient\AiClient::defaultRegistry()->setHttpTransporter(
        new class implements \WordPress\AiClient\Providers\Http\Contracts\HttpTransporterInterface {
            /**
             * Never called: this harness only exercises registration and filters.
             *
             * @param \WordPress\AiClient\Providers\Http\DTO\Request $request The request.
             * @param \WordPress\AiClient\Providers\Http\DTO\RequestOptions|null $options Transport options.
             * @return \WordPress\AiClient\Providers\Http\DTO\Response The response.
             */
            public function send(
                \WordPress\AiClient\Providers\Http\DTO\Request $request,
                ?\WordPress\AiClient\Providers\Http\DTO\RequestOptions $options = null
            ): \WordPress\AiClient\Providers\Http\DTO\Response {
                return new \WordPress\AiClient\Providers\Http\DTO\Response(200, [], '{}');
            }
        }
    );

    // Credential detection: asks the AI Client, never reads the Connectors option.
    check(!QwenCloudConfig::hasCredentials(), 'no credentials before the AI Client is given one');

    // Registration must be on init priority 5, or the Connectors card never appears.
    $callbacks = $GLOBALS['qwencloud_actions']['init'][5] ?? [];
    check($callbacks !== [], 'the provider registers on init priority 5');
    foreach ($callbacks as $callback) {
        $callback();
    }
    check(
        \WordPress\AiClient\AiClient::defaultRegistry()->hasProvider(QwenCloudConfig::PROVIDER_ID),
        'the registered provider is present in the SDK registry'
    );
    // Registering again must be a no-op rather than an error.
    foreach ($callbacks as $callback) {
        $callback();
    }
    check(true, 'registering the provider twice does not throw');
    check(!QwenCloudConfig::hasCredentials(), 'a registered provider is not yet a credentialed one');

    // The filters go through WordPress' filter machinery in production; apply them by hand here.
    $apply = static function (string $hook, array $value): array {
        $priorities = $GLOBALS['qwencloud_filters'][$hook] ?? [];
        ksort($priorities);
        foreach ($priorities as $callbacks) {
            foreach ($callbacks as $callback) {
                $value = $callback($value);
            }
        }
        return $value;
    };

    // Without a credential the provider yields no candidates, so the lists must be untouched.
    $untouched = [['anthropic', 'claude-sonnet-5']];
    check(
        $apply('wpai_preferred_text_models', $untouched) === $untouched,
        'the text preference filter changes nothing without credentials'
    );
    check(
        $apply('wpai_preferred_image_models', $untouched) === $untouched,
        'the image preference filter changes nothing without credentials'
    );

    \WordPress\AiClient\AiClient::defaultRegistry()->setProviderRequestAuthentication(
        QwenCloudConfig::PROVIDER_ID,
        new \WordPress\AiClient\Providers\Http\DTO\ApiKeyRequestAuthentication('test-key')
    );
    check(QwenCloudConfig::hasCredentials(), 'a key handed to the AI Client counts as credentials');

    // Preferred first, other providers preserved, and a different QwenCloud model kept.
    check(
        $apply('wpai_preferred_text_models', [
            ['anthropic', 'claude-sonnet-5'],
            ['qwencloud', 'qwen3.8-flash'],
            ['qwencloud', 'qwen3.7-plus'],
        ]) === [
            ['qwencloud', 'qwen3.7-plus'],
            ['anthropic', 'claude-sonnet-5'],
            ['qwencloud', 'qwen3.8-flash'],
        ],
        'the text filter prefers the default model, keeps other providers and other qwencloud models'
    );

    // The image feature needs an image model, not the chat flagship.
    check(
        $apply('wpai_preferred_image_models', [['google', 'gemini-3-pro-image-preview']]) === [
            ['qwencloud', 'qwen-image-3.0-pro'],
            ['google', 'gemini-3-pro-image-preview'],
        ],
        'the image filter prefers the image model and keeps other providers'
    );

    // Vision features need a chat model that accepts images.
    check(
        $apply('wpai_preferred_vision_models', []) === [['qwencloud', 'qwen3.7-plus']],
        'the vision filter prefers the vision-capable chat model'
    );

    // Entries from other plugins can be any shape; they must be skipped, not propagated.
    $malformed = $apply('wpai_preferred_text_models', ['nonsense', ['only-one'], ['openai', 'gpt-5.6-luna']]);
    check(
        $malformed === [['qwencloud', 'qwen3.7-plus'], ['openai', 'gpt-5.6-luna']],
        'malformed preference entries are skipped rather than propagated'
    );

    // The filter must reflect the configured model, not a hardcoded one.
    putenv('QWENCLOUD_DEFAULT_MODEL=qwen3.8-flash');
    check(
        $apply('wpai_preferred_text_models', []) === [['qwencloud', 'qwen3.8-flash']],
        'QWENCLOUD_DEFAULT_MODEL is honoured by the preference filter'
    );
    putenv('QWENCLOUD_DEFAULT_MODEL');
    putenv('QWENCLOUD_IMAGE_MODEL=qwen-image-3.0');
    check(
        $apply('wpai_preferred_image_models', []) === [['qwencloud', 'qwen-image-3.0']],
        'QWENCLOUD_IMAGE_MODEL is honoured by the preference filter'
    );
    putenv('QWENCLOUD_IMAGE_MODEL');

    // --- Translations. --------------------------------------------------------------------------
    $callbacks = $GLOBALS['qwencloud_actions']['init'][10] ?? [];
    check($callbacks !== [], 'the text domain is loaded on init');
    foreach ($callbacks as $callback) {
        $callback();
    }
    check(
        ($GLOBALS['qwencloud_textdomain'][0] ?? null) === 'ai-provider-for-qwencloud',
        'the text domain matches the plugin header'
    );
    check(
        ($GLOBALS['qwencloud_textdomain'][1] ?? null) === basename($GLOBALS['qwencloud_plugin_dir']) . '/languages',
        'translations are loaded from the plugin languages directory'
    );
}

// --- Result. -----------------------------------------------------------------------------------
fwrite(STDOUT, sprintf("\n%d checks, %d failure(s)\n", $checks, $failures));

exit($failures === 0 ? 0 : 1);
