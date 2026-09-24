<?php

/**
 * QwenCloud model metadata directory.
 *
 * @package QwenCloud\AiProvider
 */

declare(strict_types=1);

namespace QwenCloud\AiProvider\Metadata;

use QwenCloud\AiProvider\Provider\QwenCloudProvider;
use QwenCloud\AiProvider\Util\QwenCloudConfig;
use QwenCloud\AiProvider\Util\QwenCloudModelCatalog;
use WordPress\AiClient\Files\Enums\FileTypeEnum;
use WordPress\AiClient\Files\Enums\MediaOrientationEnum;
use WordPress\AiClient\Messages\Enums\ModalityEnum;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;
use WordPress\AiClient\Providers\Models\DTO\SupportedOption;
use WordPress\AiClient\Providers\Models\Enums\CapabilityEnum;
use WordPress\AiClient\Providers\Models\Enums\OptionEnum;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleModelMetadataDirectory;

/**
 * Class for the QwenCloud model metadata directory.
 *
 * QwenCloud's `/models` endpoint returns `{id, object, created, owned_by}` and no capability
 * information, so the live list is classified through {@see QwenCloudModelCatalog}. That
 * declaration is the single source of truth: the SDK decides which model may serve a request by
 * matching it against these values, so under-declaring makes a model unusable and over-declaring
 * turns into a 400 from the upstream.
 *
 * @phpstan-type ModelsResponseData array{
 *     data: list<array{id: string, object?: string, created?: int, owned_by?: string}>
 * }
 */
class QwenCloudModelMetadataDirectory extends AbstractOpenAiCompatibleModelMetadataDirectory
{
    /**
     * Include the preferred models in the cache key so a configuration change is picked up.
     *
     * @return string The base cache key.
     */
    protected function getBaseCacheKey(): string
    {
        return parent::getBaseCacheKey() . '_' . md5(
            QwenCloudConfig::getDefaultModelId() . '|' . QwenCloudConfig::getImageModelId()
        );
    }

    /**
     * {@inheritDoc}
     *
     * @param HttpMethodEnum $method The HTTP method.
     * @param string $path The API endpoint path, relative to the base URL.
     * @param array<string, string|list<string>> $headers The request headers.
     * @param string|array<string, mixed>|null $data The request data.
     * @return Request The request object.
     */
    protected function createRequest(HttpMethodEnum $method, string $path, array $headers = [], $data = null): Request
    {
        /*
         * The base class does not pass request options here, so without this the model list request
         * is sent with WordPress' 5 second HTTP default.
         */
        $headers['User-Agent'] = QwenCloudConfig::getUserAgent();

        return new Request(
            $method,
            QwenCloudProvider::url($path),
            $headers,
            $data,
            QwenCloudConfig::createRequestOptions()
        );
    }

    /**
     * {@inheritDoc}
     *
     * @param Response $response The response from the API endpoint to list models.
     * @return list<ModelMetadata> List of model metadata objects.
     */
    protected function parseResponseToModelMetadataList(Response $response): array
    {
        /** @var ModelsResponseData $responseData */
        $responseData = $response->getData();
        if (!isset($responseData['data']) || !is_array($responseData['data']) || !$responseData['data']) {
            throw ResponseException::fromMissingData('QwenCloud', 'data');
        }

        $models = [];
        $seen = [];
        foreach ($responseData['data'] as $modelData) {
            if (!is_array($modelData) || !isset($modelData['id']) || !is_string($modelData['id'])) {
                continue;
            }

            $modelId = $modelData['id'];
            if (isset($seen[$modelId])) {
                continue;
            }
            $seen[$modelId] = true;

            $models[] = $this->createMetadata($modelId);
        }

        usort($models, static function (ModelMetadata $a, ModelMetadata $b): int {
            return QwenCloudModelCatalog::compareModelIds($a->getId(), $b->getId());
        });

        return $models;
    }

