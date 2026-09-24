<?php

/**
 * QwenCloud OpenAI-compatible image generation model.
 *
 * @package QwenCloud\AiProvider
 */

declare(strict_types=1);

namespace QwenCloud\AiProvider\Models;

use QwenCloud\AiProvider\Util\QwenCloudModelCatalog;
use WordPress\AiClient\Files\Enums\MediaOrientationEnum;
use WordPress\AiClient\Providers\Http\Exception\ResponseException;
use WordPress\AiClient\Providers\OpenAiCompatibleImplementation\AbstractOpenAiCompatibleImageGenerationModel;
use WordPress\AiClient\Results\DTO\Candidate;

/**
 * Image generation model for the `qwen-image-3.0` series.
 *
 * QwenCloud's image endpoint follows the OpenAI Images spec: `POST /images/generations` with
 * `{model, prompt, n, size}` and a response of `{created, data: [{url}]}`. The base class already
 * builds and parses that shape, so only the fields QwenCloud does not accept — and the URL check the
 * base class omits — are overridden here.
 *
 * Image editing (`/images/edits`, the `image` request field) is deliberately not implemented.
 */
final class QwenCloudImageGenerationModel extends AbstractOpenAiCompatibleImageGenerationModel
{
    use QwenCloudRequestTrait;

    /**
     * {@inheritDoc}
     *
     * @param list<\WordPress\AiClient\Messages\DTO\Message> $prompt The prompt to generate an image for.
     * @return array<string, mixed> The parameters for the API request.
     */
    protected function prepareGenerateImageParams(array $prompt): array
    {
        $params = parent::prepareGenerateImageParams($prompt);

        /*
         * `response_format` and `output_format` are OpenAI gpt-image-* fields. QwenCloud always
         * returns image URLs and does not document either parameter, so sending them risks a 400.
         */
        unset($params['response_format'], $params['output_format']);

        return $params;
    }

    /**
     * {@inheritDoc}
     *
     * @param MediaOrientationEnum|null $orientation The desired media orientation.
     * @param string|null $aspectRatio The desired media aspect ratio.
     * @return string The prepared size parameter.
     */
    protected function prepareSizeParam(?MediaOrientationEnum $orientation, ?string $aspectRatio): string
    {
        /*
         * Aspect ratios are not declared as a supported option, so the framework never routes a ratio
         * here. Translate one anyway rather than throwing, so a caller that sets a ratio through a
         * custom option still gets a sensible request.
         */
        if ($aspectRatio !== null) {
            $parts = explode(':', $aspectRatio);
            if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                $orientation = $parts[0] > $parts[1]
                    ? MediaOrientationEnum::landscape()
                    : ($parts[0] < $parts[1] ? MediaOrientationEnum::portrait() : MediaOrientationEnum::square());
            }
        }

        return QwenCloudModelCatalog::sizeForOrientation(
            $orientation === null ? null : $orientation->value
        );
    }

    /**
     * {@inheritDoc}
     *
     * QwenCloud returns image URLs, so a non-HTTP(S) value is rejected with a clear error instead of
     * being handed to the file DTO as if it were base64.
     *
     * @param array<string, mixed> $choiceData The choice data from the API response.
     * @param int $index The index of the choice in the data array.
     * @param string $expectedMimeType The expected MIME type of the image.
     * @return Candidate The parsed candidate.
     * @throws ResponseException If the choice does not contain an absolute HTTP(S) URL.
     */
    protected function parseResponseChoiceToCandidate(
        array $choiceData,
        int $index,
        string $expectedMimeType = 'image/png'
    ): Candidate {
        $url = $choiceData['url'] ?? null;
        if (!is_string($url) || preg_match('#^https?://#i', $url) !== 1) {
            throw ResponseException::fromInvalidData(
                $this->providerMetadata()->getName(),
                "data[{$index}].url",
                'The value must be an absolute HTTP(S) URL.'
            );
        }

        return parent::parseResponseChoiceToCandidate($choiceData, $index, $expectedMimeType);
    }

    /**
     * {@inheritDoc}
     *
     * @param array<string, mixed> $responseData The response data from the API.
     * @return string The result ID.
     */
    protected function getResultId(array $responseData): string
    {
        // The Images API returns a `created` timestamp instead of an `id`.
        return isset($responseData['created']) && is_int($responseData['created'])
            ? 'img-' . $responseData['created']
            : '';
    }
}
