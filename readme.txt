=== AI Provider for QwenCloud ===
Contributors:      bestony
Tags:              ai, connector, qwen, qwencloud, artificial-intelligence, vision
Requires at least: 7.0
Tested up to:      7.1
Stable tag:        1.0.0
Requires PHP:      7.4
License:           GPL-2.0-or-later
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

QwenCloud provider for the WordPress AI Client: text, vision and image generation with Qwen, DeepSeek, Kimi and GLM models.

== Description ==

Adds [QwenCloud](https://www.qwencloud.com/) as a provider for the WordPress AI Client, using its
OpenAI-compatible `compatible-mode` API.

* The model list is fetched live from `GET /compatible-mode/v1/models`, so newly released models
  appear on their own.
* Text generation and chat history, including tool calling, structured output (JSON Schema and JSON
  Object) and stop sequences.
* Vision: the Qwen3.5+ commercial models, the Qwen-VL / Qwen2.5-VL / QVQ families, the Omni models
  and `deepseek-v4.1-flash` accept images, so features like Alt Text Generation work.
* Image generation with `qwen-image-3.0` and `qwen-image-3.0-pro`, returned as a URL.
* Reasoning output is surfaced as thought parts rather than mixed into the answer text.
* Custom QwenCloud parameters such as `enable_thinking`, `thinking_budget`, `top_k` and
  `enable_search` pass through `customOptions`.

The default endpoint is:

https://maas.qwencloudapi.com/compatible-mode/v1

The default models are `qwen3.7-plus` for text and vision, and `qwen-image-3.0-pro` for image
generation.

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/ai-provider-for-qwencloud/`.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to Settings → Connectors, open the QwenCloud card and paste your API key — or define it outside
   the database (see Configuration).

A QwenCloud account with API access is required. Create a key at
[https://home.qwencloud.com/api-keys](https://home.qwencloud.com/api-keys).

== Configuration ==

The API key is managed by WordPress: either save it on Settings → Connectors, or define the
`QWENCLOUD_API_KEY` environment variable or PHP constant. The plugin asks the AI Client registry
whether a credential is configured; it never reads the connector option itself.

Note: QwenCloud's own examples use `DASHSCOPE_API_KEY`, because the API is DashScope-compatible. This
plugin follows the WordPress AI Client convention and derives the variable name from its provider ID,
so the variable it looks for is `QWENCLOUD_API_KEY`. Define that name, or use the Connectors screen.

All optional settings are environment variables or PHP constants:

* `QWENCLOUD_BASE_URL` — API base URL. Default: `https://maas.qwencloudapi.com/compatible-mode/v1`.
  Set it for a regional or Token Plan endpoint.
* `QWENCLOUD_DEFAULT_MODEL` — chat model to prefer in pickers and feature filters.
  Default: `qwen3.7-plus`.
* `QWENCLOUD_IMAGE_MODEL` — image model to prefer in the image generation feature.
  Default: `qwen-image-3.0-pro`.
* `QWENCLOUD_STRUCTURED_OUTPUT` — how JSON response requests are shaped. One of:
  * `json_schema` (default) — sends the JSON schema wrapped the way QwenCloud documents it.
  * `json_object` — asks only for valid JSON, without the schema.
  * `none` — sends no `response_format` at all.
* `QWENCLOUD_SIZE_SQUARE` / `QWENCLOUD_SIZE_LANDSCAPE` / `QWENCLOUD_SIZE_PORTRAIT` — override the
  `size` sent per orientation. Defaults: `1024x1024` / `1664x928` / `928x1664`.
* `QWENCLOUD_REQUEST_TIMEOUT` — model list and text request timeout in seconds. Default: `120`.
* `QWENCLOUD_IMAGE_REQUEST_TIMEOUT` — image request timeout in seconds. Default: `600`.
* `QWENCLOUD_CONNECT_TIMEOUT` — connection timeout in seconds. Default: `10`.
* `QWENCLOUD_USER_AGENT` — optional User-Agent override.

There is no database-backed settings page: the base URL and the rest are deployment-level overrides.
Because the plugin has no settings of its own, changing an environment variable after the AI Client
has cached its model list does not invalidate that cache. If a new value does not take effect
immediately, clear the cache — in WP-CLI:

    wp cache flush
    wp transient delete --all

To influence which models the AI plugin picks, use the standard filters in your own plugin or theme —
this plugin already puts its preferred models first:

    add_filter( 'wpai_preferred_text_models', function ( $models ) {
        array_unshift( $models, array( 'qwencloud', 'qwen3.8-max' ) );
        return $models;
    } );

== Frequently Asked Questions ==

= Does this plugin work without the PHP AI Client? =

No. It requires the PHP AI Client SDK, which is provided by WordPress 7.0 or by the AI plugin. The
provider stays silent when the SDK is missing.

= Which models are used for text and vision? =

The plugin prefers `qwen3.7-plus`, a 1M-context hybrid-thinking model that also accepts images. The
model list is fetched live, and capabilities are derived from the model ID, so newer Qwen3.x models
are picked up automatically. Set `QWENCLOUD_DEFAULT_MODEL` to prefer a different one.

= Which models can generate images? =

`qwen-image-3.0` and `qwen-image-3.0-pro`. Other image families (Wan, Z-Image) use a different
protocol and are declared with no capability, so they never appear as selectable image models.

= Why is there no video or audio support? =

The AI Client has no unified interface for video, audio, embedding or reranking models, so those
models stay in the list with no capability rather than shipping code the SDK never calls.

= Why is there no image editing? =

Only text-to-image (`POST /images/generations`) is implemented. QwenCloud's image editing endpoints
take a different request shape and are not part of the AI Client's reference-image flow.

= Image generation fails with an invalid size =

The plugin sends `1664x928` for landscape and `928x1664` for portrait. If your model rejects them,
override with `QWENCLOUD_SIZE_LANDSCAPE` / `QWENCLOUD_SIZE_PORTRAIT` / `QWENCLOUD_SIZE_SQUARE`.

= A JSON feature fails with a 400 on response_format =

That feature asks for a JSON schema response. Try `QWENCLOUD_STRUCTURED_OUTPUT=json_object` to ask
for plain JSON instead, or `none` to send no `response_format` at all — the prompt still asks for
JSON.

= Where is my API key stored? =

In the WordPress options table on your own site, under the AI Client's connector option
(`connectors_ai_qwencloud_api_key`), or in an environment variable or PHP constant if you set one. It
is never transmitted anywhere except to the QwenCloud API when fulfilling a request.

= What data leaves my site? =

Only what you send to the AI: your prompts, and whatever the calling plugin or theme adds to them
(system instructions, conversation history, tool definitions, a JSON schema, or attached files). See
External services below for the exact endpoints.

== External services ==

This plugin connects to the QwenCloud API, an external service operated by Alibaba Cloud. It is
required so the WordPress AI Client can send requests to QwenCloud models from your site. QwenCloud
is a paid service: requests are billed to your QwenCloud account, and an account with API access is
required.

The plugin contacts the following endpoints under the base URL — `https://maas.qwencloudapi.com/compatible-mode/v1`
by default, or whichever base URL is set with `QWENCLOUD_BASE_URL`:

* `GET /models` — called when the AI Client refreshes its list of available models, and when it checks
  whether your credentials work. No user content is sent; only your API key, so QwenCloud can return
  the models available to your account.
* `POST /chat/completions` — called whenever any plugin or theme on your site uses the WordPress AI
  Client to generate text or to analyze an image. The request carries your API key and the prompt:
  messages, system instruction, tool definitions, and any other parameters the calling code supplied
  (conversation history, a JSON schema for structured output, or images attached to the prompt).
* `POST /images/generations` — called when something on your site asks the AI Client to generate an
  image. The request carries your API key and the image prompt.

No request is made until something on your site asks the AI Client for a generation, or the AI Client
refreshes its model list. Your API key is stored on your own site and is only ever sent to the base
URL configured above.

This service is provided by Alibaba Cloud:

* Platform and documentation: [https://www.qwencloud.com/](https://www.qwencloud.com/)
* API documentation: [https://docs.qwencloud.com/](https://docs.qwencloud.com/)
* First API call: [https://docs.qwencloud.com/developer-guides/getting-started/first-api-call](https://docs.qwencloud.com/developer-guides/getting-started/first-api-call)

== Changelog ==

= 1.0.0 =
* Initial release: text generation, chat history, tool calling, structured output, vision input and
  text-to-image generation with QwenCloud models.

== Upgrade Notice ==

= 1.0.0 =
Initial release.
