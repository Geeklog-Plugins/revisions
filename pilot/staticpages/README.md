# Revisions — Static Pages save pilot (staging only)

**Goal:** quickly verify that modifying an existing Static Page preserves its **previous editorial fields** in SQL. This is an opt-in development harness, **NOT** an installable Geeklog plugin. Do not deploy directly to production.

## Preconditions

1. Full verified SQL backup of the staging site's database and copies of changed files.
2. Geeklog 2.2.2 on a disposable/staging site, PHP 5.6-compatible code, Static Pages plugin enabled.
3. A unique site's DB and correct prefix; do not reuse other sites' revision data. Confirm InnoDB support.
4. Verify the deployed Static Pages `services.inc.php` matches the official Geeklog 2.2.2 save sequence; changes to this file require re-review.

## Install

1. Create table from [schema.sql](schema.sql), replacing `gl_` with the site's actual table prefix. For a non-default prefix, add its mapping to the site's `$_TABLES` registration in the deployed test environment:

       $_TABLES['revisions_sp_pilot'] = $_DB_table_prefix . 'revisions_sp_pilot';

   Use the site's actual Geeklog prefix configuration. **Never set a global hardcoded prefix for multisite.**

2. Place `capture.php` outside the public web root at `<Geeklog private path>/plugins/revisions/pilot/capture.php`.
3. In **the staging copy only** of `plugins/staticpages/services.inc.php`, insert immediately before the existing `DB_save($_TABLES['staticpage'], ...)`:

       require_once $_CONF['path'] . 'plugins/revisions/pilot/capture.php';
       $revsp_operation = REVSP_prepare(!empty($sp_old_id) ? $sp_old_id : $sp_id);

4. After the complete save sequence (including old-ID relocation and the derived search update, but BEFORE the service's final successful return), insert:

       REVSP_finish($revsp_operation, $sp_id);

   This second call must run **only on the success path**. Ensure it appears after all provider-side modifications to the source, including any derived source fields. Do not add it after an error return.

5. Test with a non-sensitive test page; change only its title or content and click Save.

## Inspect the archive

Read-only SQL (adapt prefix):

    SELECT revision_id, sp_id, current_sp_id, actor_id, status, created_at
    FROM gl_revisions_sp_pilot ORDER BY revision_id DESC LIMIT 20;

    SELECT snapshot_json FROM gl_revisions_sp_pilot
    WHERE status='committed' ORDER BY revision_id DESC LIMIT 1;

Expected: first change to an existing page stores its **previous** source in snapshot_json; a no-op save becomes `unchanged`. Opening, previewing or abandoning edit creates no row. New page creation does not create a revision. A `prepared` row indicates incomplete or failed operation and must NOT be treated as a valid revision.

## Important limitations

- This harness is intentionally not wired to the official plugin install/upgrade lifecycle. It requires a small **local staging patch**, and is not production-ready.
- `DB_query()` success is not equivalent to completion of all SQL/asset side effects, particularly with MyISAM. Concurrency/locking, crashes, normalizing content, modifications made outside the Static Pages save service and interrupted partial saves are NOT comprehensively handled.
- It captures the **previous database row's selected fields**. For template/PHP Static Pages, `sp_content` may contain derived search content rather than the original editable source. **Do not test these page types or restore snapshots** until a source-specific adapter is implemented.
- Current implementation omits page topic assignments, permissions, attachments and binary media. It is a capture-only pilot. Never restore snapshots automatically or use it on valuable content.
- Adding the mapping in a suitable bootstrapped Geeklog location is site-specific. The test will fail closed or log error if the mapping/table is absent.
- Revert the local patch and table mapping to uninstall the pilot; **retain** the table until archived test data is reviewed/backed up.

## Acceptance checklist

- [ ] PHP lint on the capture file and patched services file.
- [ ] Open editor and cancel: no row.
- [ ] Change content and save: `committed` snapshot contains old source.
- [ ] Save unchanged: `unchanged` entry (no usable revision).
- [ ] Introduce controlled validation failure: no committed revision.
- [ ] Check private-page protections, HTML/autotags and multilingual content.
- [ ] Confirm no cross-site writes in a two-site staging configuration.
- [ ] Compare behavior on PHP 5.6 and 8.1/8.3 before recommending deployment.

See the main repository [ROADMAP.md](../../ROADMAP.md) and [M0 audit](../../docs/M0-SAVE-HOOKS-AUDIT.md).
