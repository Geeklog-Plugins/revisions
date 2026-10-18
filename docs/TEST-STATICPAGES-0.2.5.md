# Revisions 0.2.5 — installation and acceptance checklist

This guide supersedes the historical [0.1.1 pilot steps](TEST-STATICPAGES-0.1.1.md). Use a Geeklog 2.2.2 test site with a database backup.

## Upgrade

1. Install the pre-release ZIP `revisions_0.2.5_2.2.2.zip` from [GitHub Releases](https://github.com/hostellerie/revisions/releases/tag/v0.2.5).
2. When upgrading an existing installation, click **Update** under Geeklog Plugins.
3. Confirm matching **Data: 0.2.5** and **Code: 0.2.5**.
4. Verify that earlier archived revisions are still present.

## Automatic tracking

Revisions automatically establishes a tracking baseline for existing **eligible ordinary Static Pages** at installation and upgrade. New pages begin on their first save. PHP and template pages are excluded.

To confirm history generation, edit a page that had already been enrolled, save it, and open **Revisions → Pages with revisions**. It should appear only if a previous state has actually been archived. A page that merely has a baseline but no revisions is intentionally absent from that index.

## Browse and inspect

1. Open **Pages with revisions**. Confirm titles are sorted alphabetically and each page shows its revision count.
2. Select **View revisions** to open **History** filtered to the chosen page.
3. Confirm revision numbering starts at **#1 per page**, independently from internal database IDs.
4. Select a revision. Archived and current title/meta description should appear above the archived content.
5. **Visual preview** is selected by default. Inspect the page markup inside the sandboxed iframe; scripts and Geeklog autotags must not run. Remote resources may be unavailable.
6. Switch to **HTML source — copy and paste** to select the exact saved HTML markup.
7. Expand **Revision history**, **Changed lines**, and **Current version** individually; all are collapsed by default and long content scrolls.

## What not to infer from passing tests

- Earlier changes cannot be recovered if made before the baseline was initialized.
- Saving a new page for the first time starts its baseline; it does not create an invented previous version.
- The post-save hook does not guarantee capture of every write path or safe concurrent editing.
- There is **no automatic restore**, binary-media backup, or article history in 0.2.5.
- A GitHub Actions success establishes PHP syntax and packaging/documentation gates, **not** a complete behavioral, ACL or recovery audit.

Log any discrepancy with the precise route, content type, relevant revision ID, and sanitized error.log entry.
