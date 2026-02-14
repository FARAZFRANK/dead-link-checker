# Dead Link Checker — Plugin Analysis & Improvement Plan

> **Date:** 2026-02-11  
> **Purpose:** Comprehensive analysis of scanning modes, stuck scan handling, reset/history options, and settings workflow — with **actionable solutions** for implementation.

---

## 1. 🔍 Scanning Modes

Plugin mein **3 scanning modes** hain:

### 1.1 Full Scan (Manual)
- **Trigger:** Dashboard par "Scan Now" button click karne se
- **AJAX Handler:** `ajax_start_scan()` → `BLC_Scanner::start_scan('full')`
- **Kya scan hota hai** (settings ke mutabiq):
  | Content Type | Setting Key | Default |
  |---|---|---|
  | Posts | `scan_posts` | ✅ ON |
  | Pages | `scan_pages` | ✅ ON |
  | Comments | `scan_comments` | ❌ OFF |
  | Widgets | `scan_widgets` | ✅ ON |
  | Menus | `scan_menus` | ✅ ON |
  | Custom Fields (ACF) | `scan_custom_fields` | ❌ OFF |

- **Page Builder Support:** Elementor, Divi, WPBakery, Gutenberg blocks → har ek ke liye dedicated parser class hai
- **Process Flow:**
  1. `start_scan()` → Check karta hai koi scan already running toh nahi
  2. Database mein new scan record create hota hai (`status = 'running'`)
  3. Transient set hota hai: `blc_current_scan_id`
  4. `discover_links()` → sab content types scan karke links database mein save
  5. Queue Manager ke through `blc_process_queue` schedule hota hai (2 sec delay)
  6. `process_queue()` → Batch mein links check hote hain (`concurrent_requests` setting, default: 3, max: 10)
  7. Har batch ke baad next batch schedule hoti hai
  8. Jab sab links check ho jayein → `complete_scan()` called

### 1.2 Scheduled Scan (Automatic)
- **Trigger:** WP-Cron / Action Scheduler ke through
- **Hook:** `blc_scheduled_scan` → `BLC_Scanner::run_scheduled_scan()`
- **Frequency options:** Hourly, Twice Daily, Daily, Weekly, Manual Only
- **Behaviour:** Same as Full Scan, bas trigger automatic hai
- **Important:** Agar frequency `manual` set hai → scheduled scan skip ho jayegi

### 1.3 Auto-Recheck (Broken Links Only)
- **Trigger:** Hook `blc_recheck_broken` → `BLC_Scanner::recheck_broken_links()`
- **Purpose:** Sirf broken aur warning links ko recheck karta hai (lightweight task)
- **Conditions:**
  - Sirf un links ko recheck karta hai jo 6 ghante se check nahi hue
  - `is_dismissed = 0` — dismissed links skip hote hain
  - Maximum 50 links per run
  - Full scan running ho toh skip ho jata hai
- **Delay:** Settings ke `delay_between` parameter se control (default: 500ms)

### 1.4 Fresh Scan
- **Trigger:** Dashboard par "Fresh Scan" button
- **AJAX Handler:** `ajax_fresh_scan()`
- **Process:**
  1. `clear_all_data()` → TRUNCATE dono tables (links + scans)
  2. Transients delete hote hain
  3. Full scan start hota hai
- **Use case:** Jab puri history clean karke dubara scan karna ho

---

## 2. ⚠️ Stuck Scan — Terminate / Restart Procedure

### Current State (Kya hai abhi)

**Stop Scan button exists** — `BLC_Scanner::stop_scan()`:
1. Scan status `cancelled` set karta hai
2. Transients delete karta hai (`blc_current_scan_id`, `blc_scan_progress`)
3. Queue Manager se `blc_process_queue` cancel karta hai
4. WP-Cron se bhi clear karta hai

### Problems / Gaps

| Problem | Detail |
|---|---|
| ❌ **No timeout detection** | Agar scan stuck ho jaye (e.g., slow URL), koi automatic timeout nahi hai |
| ❌ **No stale scan cleanup** | Agar transient expire ho jaye (1 hour), scan database mein "running" rehti hai — naye scan block ho jayega |
| ❌ **No watchdog** | Koi background check nahi hai jo detect kare ki scan stuck hai |
| ❌ **No auto-recovery** | Server restart hone par scan dead ho jati hai lekin database mein "running" rehti hai |
| ⚠️ **Manual workaround** | User ko deactivate/reactivate karna padta hai — `BLC_Deactivator::stop_running_scans()` tab running scans cancel karta hai |

### 🟢 Proposed Solutions

#### A. Stale Scan Auto-Recovery (Priority: HIGH)
```
- Har scan progress check mein (AJAX `get_scan_progress`):
  - Check karo ki scan ka `started_at` kitna purana hai
  - Agar 30+ minutes ho gaye aur progress change nahi hui → auto-cancel
  - Naya scan start allow karo
```

