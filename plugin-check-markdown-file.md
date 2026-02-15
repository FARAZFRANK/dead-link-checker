# Plugin Check Report

**Plugin:** Frank Dead Link Checker
**Generated at:** 2026-02-15 08:44:02


## `D:\wamp64\www\wpfrank-dev\wp-content\plugins\frank-dead-link-checker\includes\admin\class-frankdlc-dashboard.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 81 | 42 | ERROR | WordPress.WP.I18n.MissingTranslatorsComment | A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a "translators:" comment on the line above to clarify the meaning of the placeholders. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions) |
| 81 | 102 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found 'human_time_diff'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 415 | 77 | ERROR | WordPress.WP.I18n.MissingTranslatorsComment | A function call to _n() with texts containing placeholders was found, but was not accompanied by a "translators:" comment on the line above to clarify the meaning of the placeholders. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions) |

## `D:\wamp64\www\wpfrank-dev\wp-content\plugins\frank-dead-link-checker\includes\admin\class-frankdlc-admin.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 582 | 168 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$stats['broken']'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 590 | 24 | WARNING | WordPress.Security.NonceVerification.Recommended | Processing form data without nonce verification. |  |

## `D:\wamp64\www\wpfrank-dev\wp-content\plugins\frank-dead-link-checker\frank-dead-link-checker.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 185 | 9 | ERROR | PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound | load_plugin_textdomain() has been discouraged since WordPress version 4.6. When your plugin is hosted on WordPress.org, you no longer need to manually include this function call for translations under your plugin slug. WordPress will automatically load the translations for you as needed. | [Docs](https://make.wordpress.org/core/2016/07/06/i18n-improvements-in-4-6/) |

## `D:\wamp64\www\wpfrank-dev\wp-content\plugins\frank-dead-link-checker\includes\class-frankdlc-redirects.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 59 | 25 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 59 | 25 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 113 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 113 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 114 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$this-&gt;table} at &quot;SELECT * FROM {$this-&gt;table} WHERE source_url_hash = %s AND is_active = 1&quot; |  |
| 123 | 25 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 123 | 25 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 124 | 17 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$this-&gt;table} at &quot;SELECT * FROM {$this-&gt;table} WHERE source_url_hash = %s AND is_active = 1&quot; |  |
| 131 | 13 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 131 | 13 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 143 | 13 | WARNING | WordPress.Security.SafeRedirect.wp_redirect_wp_redirect | wp_redirect() found. Using wp_safe_redirect(), along with the &quot;allowed_redirect_hosts&quot; filter if needed, can help avoid any chances of malicious redirects within code. It is also important to remember to call exit() after a redirect so that no other unwanted code is executed. | [Docs](https://developer.wordpress.org/reference/functions/wp_safe_redirect/) |
| 174 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 174 | 21 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 175 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$this-&gt;table} at &quot;SELECT id FROM {$this-&gt;table} WHERE source_url_hash = %s&quot; |  |
| 183 | 19 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 244 | 19 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 244 | 19 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 265 | 23 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 265 | 23 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 318 | 41 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$this-&gt;table} at &quot;SELECT COUNT(*) FROM {$this-&gt;table} WHERE {$where_sql}&quot; |  |
| 318 | 41 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$where_sql} at &quot;SELECT COUNT(*) FROM {$this-&gt;table} WHERE {$where_sql}&quot; |  |
| 318 | 97 | WARNING | WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare | Replacement variables found, but no valid placeholders found in the query. |  |
| 322 | 24 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 322 | 24 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 322 | 31 | ERROR | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $count_sql used in $wpdb->get_var($count_sql)\n$count_sql assigned unsafely at line 320:\n $count_sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where_sql}"\n$where_sql assigned unsafely at line 309:\n $where_sql = implode(' AND ', $where)\n$where assigned unsafely at line 303:\n $where[] = '(source_url LIKE %s OR target_url LIKE %s)'\n$search assigned unsafely at line 304:\n $search = '%' . $wpdb->esc_like($args['search']) . '%'\n$args['search'] used without escaping. |  |
| 322 | 39 | ERROR | WordPress.DB.PreparedSQL.NotPrepared | Use placeholders and $wpdb->prepare(); found $count_sql |  |
| 329 | 22 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 329 | 22 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 329 | 29 | ERROR | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $sql used in $wpdb->get_results($wpdb->prepare($sql, $values))\n$sql assigned unsafely at line 328:\n $sql = "SELECT * FROM {$this->table} WHERE {$where_sql} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d"\n$where_sql assigned unsafely at line 309:\n $where_sql = implode(' AND ', $where)\n$orderby assigned unsafely at line 313:\n $orderby = in_array($args['orderby'], $valid_orderby, true) ? $args['orderby'] : 'created_at'\n$order assigned unsafely at line 314:\n $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC'\n$where assigned unsafely at line 303:\n $where[] = '(source_url LIKE %s OR target_url LIKE %s)'\n$args['orderby'] used without escaping.\n$values assigned unsafely at line 326:\n $values[] = $offset\n$count_sql assigned unsafely at line 320:\n $count_sql = "SELECT COUNT(*) FROM {$this->table} WHERE {$where_sql}"\n$search assigned unsafely at line 304:\n $search = '%' . $wpdb->esc_like($args['search']) . '%'\n$args['search'] used without escaping.\n$offset assigned unsafely at line 292:\n $offset = ($args['page'] - 1) * $args['per_page']\n$args['page'] used without escaping.\n$args['per_page'] used without escaping. |  |
| 329 | 56 | ERROR | WordPress.DB.PreparedSQL.NotPrepared | Use placeholders and $wpdb->prepare(); found $sql |  |
| 348 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 348 | 16 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 349 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$this-&gt;table} at &quot;SELECT * FROM {$this-&gt;table} WHERE id = %d&quot; |  |

