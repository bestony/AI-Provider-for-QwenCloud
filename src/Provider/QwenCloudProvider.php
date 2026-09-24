<?php

/**
 * QwenCloud provider.
 *
 * @package QwenCloud\AiProvider
 */

declare(strict_types=1);

namespace QwenCloud\AiProvider\Provider;

use QwenCloud\AiProvider\Metadata\QwenCloudModelMetadataDirectory;
use QwenCloud\AiProvider\Models\QwenCloudImageGenerationModel;
use QwenCloud\AiProvider\Models\QwenCloudTextGenerationModel;
use QwenCloud\AiProvider\Util\QwenCloudConfig;
use WordPress\AiClient\AiClient;
use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\ApiBasedImplementation\AbstractApiProvider;
use WordPress\AiClient\Providers\ApiBasedImplementation\ListModelsApiBasedProviderAvailability;
use WordPress\AiClient\Providers\Contracts\ModelMetadataDirectoryInterface;
use WordPress\AiClient\Providers\Contracts\ProviderAvailabilityInterface;
use WordPress\AiClient\Providers\DTO\ProviderMetadata;
use WordPress\AiClient\Providers\Enums\ProviderTypeEnum;
use WordPress\AiClient\Providers\Http\Enums\RequestAuthenticationMethod;
use WordPress\AiClient\Providers\Models\Contracts\ModelInterface;
use WordPress\AiClient\Providers\Models\DTO\ModelMetadata;

final class QwenCloudProvider extends AbstractApiProvider
{
    /**
     * {@inheritDoc}
     *
     * @return string The base URL for the QwenCloud API.
     */
    protected static function baseUrl(): string
    {
        return QwenCloudConfig::getBaseUrl();
    }

    /**
     * {@inheritDoc}
     *
     * @param ModelMetadata $modelMetadata The model metadata.
     * @param ProviderMetadata $providerMetadata The provider metadata.
     * @return ModelInterface The model instance.
     * @throws RuntimeException If the model has no supported capability for this provider.
     */
    protected static function createModel(
        ModelMetadata $modelMetadata,
        ProviderMetadata $providerMetadata
    ): ModelInterface {
        foreach ($modelMetadata->getSupportedCapabilities() as $capability) {
            if ($capability->isTextGeneration()) {
                $model = new QwenCloudTextGenerationModel($modelMetadata, $providerMetadata);
                $model->setRequestOptions(QwenCloudConfig::createRequestOptions());

                return $model;
            }

            if ($capability->isImageGeneration()) {
                $model = new QwenCloudImageGenerationModel($modelMetadata, $providerMetadata);
                // Image generation routinely runs for minutes; it needs its own timeout.
                $model->setRequestOptions(QwenCloudConfig::createImageRequestOptions());

                return $model;
            }
        }

        throw new RuntimeException(
            sprintf(
                /* translators: %s: model ID. */
                esc_html__('The model "%s" has no supported capability for QwenCloud.', 'ai-provider-for-qwencloud'),
                esc_html($modelMetadata->getId())
            )
        );
    }

    /**
     * {@inheritDoc}
     *
     * @return ProviderMetadata The provider metadata.
     */
    protected static function createProviderMetadata(): ProviderMetadata
    {
        $args = [
            QwenCloudConfig::PROVIDER_ID,
            'QwenCloud',
            ProviderTypeEnum::cloud(),
            'https://home.qwencloud.com/api-keys',
            RequestAuthenticationMethod::apiKey(),
        ];

        // Provider description support was added in SDK 1.2.0.
        if (version_compare(AiClient::VERSION, '1.2.0', '>=')) {
            $description = 'Text, vision and image generation with QwenCloud models.';
            $args[] = function_exists('__')
                ? __('Text, vision and image generation with QwenCloud models.', 'ai-provider-for-qwencloud')
                : $description;
        }

        // Provider logoPath support was added in SDK 1.3.0.
        if (version_compare(AiClient::VERSION, '1.3.0', '>=')) {
            $args[] = dirname(__DIR__, 2) . '/assets/images/qwencloud.svg';
        }

        return new ProviderMetadata(...$args);
    }

    /**
     * {@inheritDoc}
     *
     * @return ProviderAvailabilityInterface The provider availability check.
     */
    protected static function createProviderAvailability(): ProviderAvailabilityInterface
    {
        // Valid credentials are confirmed by listing models, which requires the API key.
        return new ListModelsApiBasedProviderAvailability(static::modelMetadataDirectory());
    }

    /**
     * {@inheritDoc}
     *
     * @return ModelMetadataDirectoryInterface The model metadata directory.
     */
    protected static function createModelMetadataDirectory(): ModelMetadataDirectoryInterface
    {
        return new QwenCloudModelMetadataDirectory();
    }
}
