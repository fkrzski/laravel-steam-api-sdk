# CONTRIBUTING

Contributions are welcome, and are accepted via pull requests.
Please review these guidelines before submitting any pull requests.

This project follows the [Contributor Covenant Code of Conduct](CODE_OF_CONDUCT.md). By participating, you are expected to uphold this code.

## Process

1. Fork the project
2. Create a new branch
3. Code, test, commit and push
4. Open a pull request detailing your changes. Please follow the [template](.github/PULL_REQUEST_TEMPLATE.md).

## Guidelines

* Please ensure the coding style running `composer lint`.
* Send a coherent commit history, making sure each individual commit in your pull request is meaningful.
* You may need to [rebase](https://git-scm.com/book/en/v2/Git-Branching-Rebasing) to avoid merge conflicts.
* Please remember that we follow [SemVer](http://semver.org/).

## Changelog

`CHANGELOG.md` follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
New entries go under `## [Unreleased]`; keep them uniform:

* **Fixed section order:** Added, Changed, Deprecated, Removed, Fixed, Security. Omit the ones with no entries.
* **Start with the subject, not a verb.** Describe the state after the change — "Request classes are grouped into per-interface subnamespaces", not "Reorganize request classes".
* **One sentence for the change, at most one more for the reason or the consequence.** Anything longer belongs in the docs.
* **Name classes exactly as they exist in `src/`,** in backticks — `GetPlayerBansRequest`, not `GetPlayerBans`.
* **Prefix breaking changes with `**BC break.**`** and say what callers must update.
* **Put the issue link last, in parentheses:** `([#20](https://github.com/fkrzski/php-steam-api-sdk/issues/20))`.
* **No prose paragraph under a version heading** — the sections carry everything.
* **Collapse dependency bumps into a single line** rather than one per package.

## Setup

Clone your fork, then install the dev dependencies:
```bash
composer install
```
## Lint

Lint your code:
```bash
composer lint
```
## Tests

Run all tests:
```bash
composer test
```

Check types:
```bash
composer test:types
```

Unit tests:
```bash
composer test:unit
```

## Test impact analysis

Local runs only re-run the tests your changes actually reach and replay the rest
from a cached dependency graph. Nothing to enable — it is on by default, and the
first run after a change records the graph.

Force a full run when you want one:
```bash
vendor/bin/pest --no-tia
```

Rebuild the graph if it ever looks stale:
```bash
vendor/bin/pest --tia --fresh
```

After a fresh clone, pull master's graph instead of recording your own (needs
the `gh` CLI, authenticated):
```bash
vendor/bin/pest --tia --baselined
```

CI passes `--ci`, which turns this off — every workflow run executes the whole
suite.
