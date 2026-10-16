# Revisions — Roadmap Geeklog 2.2.2

**Status:** planned; no implementation yet. **Target:** Revisions 1.0.0. Updated 2026-10-16.

## Scope
Automatic revision history, comparison and safe restoration for Geeklog articles (story) and Static Pages (staticpages). Not a trash bin, backup system, media archive, or Geeklog's existing story expiration archive. Versions created before installation are unavailable unless recovered from external backups.

## Binding engineering rules
Follow [Memorandum](https://github.com/hostellerie/memorandum), especially [plugin development](https://github.com/hostellerie/memorandum/blob/main/plugin-development-guide.md), [runtime policy](https://github.com/hostellerie/memorandum/blob/main/geeklog-runtime-support-policy.md), [source-field mutation contract](https://github.com/hostellerie/memorandum/blob/main/plugin-source-field-audit-mutation-contract.md), [content interoperability](https://github.com/hostellerie/memorandum/blob/main/plugin-content-interoperability-contract.md), [plugin capability contract](https://github.com/hostellerie/memorandum/blob/main/plugin-capability-contract.md), [multisite](https://github.com/hostellerie/memorandum/blob/main/multisite-development-principles.md), [persistent storage](https://github.com/hostellerie/memorandum/blob/main/plugin-persistent-storage-guide.md), [shared-files upgrade safety](https://github.com/hostellerie/memorandum/blob/main/plugin-shared-files-upgrade-safety.md), [admin UX](https://github.com/hostellerie/memorandum/blob/main/plugin-admin-ux-guidelines.md), and [manifest](https://github.com/hostellerie/memorandum/blob/main/plugin-metadata-manifest.md).

Baseline: Geeklog 2.2.2 minimum, PHP syntax compatible with PHP 5.6.40; test actual PHP 5.6, 8.1 and 8.3 runtimes. Source content stays owned and validated by its provider. No direct cross-plugin UPDATE of stories/staticpage, no theme-specific patches, no fragile SQL triggers, no data inside disposable cache. Compatible with staggered multisite upgrades.

## Milestones

### M0 — Audit save paths and API contract (blocking)
- [ ] Audit exact Geeklog 2.2.2 story and Static Pages save paths: editor, submissions, cloning, services/API, imports, scheduled writes, deletion, change of ID.
- [ ] Prove whether before-save/after-save lifecycle hooks expose previous editable source reliably; distinguish a post-save notification from actual pre-save interception.
- [ ] Trace provider-managed write validation, authorization, cache/index invalidation and events needed for restoration.
- [ ] Record coverage matrix, concurrency behavior and gaps. If a safe generic capture/mutation contract does not exist, propose minimal reusable Core/Static Pages API changes, with tests, rather than a workaround.
**Gate:** tested pre-change capture and provider-mediated restoration design for both types.

### M1 — Native installable plugin and storage (0.1.0)
- [ ] Install/enable/disable/upgrade/uninstall lifecycle, configuration, navigation, native permissions and EN/FR translations; version metadata and installable ZIP.
- [ ] Own revision table indexed by provider, content type, stable item ID, sequential revision ID, timestamp UTC, actor, reason, schema version, content fingerprint, snapshot and size.
- [ ] Snapshot *editable source*, not rendered HTML. Define fields for story title, intro/body, SEO metadata, editorial settings; staticpages title, stored page content, formatting, SEO and editorial settings.
- [ ] Explicitly exclude secrets, transient counters and unrelated records. Preserve original bytes/markup and autotags.
- [ ] Keep revision data in site-scoped SQL, never cache directories. Make migration idempotent and non-destructive; document uninstall policy.
**Gate:** reproducible install, upgrade, snapshot read/write and data preservation.

### M2 — Automatic version capture (0.2.0)
- [ ] Capture before successful mutation or equivalent atomic design without losing old value; ensure failed saves do not generate phantom revisions.
- [ ] Skip identical source using fingerprint without mutating source; detect concurrency, duplicates and retries.
- [ ] Cover the actual supported save paths of story and staticpages; log failures safely without leaking private text.
**Gate:** automated integration tests for normal, failed, concurrent and repeat saves for both providers.

### M3 — History and comparison (0.3.0)
- [ ] Responsive native admin history view with item, date, actor, version list, pagination and contextual help.
- [ ] Safely escaped source diffs: never execute historical PHP, HTML/JS or autotags in comparison.
- [ ] Strict permission checks at each request, separate view/restore/manage permissions, CSRF for mutations; never publicly index revision pages.
**Gate:** authorized editors can inspect versions; unauthorized users cannot access content or metadata.

### M4 — Safe restoration (0.4.0)
- [ ] Compare and confirm; snapshot current state before restoring.
- [ ] Restore through provider-owned validation/save lifecycle only, never through direct updates to foreign tables.
- [ ] Verify optimistic locking/current fingerprint, authorization, CSRF and error rollback; preserve canonical content ID/URL and policy-controlled editorial fields.
- [ ] Test cache invalidation, search/SEO notifications and media references. Restoring references must not imply restoring deleted binary assets.
**Gate:** round-trip restoration for articles and Static Pages with no loss of replaced version.

### M5 — Retention, quality and release (1.0.0)
- [ ] Configuration by provider, default retention cap, optional age limit, safe preview/confirmation for purge; document retention/privacy and orphan handling.
- [ ] Test shared-files staggered upgrades, isolated databases, cache cleanup, backup/restore, reinstall, disable, missing providers.
- [ ] CI lint/tests across supported PHP versions; ACL/XSS/CSRF, large/multilingual content, race conditions, malformed snapshot, failure recovery, exact source fidelity.
- [ ] EN/FR manuals, changelog, package checks, installation test, pilot validation before public release.
**Gate:** demonstrable installation, capture, comparison, restoration, cleanup and migration for BOTH content providers.

## Later / out of scope 1.0.0
Trash/recover deleted items, retroactive history, asset binary backups, public diff publishing, bulk rollback and other provider types (Forum, FAQ, Documents, MediaGallery). Expand only via the generic provider-owned contract, not direct SQL coupling.

## Stop condition
Do not claim a complete V1 if reliable pre-save capture or owner-managed restoration cannot be demonstrated for both story and Static Pages. Track any necessary upstream Core/Static Pages API work explicitly.

Tracking: https://github.com/hostellerie/todo

## M0 — Résultat d'audit statique (16 octobre 2026)

[Rapport détaillé](docs/M0-SAVE-HOOKS-AUDIT.md). Geeklog v2.2.2 appelle PLG_itemSaved() **après** DB_save() des articles et des pages statiques. PLG_itemPreSave() existe mais n'est pas invoqué dans les deux chemins étudiés. Une capture depuis itemsaved seule ne peut donc pas garantir l'ancien contenu ; prévoir un contrat avant mutation fourni par le propriétaire. Les pages statiques peuvent ensuite recalculer sp_content pour la recherche. **M0 reste bloqué** tant qu'un prototype sur installation réelle et le contrat de restauration n'ont pas été validés.
