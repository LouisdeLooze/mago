<p align="center">
  <a href="https://askmago.com"><img src="docs/images/mago-lockup.svg" alt="Mago" width="360"></a>
</p>

<h1 align="center">Ask Mago anything. Or tell it what to do.</h1>

<p align="center">
  <strong>The free, open source AI admin assistant for Magento 2 and Mage-OS.</strong><br>
  Ask what sold best this week and get a live answer — or tell it to change a setting, and it does, after your OK.
</p>

<p align="center">
  <a href="https://askmago.com">askmago.com</a> ·
  <a href="#installation">Install</a> ·
  <a href="#what-you-can-ask">What you can ask</a> ·
  <a href="docs/skills-architecture.md">Build an addon</a>
</p>

<p align="center">
  <img alt="Free for merchants" src="https://img.shields.io/badge/price-free-FF7A33">
  <img alt="License MIT" src="https://img.shields.io/badge/license-MIT-373330">
  <img alt="Magento 2.4.9+" src="https://img.shields.io/badge/Magento-2.4.9%2B-373330">
  <img alt="Mage-OS 3+" src="https://img.shields.io/badge/Mage--OS-3.0%2B-373330">
  <img alt="PHP 8.2+" src="https://img.shields.io/badge/PHP-8.2%2B-373330">
</p>

---

## Meet Mago

Running a Magento store means living in the admin panel: digging through reports, hunting for
that one configuration setting, flushing the cache after every deploy. Mago puts a chat window
in your admin and does that work with you.

Type a question in plain language and Mago answers with **live data from your own store** —
revenue, best sellers, customers, configuration, content. Tell it to change something and it
**does it for you**, but only after you've confirmed. It streams its answer as it works, shows
numbers as stat cards, charts and tables where that helps, and never touches anything your
admin role doesn't allow.

<p align="center">
  <img src="docs/images/mago-chat.png" alt="Mago chat in the Magento admin: a best-sellers question answered with a bar chart, followed by a settings change waiting for confirmation" width="640">
</p>

### Why merchants like it

- **Free, forever.** Mago is open source under the MIT licence — no licence fee, no seats, no subscription. You only pay your AI provider for what you use, and Mago keeps its prompts small so a typical action costs a few cents.
- **Your AI, your key.** Bring your own Anthropic or OpenAI key (or Gemini, Azure, DeepSeek, Ollama and more). Requests go straight from your store to your provider — there is no Mago server in between.
- **Nothing changes without your OK.** Reading is instant; every write asks first. Irreversible actions such as cancelling an order or issuing a refund show their impact before you confirm.
- **Follows your admin roles.** Read and write access run through Magento's own ACL, so every admin sees exactly what their role allows.
- **Answers you can act on.** Stat cards, charts, tables and record lists, plus deep links straight into the admin page you need.
- **Grows with the community.** Agencies and module vendors add new skills as addons, without touching core.

### What you can ask

| Ask a question… | …or give an instruction |
|---|---|
| "What were our best sellers this week?" | "Set the free shipping threshold to €75" |
| "How did revenue compare to last month?" | "Flush the cache" (`/cache flush` works too) |
| "Which customers ordered more than three times?" | "Reindex the catalog" |
| "Where do I change the tax display setting?" | "Write a product description for SKU LT-2201" |
| "Which products have no image?" | "Disable the Christmas CMS block" |
| "How do I set up a cart price rule?" *(answered from the official docs)* | "Cancel order #100004521" *(shows impact, asks to confirm)* |

See [docs/skills-examples.md](docs/skills-examples.md) for more example prompts per skill.

---

## Features

- **Natural language chat** in the Magento admin panel
- **Real-time streaming** responses via SSE
- **Built-in skills** for sales analytics, store configuration, content management, and admin navigation
- **Slash commands** — `/cache flush`, `/cache clean <type>`, `/index status`, `/index reindex` run directly against Magento, no AI round-trip
- **Extensible architecture** — third-party modules can register custom skills via DI
- **ACL-based permissions** — read/write access controlled per admin role
- **Write confirmation** — every write asks first; irreversible actions (cancel, refund, delete) show their impact and need an explicit acknowledgement, several writes in one turn become a tick list
- **Answer widgets** — stat cards, charts, tables and record lists in the answer where the data allows it (see [docs/ui-components.md](docs/ui-components.md))
- **Write confirmation** — destructive actions always require explicit user approval
- **Form access** — reads and stages field changes on the admin form open in the browser
  (including unsaved edits), but never saves: changes are only staged into the form's own fields,
  same as typing them in, and the administrator's own Save click is what persists anything
