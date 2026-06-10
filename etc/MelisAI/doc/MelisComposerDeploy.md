---
title: MelisComposerDeploy module
package: melisplatform/melis-composerdeploy
doc_type: module-documentation
audience: [users, developers, ai]
language: en
module_version: unversioned
last_reviewed: 2026-06-08
maintainer: Melis Technology
keywords: [composer, deploy, install, update, remove, modules, marketplace, autoload, melis, core, foundation]
screenshots_dir: ./images
---

# MelisComposerDeploy — Functional & Technical Documentation (for AI)

> **What this is.** MelisComposerDeploy lets the platform **run Composer from inside the
> application** — to **install, update or remove modules** programmatically (the engine behind the
> back-office Modules tool / marketplace and the installer). It wraps the real `composer/composer`
> library so module management doesn't require a terminal. It's part of the platform foundation
> (§0) and invisible to end-users.
>
> **Two parts:** **[Part A — Functional Guide](#part-a--functional-guide)** ·
> **[Part B — Technical Reference](#part-b--technical-reference)** (developers/AI, with examples).
> Consumed by the **MelisAI** MCP. **No screenshots** — headless infrastructure. Reviewed 2026-06-08.

---

## 0. The MelisCore platform foundation (this family of modules)

> These modules are the **foundation of the Melis platform** — collectively referred to as
> **"MelisCore"**. *MelisCore* proper is the back-office heart everything depends on; the other
> four are the infrastructure that installs, deploys, serves and migrates the platform.

- **MelisCore** — the **back-office foundation** (login, users/rights, tools framework, dashboard,
  config, events, base services). **Every module depends on it.**
  → [MelisCore doc](../../../melis-core/etc/MelisAI/doc/MelisCore.md)
- **MelisAssetManager** — serves module assets & bundles them; module discovery.
  → [MelisAssetManager doc](../../../melis-asset-manager/etc/MelisAI/doc/MelisAssetManager.md)
- **MelisDbDeploy** — applies database migrations.
  → [MelisDbDeploy doc](../../../melis-dbdeploy/etc/MelisAI/doc/MelisDbDeploy.md)
- **MelisComposerDeploy** *(this module)* — **runs Composer from inside the platform** to
  install/update/remove modules.
- **MelisInstaller** — the first-run installer wizard.
  → [MelisInstaller doc](../../../melis-installer/etc/MelisAI/doc/MelisInstaller.md)

**Dependency note:** MelisComposerDeploy is a low-level standalone tool (depends only on
**composer/composer**); MelisCore's Modules tool / marketplace and the installer drive it, and it
typically runs **MelisDbDeploy** afterwards so new modules' database migrations are applied.

---
---

# PART A — Functional Guide

## A1. What it does for you (invisibly)

You never open MelisComposerDeploy directly — you use it through the back-office **Modules tool /
marketplace**. It is what makes those buttons work:

- **Install a module** from the marketplace → it Composer-downloads the package and wires it in.
- **Update a module** → it pulls the new version.
- **Remove a module** → it uninstalls the package.

So when you add or remove a feature from the back-office without touching a terminal,
MelisComposerDeploy is doing the Composer work behind the scenes (and then MelisDbDeploy applies
any database changes).

---
---

# PART B — Technical Reference

## B1. Metadata & dependencies

| Item | Value |
|---|---|
| Package | `melisplatform/melis-composerdeploy` (module `MelisComposerDeploy`) · namespace `MelisComposerDeploy\` |
| Requires | `composer/composer` (`2.5.8`) — no `melis-core` dependency (standalone tool) |

## B2. Service `MelisComposerService` (with examples)

Wraps the Composer library to perform package operations against the project root:

```php
$composer = $sm->get(\MelisComposerDeploy\Service\MelisComposerService::class);
$composer->setDocumentRoot($projectRoot);
$composer->setDryRun(false);                     // true = simulate without writing

$composer->download('melisplatform/melis-cms-news');   // require/install a package
$composer->update();                                    // composer update
$composer->remove('melisplatform/melis-cms-news');      // remove a package
$composer->dumpAutoload();                              // regenerate the autoloader
```

Methods: `download`, `update`, `remove`, `dumpAutoload`, `setDryRun`/`getDryRun`,
`setDocumentRoot`/`getDocumentRoot`. A factory (`MelisComposerServiceFactory`) builds it with the
right Composer IO/output (custom `ComposerOutputFormatterStyle` for capturing output).

## B3. Where it fits

It is the execution layer under the back-office **Modules tool / marketplace** (MelisCore) and the
**MelisInstaller**. A typical install flow: `MelisComposerService::download()` →
`dumpAutoload()` → **MelisDbDeploy** applies the new module's SQL deltas → the module is active
(MelisAssetManager now serves its assets).

## B4. Quick code map

```
melis-composerdeploy/
├── composer.json                 → dep: composer/composer
├── src/   Service/ (MelisComposerService, MelisComposerServiceFactory, ComposerOutputFormatterStyle)
└── etc/   MarketPlace + MelisAI/doc (this doc)
```

---

*Document for AI consumption (MelisAI MCP) — `melisplatform/melis-composerdeploy`. Part A =
functional; Part B = technical with examples. Part of the MelisCore platform foundation. Last
reviewed 2026-06-08.*
