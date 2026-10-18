# M0 — SQL engines, locks and recovery: design decision

Date: 2026-10-16. **Static code verification only** against Geeklog-Core/geeklog tag v2.2.2; production deployments may have changed table engines. No core patch and no integration test yet.

## Source evidence

- Core install schema: sql/mysql_tableanddata.php defines stories (lines 270–316), topic_assignments (375–386) and article_images (14–19) as ENGINE=MyISAM.
- Static Pages install schema: plugins/staticpages/sql/mysql_install.php (table ending line 84) defines staticpage as ENGINE=MyISAM.
- Article::saveToDatabase (system/classes/article.class.php around 879–894): DB_save(stories), TOPIC_saveTopicSelectionControl, possibly delete old SID and submission. The service sends PLG_itemSaved after save.
- Static Pages service (plugins/staticpages/services.inc.php around 639–724): DB_save(staticpage), topics, old-ID cleanup, post-save notification and possible derived-search updates.
- MyISAM does not provide multi-statement transactional rollback. A Revisions table using InnoDB would not retroactively make provider MyISAM writes atomic.

## Design choice for next phase (proposed, NOT validated)

**Prefer a durable per-save intent + an owner-controlled save adapter**, preserving existing MyISAM data. Use an own SQL table for prepared operations and committed snapshots. Do not modify the story/staticpage SQL directly from Revisions.

Suggested operation states:
- prepared: old editable snapshot + old fingerprint saved durably, owner write not yet confirmed.
- committing: owner save started (optional); never equate this state with success.
- committed: owner save and expected final stored state verified; historic old snapshot visible.
- aborted: owner save not applied and this is verifiable.
- needs_review: current state absent/unexpected, partial side effects possible, or ambiguous recovery outcome.

**Non-negotiable invariants:**
1. Capture previous content before owner overwrites it, including source-format fidelity; no history for viewing, abandoned edits or effective no-op saves.
2. Save operations must serialize on stable provider + ID, or use a provider-supported compare-and-swap operation under the same lock. A mere PHP fingerprint comparison is not a concurrency guarantee.
3. Never silently decide success after crash based solely on equality of hashes: verify provider return, normalized stored fields and related changes, and classify indeterminate outcomes as needs_review.
4. Store idempotent operation IDs with a unique index, durable intent and error states; no clearing unresolved records as cache. Compare incoming/editable fields *after* provider sanitization when selecting expected final fingerprint.
5. On restore, current source becomes new history and selected source is passed to provider save. Preserve current owner/ACL/hits/IDs by default. Refresh topic assignments, search cache, feeds and other owner-controlled effects.
6. Do not expose private snapshot data publicly. Separate permissions for viewing/restoration/maintenance, CSRF confirmation, safe diffs and audit trails.

## Locking alternatives requiring investigation

- A **site DB named advisory lock** keyed by an unambiguous, hashed (provider,type,item-ID) namespace could coordinate participating writers. Verify DB driver features, timeout and connection semantics; existing Geeklog or external writers **that do not acquire the same lock remain a race**.
- A **provider-owned serialized edit service** is preferable if it covers all write paths; check and update expected fingerprint inside its critical section.
- Migrating all related tables to InnoDB solely for Revisions is **not the V1 default**. Such migration would require separate Core-level analysis of compatibility, upgrades, backup and operations and still need provider business-logic atomicity.

## Concrete tests required

- Query live/staging SHOW TABLE STATUS / SHOW CREATE TABLE to confirm actual engines (never assume install schema matches deployment).
- Save an article with topics/images and a Static Page with topic assignment/template; inject interruptions after each durable step.
- Parallel sessions on the same object: both normal saves and simultaneous restore/edit; rejected write must not change current source.
- Failed owner validation, failed DB_save, changes in filtering, old-SID and old-sp_id handling, disabled provider, orphaned intent.
- Demonstrate that recovery cannot cause duplicate revisions, overwrite a newer edit or resurrect deleted media automatically.
- Repeat Geeklog 2.2.2 tests on PHP 5.6, 8.1 and 8.3 where supported.
- Evaluate server database advisory locking, without assuming it is supported, and instrument a diagnostic when unavailable.

## Decision gate

**M0 remains OPEN.** Do not implement an unconditional SQL-level interception, claim crash-safety, release an archive, or merge into Core before validating owner integration, locking and ambiguous crash recovery on disposable Geeklog 2.2.2.

Sources:
- https://github.com/Geeklog-Core/geeklog/blob/v2.2.2/sql/mysql_tableanddata.php
- https://github.com/Geeklog-Core/geeklog/blob/v2.2.2/plugins/staticpages/sql/mysql_install.php
- https://github.com/Geeklog-Core/geeklog/blob/v2.2.2/system/classes/article.class.php
- https://github.com/Geeklog-Core/geeklog/blob/v2.2.2/plugins/staticpages/services.inc.php