- **Multi-provider** — Anthropic, OpenAI, Azure, Google Gemini, DeepSeek, Hugging Face, OpenRouter, Ollama and LM Studio, through [MageOS_AiBase](https://github.com/mage-os-lab/module-ai-base)
- **Documentation grounding** — answers admin how-to questions from Magento/Adobe Commerce docs, fetched into your database (optional)

## Requirements

- PHP >= 8.2
- Magento >= 2.4.9 or Mage-OS >= 3.0 (Symfony 7.3+ required by the AI bridges)
- An API key for one of the supported providers

## Installation

Anthropic and OpenAI are included out of the box. For other providers (Azure, Gemini, DeepSeek,
Ollama, LM Studio, etc.) install the matching Symfony AI bridge — see `composer.json` suggests.

```bash
composer require mago-assistant/mago
bin/magento module:enable MageOS_AiBase MagoAssistant_Mago
bin/magento setup:upgrade
```

## Configuration

First add a provider under `Stores > Configuration > Mage-OS > AI Configuration`: pick the
backend, paste the API key, choose a model, and use **Test Connection** to check it answers.

Then, under `Stores > Configuration > Mago Assistant`:

1. **Enable** the module (General)
2. **Pick the AI Service** the assistant runs on (API Settings). Leave it on *Automatic* to use
   the first usable one, which is what a single-provider store wants.

### Internal API URL (Docker / reverse proxy setups)

If the module's internal REST API calls fail (e.g. in Docker environments where PHP can't reach itself via the public hostname), configure `Stores > Configuration > Mago Assistant > API Settings > Internal API URL`.

Examples:
- markshust/docker-magento: `https://app:8443`
- DDEV: `https://ddev-<project>-web:443`
- Leave empty to use the store's base URL (works for most setups)

These calls verify the TLS certificate by default. If the internal URL points at a host whose certificate cannot match (loopback addresses, container hostnames, self-signed certificates), set `Verify TLS Certificate` to No. Keep it enabled in production.

## Built-in Skills

| Skill area | Tools | Access |
|------------|-------|--------|
| Store Analytics | `sales_data`, `product_data`, `customer_data` | Read |
| Store Configuration | `config_reader`, `config_writer`, `cache_manager`, `indexer_manager` | Read / Write |
| Content Management | `cms_data`, `content_generator` | Read / Write |
| Navigation | `admin_navigator` | Read |
| Inventory | `stock_level` (without MSI), `stock_level_msi` (with MSI) | Read |
| Documentation | `docs_search` | Read |
| Form Access | `page_form` | Read / Stage (never saves) |

See [docs/skills-examples.md](docs/skills-examples.md) for example prompts per skill and [docs/skills-roadmap.md](docs/skills-roadmap.md) for the full roadmap of planned skills.

### Slash commands

Typing `/` in the chat shows the available commands. These run without the AI provider and reply instantly:

| Command | Does |
|---|---|
| `/cache flush` | Flush all caches |
| `/cache clean <type> [type...]` | Clean specific cache types |
| `/cache status` | List cache types and their status |
| `/index list` | List all indexers |
| `/index status` | Show indexer status |
| `/index reindex [indexer_id...]` | Reindex all indexers, or only the given IDs |
| `/help` | List the commands you may use |

