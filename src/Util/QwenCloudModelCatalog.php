<?php

/**
 * QwenCloud model catalog.
 *
 * Pure string logic, no WordPress and no SDK: this is the part of the plugin worth unit testing, and
 * `scripts/selfcheck.php` does exactly that.
 *
 * The `/models` endpoint reports only IDs (`{id, object, created, owned_by}`), so every capability
 * and every request parameter this plugin declares is derived from the model ID here. Sources are the
 * QwenCloud model guides (text generation, visual understanding, structured output, thinking) checked
 * 2026-09-24.
 *
 * @package QwenCloud\AiProvider
 */

declare(strict_types=1);

namespace QwenCloud\AiProvider\Util;

final class QwenCloudModelCatalog
{
    /**
     * Text-to-image families this plugin implements.
     *
     * Only the `qwen-image-3.0` series is declared, because it is the only family with a documented
     * OpenAI-compatible `/images/generations` request. Other image families (`wan*`, `z-image`, …)
     * use a different protocol and are therefore left without a capability.
     *
     * @var list<string>
     */
    private const IMAGE_MODEL_PATTERNS = [
        '#^qwen-image-3\.0(?:-pro)?$#',
    ];

    /**
     * Model ID patterns that are not text-generation models at all.
     *
     * Checked *before* the catch-all text rule, so an embedding, video or audio family can never be
     * mistaken for a chat model.
     *
     * @var list<string>
     */
    private const NON_TEXT_MODEL_PATTERNS = [
        // Text and multimodal embeddings.
        '#embedding#',
        // Reranking.
        '#rerank#',
        // Image generation and editing other than the declared family.
        '#^qwen-image-#',
        '#image-edit#',
        '#image2image#',
        '#^z-image#',
        '#^wan[0-9]#',
        // Video generation.
        '#video#',
        '#^happyhorse#',
        // World models.
        '#^happyoyster#',
        '#world-model#',
        // Speech synthesis and recognition.
        '#^qwen-audio#',
        '#-tts#',
        '#-asr#',
        '#audio#',
        '#cosyvoice#',
        '#captioner#',
        // Real-time-only protocols (no synchronous Chat Completions entry point).
        '#realtime#',
        // Image translation and the decision model.
        '#mt-image#',
        '#^decision-model#',
    ];

    /**
     * Model ID patterns that accept image input.
     *
     * Every general-purpose Qwen3.5+ commercial model accepts text, image and video; the vision
     * guide lists them explicitly. `deepseek-v4.1-flash` is the one third-party model documented with
     * "vision understanding". A model wrongly declared here 400s rather than silently degrading, so
     * the list stays as narrow as the evidence.
     *
     * @var list<string>
     */
    private const VISION_MODEL_PATTERNS = [
        // Qwen3.5–Qwen3.8 commercial max/plus/flash families.
        '#^qwen3\.(?:5|6|7|8)-(?:max|plus|flash)#',
        // Qwen3.5/3.6 open-weight vision variants.
        '#^qwen3\.(?:5|6)-(?:397b|122b|35b|27b)#',
        // Omni families (text, image, audio, video input; text output in this plugin).
        '#^qwen3\.8-omni#',
        '#^qwen3\.5-omni-(?:plus|flash)#',
        '#^qwen3-omni#',
        '#^qwen-omni#',
        '#^qwen2\.5-omni#',
        // Dedicated vision families.
        '#^qwen3-vl#',
        '#^qwen-vl#',
        '#^qvq-#',
        '#^qwen2\.5-vl#',
        // Third-party vision model.
        '#^deepseek-v4\.1-flash$#',
    ];

    /**
     * Model ID patterns that generate text.
     *
     * This is the catch-all: it runs only after the non-text exclusions, so it is safe to keep broad
     * enough that a newly released Qwen chat model stays usable.
     *
     * @var list<string>
     */
    private const TEXT_MODEL_PATTERNS = [
        '#^qwen3#',
        '#^qwen2\.5-#',
        '#^qwen-#',
        '#^qwq-#',
        '#^qvq-#',
        '#^deepseek-#',
        '#^kimi-#',
        '#^glm-#',
    ];

    /**
     * Text models that do not support function calling.
     *
     * Translation and roleplay models have no tools, and the reasoning-only QwQ/QVQ families do not
     * expose the function-calling API.
     *
     * @var list<string>
     */
    private const TOOLLESS_MODEL_PATTERNS = [
        '#^qwen-mt-#',
        '#-character#',
        '#^qwq-#',
        '#^qvq-#',
    ];

