# Revisions — Geeklog content version history

**Current pre-release: 0.2.5** · **Geeklog 2.2.2+** · **PHP 5.6.40+**

Revisions is an experimental Geeklog plugin that automatically tracks eligible **Static Pages**, archives earlier editable content when tracked pages are saved, and lets administrators inspect and copy previous versions. It demonstrates the concept of editorial revision history; it is **not yet a full backup or restoration system**.

## What works in 0.2.5

- Automatic initialization of eligible existing Static Pages at installation and upgrade, without resetting previously tracked baselines. New pages begin tracking on their first save.
- Archive of a previous stored editorial snapshot when a tracked page changes via supported Geeklog item-saved hooks; identical snapshots do not create new revisions.
- Administration sections: Overview, Pages with revisions, History, and Help.
- Alphabetical list of pages **that have actual archived revisions**, with per-page counts and links to filtered history.
- Per-page version numbering, distinct from internal global revision IDs.
- Revision detail showing archived/current title and meta description, historical HTML, an isolated **Visual preview** (selected by default), and a **HTML source — copy and paste** tab.
- Collapsible history, changed lines and current content sections.
- A read-only baseline consistency check; no automatic restoration.

The visual preview is rendered inside a sandboxed iframe with scripts disabled, no Geeklog autotag processing, and blocked external network resources. Its appearance may differ from the live page. Copyable HTML is the saved source, not a generated rendering.

## Installation / update

1. Download the latest experimental package from [GitHub Releases](https://github.com/hostellerie/revisions/releases) or the development branch's [dist](dist/) folder.
2. Install the ZIP using the normal Geeklog plugin installation process, or update the existing plugin files and select **Update** in the Geeklog Plugins administration.
3. Check that **Data** and **Code** versions both show **0.2.5**.
4. Open **Revisions → Overview**, then **Pages with revisions** or **History**.
5. Edit and save an eligible Static Page; if its baseline existed before the edit, an archived earlier version should appear.

Existing revision records are retained on normal version upgrades. Make a backup of your site/database before installing pre-release software.

## Limitations and safety

- Only ordinary eligible Static Pages are supported. PHP and template-based pages are excluded. Geeklog articles are **not supported yet**.
- No history can be reconstructed from before initial enrollment; the first save of a new/untracked page establishes a baseline.
- Capture uses the **post-save** callback and an earlier stored baseline. It is **not** a guaranteed atomic pre-save capture across every writing path; concurrency, failures, alternate save paths and complete recovery still require work.
- No automatic restoration, binary-media backup, or recovery of deleted assets.
- Revision inspection is restricted to administrators with the required rights and current-page ACL. Validate authorization and operational behavior before enabling on a production site.
- Uninstall and data-retention behavior still require further hardening and testing.

## Development

- [Roadmap and status](ROADMAP.md)
- [Latest installable ZIP and checksums](dist/)
- [Historic 0.1.1 test procedure](docs/TEST-STATICPAGES-0.1.1.md) (archived, not current instructions)
- [Geeklog save hooks audit](docs/M0-SAVE-HOOKS-AUDIT.md)
- [Memorandum development guidelines](https://github.com/hostellerie/memorandum/blob/main/plugin-development-guide.md)

Source header author: **Ben hostellerie DOT org AT gmail DOT com**. License: GPL v2 or later (see [LICENSE](LICENSE)).

## Pre-release status

0.2.5 is a concept preview, validated by GitHub Actions syntax checks on PHP 5.6, 8.1 and 8.3 and a reported manual installation test on Geeklog 2.2.2. These checks do **not** establish production readiness or complete recovery guarantees.