Write commands need the `MagoAssistant_Mago::assistant_write` ACL resource plus a write grant on the underlying skill. See [docs/skills-architecture.md](docs/skills-architecture.md#slash-commands) for registering your own commands.

The answer widgets and skill cards the chat panel renders are documented in [docs/ui-components.md](docs/ui-components.md); [docs/ui-components-examples.md](docs/ui-components-examples.md) shows every component with sample data and the call behind it.
`page_form` reads the admin form currently open in the browser and can stage new field values for
the administrator to confirm — it never writes to the database itself, only into the same fields
the administrator would type into, so their own Save button is what persists anything. See
[docs/form-access.md](docs/form-access.md) for what it can see, which forms are excluded, and the
directive contract it uses to reach the browser.

## Documentation grounding

When enabled, the assistant can answer "how do I…" questions from the official Magento admin documentation instead of guessing. The `docs_search` skill runs a MySQL FULLTEXT search over an indexed copy of the docs and cites the source page it used.

### Configuration

`Stores > Configuration > Mago Assistant > Documentation`

| Field | Config path | Default | Purpose |
|---|---|---|---|
| Enable documentation grounding | `mago/docs/enabled` | `0` | Master switch; also gates the sync cron |
| Source repository | `mago/docs/source_repo` | `mage-os/mirror-commerce-admin.en` | GitHub `owner/repo` to index. Default is the MIT-licensed Mage-OS mirror of Adobe's Commerce Admin docs |
| Source branch / commit | `mago/docs/ref` | `main` | Branch or commit to index; pin to a commit for reproducibility |
| Source | `mago/docs/source` | `github_public` | Where the repo is hosted and how it is read: `github_public`, or `github_app` for a **private** repo (see below) |
| GitHub App ID | `mago/docs/github_app_id` | — | Numeric App ID (GitHub App mode only) |
| GitHub App private key | `mago/docs/github_private_key` | — | The App's PEM private key, stored **encrypted** (GitHub App mode only) |
| GitHub App installation ID | `mago/docs/github_installation_id` | — | Optional; auto-discovered from the source repo when blank |
| Describe what the documentation contains | `mago/docs/describe_enabled` | `0` | Turn on to tell the assistant what your own docs cover (instead of assuming official Magento docs) |
| Documentation description | `mago/docs/description` | — | Free-text description of the corpus, woven into the `docs_search` tool so the model knows when to search it |
| Results per search | `mago/docs/top_k` | `5` | Max doc pages returned per search |
| Sync schedule | `mago/docs/cron_expr` | `0 4 1 * *` (monthly) | Cron expression for the re-index job |

### How the corpus is built

A cron job (`mago_docs` group, its own process) indexes the docs into the `mago_doc` table (~5 MB). To index immediately instead of waiting for the cron:

```bash
bin/magento mago:docs:index --force
```

Sync is cheap to run often because it is **change-detected by the ref's commit SHA**: an unchanged source repo costs a single small API call and skips the download entirely. This is why the default schedule can safely be raised when you feed docs that update more often than the Mage-OS mirror. On a real change, the whole ref is downloaded **once as a tarball** (`GET /repos/{repo}/tarball/{ref}`) rather than fetching each file individually — dramatically faster than the old per-file approach (the default mirror is ~620 files). The tarball is streamed to a temp file and parsed a block at a time, so peak memory stays around 12 MB even though the mirror's archive is ~170 MB. Every `help/**.md` is read from it (including `_includes/`, needed to resolve `{{$include}}` partials), Experience League markup is normalized to plain text, and the table is swapped in a single transaction — a failed sync keeps the previous corpus, and searches keep answering from the old corpus until the new one is committed. Only one sync runs at a time: a second invocation (cron overlapping a manual run, or a double-started command) reports `another sync is already running` and exits instead of interfering.

### Why MySQL FULLTEXT (not embeddings)

Retrieval uses a MySQL FULLTEXT index rather than vector embeddings so the feature works on any Magento install with **zero extra infrastructure** — no vector database, no embeddings service, and no dependency on a specific AI provider (Anthropic, for one, has no embeddings API). For a bounded, well-structured doc corpus this keyword search is accurate enough, and the assistant compensates for the lack of semantic matching by issuing multiple searches with different terms when the first result set is thin. Semantic/vector retrieval can be added later as an optional backend without changing the skill contract.

### Custom docs

The pipeline is source-repo agnostic: point `Source repository` at any GitHub repo of Adobe Experience League-flavored (or plain) markdown to ground the assistant on your own documentation. Public repos need no credentials; private repos are read via a GitHub App (below). Other Git hosts plug in by implementing `Api/Docs/DocsSourceInterface` and registering it in the `DocsSourcePool` in `etc/di.xml`; it then appears in the **Source** dropdown.

When you point it at your own docs, turn on **Describe what the documentation contains** and write a one-line **Documentation description** of the corpus (e.g. "our internal warehouse and fulfilment runbooks"). By default the assistant is told the docs are the official Magento admin docs; the description replaces that framing in the `docs_search` tool so the model knows what the docs cover and searches them for the right questions.

### Private repositories via a GitHub App

To index a **private** repo, authenticate as a [GitHub App](https://docs.github.com/en/apps) instead of a personal token — org-managed, scoped to just the repos you install it on, and using short-lived tokens rather than a long-lived secret.

**One-click setup (recommended).** In `Stores > Configuration > Mago Assistant > Documentation`, set **Source** to *GitHub App* and click **Create GitHub App on GitHub**. This uses GitHub's [App Manifest flow](https://docs.github.com/en/apps/sharing-github-apps/registering-a-github-app-from-a-manifest): GitHub opens with the App pre-configured (Contents: read-only), and when you click *Create GitHub App* it sends you back here with the **App ID** and **private key** already filled in and saved (the key encrypted). You are then taken to GitHub to **install** the App on your private repository. Finally run `bin/magento mago:docs:index --force`. To create the App under an organization, type the org login into the field next to the button before clicking.

**Manual setup.** If you already have (or prefer to create) an App by hand:

1. Create a GitHub App (org or personal) with **Repository permissions → Contents: Read-only**. No webhook is needed.
2. Generate a **private key** on the App's page and download the `.pem`.
3. **Install** the App on the account/org and grant it access to the source repository.
4. Set **Source** to *GitHub App*, then paste the **App ID** and **private key** (stored encrypted). Leave **installation ID** blank to auto-discover it from the source repo, or set it to force a specific installation.
5. Run `bin/magento mago:docs:index --force` to index immediately.

Under the hood the sync signs a short-lived RS256 JWT with the App's key, exchanges it for an installation access token, and reads the repo through the authenticated GitHub Contents API. Tokens are minted per sync run and never stored.

## Extending with Custom Skills

Third-party modules can register tools by implementing `ToolInterface` and adding them to the `ToolRegistry` via `di.xml`. No core modifications needed.

See [docs/skills-architecture.md](docs/skills-architecture.md) for the full architecture reference, including:

- How the skill/tool system works
- Step-by-step guide for building custom skills
- ACL and permissions model
- Data & privacy details
- MCP compatibility roadmap

## Testing

End-to-end tests run with Playwright against Chromium. No test calls a real provider: the
chat panel is covered with browser-level SSE stubs, the backend with WireMock standing in for
the provider endpoint.

```bash
cd Test/End-2-end
npm install && npx playwright install chromium
BASE_URL="https://your-store.test/" npx playwright test
```

See [Test/End-2-end/README.md](Test/End-2-end/README.md) for configuration, the WireMock setup
and how to add scenarios.

## Permissions

| ACL Resource | Grants |
|---|---|
| `MagoAssistant_Mago::config` | Module configuration access |
| `MagoAssistant_Mago::assistant_read` | Read-only tools (analytics, config reading, navigation) |
| `MagoAssistant_Mago::assistant_write` | Write tools (config changes, CMS, content generation) |

## Data & privacy

Mago stores nothing outside your Magento installation. Every request goes directly from your
store to the AI provider you configured, using your own API key; what that provider does with
the request is governed by its API terms. Mago only reads what the current admin's role allows
and never writes without an explicit confirmation. Details in
[docs/skills-architecture.md](docs/skills-architecture.md#data--privacy).

## Community

Mago is built in the open with developers, community builders and backers from the Dutch
Magento ecosystem. Issues and pull requests are welcome at
[github.com/mago-assistant](https://github.com/mago-assistant); brand assets and press
material live at [askmago.com/brand.html](https://askmago.com/brand.html).

## License

MIT
