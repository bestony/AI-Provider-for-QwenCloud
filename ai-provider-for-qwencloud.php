<?php
/**
 * Plugin Name:       AI Provider for QwenCloud
 * Plugin URI:        https://github.com/bestony/AI-Provider-for-QwenCloud
 * Description:       QwenCloud provider for the WordPress AI Client.
 * Requires at least: 7.0
 * Requires PHP:      7.4
 * Version:           1.0.0
 * Author:            Bestony
 * Author URI:        https://github.com/bestony
 * License:           GPL-2.0-or-later
 * License URI:       https://spdx.org/licenses/GPL-2.0-or-later.html
 * Text Domain:       ai-provider-for-qwencloud
 *
 * @package QwenCloud\AiProvider
 */

declare(strict_types=1);

namespace QwenCloud\AiProvider;

use QwenCloud\AiProvider\Provider\QwenCloudProvider;
use QwenCloud\AiProvider\Util\QwenCloudConfig;
use WordPress\AiClient\AiClient;

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/src/autoload.php';

/**
 * Loads the plugin's translations.
 *
 * @return void
 */
function load_textdomain(): void
{
    load_plugin_textdomain(
        'ai-provider-for-qwencloud',
        false,
        dirname(plugin_basename(__FILE__)) . '/languages'
    );
}

add_action('init', __NAMESPACE__ . '\\load_textdomain');

/**
 * Registers the provider with the AI Client.
 *
 * Runs on `init` priority 5: WordPress core builds the Connectors entries at priority 10 from
 * whatever providers are in the registry, so a later priority means no Connectors card (and no
 * place for the user to paste an API key).
 *
 * @return void
 */
function register_provider(): void
{
    if (!class_exists(AiClient::class)) {
        return;
    }

    $registry = AiClient::defaultRegistry();

    if ($registry->hasProvider(QwenCloudProvider::class)) {
        return;
    }

    $registry->registerProvider(QwenCloudProvider::class);
}

add_action('init', __NAMESPACE__ . '\\register_provider', 5);

/**
 * Puts the configured QwenCloud model first in a preference list.
 *
 * The AI plugin's defaults are Anthropic/Google/OpenAI, none of which the user may have configured.
 * Existing entries from other providers are preserved. Only an entry for the exact same provider and
 * model is removed, so a user who added a different QwenCloud model elsewhere keeps it.
 *
 * @param mixed $preferredModels List of `[provider_id, model_id]` tuples.
 * @param string $defaultModel The model ID to put first.
 * @return array<int, array{string, string}>
 */
function prefer_qwencloud_model($preferredModels, string $defaultModel): array
{
    $preferredList = is_array($preferredModels) ? array_values($preferredModels) : [];

    // Without a credential the provider never yields candidates, so there is nothing to prioritise.
    if (!QwenCloudConfig::hasCredentials() || $defaultModel === '') {
        return $preferredList;
    }

    $preferred = [[QwenCloudConfig::PROVIDER_ID, $defaultModel]];
    foreach ($preferredList as $entry) {
        if (!is_array($entry) || count($entry) < 2) {
            continue;
        }
        $entry = array_values($entry);
        if (!is_string($entry[0]) || !is_string($entry[1])) {
            continue;
        }
        if (QwenCloudConfig::PROVIDER_ID === $entry[0] && $defaultModel === $entry[1]) {
            continue;
        }
        $preferred[] = [$entry[0], $entry[1]];
    }

    return $preferred;
}

/**
 * Prefers the configured chat model for text and vision features.
 *
 * @param mixed $preferredModels List of `[provider_id, model_id]` tuples.
 * @return array<int, array{string, string}>
 */
function prefer_qwencloud_text_models($preferredModels): array
{
    return prefer_qwencloud_model($preferredModels, QwenCloudConfig::getDefaultModelId());
}

/**
 * Prefers the configured image model for the image generation feature.
 *
 * The chat flagship cannot generate images, so the image feature needs its own entry: without it the
 * feature would try the chat model first and fail with "no model supports image generation".
 *
 * @param mixed $preferredModels List of `[provider_id, model_id]` tuples.
 * @return array<int, array{string, string}>
 */
function prefer_qwencloud_image_models($preferredModels): array
{
    return prefer_qwencloud_model($preferredModels, QwenCloudConfig::getImageModelId());
}

add_filter('wpai_preferred_text_models', __NAMESPACE__ . '\\prefer_qwencloud_text_models');
add_filter('wpai_preferred_vision_models', __NAMESPACE__ . '\\prefer_qwencloud_text_models');
add_filter('wpai_preferred_image_models', __NAMESPACE__ . '\\prefer_qwencloud_image_models');
