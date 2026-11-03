# Frank Dead Link Checker — Security & Code Audit

**Target:** `frank-dead-link-checker` WordPress plugin, version **1.0.6**
**Location audited:** `D:\Desktop\frank-dead-link-checker.1.0.6\frank-dead-link-checker`
**Files:** 21 source files, ~5,918 lines of PHP + `assets/js/admin.js`, `assets/css/admin.css`
**Date:** 2026-10-12
**Methodology:** Full manual source review of every PHP/JS file, static pattern search for dangerous sinks, `php -l` syntax check on all files (all clean under PHP 8.2.30), plus isolated PHP reproductions of the two highest-impact logic bugs.

---

## 1. Executive summary

The plugin is well-structured for a WordPress plugin and gets the two hardest things right: **every admin AJAX endpoint verifies a nonce *and* checks `current_user_can('manage_options')`**, and the two database query builders correctly whitelist sort columns and bind all values through `$wpdb->prepare()`. No SQL injection, no CSRF, and no classic reflected/stored XSS sinks were found in the server-rendered admin UI (output is consistently escaped).

However, the audit found **three high-severity issues**, the most impactful being a **timezone bug that can make every scan self-destruct on any site whose UTC offset is not zero**, and a **server-side request forgery (SSRF)** vector inherent to fetching links discovered in post content. There is also a cluster of correctness/UX bugs (settings silently discarded, exclusions ignored for Gutenberg content, a broken mail configuration surfaced to end users) and a significant amount of dead code and dead settings that mislead both users and maintainers.

### Severity breakdown

| Severity | Count |
|---|---|
| High | 3 |
| Medium | 11 |
| Low | 15 |
| Informational / hygiene | 12 |

> **Second pass (added 2026-10-12):** every original finding was re-checked line-by-line against the source and two candidate issues were *disproved* and removed (see §10). Three new Medium, three new Low, and two new informational findings were found (see §11).

---

## 2. Findings at a glance