#### B. Scan Timeout Watchdog (Priority: HIGH)
```
- Background scheduled task add karo (every 5 minutes)
- Check karo ki `blc_current_scan_id` transient aur database scan match karti hai
- Agar mismatch ya stale hai → auto-cleanup
```

#### C. Force Stop Button (Priority: MEDIUM)
```
- Dashboard par "Force Stop" ya "Reset Scan State" button
- Ye forcefully:
  - Database mein running/pending scans cancel kare
  - Sab scan transients delete kare
  - Queue Manager se sab pending actions cancel kare
```

#### D. Scan Status Debugging Info (Priority: LOW)
```
- Settings/Help page par scan status details show karo:
  - Current scan ID, started_at, links checked/total
  - Queue method (Action Scheduler ya WP-Cron)
  - Last queue processing time
```

---

## 3. 🔄 Plugin Reset Option

### Current State

| Feature | Available? | Detail |
|---|---|---|
| Fresh Scan | ✅ Yes | Data clear + new scan (`clear_all_data()`) |
| Full Settings Reset | ❌ No | Koi "Reset to Defaults" button nahi hai |
| Uninstall Cleanup | ⚠️ Partial | Deactivation par sirf transients + cron clear — Tables aur settings rehti hain |

### 🟢 Proposed Solutions

#### A. "Reset Settings to Default" Button (Priority: MEDIUM)
```
- Settings page par button add karo
- `delete_option('blc_settings')` se sab settings clear
- Default values automatically load hongi
- Confirmation dialog zaroor ho
```

#### B. "Full Plugin Reset" Button (Priority: MEDIUM)
```
- Dashboard ya Settings page par button
- Sab kuch reset kare:
  - Database tables TRUNCATE (links + scans)
  - Settings delete (`blc_settings` option)
  - Transients clear
  - Scheduled events clear
  - Export files delete
- Double confirmation required
```

#### C. Uninstall Hook Cleanup (Priority: HIGH)
```
- uninstall.php file add karo (currently missing)
- Plugin delete karne par:
  - Database tables DROP
  - Settings delete
  - Transients clear
  - Export directory delete
```

---

## 4. 📋 History Clean Procedure

### Current State

| Feature | Available? | Detail |
|---|---|---|
| Fresh Scan | ✅ Yes | Links + Scans dono TRUNCATE |
| Individual link delete | ✅ Yes | `ajax_delete_link()` |
| Bulk delete | ✅ Yes | `ajax_bulk_action()` → delete action |
| Scan history clear | ❌ No | Purani scans ka record permanently rehta hai |
| Export file cleanup | ❌ No | `dlc-exports/` folder mein files accumulate hoti hain |

### 🟢 Proposed Solutions

#### A. "Clear Scan History" Button (Priority: MEDIUM)
```
- Dashboard par button add karo
- Sirf scan history (blc_scans table) clear kare
- Links data as-is rahe
```

#### B. "Delete All Links" Button (Priority: MEDIUM)
```
- Sirf links clear karo, scan history intact rahe
- Useful jab links data corrupt ho ya stale ho
```

#### C. Auto-Cleanup of Old Data (Priority: LOW)
```
- Setting mein option do: "Auto-delete scan history older than X days"
- `blc_cleanup_old_data` cron hook already registered hai in deactivator —
  lekin implementation missing hai!
- Default: 90 days
```

#### D. Export File Cleanup (Priority: LOW)
```
- Setting mein option: "Auto-delete export files older than X days"
- Ya dashboard par "Clean Export Files" button
```

---

## 5. ⚙️ Plugin Settings — Step by Step

Settings page 5 tabs mein organized hai:

### Tab 1: General
| Setting | Type | Default | Purpose |
|---|---|---|---|
| Scan Frequency | Dropdown | Daily | Kitni bar automatically scan ho |
| Request Timeout | Number (5-120) | 30 sec | Ek link check karne ka max wait time |

### Tab 2: Scan Scope
| Setting | Type | Default | Purpose |
|---|---|---|---|
| Posts | Checkbox | ✅ ON | Posts scan kare |
| Pages | Checkbox | ✅ ON | Pages scan kare |
| Comments | Checkbox | ❌ OFF | Comments scan kare |
| Widgets | Checkbox | ✅ ON | Widgets scan kare |
| Menus | Checkbox | ✅ ON | Menus scan kare |
| Custom Fields (ACF) | Checkbox | ❌ OFF | Custom fields/ACF scan kare |
| Internal Links | Checkbox | ✅ ON | Internal URLs check kare |
| External Links | Checkbox | ✅ ON | External URLs check kare |
| Images | Checkbox | ✅ ON | Image URLs check kare |

### Tab 3: Exclusions
| Setting | Type | Default | Purpose |
|---|---|---|---|
| Excluded Domains | Textarea | Empty | In domains ke links skip honge (per line) |

### Tab 4: Notifications
| Setting | Type | Default | Purpose |
|---|---|---|---|
| Email Notifications | Checkbox | ✅ ON | Broken links milne par email |
| Email Frequency | Dropdown | Weekly | Kitni bar email aye |
| Recipients | Textarea | Admin email | Kisko email jayegi |

