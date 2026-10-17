# dist — Automatically generated installable ZIP

On each relevant push to `develop-m0-capture-contract`, GitHub Actions validates the plugin and **commits the generated ZIP and its SHA256SUMS into this tracked directory**:

- [Download revisions_0.1.9_2.2.2.zip from dist](https://github.com/hostellerie/revisions/raw/refs/heads/develop-m0-capture-contract/dist/revisions_0.1.9_2.2.2.zip)
- [Build workflow](https://github.com/hostellerie/revisions/actions/workflows/build-dist.yml)
- [Checksums](SHA256SUMS) (once first build has succeeded)

The workflow also uploads a GitHub Actions artifact. Its generated commit is made with the GitHub Actions bot and `[skip ci]`. The push trigger watches plugin source paths rather than `dist/**`, preventing build loops. Pull requests validate packages but do not commit binaries.

Checks include PHP syntax for 5.6/8.1/8.3, exact single `revisions/` ZIP root, required files, no development files, and byte-for-byte comparison with source files. See [Memorandum](https://github.com/hostellerie/memorandum/blob/main/plugin-development-guide.md).

**Important:** the link to the ZIP will work only **after a successful initial run**. If Actions permissions prevent the bot from pushing, review the workflow's logs and repository Actions settings (workflow write permissions and branch protection).

**Experimental version 0.1.9**: manual baseline initialization of simple Static Pages only; no restoration. No real Geeklog installation validated; use a disposable test site.
