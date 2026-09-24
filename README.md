# AI Provider for QwenCloud

[QwenCloud](https://www.qwencloud.com/) as a provider for the WordPress AI Client: text, vision and
text-to-image generation with Qwen, DeepSeek, Kimi and GLM models through the OpenAI-compatible
`compatible-mode` API.

## What it does

* Fetches the model list live from `GET /compatible-mode/v1/models`, so newly released models show up
  on their own.
* Text generation and chat history, including tool calling, structured output (JSON Schema and JSON
  Object) and stop sequences.
* Vision input for the Qwen3.5+ commercial models, the Qwen-VL / Qwen2.5-VL / QVQ families, the Omni
  models and `deepseek-v4.1-flash`, so features like Alt Text Generation work.
* Text-to-image generation with the `qwen-image-3.0` and `qwen-image-3.0-pro` models, returned as a
  URL.
* Reasoning output surfaces as thought parts instead of being mixed into the answer.
* Custom QwenCloud parameters — `enable_thinking`, `thinking_budget`, `top_k`, `enable_search` and
  friends — pass through `customOptions`.

## Requirements

* WordPress 7.0 or newer, with the PHP AI Client SDK (bundled in WordPress 7.0, or provided by the AI
  plugin)
* PHP 7.4 or newer
* A QwenCloud account with API access

## Install

Download the zip from [Releases](../../releases) and upload it through **Plugins → Add New → Upload
Plugin**, or copy the plugin folder to `wp-content/plugins/ai-provider-for-qwencloud/`. Activate it,
then open **Settings → Connectors**, open the QwenCloud card and paste your API key.

## Configuration

The API key is managed by WordPress: either save it on **Settings → Connectors** or define
`QWENCLOUD_API_KEY` in the environment. This plugin does not read the connector option directly —
whether a credential exists is asked of the AI Client registry.

> QwenCloud's own examples use `DASHSCOPE_API_KEY`, because the API is DashScope-compatible. This
> plugin follows the WordPress AI Client convention and derives the variable name from its provider
> ID, so the key it looks for is **`QWENCLOUD_API_KEY`**. Define that name, or use the Connectors
> screen.

Optional settings are environment variables or PHP constants:

| Setting | Default | Purpose |
| --- | --- | --- |
| `QWENCLOUD_BASE_URL` | `https://maas.qwencloudapi.com/compatible-mode/v1` | API base URL; set it for a regional or Token Plan endpoint |
| `QWENCLOUD_DEFAULT_MODEL` | `qwen3.7-plus` | Chat model to prefer in pickers and feature filters |
| `QWENCLOUD_IMAGE_MODEL` | `qwen-image-3.0-pro` | Image model to prefer in the image generation feature |
| `QWENCLOUD_STRUCTURED_OUTPUT` | `json_schema` | `json_schema`, `json_object`, or `none` |
| `QWENCLOUD_SIZE_SQUARE` / `QWENCLOUD_SIZE_LANDSCAPE` / `QWENCLOUD_SIZE_PORTRAIT` | `1024x1024` / `1664x928` / `928x1664` | Override the `size` sent per orientation |
| `QWENCLOUD_REQUEST_TIMEOUT` | `120` | Model list and text request timeout, seconds |
| `QWENCLOUD_IMAGE_REQUEST_TIMEOUT` | `600` | Image request timeout, seconds |
| `QWENCLOUD_CONNECT_TIMEOUT` | `10` | Connection timeout, seconds |
| `QWENCLOUD_USER_AGENT` | `ai-provider-for-qwencloud/<version>` | Optional User-Agent override |

There is no database-backed settings page: the base URL and the rest are deployment-level overrides.

Because the plugin has no settings of its own, changing an environment variable does not invalidate
the AI Client's cached model list. If a new value does not take effect, run `wp cache flush`.

To change which model the AI plugin picks for its features, use the standard filter — this plugin
already puts its preferred models first:

```php
add_filter( 'wpai_preferred_text_models', function ( $models ) {
    array_unshift( $models, array( 'qwencloud', 'qwen3.8-max' ) );
    return $models;
} );
```

## Model capabilities

The `/models` endpoint reports no capability data, so the live list is classified through a local
catalog (`QwenCloudModelCatalog`). The rules are:

* **Text** — the Qwen3.x and Qwen3-* families, `qwen2.5-*`, the legacy `qwen-*` families, and the
  documented DeepSeek, Kimi and GLM models.
* **Text + image** — the Qwen3.5–3.8 `max`/`plus`/`flash` models, the Qwen-VL / Qwen2.5-VL / QVQ
  families, the Omni models, and `deepseek-v4.1-flash`.
* **Image generation** — only `qwen-image-3.0` and `qwen-image-3.0-pro`.
* Everything else — video, audio, embeddings, reranking, world models, the decision model and image
  translation — stays in the list with **no capability**, so it is visible but never selected.

## Not implemented

* **Video, audio, embeddings, reranking and the World Model APIs.** The AI Client has no unified
  interface for them, so those models are listed without a capability rather than shipping code the
  SDK never calls.
* **Image editing.** Only text-to-image (`POST /images/generations`) is implemented; the image
  editing endpoints take a different request shape and are not part of the AI Client's reference
  image flow.
* **Streaming and the Responses API.** Only synchronous Chat Completions is used.

## Data and privacy

Prompts you send to the AI, plus whatever the calling plugin or theme adds to them (system
instructions, conversation history, tool definitions, a JSON schema, attached files), are sent to
QwenCloud. Your API key is stored on your own site and is only ever sent to the QwenCloud host
configured above. Nothing is sent until your site actually asks the AI Client for a generation, and
the plugin never logs prompts, tokens, cookies or Authorization headers.

## QwenCloud documentation

* [First API call](https://docs.qwencloud.com/developer-guides/getting-started/first-api-call)
* [OpenAI-compatible chat API](https://docs.qwencloud.com/api-reference/chat/openai-chat)
* [Choose models](https://docs.qwencloud.com/developer-guides/getting-started/model-selection)
* [Structured output](https://docs.qwencloud.com/developer-guides/text-generation/structured-output)
* [Thinking](https://docs.qwencloud.com/developer-guides/text-generation/thinking)
* [OpenAI-compatible image generation](https://docs.qwencloud.com/api-reference/image-generation/qwen-text-to-image-openai)

## Development

```
php scripts/selfcheck.php                                              # logic checks, no WordPress needed
php scripts/selfcheck.php --sdk=/path/to/php-ai-client                 # plus live-SDK checks
```

The self-check asserts model classification, sort order, the declared capabilities and options, the
request shapes for chat and images, and that the declared metadata satisfies the requirements the AI
plugin's features actually send. It needs neither WordPress nor an API key.

Release: push a tag matching the plugin version (`git tag <version> && git push origin <version>`).
The [release workflow](.github/workflows/release.yml) verifies the tag against `Version:`/`Stable
tag:`, verifies a matching changelog entry exists, builds the plugin zip and publishes it.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

The `assets/images/qwencloud.svg` mark is a generic icon used only to identify the service this
plugin connects to.

The full WordPress plugin readme, including the changelog, lives in [readme.txt](readme.txt).
