# dist — GitHub Actions build artifacts

The source repository tracks this directory through README.md. The installable ZIP is generated in `dist/` **during the GitHub Actions run** and uploaded as a workflow artifact, not committed as a binary.

Open [Actions: Build installable Revisions ZIP](https://github.com/hostellerie/revisions/actions/workflows/build-dist.yml), select branch `develop-m0-capture-contract`, choose **Run workflow** if necessary and download the artifact from the completed run. Extract GitHub's outer artifact ZIP and upload the inner `revisions_0.1.0_2.2.2.zip` to Geeklog.

The ZIP contains one top-level `revisions/` directory and is checked for syntax (PHP 5.6, 8.1, 8.3), contents and source-byte identity as requested by the [Memorandum](https://github.com/hostellerie/memorandum/blob/main/plugin-development-guide.md).

**Experimental plugin:** Static Pages baseline tracking needs explicit initialization before editing. PHP and template pages are excluded. No restoration or real Geeklog integration tests yet. Use only on a test instance.