    /**
     * Build metadata for one live model ID using the local catalog.
     *
     * @param string $modelId The model ID.
     * @return ModelMetadata The model metadata.
     */
    private function createMetadata(string $modelId): ModelMetadata
    {
        if (QwenCloudModelCatalog::isImageModel($modelId)) {
            return new ModelMetadata(
                $modelId,
                $modelId,
                [CapabilityEnum::imageGeneration()],
                $this->createImageOptions()
            );
        }

        if (QwenCloudModelCatalog::isTextModel($modelId)) {
            return new ModelMetadata(
                $modelId,
                $modelId,
                [
                    CapabilityEnum::textGeneration(),
                    CapabilityEnum::chatHistory(),
                ],
                $this->createTextOptions($modelId)
            );
        }

        /*
         * Video, audio, embedding and any future family this plugin does not implement. The model
         * stays in the list — so it is visible and does not look like the API is hiding something —
         * but with no capability it can never be selected for a request.
         */
        return new ModelMetadata($modelId, $modelId, [], []);
    }

    /**
     * Build the supported options for a text generation model.
     *
     * @param string $modelId The model ID.
     * @return list<SupportedOption> The supported options.
     */
    private function createTextOptions(string $modelId): array
    {
        $inputModalities = [[ModalityEnum::text()]];
        if (QwenCloudModelCatalog::supportsImageInput($modelId)) {
            $inputModalities[] = [ModalityEnum::text(), ModalityEnum::image()];
        }

        $options = [
            new SupportedOption(OptionEnum::systemInstruction()),
            new SupportedOption(OptionEnum::maxTokens()),
            new SupportedOption(OptionEnum::stopSequences()),
            new SupportedOption(OptionEnum::customOptions()),
            new SupportedOption(OptionEnum::inputModalities(), $inputModalities),
            new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::text()]]),
        ];

        if (QwenCloudModelCatalog::supportsStructuredOutput($modelId)) {
            $options[] = new SupportedOption(OptionEnum::outputMimeType(), ['text/plain', 'application/json']);
        }

        if (QwenCloudModelCatalog::supportsJsonSchema($modelId)) {
            $options[] = new SupportedOption(OptionEnum::outputSchema());
        }

        if (QwenCloudModelCatalog::supportsTools($modelId)) {
            $options[] = new SupportedOption(OptionEnum::functionDeclarations());
        }

        if (QwenCloudModelCatalog::supportsSampling($modelId)) {
            $options = array_merge(
                $options,
                [
                    new SupportedOption(OptionEnum::temperature()),
                    new SupportedOption(OptionEnum::topP()),
                    new SupportedOption(OptionEnum::presencePenalty()),
                    new SupportedOption(OptionEnum::frequencyPenalty()),
                ]
            );
        }

        if (QwenCloudModelCatalog::supportsCandidateCount($modelId)) {
            $options[] = new SupportedOption(OptionEnum::candidateCount());
        }

        return $options;
    }

    /**
     * Build the supported options for an image generation model.
     *
     * @return list<SupportedOption> The supported options.
     */
    private function createImageOptions(): array
    {
        return [
            new SupportedOption(OptionEnum::inputModalities(), [[ModalityEnum::text()]]),
            new SupportedOption(OptionEnum::outputModalities(), [[ModalityEnum::image()]]),
            // The API accepts 1–6 images per request.
            new SupportedOption(OptionEnum::candidateCount(), [1, 2, 3, 4, 5, 6]),
            // QwenCloud returns image URLs, so only remote output is declared.
            new SupportedOption(OptionEnum::outputFileType(), [FileTypeEnum::remote()]),
            new SupportedOption(OptionEnum::outputMediaOrientation(), [
                MediaOrientationEnum::square(),
                MediaOrientationEnum::landscape(),
                MediaOrientationEnum::portrait(),
            ]),
            new SupportedOption(OptionEnum::customOptions()),
        ];
    }
}
