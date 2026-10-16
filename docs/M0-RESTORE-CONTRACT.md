# M0 — Restoration contract for Geeklog articles and Static Pages

**Status:** architecture spike, not integrated or validated on a Geeklog installation.

## Confirmed owner entry points (official Geeklog v2.2.2)

- Article: `service_submit_story($args, &$output, &$svc_msg)` in `system/lib-article.php`; delegates validation/loading to the article class, then `saveToDatabase()`, then `PLG_itemSaved()`. The article class applies GLText filtering before writing and has particular handling for changed IDs, attachments, topics, permissions and submissions.
- Static Pages: `service_submit_staticpages($args, &$output, &$svc_msg)` in `plugins/staticpages/services.inc.php`; checks `staticpages.edit`, page settings and `staticpages.PHP`, sanitizes and saves, notifies `PLG_itemSaved()`, and in some cases stores generated search content in `sp_content`.

These entry points demonstrate where owner-controlled restoration should be explored. **Their current argument/return contracts have not been proven sufficient for restoring every field safely.**

## Proposed restore transaction

1. Authorize user to view history, restore revisions and **edit the current provider item**. Confirm CSRF, item still exists, no unsafe public revision endpoint.
2. Load the chosen immutable snapshot and validate snapshot schema, provider, content type, item ID, checksum and permitted fields. Reject foreign IDs or corrupted snapshots.
3. Load current **editable** provider state, not generated search HTML. Compute a server-side concurrency fingerprint and compare with fingerprint shown at preview/confirmation.
4. Show escaped source diff and confirm explicitly. Never execute stored PHP, JS or autotags while previewing.
5. Translate snapshot fields into the provider's canonical **edit submission** preserving the original item identity and current nonversioned fields (ownership, permissions, counters) unless deliberately selected.
6. Call the provider's existing validation and save route with an explicit `cause=restore` context so that restoring generates a **new** history entry for the replaced state.
7. Confirm success, refresh caches/derived data and current edit view, log actor/timestamp/previous and restored revision IDs without logging private content.
8. If owner save fails, return a clear error and leave content/history consistent. This requires atomic storage or a durable compensating/commit protocol; it is **not proven** for current MyISAM deployment.

### Provider considerations

**Article:** do not change SID during restore; require verified article.edit ACL; decide whether to restore intro/body/title/meta/structured type or optional editorial flags and topics. Article save path filters HTML and updates image references, topics and feeds; restoration cannot recreate deleted image files. Use current field values for counters, creation date, owner and permissions by default.

**Static Pages:** preserve sp_id; respect staticpages.edit and separate PHP permissions; distinguish source `page_data`, `sp_content`, template/PHP generated search content before selecting restore fields. Must check template descendants, cache invalidation and topic links. The existing save service may change content via sanitization, and a restored historical source may no longer pass current permissions or filters.

### Concurrency and error policy

If current fingerprint differs from preview, **abort** and request a new review. If source and target match, return unchanged without generating a duplicate. Never silently convert failed edit into successful restoration. Do not mark a restore successful just because the provider emitted a post-save event.

## Test artifact

[tests/m0-restore-contract.php](../tests/m0-restore-contract.php) is an in-memory PHP 5.6-style simulation with tests for both provider types: normal restore, denied permission, stale state, failed save, exact source recovery, reversibility and repeated restore.

Run locally with `php tests/m0-restore-contract.php`. **Passing this script does not establish Geeklog integration, CSRF coverage, SQL atomicity, provider ACL or real field fidelity.**

## Next acceptance gates

- [ ] Execute PHP lint and both M0 scripts in PHP 5.6, 8.1 and 8.3.
- [ ] On a disposable Geeklog 2.2.2 installation, verify service `gl_edit` and `gl_svc` formats, security/CSRF boundaries, detailed fields and actual return behavior.
- [ ] Verify source preservation in Static Pages templates/PHP and changing HTML filters.
- [ ] Select transactional or durable compensation strategy for history+content; do not rely on MyISAM rollback.
- [ ] Design minimal, generic before-commit event/adapter; add actual integration tests, then consider Core/Static Pages PR.
- [ ] Test cache, topics, SEO, feed, images, template descendants and concurrency on pilot data.

**No deploy, release or production-ready claim.**
