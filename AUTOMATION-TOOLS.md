# Automatic Checks

The scripts and commands that check the plugin for you.

---

## The two commands you need

```bash
composer check          # before every commit
composer check:release  # before every release
```

- **`composer check`**: syntax, coding style, unit tests. Takes seconds.
- **`composer check:release`**: all of the above, plus the build, the security scan and the quality scan.

Run the integration tests too: `composer test:integration` (see [tests/Integration/README.md](tests/Integration/README.md)).

---

## The tools

### 1. Security scan

```bash
bash bin/security-audit.sh
```

**It looks for:**
- security tokens (nonces) in AJAX actions;
- unsafe database queries;
- rights checks;
- escaped output;
- cleaned input;
- file upload checks;
- risky functions (`eval`, `exec`, `base64_decode`…);
- calls to outside servers;
- debug code left behind;
- files that can be opened directly (every PHP file must stop if WordPress isn't loaded);
- required files (`uninstall.php`, `readme.txt`, license).

**Result:** `0` = fine. `1` = something must be fixed.

### 2. Quality scan

```bash
bash bin/code-quality-check.sh
```

**It looks for:**
- the right files and folders;
- a complete plugin header;
- a valid `readme.txt`;
- tests and docs;
- files that shouldn't ship;
- namespaces;
- a fresh build.

**Result:** `0` = fine. `1` = something must be fixed.

### 3. Coding style

```bash
composer lint          # show problems. Must be zero.
composer lint:fix      # fix what can be fixed on its own
composer lint:diff     # only files you changed
composer lint:report   # problems grouped by rule
composer lint:syntax   # php -l on src/ and templates/
```

- The rules are in `phpcs.xml.dist`. It says which WordPress rules we use and why.
- Don't run `phpcs --standard=WordPress` by hand. It skips that file and prints thousands of findings we don't use.
- No `phpcs:ignore` comments. Fix the code.

### 4. Tests

```bash
composer test:unit         # 263 tests, no WordPress needed
composer test:integration  # 579 tests, real WordPress
```

More in [TESTING.md](TESTING.md).

### 5. GitHub

- **`.github/workflows/tests.yml`**: runs lint and both test sets on every push and pull request. PHP 8.1, 8.3 and 8.4.
- **`.github/workflows/deploy-to-wporg.yml`**: when you push a version tag, it runs the unit tests, builds, and sends the plugin to WordPress.org.

---

## Latest results (2026-09-28)

**Tests**
- Unit: 263 tests, 741 checks. All pass.
- Integration: 579 tests, 2417 checks. All pass.

**Coding style**
- Zero findings.

**Security scan: good, 3 warnings. All three are expected:**
1. "Unsafe SQL" in `src/Data/CollectionLock.php`. The query *is* prepared, one line below. The script only reads the first line.
2. "Direct superglobal access". Form handlers read `$_POST`, but always with a nonce check, `wp_unslash()` and cleaning.
3. "Dangerous function `base64_decode`". Code fields are stored base64-encoded. Only code fields are decoded.

Other numbers: 23 nonce checks, 41 rights checks, 261 escaped outputs, 71 cleaned inputs, 106 of 106 PHP files protected, no outside calls, no debug code.

**Quality scan: good, 1 warning:**
- `.git` folder found. It's left out of the ZIP by `.distignore`. Fine.

---

## Before every commit

```bash
composer check
```

If you added, removed or renamed a class in `src/`, also run:

```bash
./build-scoped.sh
```

The built autoloader only knows the classes it saw. `ScopedAutoloaderTest` fails if you forget.

## Before a release

```bash
composer check:release     # style, build, unit tests, security, quality
composer test:integration  # real WordPress
./package-for-wporg.sh     # make the ZIP
```

Then:
1. Install the ZIP on a fresh WordPress.
2. Do the quick check in [TESTING.md](TESTING.md).
3. For big releases, do [MANUAL-TESTING.md](MANUAL-TESTING.md) too.

Full steps: [RELEASE.md](RELEASE.md).

---

## Security checks by hand

The scripts can't check everything. Test these yourself:

- [ ] Log in as a Contributor, an Author, an Editor and an Admin. Each sees only what they should.
- [ ] Upload a file that isn't JSON to an import. It's refused.
- [ ] Import JSON with HTML and script tags in it. They are cleaned.
- [ ] Try it on a multisite network.

---

## Quick list

| What | Command |
|---|---|
| Before a commit | `composer check` |
| Before a release | `composer check:release` |
| Unit tests | `composer test:unit` |
| Integration tests | `composer test:integration` |
| Coding style | `composer lint` |
| Fix style | `composer lint:fix` |
| Syntax | `composer lint:syntax` |
| Security scan | `bash bin/security-audit.sh` |
| Quality scan | `bash bin/code-quality-check.sh` |
| Build | `./build-scoped.sh` |
| Make ZIP | `./package-for-wporg.sh` |

## Links

- [PHPUnit](https://phpunit.de/)
- [Plugin Check plugin](https://wordpress.org/plugins/plugin-check/)
- [readme.txt validator](https://wordpress.org/plugins/developers/readme-validator/)
- [Plugin security handbook](https://developer.wordpress.org/plugins/security/)

---

**Last updated:** 2026-09-28
**Plugin version:** 2.0.0
**Tests:** 263 unit, 579 integration
