# M0 — Recoverable revision intent: design spike

Status: **simulation only**. [PHP script](../tests/m0-recoverable-intent.php) models two providers with in-memory maps. This document is NOT a proof of MyISAM consistency or a working Geeklog integration.

## Proposed state machine
1. Verify provider ACL, validate content and identify the persisted **editable** source; check pre-save fingerprint.
2. Write a **durable intent** (unique operation ID, provider, stable item ID, old snapshot, old/new fingerprints, schema version, actor, status=prepared) before calling owner save.
3. Invoke the **existing provider-owned save path**, preserving original content identity and using the provider's normal validation. Do not directly UPDATE stories/staticpage from Revisions.
4. Reconcile actual current provider source:
   - equal expected new hash: finalize history once (committed);
   - equal previous hash: mark operation aborted; never report it as a completed revision;
   - any third state, record missing, or unverifiable source: flag needs_review; do **not** restore or overwrite automatically.
5. On a crash between owner-save and finalize, a protected recovery command revisits prepared intents. Idempotent unique operation IDs prevent repeated revisions. Expiring/cleaning intents requires explicit safe policy, not deletion by cache cleanup.
6. Restoring a revision follows the same process, with cause=restore. The original current source becomes the new historical snapshot; the requested snapshot becomes current only after owner save succeeds.

## Important limitations / unresolved invariants

- A two-record intent pattern is **not atomic** across MyISAM. A process can fail after writing an intent and before the provider write, or during a multi-table provider operation. Reconciliation can classify clear cases but **cannot prove** all side effects committed (images, topics, search caches, templates, events).
- If another writer changes the source between last read and write, a fingerprint checked only in PHP is **not a lock**. A real per-item lock, CAS supported by the owner, or transaction-aware adapter is required.
- Owner validation can normalize source (HTML filtering, Static Pages PHP/template behavior), so the new hash must represent **actual final stored editable source**. A hash of pre-validation form input is insufficient.
- Never auto-replay unknown operations or declare success solely by matching a hash. Persist provider-save evidence, check related state and expose an operator review path.
- Proper design must decide InnoDB migration/transactions versus durable intent with provider-owned lock and recoverable side effects, with staged multisite upgrades protected.
- A successful intent commit and a successful provider save are distinct. Failure handling must not silently mask an edit or leak source to logs.

## Acceptance before production
- [ ] Audit DB_save semantics and table engines, and provider secondary writes.
- [ ] Confirm source representation, expected hashes and provider edit API.
- [ ] Test crash injection before/after intent, during provider save, after save, during finalize, and during restore.
- [ ] Verify concurrent updates are serialized or rejected, including two admin sessions.
- [ ] Test provider ACL/CSRF and media side effects on disposable Geeklog 2.2.2, PHP 5.6/8.1/8.3.
- [ ] Document recovery workflow and migration rollback; no silent purge of unresolved intents.

**Recommendation:** treat this as a study of failure cases, not implementation approval. Prefer one generic owner-controlled lifecycle with transaction/locking guarantees over piling bespoke patches onto providers.
