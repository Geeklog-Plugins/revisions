# Editor lifecycle and missing-media preview — M0 design

**Status:** demonstration only. The standalone [test](../tests/m0-editor-lifecycle.php) simulates both article and Static Pages workflows; it does not hook into Geeklog or change its editor.

## Revision creation decision

- Opening the editor: **no revision**.
- Editing a form in the browser, then leaving or clicking Cancel: **no revision**.
- Clicking Save without an effective change to the persisted editable fields: **no revision**.
- A rejected or failed save: **no committed revision**, no false success report.
- Confirmed successful save with an actual changed source: **previous stored state archived once**.
- Restoring an old version: a provider save with cause=restore; preserve the state being replaced.

Do not use browser autosave as revision history. Autosave/recovery, if added later, must have separate retention and privacy semantics.

**Atomicity remains unresolved** with existing storage paths; the simulation must not be interpreted as guaranteeing real MyISAM consistency.

## Missing media in admin revision preview

- Keep original source references intact: article `[image1]`, MediaGallery/autotags, plugin-managed and external references.
- Resolve media availability using the owning provider and its permissions; do not guess paths from user-controlled strings or disclose private paths.
- If definitively absent, render a fixed, locally bundled neutral placeholder in the **administrator's preview only**, labelled in the user's language e.g. "Image indisponible". Show sanitized logical media ID/filename if authorized.
- If availability cannot be determined (remote network blocked, provider absent, restricted permission), show an **unknown/unverified** state, not "missing".
- Do not replace the media reference in the version snapshot, and do not inject placeholders into the restored published content automatically.
- Before restoration, display warnings listing unresolved/missing media; require informed confirmation when loss of display fidelity is foreseeable.
- Prevent HTML/script injection through filenames, captions or media URLs. Preview historical HTML as escaped source, not executable content.

## Test and acceptance backlog

- [ ] Hook into confirmed effective save (including editor and service APIs) for articles and Static Pages.
- [ ] Validate save success vs validation failure, duplicate saves and abandon on a disposable Geeklog instance.
- [ ] Confirm MediaGallery and Geeklog image reference resolution, ACL and media deletion cases.
- [ ] Test preview visual placeholder, localization, keyboard accessibility and no publication-side mutation.
- [ ] Verify fallback when image provider missing or remote availability cannot be checked.