| ID | Sev | Title | Primary location |
|----|-----|-------|------------------|
| H1 | High | Timezone mismatch (`current_time('mysql')` vs `gmdate()`) breaks scan staleness logic | scanner / database |
| H2 | High | SSRF — server fetches arbitrary/internal URLs from post content | `scanner/class-frankdlc-checker.php` |
| H3 | High | Post-content corruption via unescaped `$`/`\` in `preg_replace` replacement | `admin/class-frankdlc-admin.php` |
| M1 | Med | Gutenberg/block links bypass exclusion list and link-type toggles | `scanner/parsers/class-frankdlc-parser-gutenberg.php` |
| M2 | Med | HTML/`showToast` injection pattern (latent DOM-XSS) in admin JS | `assets/js/admin.js` |
| M3 | Med | `scan_frequency` setting has no effect — cron is always daily | `class-frankdlc-activator.php` |
| M4 | Med | `sanitize_settings()` silently deletes valid settings on every save | `admin/class-frankdlc-settings.php` |
| M5 | Med | Hard-coded developer e-mail shipped in the Settings UI | `admin/class-frankdlc-settings.php` |
| M6 | Med | `load_plugin_textdomain()` never called — bundled translations never load | plugin bootstrap |
| M7 | Med | Full link discovery runs synchronously in one AJAX request (timeout risk) | `scanner/class-frankdlc-scanner.php` |
| M8 | Med | Auto-recheck performs up to 50 blocking HTTP requests inside WP-Cron | `scanner/class-frankdlc-scanner.php` |
| M9 | Med | Bundled `.po`/`.mo`/`.pot` are Pro-plugin leftovers; Hindi translates the plugin name as "Dead Link Checker Pro" | `languages/*` |
| M10 | Med | Recheck "self-heal" deletes valid rows for relative/HTML-encoded URLs | `admin/class-frankdlc-admin.php` |
| M11 | Med | Scan can get permanently stuck `running`; the watchdog that should fix it is never scheduled | `scanner`/`uninstall.php` |
| N4 | Low | Protocol-relative `Location:` headers resolved incorrectly | `scanner/class-frankdlc-checker.php` |
| N5 | Low | Stats transient thrashing (cache cleared per link during a scan) | `database` / `scanner` |
| N6 | Low | Uninstall deletes options/legacy names that are never created | `uninstall.php` |
| L1–L12 | Low | See section 6 | various |
| I1–I10 | Info | See section 7 | various |

---

## 3. High-severity findings

### H1 — Timezone mismatch makes scans cancel/expire immediately on most non-UTC sites

**Files:**
- `includes/scanner/class-frankdlc-scanner.php` — `get_progress()` (~line 447), `cleanup_stale_scans()` (line 377)
- `includes/class-frankdlc-database.php` — `get_links_to_check()` (line 678), `cleanup_old_data()` (line 926)
- `includes/scanner/class-frankdlc-scanner.php` — `recheck_broken_links()` (line 478)

**What happens:** Records are written with `current_time('mysql')`, which returns **site-local** time (WordPress = UTC offset applied). The staleness checks compare those values against `gmdate(...)`, which returns **UTC** time. WordPress forces PHP's default timezone to `UTC`, so `strtotime()` on a local-time string is also misinterpreted as UTC.

**Impact (reproduced):** For a site at UTC−8 (all US timezones):

```
started_at(local) = 2026-10-11 23:27:45   <-- written by current_time('mysql')
threshold(utc)    = 2026-10-12 06:57:45   <-- gmdate('-30 minutes')
started_at < threshold  =>  TRUE  => scan is considered STALE the instant it starts
get_progress age  = 28800 s (>1800)  => "idle"
```

Consequences:
1. `get_progress()` immediately reports `idle`, so the dashboard progress bar never runs and `pollProgress()` in JS reloads the page thinking the scan finished.
2. `cleanup_stale_scans()` marks the just-started scan as `failed` with *"Scan timed out (exceeded 30 minutes)"* and clears the queue.
3. `get_links_to_check()` uses `gmdate('-1 day')` against a local `last_check`, so links are either re-checked far too often or never, depending on the sign of the offset.
4. `cleanup_old_data()` may delete scan history far too early (or never).

Sites at UTC+ offsets (e.g., Europe, India, Australia) have the opposite error: stale scans are **never** detected, so a genuinely stuck scan blocks new scans indefinitely.

**Fix:** Use one time base everywhere. Either store with `current_time('mysql', true)` (UTC) and compare with `gmdate()`, or keep local time and compute thresholds with `current_time('timestamp')` + `wp_date()`. Do not mix the two.

---

### H2 — Server-Side Request Forgery (SSRF) via links extracted from content

**File:** `includes/scanner/class-frankdlc-checker.php` (`check_url()`, `follow_redirects()`)

The scanner takes every URL found in `post_content` / excerpts / blocks and performs `wp_remote_head()` / `wp_remote_get()` against it, then follows redirects by issuing further requests to whatever `Location` target is returned. There is **no allow-list of hosts, no blocking of private/link-local ranges, and no restriction on ports or schemes beyond `http/https`**.

An account that can author content (Author/Contributor/Editor, or any plugin/API that writes post content) can plant links such as:

- `http://169.254.169.254/latest/meta-data/iam/security-credentials/` (AWS/GCP/Azure instance metadata)
- `http://127.0.0.1:8080/admin` / `http://localhost:<port>/` (internal services)
- `http://10.0.0.5:9200/_cluster/health` (internal network reconnaissance)
- A public URL that 302-redirects to an internal address

When an administrator clicks **Scan Now**, the server performs those requests, and the plugin stores the resulting status code, response time, and error messages in the database — a usable SSRF / internal-port-scan primitive, and a redirect-based bypass.

**Fix:** Before requesting, resolve the host and reject private, loopback, link-local, reserved, and metadata ranges (use `filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)`), restrict ports to 80/443, and re-validate the target of every redirect hop. Consider making external checking opt-in per host.

---

### H3 — `preg_replace` replacement uses the URL verbatim → content corruption

**File:** `includes/admin/class-frankdlc-admin.php`, `ajax_edit_link()` (lines ~541–600)

```php
$content = preg_replace(
    '/(<a\s[^>]*href=["\'])' . $old_url_escaped . '(["\'])/i',
    '${1}' . $new_url . '${2}',   // <-- $new_url is used inside the replacement
    $content, 1
);
```

The replacement string is interpreted for back-references. A URL containing `$` or `\` (both legal in URLs and preserved by `esc_url_raw()`) expands as a capture group. Reproduced:

```php
$new = "http://new.example/$1";
// result: <a href="http://new.example/<a href="">link</a>
```

This silently corrupts the post content (broken HTML, duplicated attributes). The same pattern exists in the anchor-text branches and in `ajax_remove_link()`. The client-side validation only checks the scheme, so this is reachable from the admin UI.

**Fix:** Escape the replacement with `preg_replace_callback()` (return the literal value) or `str_replace` semantics; never concatenate user input into a replacement string. Also store the resulting content through `wp_slash()`/`kses` as appropriate.

---

## 4. Medium-severity findings

### M1 — Gutenberg/block links ignore exclusions and link-type toggles

**File:** `includes/scanner/class-frankdlc-scanner.php` (`scan_post_type`) + `scanner/parsers/class-frankdlc-parser-gutenberg.php`

When a post `has_blocks()`, the scanner skips the standard parser (good, avoids duplicates) and uses only `FRANKDLC_Parser_Gutenberg::extract_links()`. The standard parser applies `is_excluded()` and `is_type_enabled()` (internal/external/image toggles), but **the Gutenberg parser applies neither**. Result:

- Domains the admin added to **Excluded Domains** are still checked if they appear in blocks (e.g. `core/social-link`, `core/embed`, `core/button`).
- Disabling "Internal Links" or "External Links" does not stop block-based links from being scanned.
- Block URLs from attributes are not normalized to absolute, are not truncated to the `varchar(500)` column, and are not de-duplicated, so relative/duplicate rows can be created.

**Fix:** Route block-extracted links through the same normalise/`is_excluded`/`is_type_enabled`/dedupe pipeline as HTML links.

### M2 — Latent HTML injection in `showToast()` / `confirmModal()`

**File:** `assets/js/admin.js`

```js
const $toast = $('<div class="frankdlc-toast ' + type + '">' + message + '</div>');
```

and

```js
<p ...>${message}</p>
```

`message`/`type` are interpolated as HTML rather than text. Today almost all call sites pass plugin-owned translated strings, so exploitability is low, but this is a fragile pattern: any future value fed from the database (anchor text, error message, server response) becomes a DOM-XSS. Use `.text()` / `createTextNode`, or `document.createElement`. (No current stored-XSS path was proven — this is defence-in-depth.)

### M3 — `scan_frequency` is cosmetic; automatic scans always run daily

**File:** `includes/class-frankdlc-activator.php`, `schedule_events()`

The cron is scheduled only once at activation as `wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'FRANKDLC_scheduled_scan')` and is never rescheduled when settings change. `run_scheduled_scan()` only honours the literal value `'manual'`; every other value (including the UI's `'weekly'`) results in the daily event. The Settings UI therefore promises "Weekly" while the plugin scans daily, and changing the value has no effect until the plugin is re-activated. The "Daily PRO Upgrade" label in the same dropdown contradicts the behaviour.

**Fix:** On settings save, `wp_clear_scheduled_hook()` and reschedule using the selected (`weekly`/`daily`/`manual`) value, and have `run_scheduled_scan()` respect it.

### M4 — `sanitize_settings()` discards legitimate settings on every save

**File:** `includes/admin/class-frankdlc-settings.php`

Because `register_setting()` replaces the entire option with the sanitizer's return value, any key not explicitly re-added is deleted on save. The sanitizer omits `excluded_patterns`, `delay_between`, `mark_broken_after`, `links_per_page`, and `max_redirects`. The first three are read elsewhere (`delay_between` in `recheck_broken_links()`), so saving settings silently resets the request delay and drops the "(re)check N times before marking broken" concept. The sanitizer also introduces keys (`email_notifications`, `email_frequency`, `email_recipients`, `scan_widgets`, `scan_menus`, …) via `FRANKDLC_Database::get_default_settings()` that the Settings form never submits, and the two default sets disagree (`scan_frequency` `weekly` at activation vs `daily` on reset; `scan_type` absent from the reset defaults).

**Fix:** Merge sanitized input over the existing option instead of replacing it, and reconcile the two default arrays.

### M5 — Hard-coded developer e-mail address shipped to all users

**File:** `includes/admin/class-frankdlc-settings.php`, `field_email_notifications()`

```html
<textarea disabled ...>farazfrank777@gmail.com</textarea>
```

A personal address is rendered in the admin UI of every install (Settings → Notifications). Even though disabled, it exposes the developer's mailbox and is poor practice for a distributed plugin. Replace with a neutral placeholder (e.g. `admin@example.com` / `get_option('admin_email')`) or omit the field entirely since notifications are a Pro placeholder.

### M6 — Translations never load; help-page paths are wrong

The plugin never calls `load_plugin_textdomain()`, so the bundled `languages/frank-dead-link-checker-hi_IN.mo` and any user-supplied `.mo` in `/languages` are never loaded (WP.org language packs aside). Additionally the Help page instructs users to open `wp-content/plugins/dead-link-checker-pro/languages/dead-link-checker.pot` — the directory (`-pro`), text-domain filename, and slug are all wrong for this plugin.

**Fix:** Call `load_plugin_textdomain('frank-dead-link-checker', false, dirname(FRANKDLC_PLUGIN_BASENAME) . '/languages')` on `init`, and correct the documented paths.

### M7 — Link discovery is synchronous inside a single request

**File:** `includes/scanner/class-frankdlc-scanner.php`, `start_scan()` → `discover_links()` → `scan_post_type()`

Discovery loops over every published post/page in batches of 50 and runs `do_shortcode()` + regex parsing on each post — all inside the `FRANKDLC_start_scan` AJAX request, before any queue is scheduled. On a large site this request will exceed `max_execution_time`/memory limits and the user sees "Scan failed" while partial rows are already written. There is no `set_time_limit()` guard and no chunking. Running `do_shortcode()` on stored content during a scan can also trigger heavy or side-effecting shortcodes. Chunk discovery like the checking phase (queue-based) and cap work per request.

### M8 — Auto-recheck blocks WP-Cron for up to ~25 s+

**File:** `includes/scanner/class-frankdlc-scanner.php`, `recheck_broken_links()`

Up to 50 links are checked sequentially with a blocking HTTP request each and `usleep($delay * 1000)` between them (default 500 ms). With slow hosts this routinely exceeds cron/PHP time limits and stalls whichever request triggered cron. Batch it (reuse the queue) or move it to Action Scheduler.

---

## 5. Verified strengths (no action needed)

- **CSRF:** all 15 AJAX handlers call `check_ajax_referer('FRANKDLC_admin_nonce', 'nonce')`; the dashboard search POST verifies its own nonce; Settings uses `settings_fields()`; the activation redirect is a deliberate, nonce-free redirect.
- **Authorization:** every AJAX handler and menu page requires `manage_options`; `ajax_edit_link()` and `ajax_remove_link()` additionally check `current_user_can('edit_post', $post->ID)` before writing post content.
- **SQL injection:** `get_links()` and `get_links_count()` whitelist `orderby` (`$allowed_orderby`) and `order`, whitelist `status`, bind all filters/search/date/status values through `$wpdb->prepare()`, and build the `per_page`/`OFFSET` as `%d`. Table names come from `$wpdb->prefix` and are `esc_sql()`-escaped. Other queries are prepared or use `$wpdb->insert/update/delete` with format arrays.
- **Output escaping:** admin templates consistently use `esc_html`/`esc_attr`/`esc_url`/`esc_textarea`; `get_status_badge()` returns pre-escaped HTML (documented with phpcs annotations).
- **Bootstrap protection:** every file guards with `defined('ABSPATH') || exit;`; `uninstall.php` guards on `WP_UNINSTALL_PLUGIN`.
- **Autoloader** correctly maps `FRANKDLC_*` class names to `class-frankdlc-*.php` across all five include directories.
- **Singleton** prevents cloning and throws on `__wakeup` (limits unserialize abuse).
- **`php -l`** on all 14 PHP files: no syntax errors under PHP 8.2.

---

## 6. Low-severity findings

- **L1 — Timeout not clamped server-side.** `sanitize_settings()` uses `absint()` only; the 5–120 range exists solely in the HTML `min`/`max`. A crafted POST can set `timeout` to `0` or an enormous value (DoS / hung requests). Clamp in PHP.
- **L2 — Negative `OFFSET`.** `render_page()` does `absint($_GET['paged'])`, so `?paged=0` yields `offset = (0-1)*per_page`, producing `LIMIT 10 OFFSET -10` and a SQL error. Coerce `page` to `>= 1`.
- **L3 — Column-length overflows.** The block parser adds anchor text (from `alt`/inner text) without the `mb_substr(…, 0, 500)` truncation the HTML parser applies; `error_message` (varchar 500) and `url` (varchar 2048) are likewise untruncated. On MySQL strict mode these inserts error and the row is silently skipped.
- **L4 — `mark_broken_after` is ignored.** Defaults store `mark_broken_after = 3` but a link is flagged broken on the *first* failure, so transient network blips create false positives. Either implement the consecutive-failure counter or drop the setting.
- **L5 — `total_links` is inflated.** `save_link()` returns the existing row id and the caller still increments its counter on re-scan, so `scans.total_links` counts re-discovered links too, skewing the progress percentage.
- **L6 — `add_help_tab()` is dead code.** It is defined (admin class, ~line 792) but never attached to any `load-*`/`admin_head` action, so the four help tabs it builds never render. Same for `render_custom_notices()` being invoked only from the dashboard.
- **L7 — Uninstall references non-existent cron hooks.** `uninstall.php` clears `FRANKDLC_stale_scan_watchdog`, which is never registered anywhere, and honours `keep_data_on_uninstall`, which no UI ever sets. Harmless but misleading.
- **L8 — Over-broad asset enqueue.** `enqueue_assets()` matches any admin hook containing `frankdlc-`, which can collide with unrelated third-party screens.
- **L9 — "Concurrent Requests" field is a hard-coded `3`** with no input, yet `sanitize_settings()` reads `concurrent_requests`, so the stored value is overwritten to `3` on every save.
- **L10 — Bulk actions bypass per-post capability checks.** `ajax_bulk_action()`'s `delete`/`recheck` operate on the plugin table with `manage_options` only (no `edit_post` check). Acceptable for the plugin's own data, but note the asymmetry with the single-link handlers.
- **L11 — `mb_substr`/`mbstring` assumed.** `includes/scanner/class-frankdlc-parser.php` calls `mb_substr()` without checking `extension_loaded('mbstring')`; the plugin advertises only "PHP 7.4+".
- **L12 — Byte-based truncation of URLs.** `FRANKDLC_Link::get_display_url()` uses `strlen`/`substr`, which can split a multibyte character and produce mojibake for internationalised URLs.

---

## 7. Informational / hygiene

- **I1** — Marketing copy for a "Pro" product is embedded in Help/Upgrade/Settings pages; the readme ships an email (`farazfrank777@gmail.com`, again) that is really a personal address. Fine for a freemium listing, but keep real PII out of the package.
- **I2** — `readme.txt` field `Tested up to: 7.1` is invalid (no such WordPress version) and will be rejected/normalised by the WP.org readme validator.
- **I3** — Naming inconsistency: title/slug says "Dead Link Checker" while the plugin header, menu, and strings say "Frank Dead Link Checker"; the `@package BrokenLinkChecker` docblock points at a different project name entirely. Pick one internal name.
- **I4** — `HEAD`→`GET` fallback only triggers on HTTP 405; many servers answer `HEAD` with `403`/`501`, producing false "broken" results. Consider falling back on 403/405/501 or on empty `Content-Length`.
- **I5** — `evaluate_status()` treats a `0`/`null` status code (should be unreachable but possible) as "working" unless it took > 5 s; guard explicitly.
- **I6** — `follow_redirects()` never updates `response_time`, so a slow redirect chain is reported with the first hop's timing.
- **I7** — Redirect chains are followed without re-applying the SSRF guard (see H2).
- **I8** — `scans.error_message TEXT DEFAULT NULL` and `first_detected DATETIME NOT NULL` (no default) are known `dbDelta()` friction points; verify the schema is applied cleanly on MariaDB/older MySQL.
- **I9** — The `usr` search field is read from `$_POST` only, while sort/pagination links emit an `s` query arg, so search is lost as soon as the admin sorts or pages.
- **I10** — `FRANKDLC_Queue_Manager::schedule_recurring()` and `get_pending_count()`/`cancel_group()` are largely unused; the Action-Scheduler `cancel_group()` references `ActionScheduler_Store` unguarded (fatal if the class is absent while `as_*` functions exist).

---

## 8. Recommended remediation order

1. **H1** — Fix the time base (blocks the entire feature on non-UTC sites).
2. **H3** — Replace `preg_replace` replacement with `preg_replace_callback` (data integrity).
3. **H2** — Add SSRF guard (host/IP/port validation + per-redirect re-validation).
4. **M3 / M4** — Make `scan_frequency` real and stop `sanitize_settings()` from wiping settings.
5. **M1** — Apply exclusions and type toggles to Gutenberg links.
6. **M5 / M6** — Remove the hard-coded e-mail; load the text domain and fix help paths.
7. **M7 / M8** — Chunk discovery and rechecking to avoid timeouts.
8. **M2** — Switch `showToast`/`confirmModal` to text nodes.
9. Low/info items as cleanup, plus removing dead settings and dead code.

---

## 9. Reproduction artefacts

`php -l` across all PHP files returned no syntax errors (PHP 8.2.30, ZTS, Windows). The H1 and H3 behaviours above were reproduced with standalone PHP snippets (outputs included inline in sections 3.1 and 3.3). No live WordPress instance was used; findings are derived from static analysis plus these isolated reproductions.

---

## 10. Verification pass — every original finding re-checked

Each finding was re-read at its exact source location during the second pass. Results:

| ID | Status after re-check | Evidence |
|----|----------------------|----------|
| H1 | **Confirmed** | `gmdate()` thresholds vs `current_time('mysql')` writes at `scanner:377/478`, `database:678/926`; reproduction still shows instant "stale" for UTC−8 |
| H2 | **Confirmed** | `checker.php` `check_url()`/`follow_redirects()` issue `wp_remote_head/get` with no host/IP filter |
| H3 | **Confirmed** | `admin.php` ~L550–600; reproduction re-run, content mangled |
| M1 | **Confirmed** | `parser-gutenberg.php` never calls `is_excluded()`/`is_type_enabled()` (both are `private` in `class-frankdlc-parser.php`) |
| M2 | **Confirmed** | `admin.js` `showToast()` / `confirmModal()` interpolate into HTML |
| M3 | **Confirmed** | Only `wp_schedule_event(..., 'daily', ...)` in `activator.php`; no `wp_clear_scheduled_hook` in `settings.php` |
| M4 | **Confirmed** | `sanitize_settings()` returns 12 keys; activator default array has 16 |
| M5 | **Confirmed** | `settings.php` `field_email_notifications()` literal `farazfrank777@gmail.com` |
| M6 | **Confirmed** | `grep` for `load_plugin_textdomain` across the tree returns **zero** matches |
| M7 / M8 | **Confirmed** | `discover_links()` runs inline in the AJAX request; `recheck_broken_links()` loops 50 links with `usleep` |
| L1–L12 | **Confirmed** | See §6; all re-verified against source |
| I1–I10 | **Confirmed** | See §7 |

### Candidate issues that were DISPROVED (deliberately removed)

Two things I initially suspected are **not** defects — recorded here so they are not re-raised:

- **False positive 1 — `is_excluded()` substring over-matching.** I expected `stripos($host, '.'.$domain)` to wrongly exclude hosts such as `example.com.evil.net`. Tested: it does **not**. `www.example.com` and `sub.example.com` are correctly excluded, and neither `example.com.evil.net` nor `notexample.com` matches. The logic is sound.
- **False positive 2 — broken relative-redirect resolution.** I suspected `dirname()` on a full URL produced garbage. Tested: `dirname('http://example.com/a/b')` returns `http://example.com/a` as intended. Only the protocol-relative case is wrong (new finding N4 below).

---

## 11. New findings (second pass)

### M9 — Bundled translation files are leftovers from the Pro plugin (and rename the plugin in Hindi)

**Files:** `languages/frank-dead-link-checker.pot`, `languages/frank-dead-link-checker-hi_IN.po`, `…-hi_IN.mo`

Both catalogs were generated from a **different codebase**: every `#:` reference points at files that do not exist in this plugin — `includes/admin/class-blc-admin.php`, `includes/class-blc-redirects.php`, `includes/class-blc-notifications.php`, `includes/class-blc-multisite.php`, `includes/class-blc-export.php`, `includes/scanner/class-blc-scanner.php`. The catalog also contains msgids for features this build does not ship (Redirect Manager, "301 - Permanent Redirect (Recommended)", "Export created successfully!", "Cleanup Exports", "A redirect for this URL already exists.", "Network Overview"), and is stamped `Project-Id-Version: … 1.0.1` against a 1.0.6 plugin.

Most visibly, the Hindi translation renders the **plugin name as the paid product**:

```
msgid  "Frank Dead Link Checker"          ->  msgstr "डेड लिंक चेकर प्रो"   (= "Dead Link Checker Pro")
msgid  "Frank Dead Link Checker Settings" ->  msgstr "डेड लिंक चेकर प्रो सेटिंग्स"
```

So a Hindi-locale admin sees the free plugin's own menu/title/error strings naming the Pro product, and translators editing the `.pot` are pointed at dead file paths. The catalogs are also branded `Copyright (C) 2026 WPFrank` with `developer@wpfrank.com` — a *third* vendor identity alongside the plugin header ("A WP Life") and the Pro URL (`awplife.com`).

**Fix:** regenerate `.pot`/`.po` from this codebase (`wp i18n make-pot`), retranslate the plugin name correctly, and delete the Pro-only strings.

### M10 — Recheck "self-heal" deletes valid rows (relative & HTML-encoded URLs)

**File:** `includes/admin/class-frankdlc-admin.php`, `ajax_recheck_link()` (line ~425)

```php
if (strpos($post->post_content, $link->url) === false) {
    FRANKDLC()->database->delete_link($link_id);   // row silently removed
```

The stored URL is the **normalised absolute** value produced by `FRANKDLC_Parser::normalize_url()` (a root-relative `href="/about/"` becomes `http://site.com/about/`), but `post_content` still contains the **original** `href`. So a plain interior link written as `/about/` fails this `strpos()` and the row is deleted as "fixed". The same happens for any URL containing `&`, which WordPress stores HTML-encoded as `&amp;`:

```
link_url = "http://x.test/?a=1&b=2"
content  = '<a href="http://x.test/?a=1&amp;b=2">'  ->  strpos = NO -> row wrongly deleted
```

The handler also calls `get_post($link->source_id)` without checking `source_type`, so a comment/custom-field row would be matched against an unrelated post. In the free build the Recheck button is UI-disabled, but the endpoint is registered and reachable by an admin, and the same defect affects any Pro-migrated data.

**Fix:** compare like-for-like (normalise the content, or match on the raw href/target), and skip non-post source types as `ajax_remove_link()` already does.

### M11 — A scan can get permanently stuck `running`, and the watchdog that would recover it is dead

**Files:** `includes/scanner/class-frankdlc-scanner.php`, `uninstall.php`

`process_queue()` opens with `$scan_id = get_transient('FRANKDLC_current_scan_id'); if (!$scan_id) return;`. The transient is stored with a **1-hour TTL**. If it expires (or is flushed by an object-cache eviction / `clear_all_data()`) while a large scan is still working through batches, the queue stops scheduling itself, the `scans` row is left in `status='running'`, and `is_scan_running()` then returns `true` — so **no new scan can ever start** and the "Scan Now" path returns "A scan is already in progress."

The intended safety net, `cleanup_stale_scans()`, is only invoked from `get_progress()` (i.e. an admin must open the dashboard), and the cron hook that was clearly meant to run it — `FRANKDLC_stale_scan_watchdog` — is referenced in `uninstall.php` (line 83) but **registered nowhere in the codebase**. Worse, on UTC+ sites the H1 timezone bug means `get_progress()`'s staleness test never fires either, so the block is permanent until a manual Force Stop.

**Fix:** register a real watchdog cron that calls `cleanup_stale_scans()`, and/or make `is_scan_running()` ignore scans older than a timeout rather than relying on the transient.

### N4 — Protocol-relative redirect targets are resolved incorrectly (Low)

**File:** `includes/scanner/class-frankdlc-checker.php`, `follow_redirects()`

`strpos($current_url, '/') === 0` is used to detect root-relative paths, but that is also true for a protocol-relative `Location: //cdn.example.com/x`, which then becomes `http://<original-host>//cdn.example.com/x` instead of `https://cdn.example.com/x`. Cosmetic/misclassification at worst.

### N5 — Stats transient is thrashed during a scan (Low)

`FRANKDLC_Database::update_link_result()` calls `clear_stats_cache()` on **every** processed link, so during an N-link scan the 5-minute stats transient is deleted N times and the dashboard's multi-`COUNT(*)` query re-runs on each visit. Consider clearing once per batch in `process_queue()`.

### N6 — Uninstall removes keys that are never created (Low)

`uninstall.php` deletes the options `FRANKDLC_last_scan` and `FRANKDLC_scan_stats` and clears the transient pattern `_transient_FRANKDLC_%`; the two options are never set anywhere in the plugin, and the transient cleanup cannot see named transients stored via the object cache. Harmless residue that suggests incomplete refactoring.

### Additional informational note (I11–I12)

- **I11** — The stale `vendor identity` is pervasive: every class docblock still says `@package BrokenLinkChecker`, the default User-Agent strings say `BrokenLinkChecker/<version>`, the readme title is "Dead Link Checker - SEO and 404 error Fix", while the header author is "A WP Life", the catalogs credit "WPFrank", the upgrade URL is `awplife.com`, and a `farazfrank777@gmail.com` address is hard-coded. At least four conflicting product/brand names ship in one package.
- **I12** — `readme.txt` lists contributors including `FARAZFRANK` and points at `awplife.com` for Pro; consistent with the fork/branding confusion above.