    /**
     * Text models that support JSON Schema structured output.
     *
     * From the structured output guide: Qwen3.8-Max, Qwen3.8-Flash, Qwen3.7-Max, Qwen3.7-Plus and
     * Qwen3.7-Flash series.
     *
     * @var list<string>
     */
    private const JSON_SCHEMA_MODEL_PATTERNS = [
        '#^qwen3\.8-(?:max|flash)#',
        '#^qwen3\.7-(?:max|plus|flash)#',
    ];

    /**
     * Text models that do not support structured output at all.
     *
     * Everything else that generates text supports at least JSON Object mode.
     *
     * @var list<string>
     */
    private const NO_STRUCTURED_OUTPUT_MODEL_PATTERNS = [
        '#^qwen-mt-#',
        '#-character#',
        '#^qwq-#',
        '#^qvq-#',
        '#^deepseek-v4-flash-0731$#',
        '#^deepseek-v3\.2$#',
        '#^qwen3\.5-omni-flash#',
        '#^qwen3-omni-flash#',
        '#^qwen-omni-#',
        '#^qwen2\.5-omni-#',
    ];

    /**
     * Text models that reject non-default sampling parameters.
     *
     * The API reference says not to change `temperature`, `top_p`, `presence_penalty` or `top_k` for
     * QVQ, and translation/roleplay models do not document sampling at all.
     *
     * @var list<string>
     */
    private const NO_SAMPLING_MODEL_PATTERNS = [
        '#^qwen-mt-#',
        '#-character#',
        '#^qvq-#',
    ];

    /**
     * Text models that support `n` (candidate count).
     *
     * The API reference documents `n` (1–4) only for Qwen3 (non-thinking mode) models.
     *
     * @var list<string>
     */
    private const CANDIDATE_COUNT_MODEL_PATTERNS = [
        '#^qwen3-#',
    ];

    /**
     * Models that support `n` but are excluded from the Qwen3 rule above.
     *
     * @var list<string>
     */
    private const NO_CANDIDATE_COUNT_MODEL_PATTERNS = [
        '#^qwen3-(?:vl|omni|coder)#',
    ];

    /**
     * The size used per orientation when nothing is overridden.
     *
     * Qwen image generation accepts `widthxheight` with a pixel area between 512x512 and 2048x2048
     * and an aspect ratio up to 8:1. These are the standard Qwen-Image resolutions.
     *
     * @var array<string, string>
     */
    private const SIZES_BY_ORIENTATION = [
        'square' => '1024x1024',
        'landscape' => '1664x928',
        'portrait' => '928x1664',
    ];

    /**
     * The size used when nothing better is known.
     *
     * @var string
     */
    public const DEFAULT_SIZE = '1024x1024';

    /**
     * Whether a model accepts image input.
     *
     * @param string $modelId The model ID.
     * @return bool Whether the model accepts images.
     */
    public static function supportsImageInput(string $modelId): bool
    {
        return self::isTextModel($modelId) && self::matches($modelId, self::VISION_MODEL_PATTERNS);
    }

    /**
     * Whether a model generates images rather than text.
     *
     * @param string $modelId The model ID.
     * @return bool Whether the model generates images.
     */
    public static function isImageModel(string $modelId): bool
    {
        return self::matches($modelId, self::IMAGE_MODEL_PATTERNS);
    }

    /**
     * Whether a model can generate text at all.
     *
     * Embedding, reranking, image, video and audio models are excluded first; the remaining ID is
     * treated as a chat model if it belongs to a known text family.
     *
     * @param string $modelId The model ID.
     * @return bool Whether the model generates text.
     */
    public static function isTextModel(string $modelId): bool
    {
        if (self::matches($modelId, self::NON_TEXT_MODEL_PATTERNS)) {
            return false;
        }

        return self::matches($modelId, self::TEXT_MODEL_PATTERNS);
    }

    /**
     * Whether this plugin declares no capability for a model.
     *
     * @param string $modelId The model ID.
     * @return bool Whether the model is unsupported.
     */
    public static function isUnsupported(string $modelId): bool
    {
        return !self::isTextModel($modelId) && !self::isImageModel($modelId);
    }

    /**
     * Whether a text model supports function calling.
     *
     * @param string $modelId The model ID.
     * @return bool Whether function declarations may be sent.
     */
    public static function supportsTools(string $modelId): bool
    {
        return self::isTextModel($modelId) && !self::matches($modelId, self::TOOLLESS_MODEL_PATTERNS);
    }

    /**
     * Whether a text model supports JSON Schema structured output.
     *
     * @param string $modelId The model ID.
     * @return bool Whether a JSON schema may be sent.
     */
    public static function supportsJsonSchema(string $modelId): bool
    {
        return self::supportsStructuredOutput($modelId)
            && self::matches($modelId, self::JSON_SCHEMA_MODEL_PATTERNS);
    }

