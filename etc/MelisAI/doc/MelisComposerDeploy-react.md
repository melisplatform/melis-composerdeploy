---
title: MelisComposerDeploy module — React back-office
package: melisplatform/melis-composerdeploy
doc_type: module-documentation-react
audience: [users, developers, ai]
language: en
module_version: unversioned
last_reviewed: 2026-08-19
maintainer: Melis Technology
keywords: [composer, deploy, module install, module update, module remove, infrastructure, react, back-office, autoload, marketplace, foundation]
related_docs: [./MelisComposerDeploy.md]
---

# MelisComposerDeploy (React back-office) — Infrastructure Role Documentation (for AI)

> **What this is.** MelisComposerDeploy has **no React back-office tool and no React UI**:
> there is no `ui-react/` brick source, no `public/ui-react/brick.manifest.json`, no
> `config/react-api.php` and no `config/react.capabilities.php`, and no `src/Controller/`.
> It is a **headless infrastructure module** — the engine that runs Composer from inside the
> platform to **install, update and remove modules**. This document exists so an AI building
> in the React back-office (`/melis-react`) understands the module's **role relative to** the
> React BO and the deployment flow. It does **not** describe a UI, because there is none.
>
> For the full functional/technical detail (service methods, examples, where it fits in the
> platform foundation), see the [legacy doc](./MelisComposerDeploy.md) — this doc does not
> duplicate it.
>
> **How this document is organised — two clearly separated parts:**
> - **[Part A — Functional](#part-a--functional)** — plain language: what the module is and
>   how it relates (indirectly) to the React back-office.
> - **[Part B — Technical](#part-b--technical)** — the real mechanism (classes, config) and
>   its place in the deploy pipeline, with a quick code map.
>
> **Audience**: consumed by the **MelisAI** MCP. **Status**: reviewed 2026-08-19.

---

## 0. Where this lives in the React back-office — read this first

**Nowhere directly.** MelisComposerDeploy does **not** appear in the `/melis-react` sidebar,
has no brick, no route, no `forwardKey`/`melisKey`, and no react-api endpoints. It is invisible
infrastructure, exactly as it is invisible in the legacy back-office.

Its relationship to the React back-office is **indirect**:

- The React back-office ships as **built assets committed inside `melis-core`**
  (`vendor/melisplatform/melis-core/public/ui-react/`), and every module's React **brick** (when
  it has one) ships **built and committed inside that module** (`<module>/public/ui-react/`). In
  this project **all of `vendor/` is committed** and there is **no `composer install` on the
  server** (see `CLAUDE.md` — "pas de composer install serveur"). So on the deployed platform,
  MelisComposerDeploy is **not** what puts React assets in place — git checkout + the CI image
  build do.
- Where MelisComposerDeploy **does** matter is the **runtime "install a module" flow** (the
  back-office Modules tool / marketplace, and the first-run installer). When a module is
  installed that way, MelisComposerDeploy Composer-downloads the package and regenerates the
  autoloader. If that installed module happens to carry a **React brick** (built bundle +
  `brick.manifest.json`), the brick becomes discoverable **once the module is active** — but
  MelisComposerDeploy itself knows nothing about React; it only handles the Composer package.

> **In short:** MelisComposerDeploy is a deployment/packaging engine. Its only link to the React
> BO is that the modules it installs may contain a committed React brick. It never touches,
> builds or serves React assets.

---
---

# PART A — Functional

## A1. What the module is

MelisComposerDeploy lets the platform **run Composer from inside the application** — to
**install, update or remove modules** programmatically, without a terminal. It is the engine
behind the back-office **Modules tool / marketplace** and the installer wizard. It is part of the
platform foundation ("MelisCore" family) and is invisible to end-users.

## A2. It has no React tool / UI

You never open MelisComposerDeploy, in either the legacy back-office **or** the React back-office
(`/melis-react`). There is no page, no menu entry, no form. In the React BO it simply does not
surface — there is nothing to click.

## A3. How it relates to the React back-office (indirectly)

- **It installs modules; modules may carry a React brick.** When you install a module through the
  marketplace, MelisComposerDeploy downloads the Composer package. If that package includes a
  built React brick, the React shell will pick the brick up **once the module is active** (brick
  discovery is a `melis-react-api` concern, not this module's).
- **It does not deploy the React BO itself.** On the deployed platform the React build is already
  present (committed in `melis-core`, shipped by the CI image — no server-side `composer
  install`). MelisComposerDeploy is the *runtime module-management* path, not the *deployment* of
  the pre-built React app.

> Rule of thumb: if a question is about the React UI of a *feature*, it belongs to that feature's
> module doc — not here. This module only concerns Composer package operations.

---
---

# PART B — Technical

## B1. Metadata & dependencies

| Item | Value |
|---|---|
| Package | `melisplatform/melis-composerdeploy` (module `MelisComposerDeploy`) · namespace `MelisComposerDeploy\` |
| Type | `melisplatform-module` · `extra.module-name: MelisComposerDeploy` · `extra.dbdeploy: true` |
| Requires | `php ^8.3\|^8.5`, `composer/composer ^2.9.6` — **no** `melis-core` dependency (standalone tool) |
| React presence | **None** — no `ui-react/`, no `public/ui-react/`, no `config/react-api.php`, no `config/react.capabilities.php`, no `src/Controller/` |

## B2. The real mechanism — `MelisComposerService`

The whole module is essentially one service, aliased `MelisComposerService`
(`config/module.config.php` → `service_manager.aliases`), class
`MelisComposerDeploy\Service\MelisComposerService` (extends `MelisCore\Service\MelisServiceManager`).

It builds a Composer console command string and runs it in-process via Symfony Console
(`Composer\Console\Application` + `StringInput` + `StreamOutput` to `php://output`). The Composer
library it drives is the **vendored copy** at `bin/extracted-composer/` (constant `COMPOSER`),
and output is styled by `ComposerOutputFormatterStyle`.

Public methods (the operations it exposes):

```php
$composer = $sm->get('MelisComposerService'); // alias → MelisComposerService::class

$composer->setDocumentRoot($projectRoot);           // working-dir for composer (defaults to DOCUMENT_ROOT/../)
$composer->setDryRun(true);                          // simulate without writing (--dry-run)

$composer->download('melisplatform/melis-cms-news'); // composer require <pkg>[:version]
$composer->update('melisplatform/melis-cms-news');   // composer update --root-reqs
$composer->remove('melisplatform/melis-cms-news');   // composer remove --no-scripts
$composer->dumpAutoload();                            // composer dump-autoload
```

Notes derived from source:
- **Allowed commands only** (`availableCommands()`): `install`, `update`, `dump-autoload`,
  `require`, `remove`. Anything else returns a "unknown command" translation.
- **Argument hardening** (`buildPackageArg()`): the `package[:version]` string is validated
  against a strict `vendor/name` + semver-ish constraint regex and rejects whitespace, to prevent
  argument injection into the Composer command line.
- Commands run with `--ignore-platform-reqs --no-progress --no-scripts --prefer-dist` and
  `--working-dir=<docRoot>`; `remove` runs `--no-scripts` only.
- No routes, no controller, no events, no view — it is a service invoked by other modules.

## B3. Its place in the deploy pipeline

Callers (not this module) drive it:
- **MelisCore Modules tool / marketplace** — install/update/remove a module at runtime.
- **MelisInstaller** — first-run wizard.

Typical runtime install flow:

```
MelisComposerService::download(pkg)   →  composer require (package downloaded into vendor/)
        ↓
MelisComposerService::dumpAutoload()  →  autoloader regenerated (new classes visible)
        ↓
MelisDbDeploy                          →  applies the new module's SQL deltas (extra.dbdeploy: true)
        ↓
module active                          →  MelisAssetManager serves its assets;
                                          if the module ships a React brick, the React shell
                                          discovers it (via melis-react-api /react-modules)
```

The last arrow is the **only** contact point with the React back-office, and it is passive:
MelisComposerDeploy makes the module present and active; React brick discovery is handled entirely
by `melis-react-api` / the module's own committed brick — not by this module.

> ⚠ On the deployed dev/test environments this project does **not** run `composer install` on the
> server (all `vendor/` is committed and the CI image is rebuilt). There, MelisComposerDeploy is
> the *runtime marketplace* engine, not the mechanism that ships the pre-built React app.

## B4. Quick code map

```
melis-composerdeploy/
├── composer.json                        → type melisplatform-module; requires composer/composer ^2.9.6
├── src/
│   ├── Module.php                       → getConfig() merges module.config + diagnostic.config; StandardAutoloader
│   ├── MelisComposer.php                → (helper)
│   └── Service/
│       ├── MelisComposerService.php     → THE engine: download/update/remove/dumpAutoload (Composer console in-process)
│       ├── ComposerOutputFormatterStyle.php  → styles captured composer output
│       └── Factory/MelisComposerServiceFactory.php
├── config/
│   ├── module.config.php                → alias MelisComposerService → MelisComposerService::class
│   └── diagnostic.config.php
├── bin/extracted-composer/              → the vendored composer/composer library it drives
└── etc/MelisAI/doc/
    ├── MelisComposerDeploy.md           → legacy full doc (cross-linked)
    └── MelisComposerDeploy-react.md     → this infrastructure-role doc

  (No ui-react/, no public/ui-react/, no react-api.php, no react.capabilities.php, no Controller/)
```

---

*Document for AI consumption (MelisAI MCP) — React-back-office role of
`melisplatform/melis-composerdeploy`. This module has **no React tool/UI**; it is headless
infrastructure whose only link to the React BO is that the modules it installs may carry a
committed React brick. Full detail: [./MelisComposerDeploy.md](./MelisComposerDeploy.md). Last
reviewed 2026-08-19.*
