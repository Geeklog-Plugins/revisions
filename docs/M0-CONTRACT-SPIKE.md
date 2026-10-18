# M0 contract spike — preliminary, not production code

Branch: develop-m0-capture-contract.

Run `php tests/m0-contract-spike.php` (PHP 5.6 syntax; validate on actual supported runtimes separately).

The script is a **standalone, in-memory simulation**, NOT a Geeklog plugin or automated integration test. It demonstrates desired behavior for both providers: unchanged save skipped, no history on simulated failure, previous exact source saved, stale fingerprint rejected, restore archives replaced state.

## Proposed integration boundary

1. The **owning provider** loads and validates the existing source plus incoming edit and permissions.
2. Immediately before mutation, the provider passes old editable source plus stable identity and a concurrency token to a generic pre-write revision adapter.
3. A successful save must commit both provider update and revision atomically, or use a durable recoverable pending/success protocol if the storage engine cannot provide transactions.
4. The owner emits existing post-save events and invalidates caches as today.
5. Restores call the **same owner-controlled save service**, mark cause=restore, and preserve the replaced state; they never update foreign SQL tables directly.

**Open decision:** Existing `PLG_itemPreSave` is a veto-style dispatcher returning errors; simply invoking it before SQL without a durable commit/abort contract is insufficient to guarantee revisions. Avoid claiming that a call to the existing hook solves atomicity. Check MyISAM tables and partial writes.

## Next tasks
- Test actual Core article and Static Pages in a disposable Geeklog 2.2.2 instance.
- Review owner-controlled restore service, source fidelity for Static Pages templating/PHP, sid/sp_id changes, service side effects, item deletion and permissions.
- Choose minimal generic lifecycle contract and formal Core PR only after tests.
- Define persistence transaction/compensation strategy before converting this spike into code.

See [main M0 audit](M0-SAVE-HOOKS-AUDIT.md).