    /**
     * Whether a text model supports JSON Object structured output.
     *
     * @param string $modelId The model ID.
     * @return bool Whether a `json_object` response format may be sent.
     */
    public static function supportsStructuredOutput(string $modelId): bool
    {
        return self::isTextModel($modelId)
            && !self::matches($modelId, self::NO_STRUCTURED_OUTPUT_MODEL_PATTERNS);
    }

    /**
     * Whether a text model accepts non-default sampling parameters.
     *
     * @param string $modelId The model ID.
     * @return bool Whether sampling parameters may be sent.
     */
    public static function supportsSampling(string $modelId): bool
    {
        return self::isTextModel($modelId) && !self::matches($modelId, self::NO_SAMPLING_MODEL_PATTERNS);
    }

    /**
     * Whether a text model accepts an explicit candidate count.
     *
     * @param string $modelId The model ID.
     * @return bool Whether `n` may be sent.
     */
    public static function supportsCandidateCount(string $modelId): bool
    {
        return self::isTextModel($modelId)
            && self::matches($modelId, self::CANDIDATE_COUNT_MODEL_PATTERNS)
            && !self::matches($modelId, self::NO_CANDIDATE_COUNT_MODEL_PATTERNS);
    }

    /**
     * Whether a model is a preview or experimental variant.
     *
     * @param string $modelId The model ID.
     * @return bool Whether the model is a preview.
     */
    public static function isPreview(string $modelId): bool
    {
        return stripos($modelId, 'preview') !== false
            || stripos($modelId, '-exp') !== false
            || stripos($modelId, 'beta') !== false;
    }

    /**
     * Whether a model ID is a dated snapshot.
     *
     * QwenCloud names snapshots either with a full date (`qwen3.7-max-2026-06-08`) or a short
     * `MMDD` (`qwen3.8-max-0902`).
     *
     * @param string $modelId The model ID.
     * @return bool Whether the model is a snapshot.
     */
    public static function isSnapshot(string $modelId): bool
    {
        return preg_match('#-\d{4}(?:-\d{2}-\d{2})?$#', $modelId) === 1;
    }

    /**
     * Resolve the `size` parameter for an image request.
     *
     * @param string|null $orientation `square`, `landscape`, `portrait` or null.
     * @return string The size value to send.
     */
    public static function sizeForOrientation(?string $orientation): string
    {
        $orientation = $orientation === null ? 'square' : strtolower($orientation);

        $override = QwenCloudConfig::env('QWENCLOUD_SIZE_' . strtoupper($orientation));
        if ($override !== '') {
            return $override;
        }

        return self::SIZES_BY_ORIENTATION[$orientation] ?? self::DEFAULT_SIZE;
    }

    /**
     * Order model IDs so the models a user is most likely to want appear first.
     *
     * Not a judgement about which model is best: the goal is that the first entry in the picker is a
     * current flagship rather than something alphabetically lucky.
     *
     * @param string $modelIdA The first model ID.
     * @param string $modelIdB The second model ID.
     * @return int Negative if the first model should sort first, positive otherwise.
     */
    public static function compareModelIds(string $modelIdA, string $modelIdB): int
    {
        if ($modelIdA === $modelIdB) {
            return 0;
        }

        // The configured default is pinned to the top of every picker.
        $preferred = QwenCloudConfig::getDefaultModelId();
        if ($modelIdA === $preferred || $modelIdB === $preferred) {
            return $modelIdA === $preferred ? -1 : 1;
        }

        // Models that can actually do something come before ones with no capability.
        $unsupportedDelta = (int) self::isUnsupported($modelIdA) <=> (int) self::isUnsupported($modelIdB);
        if ($unsupportedDelta !== 0) {
            return $unsupportedDelta;
        }

        // Preview and dated snapshots are rate-limited or short-lived.
        $previewA = self::isPreview($modelIdA) || self::isSnapshot($modelIdA);
        $previewB = self::isPreview($modelIdB) || self::isSnapshot($modelIdB);
        $previewDelta = (int) $previewA <=> (int) $previewB;
        if ($previewDelta !== 0) {
            return $previewDelta;
        }

        // Text generation is the common case; image models are picked explicitly.
        $imageDelta = (int) self::isImageModel($modelIdA) <=> (int) self::isImageModel($modelIdB);
        if ($imageDelta !== 0) {
            return $imageDelta;
        }

        return strnatcasecmp($modelIdA, $modelIdB);
    }

    /**
     * Test a model ID against a list of anchored regular expressions.
     *
     * @param string $modelId The model ID.
     * @param list<string> $patterns The patterns.
     * @return bool Whether any pattern matches.
     */
    private static function matches(string $modelId, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $modelId) === 1) {
                return true;
            }
        }

        return false;
    }
}