### Tab 5: Advanced
| Setting | Type | Default | Purpose |
|---|---|---|---|
| Concurrent Requests | Number (1-10) | 3 | Ek time mein kitni links check hon |
| User Agent | Text | Mozilla/5.0... | HTTP request ka user agent string |
| Verify SSL | Checkbox | ✅ ON | SSL certificates verify kare |

### Tab 6: Help
- HTTP status codes ka reference guide (200, 301, 302, 403, 404, 500, etc.)

### Settings Save Flow:
1. User changes karta hai → "Save Changes" click
2. WordPress Settings API `sanitize_settings()` call karta hai
3. Sab values sanitize hoti hain (type casting, validation)
4. `blc_settings` option mein save hota hai
5. Scheduled scan frequency change → cron events re-schedule

### ⚠️ Missing in Settings:
| Missing Feature | Priority | Detail |
|---|---|---|
| Custom Post Types scan | HIGH | Sirf `post` aur `page` support hai, CPT nahi |
| Delay between requests | MEDIUM | `delay_between` setting code mein used hai lekin UI mein missing |
| Reset to defaults button | MEDIUM | Settings page par nahi hai |
| Max redirects setting | LOW | Hardcoded 5 hai, configurable hona chahiye |
| Scan batch size control | LOW | `concurrent_requests` hai lekin "Batch Size" terminology better |

---

## 6. 🛠️ Architecture Summary

```
dead-link-checker-pro/
├── dead-link-checker.php          ← Main plugin file, plugin init
├── includes/
│   ├── class-blc-database.php     ← Database operations (links table, scans table)
│   ├── class-blc-activator.php    ← Activation: table creation, defaults
│   ├── class-blc-deactivator.php  ← Deactivation: cron clear, transients clear
│   ├── class-blc-export.php       ← CSV/JSON export functionality
│   ├── class-blc-notifications.php ← Email notifications
│   ├── class-blc-redirects.php    ← 301/302/307 redirect manager
│   ├── class-blc-queue-manager.php ← Action Scheduler / WP-Cron abstraction
│   ├── class-blc-multisite.php    ← Multisite network support
│   ├── scanner/
│   │   ├── class-blc-scanner.php  ← Main scanner orchestrator
│   │   ├── class-blc-checker.php  ← HTTP HEAD/GET link checking
│   │   ├── class-blc-parser.php   ← HTML content link extractor
│   │   └── parsers/               ← Page builder parsers
│   │       ├── class-blc-parser-elementor.php
│   │       ├── class-blc-parser-divi.php
│   │       ├── class-blc-parser-wpbakery.php
│   │       └── class-blc-parser-gutenberg.php
│   └── admin/
│       ├── class-blc-admin.php    ← Admin controller, AJAX handlers
│       ├── class-blc-dashboard.php ← Dashboard UI rendering
│       └── class-blc-settings.php  ← Settings page
└── assets/
    ├── js/admin.js                ← Frontend JS (scan, export, UI)
    └── css/admin.css              ← Admin styles
```

---

## 7. 📊 Implementation Priority Summary

| # | Feature | Priority | Files to Modify | Effort |
|---|---------|----------|----------------|--------|
| 1 | Stale scan auto-recovery | 🔴 HIGH | `class-blc-scanner.php`, `class-blc-admin.php` | Small |
| 2 | Scan timeout watchdog | 🔴 HIGH | `class-blc-scanner.php`, `class-blc-activator.php` | Medium |
| 3 | Uninstall hook (cleanup) | 🔴 HIGH | New `uninstall.php` file | Small |
| 4 | Force Stop / Reset Scan button | 🟡 MEDIUM | `class-blc-admin.php`, `class-blc-dashboard.php`, `admin.js` | Medium |
| 5 | Reset Settings to Default | 🟡 MEDIUM | `class-blc-settings.php`, `admin.js` | Small |
| 6 | Full Plugin Reset button | 🟡 MEDIUM | `class-blc-admin.php`, `class-blc-dashboard.php`, `admin.js` | Medium |
| 7 | Clear Scan History button | 🟡 MEDIUM | `class-blc-database.php`, `class-blc-admin.php`, `admin.js` | Small |
| 8 | Custom Post Types scan | 🟡 MEDIUM | `class-blc-scanner.php`, `class-blc-settings.php` | Medium |
| 9 | Delay between requests UI | 🟡 MEDIUM | `class-blc-settings.php` | Small |
| 10 | Auto-cleanup old data | 🟢 LOW | `class-blc-database.php`, `class-blc-activator.php` | Medium |
| 11 | Export file cleanup | 🟢 LOW | `class-blc-export.php`, `class-blc-admin.php` | Small |
| 12 | Scan debugging info panel | 🟢 LOW | `class-blc-dashboard.php` | Small |

---

> **Next Step:** Aaap decide karo kaunse features implement karne hain. Priority order mein kaam karna best rahega — pehle HIGH priority items, phir MEDIUM, phir LOW.