## `D:\wamp64\www\wpfrank-dev\wp-content\plugins\frank-dead-link-checker\includes\scanner\class-frankdlc-scanner.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 269 | 21 | ERROR | Generic.PHP.ForbiddenFunctions.Found | The use of function wp_get_sidebars_widgets() is forbidden |  |
| 722 | 31 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table used in $wpdb-&gt;get_results($wpdb-&gt;prepare(\r\n &quot;SELECT id FROM {$table} WHERE status IN (&#039;running&#039;, &#039;pending&#039;) AND started_at &lt; %s&quot;,\r\n $stale_threshold\r\n ))\n$table assigned unsafely at line 717:\n $table = FRANKDLC()-&gt;database-&gt;get_scans_table() |  |
| 753 | 17 | WARNING | WordPress.PHP.DevelopmentFunctions.error_log_error_log | error_log() found. Debug code should not normally be used in production. |  |
| 821 | 25 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table used in $wpdb-&gt;get_results($wpdb-&gt;prepare(\r\n &quot;SELECT * FROM {$table} \r\n WHERE (is_broken = 1 OR is_warning = 1) \r\n AND is_dismissed = 0 \r\n AND (last_check IS NULL OR last_check &lt; %s)\r\n ORDER BY last_check ASC\r\n LIMIT 50&quot;,\r\n $stale_threshold\r\n ))\n$table assigned unsafely at line 815:\n $table = FRANKDLC()-&gt;database-&gt;get_links_table() |  |
| 855 | 13 | WARNING | WordPress.PHP.DevelopmentFunctions.error_log_error_log | error_log() found. Debug code should not normally be used in production. |  |

## `D:\wamp64\www\wpfrank-dev\wp-content\plugins\frank-dead-link-checker\includes\scanner\parsers\class-frankdlc-parser-divi.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 310 | 21 | ERROR | WordPress.WP.AlternativeFunctions.parse_url_parse_url | parse_url() is discouraged because of inconsistency in the output across PHP versions; use wp_parse_url() instead. |  |
| 311 | 22 | ERROR | WordPress.WP.AlternativeFunctions.parse_url_parse_url | parse_url() is discouraged because of inconsistency in the output across PHP versions; use wp_parse_url() instead. |  |

## `D:\wamp64\www\wpfrank-dev\wp-content\plugins\frank-dead-link-checker\includes\scanner\parsers\class-frankdlc-parser-elementor.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 198 | 21 | ERROR | WordPress.WP.AlternativeFunctions.parse_url_parse_url | parse_url() is discouraged because of inconsistency in the output across PHP versions; use wp_parse_url() instead. |  |
| 199 | 22 | ERROR | WordPress.WP.AlternativeFunctions.parse_url_parse_url | parse_url() is discouraged because of inconsistency in the output across PHP versions; use wp_parse_url() instead. |  |

## `D:\wamp64\www\wpfrank-dev\wp-content\plugins\frank-dead-link-checker\includes\scanner\parsers\class-frankdlc-parser-gutenberg.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 437 | 21 | ERROR | WordPress.WP.AlternativeFunctions.parse_url_parse_url | parse_url() is discouraged because of inconsistency in the output across PHP versions; use wp_parse_url() instead. |  |
| 438 | 22 | ERROR | WordPress.WP.AlternativeFunctions.parse_url_parse_url | parse_url() is discouraged because of inconsistency in the output across PHP versions; use wp_parse_url() instead. |  |

## `D:\wamp64\www\wpfrank-dev\wp-content\plugins\frank-dead-link-checker\includes\scanner\parsers\class-frankdlc-parser-wpbakery.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 341 | 21 | ERROR | WordPress.WP.AlternativeFunctions.parse_url_parse_url | parse_url() is discouraged because of inconsistency in the output across PHP versions; use wp_parse_url() instead. |  |
| 342 | 22 | ERROR | WordPress.WP.AlternativeFunctions.parse_url_parse_url | parse_url() is discouraged because of inconsistency in the output across PHP versions; use wp_parse_url() instead. |  |

## `D:\wamp64\www\wpfrank-dev\wp-content\plugins\frank-dead-link-checker\includes\class-frankdlc-database.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 362 | 23 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $sql used in $wpdb-&gt;get_results($sql)\n$sql assigned unsafely at line 358:\n $sql = $wpdb-&gt;prepare($sql, $values)\n$sql assigned unsafely at line 352:\n $sql = &quot;SELECT * FROM {$this-&gt;table_links} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d&quot;\n$where_clause assigned unsafely at line 330:\n $where_clause = implode(&#039; AND &#039;, $where)\n$orderby assigned unsafely at line 344:\n $orderby = in_array($args[&#039;orderby&#039;], $allowed_orderby, true) ? $args[&#039;orderby&#039;] : &#039;last_check&#039;\n$order assigned unsafely at line 345:\n $order = strtoupper($args[&#039;order&#039;]) === &#039;ASC&#039; ? &#039;ASC&#039; : &#039;DESC&#039;\n$where assigned unsafely at line 305:\n $where[] = &#039;last_check &lt;= %s&#039;\n$args[&#039;orderby&#039;] used without escaping.\n$offset assigned unsafely at line 348:\n $offset = ($args[&#039;page&#039;] - 1) * $args[&#039;per_page&#039;]\n$args[&#039;page&#039;] used without escaping.\n$args[&#039;per_page&#039;] used without escaping.\n$args[&#039;date_to&#039;] used without escaping. |  |
| 430 | 29 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $sql used in $wpdb-&gt;get_var($sql)\n$sql assigned unsafely at line 426:\n $sql = $wpdb-&gt;prepare($sql, $values)\n$sql assigned unsafely at line 422:\n $sql = &quot;SELECT COUNT(*) FROM {$this-&gt;table_links} WHERE {$where_clause}&quot;\n$where_clause assigned unsafely at line 420:\n $where_clause = implode(&#039; AND &#039;, $where)\n$where assigned unsafely at line 414:\n $where[] = &#039;(url LIKE %s OR anchor_text LIKE %s)&#039;\n$search_term assigned unsafely at line 415:\n $search_term = &#039;%&#039; . $wpdb-&gt;esc_like($args[&#039;search&#039;]) . &#039;%&#039;\n$args[&#039;search&#039;] used without escaping. |  |
| 733 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$this-&gt;table_scans} at &quot;SELECT * FROM {$this-&gt;table_scans} ORDER BY id DESC LIMIT 1&quot; |  |
| 768 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$this-&gt;table_scans} at &quot;SELECT COUNT(*) FROM {$this-&gt;table_scans} WHERE status IN (&#039;pending&#039;, &#039;running&#039;)&quot; |  |
| 785 | 13 | WARNING | WordPress.DB.PreparedSQL.InterpolatedNotPrepared | Use placeholders and $wpdb-&gt;prepare(); found interpolated variable {$this-&gt;table_scans} at &quot;SELECT * FROM {$this-&gt;table_scans} WHERE status IN (&#039;pending&#039;, &#039;running&#039;) ORDER BY id DESC LIMIT 1&quot; |  |

## `D:\wamp64\www\wpfrank-dev\wp-content\plugins\frank-dead-link-checker\includes\class-frankdlc-deactivator.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 69 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 69 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 93 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 93 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |
| 106 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.DirectQuery | Use of a direct database call is discouraged. |  |
| 106 | 9 | WARNING | WordPress.DB.DirectDatabaseQuery.NoCaching | Direct database call without caching detected. Consider using wp_cache_get() / wp_cache_set() or wp_cache_delete(). |  |

## `readme.txt`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | WARNING | readme_parser_warnings_too_many_tags | One or more tags were ignored. Please limit your plugin to 5 tags. |  |

## `D:\wamp64\www\wpfrank-dev\wp-content\plugins\frank-dead-link-checker\includes\class-frankdlc-multisite.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 240 | 49 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table_name used in $wpdb-&gt;get_var(&quot;SELECT COUNT(*) FROM {$table_name}&quot;)\n$table_name assigned unsafely at line 218:\n $table_name = $wpdb-&gt;prefix . &#039;FRANKDLC_links&#039; |  |
| 242 | 50 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table_name used in $wpdb-&gt;get_var(&quot;SELECT COUNT(*) FROM {$table_name} WHERE is_broken = 1 AND is_dismissed = 0&quot;)\n$table_name assigned unsafely at line 218:\n $table_name = $wpdb-&gt;prefix . &#039;FRANKDLC_links&#039; |  |
| 244 | 52 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table_name used in $wpdb-&gt;get_var(&quot;SELECT COUNT(*) FROM {$table_name} WHERE status_code BETWEEN 300 AND 399 AND is_dismissed = 0&quot;)\n$table_name assigned unsafely at line 218:\n $table_name = $wpdb-&gt;prefix . &#039;FRANKDLC_links&#039; |  |
| 251 | 51 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $scans_table used in $wpdb-&gt;get_var(&quot;SELECT end_time FROM {$scans_table} WHERE status = &#039;completed&#039; ORDER BY id DESC LIMIT 1&quot;)\n$scans_table assigned unsafely at line 247:\n $scans_table = $wpdb-&gt;prefix . &#039;FRANKDLC_scans&#039;\n$site_stats[&#039;last_scan&#039;] assigned unsafely at line 251:\n $site_stats[&#039;last_scan&#039;] = $wpdb-&gt;get_var( &quot;SELECT end_time FROM {$scans_table} WHERE status = &#039;completed&#039; ORDER BY id DESC LIMIT 1&quot; ) |  |

## `D:\wamp64\www\wpfrank-dev\wp-content\plugins\frank-dead-link-checker\uninstall.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 41 | 12 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table_links used in $wpdb-&gt;query(&quot;DROP TABLE IF EXISTS {$table_links}&quot;)\n$table_links assigned unsafely at line 37:\n $table_links = $wpdb-&gt;prefix . &#039;FRANKDLC_links&#039;\n$table_scans assigned unsafely at line 38:\n $table_scans = $wpdb-&gt;prefix . &#039;FRANKDLC_scans&#039; |  |
| 43 | 12 | WARNING | PluginCheck.Security.DirectDB.UnescapedDBParameter | Unescaped parameter $table_scans used in $wpdb-&gt;query(&quot;DROP TABLE IF EXISTS {$table_scans}&quot;)\n$table_scans assigned unsafely at line 38:\n $table_scans = $wpdb-&gt;prefix . &#039;FRANKDLC_scans&#039;\n$table_links assigned unsafely at line 37:\n $table_links = $wpdb-&gt;prefix . &#039;FRANKDLC_links&#039; |  |
