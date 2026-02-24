<?php
/**
 * Plugin Name: Associazioni Browser
 * Description: Drill-down browser for associazioni: category -> subcategory -> region -> associations (search, filters, pagination). Shows Regions at any level (optional) + sibling categories at leaf + improved breadcrumb + styled region accordion/list.
 * Version: 1.6.0
 */

if (!defined('ABSPATH')) exit;
if (!defined('AB_SETTORI_HERO_OVERRIDES_OPTION')) {
  define('AB_SETTORI_HERO_OVERRIDES_OPTION', 'ab_settori_hero_image_overrides');
}

/**
 * Optional override:
 *   define('AB_ASSOCIAZIONI_TABLE', 'custom_table_name');
 */
function ab_table(): string {
  global $wpdb;
  if (defined('AB_ASSOCIAZIONI_TABLE') && is_string(AB_ASSOCIAZIONI_TABLE) && AB_ASSOCIAZIONI_TABLE !== '') {
    return AB_ASSOCIAZIONI_TABLE;
  }
  // Default: prefix-aware and portable
  return $wpdb->prefix . 'associazioni';
}

/** Safe identifier wrapper for table name. */
function ab_table_sql(): string {
  $t = ab_table();
  // Remove backticks just in case, then wrap in backticks
  $t = str_replace('`', '', $t);
  return '`' . $t . '`';
}

/** Simple transient cache helper. */
function ab_cache_get(string $key) {
  return get_transient($key);
}
function ab_cache_set(string $key, $value, int $ttl_seconds): void {
  set_transient($key, $value, $ttl_seconds);
}
function ab_cache_key(string $prefix, array $parts): string {
  return $prefix . md5(wp_json_encode($parts));
}

/** Cache hierarchy detection (full scan otherwise). */
function ab_has_hierarchy(): bool {
  $cache_key = ab_cache_key('ab_has_hierarchy_', [get_current_blog_id(), ab_table()]);
  $cached = ab_cache_get($cache_key);
  if ($cached !== false) return (bool)$cached;

  global $wpdb;
  $table = ab_table_sql();
  // Uses LIKE scan; cached result avoids doing this on every view.
  $has = ((int)$wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE category LIKE '%>%'") > 0);

  ab_cache_set($cache_key, $has ? 1 : 0, 12 * HOUR_IN_SECONDS);
  return $has;
}

function ab_table_has_column(string $column): bool {
  $column = trim($column);
  if ($column === '' || !preg_match('/^[a-zA-Z0-9_]+$/', $column)) {
    return false;
  }

  $cacheKey = ab_cache_key('ab_table_col_', [get_current_blog_id(), ab_table(), $column]);
  $cached = ab_cache_get($cacheKey);
  if ($cached !== false) {
    return (bool)$cached;
  }

  global $wpdb;
  $tableSql = ab_table_sql();
  $exists = (bool)$wpdb->get_var(
    $wpdb->prepare("SHOW COLUMNS FROM {$tableSql} LIKE %s", [$column])
  );

  ab_cache_set($cacheKey, $exists ? 1 : 0, 12 * HOUR_IN_SECONDS);
  return $exists;
}

function ab_split_category(string $cat): array {
  if (strpos($cat, '>') !== false) {
    $parts = array_map('trim', explode('>', $cat));
    return array_values(array_filter($parts, fn($p) => $p !== ''));
  }
  $t = trim($cat);
  return $t === '' ? [] : [$t];
}

function ab_join_category(array $segments): string {
  $segments = array_values(array_filter(array_map('trim', $segments), fn($s) => $s !== ''));
  return implode(' > ', $segments);
}

function ab_base_url(): string {
  return get_permalink();
}

function ab_link(array $args): string {
  // add_query_arg with null removes the arg
  return esc_url(add_query_arg($args, ab_base_url()));
}

function ab_h(string $s): string {
  return esc_html($s);
}

function ab_get_qs(string $key): string {
  return isset($_GET[$key]) ? sanitize_text_field(wp_unslash($_GET[$key])) : '';
}

function ab_get_int_qs(string $key, int $default = 1): int {
  $v = ab_get_qs($key);
  $n = (int)$v;
  return $n > 0 ? $n : $default;
}

function ab_assoc_normalize_key(string $value): string {
  $value = trim($value);
  if ($value === '') return '';
  $value = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
  if (function_exists('remove_accents')) {
    $value = remove_accents($value);
  }
  $value = strtolower($value);
  $value = preg_replace('/\s+/u', ' ', $value);
  $value = preg_replace('/[^a-z0-9]+/', '-', $value);
  return trim((string)$value, '-');
}

function ab_assoc_is_placeholder_label(string $value): bool {
  $key = ab_assoc_normalize_key($value);
  if ($key === '') return true;
  return in_array($key, [
    'senza-categoria',
    'uncategorized',
    'non-classificato',
    'none',
    'na',
    'n-a',
    'null',
  ], true);
}

function ab_assoc_category_from_levels(string $macro, string $settore, string $settore2): string {
  $macro = trim($macro);
  $settore = trim($settore);
  $settore2 = trim($settore2);

  $segments = [];
  if ($macro !== '' && !ab_assoc_is_placeholder_label($macro)) {
    $segments[] = $macro;
  }
  if ($settore !== '' && !ab_assoc_is_placeholder_label($settore)) {
    $segments[] = $settore;
  }
  if ($settore2 !== '' && !ab_assoc_is_placeholder_label($settore2)) {
    $segments[] = $settore2;
  }

  if (!empty($segments)) {
    return ab_join_category($segments);
  }

  if ($settore2 !== '' && !ab_assoc_is_placeholder_label($settore2)) return $settore2;
  if ($settore !== '' && !ab_assoc_is_placeholder_label($settore)) return $settore;
  if ($macro !== '' && !ab_assoc_is_placeholder_label($macro)) return $macro;

  return '';
}

function ab_assoc_resolve_category_from_row(array $row): string {
  $existing = trim((string)($row['category'] ?? ''));
  if ($existing !== '' && !ab_assoc_is_placeholder_label($existing)) {
    return $existing;
  }

  return ab_assoc_category_from_levels(
    (string)($row['macro'] ?? ''),
    (string)($row['settore'] ?? ''),
    (string)($row['settore2'] ?? '')
  );
}

function ab_assoc_category_paths_from_row(array $row): array {
  $all = trim((string)($row['all_categories'] ?? ''));
  $source = [];
  if ($all !== '') {
    $parts = preg_split('/\s*\|\s*/', $all);
    if (is_array($parts)) {
      foreach ($parts as $part) {
        $part = trim((string)$part);
        if ($part === '' || ab_assoc_is_placeholder_label($part)) continue;
        $source[] = $part;
      }
    }
  }

  if (empty($source)) {
    $single = ab_assoc_resolve_category_from_row($row);
    if ($single !== '') $source[] = $single;
  }

  return $source;
}

function ab_assoc_collect_category_activity_labels(array $categoryPaths): array {
  $categories = [];
  $activities = [];

  foreach ($categoryPaths as $categoryPath) {
    $parts = ab_split_category((string)$categoryPath);
    $macro = isset($parts[0]) ? trim((string)$parts[0]) : '';
    $settore = isset($parts[1]) ? trim((string)$parts[1]) : '';
    $settore2 = isset($parts[2]) ? trim((string)$parts[2]) : '';

    if ($macro !== '' && !ab_assoc_is_placeholder_label($macro)) {
      $categoryLabel = $macro;
      if ($settore !== '' && !ab_assoc_is_placeholder_label($settore)) {
        $categoryLabel .= ' > ' . $settore;
      }
      $categories[ab_assoc_normalize_key($categoryLabel)] = $categoryLabel;
    }

    if ($settore2 !== '' && !ab_assoc_is_placeholder_label($settore2)) {
      $activities[ab_assoc_normalize_key($settore2)] = $settore2;
    }
  }

  $categoryLabels = array_values($categories);
  $activityLabels = array_values($activities);
  sort($categoryLabels, SORT_NATURAL | SORT_FLAG_CASE);
  sort($activityLabels, SORT_NATURAL | SORT_FLAG_CASE);

  return [
    'categories' => $categoryLabels,
    'activities' => $activityLabels,
  ];
}

function ab_assoc_source_key(string $organization, string $province = '', string $city = '', string $region = ''): string {
  $org = ab_assoc_normalize_key($organization);
  if ($org === '') return '';
  $region = ab_assoc_normalize_key($region);
  $prov = ab_assoc_normalize_key($province);
  $city = ab_assoc_normalize_key($city);
  return $org . '|' . $region . '|' . $prov . '|' . $city;
}

function ab_assoc_row_key_from_values(string $organization, string $region = '', string $province = '', string $city = '', string $category = ''): string {
  $org = ab_assoc_normalize_key($organization);
  if ($org === '') return '';

  $category = ab_assoc_normalize_key($category);
  $region = ab_assoc_normalize_key($region);
  $province = ab_assoc_normalize_key($province);
  $city = ab_assoc_normalize_key($city);

  return implode('|', [$org, $category, $region, $province, $city]);
}

function ab_assoc_row_key(array $row): string {
  $explicit = trim((string)($row['row_key'] ?? ''));
  if ($explicit !== '') {
    return $explicit;
  }

  $organization = trim((string)($row['organization'] ?? ''));
  $region = trim((string)($row['region'] ?? ''));
  $province = trim((string)($row['province'] ?? ''));
  $city = trim((string)($row['city'] ?? ''));
  $category = ab_assoc_resolve_category_from_row($row);

  return ab_assoc_row_key_from_values($organization, $region, $province, $city, $category);
}

function ab_assoc_post_csv_category(int $postId): string {
  $category = trim((string)get_post_meta($postId, '_ab_csv_category', true));
  if ($category !== '' && !ab_assoc_is_placeholder_label($category)) {
    return $category;
  }

  $macro = ab_assoc_first_meta_value($postId, ['_ab_csv_macro', 'macro', 'macro_categoria']);
  $settore = ab_assoc_first_meta_value($postId, ['_ab_csv_settore', 'settore', 'settore_1']);
  $settore2 = ab_assoc_first_meta_value($postId, ['_ab_csv_settore2', 'settore2', 'settore_2', 'sotto_settore']);

  return ab_assoc_category_from_levels($macro, $settore, $settore2);
}

function ab_assoc_post_row_key(int $postId, string $titleFallback = ''): string {
  $organization = trim((string)$titleFallback);
  if ($organization === '') {
    $organization = trim((string)get_the_title($postId));
  }
  if ($organization === '') {
    return '';
  }

  $region = ab_assoc_first_meta_value($postId, ['_ab_csv_region', 'region', 'regione']);
  $province = ab_assoc_first_meta_value($postId, ['province', '_ab_csv_province', 'provincia']);
  $city = ab_assoc_first_meta_value($postId, ['comune', 'city', '_ab_csv_city']);
  $category = ab_assoc_post_csv_category($postId);

  return ab_assoc_row_key_from_values($organization, $region, $province, $city, $category);
}

function ab_assoc_normalize_url(string $url): string {
  $url = trim($url);
  if ($url === '') return '';
  if (!preg_match('~^https?://~i', $url) && preg_match('~^[a-z0-9][a-z0-9\.\-]+\.[a-z]{2,}(/.*)?$~i', $url)) {
    $url = 'https://' . $url;
  }
  return esc_url_raw($url);
}

function ab_assoc_first_meta_value(int $postId, array $keys): string {
  foreach ($keys as $key) {
    $value = trim((string)get_post_meta($postId, (string)$key, true));
    if ($value !== '') return $value;
  }
  return '';
}

function ab_assoc_extract_emails_from_text(string $text): string {
  $text = trim($text);
  if ($text === '') return '';
  if (!preg_match_all('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $text, $matches)) {
    return '';
  }
  $emails = [];
  foreach ((array)($matches[0] ?? []) as $raw) {
    $email = sanitize_email(trim((string)$raw));
    if ($email !== '') $emails[strtolower($email)] = $email;
  }
  if (empty($emails)) return '';
  return implode(', ', array_values($emails));
}

function ab_assoc_extract_urls_from_text(string $text): array {
  $text = trim($text);
  if ($text === '') return [];

  if (!preg_match_all('/(?:https?:\/\/|www\.)[^\s<>"\'\)\]]+/i', $text, $matches)) {
    return [];
  }

  $urls = [];
  foreach ((array)($matches[0] ?? []) as $raw) {
    $token = trim((string)$raw);
    if ($token === '') continue;
    $token = rtrim($token, ".,;:!?)]}");
    $normalized = ab_assoc_normalize_url($token);
    if ($normalized === '') continue;
    $urls[$normalized] = true;
  }

  return array_keys($urls);
}

function ab_assoc_merge_emails(string $currentRaw, string $incomingRaw): string {
  $merged = [];
  foreach ([$currentRaw, $incomingRaw] as $raw) {
    $parts = preg_split('/[|,;\r\n\s]+/', (string)$raw);
    if (!is_array($parts)) continue;
    foreach ($parts as $part) {
      $email = sanitize_email(trim((string)$part));
      if ($email === '') continue;
      $merged[strtolower($email)] = $email;
    }
  }
  if (empty($merged)) return '';
  return implode(', ', array_values($merged));
}

function ab_assoc_merge_urls(string $currentRaw, string $incomingRaw): string {
  $merged = [];

  foreach ([$currentRaw, $incomingRaw] as $raw) {
    $parts = preg_split('/[\|,\;\r\n]+/', (string)$raw);
    if (!is_array($parts)) continue;
    foreach ($parts as $part) {
      $url = ab_assoc_normalize_url(trim((string)$part));
      if ($url === '') continue;
      $merged[$url] = true;
    }
  }

  if (empty($merged)) return '';
  return implode(' | ', array_keys($merged));
}

function ab_assoc_is_external_url(string $url): bool {
  $url = trim($url);
  if ($url === '') return false;

  $parsed = wp_parse_url($url);
  if (!is_array($parsed)) return false;
  $host = strtolower((string)($parsed['host'] ?? ''));
  $scheme = strtolower((string)($parsed['scheme'] ?? ''));
  if ($host === '' || ($scheme !== 'http' && $scheme !== 'https')) return false;
  $isIp = (bool)filter_var($host, FILTER_VALIDATE_IP);
  $isLocalhost = ($host === 'localhost');
  $hasPublicSuffix = (strpos($host, '.') !== false);
  if (!$isIp && !$isLocalhost && !$hasPublicSuffix) return false;

  $homeHost = strtolower((string)(wp_parse_url(home_url(), PHP_URL_HOST) ?? ''));
  if ($homeHost !== '' && $host === $homeHost) return false;

  return true;
}

function ab_assoc_parse_urls(string $urlsRaw): array {
  $parts = preg_split('/[\|,\;\r\n]+/', $urlsRaw);
  if (!is_array($parts)) $parts = [];
  $links = array_values(array_filter(array_map('trim', $parts), fn($v) => $v !== ''));
  $out = [
    'website' => '',
    'facebook' => '',
    'instagram' => '',
    'others' => [],
  ];

  foreach ($links as $raw) {
    $url = ab_assoc_normalize_url($raw);
    if ($url === '') continue;
    $lower = strtolower($url);

    if ($out['facebook'] === '' && strpos($lower, 'facebook.com') !== false) {
      $out['facebook'] = $url;
      continue;
    }
    if ($out['instagram'] === '' && strpos($lower, 'instagram.com') !== false) {
      $out['instagram'] = $url;
      continue;
    }
    if ($out['website'] === '') {
      $out['website'] = $url;
      continue;
    }
    $out['others'][] = $url;
  }

  return $out;
}

function ab_assoc_pick_best_candidate(array $candidateIds, string $region, string $province, string $city): int {
  if (empty($candidateIds)) return 0;

  $targetRegion = ab_assoc_normalize_key($region);
  $targetProv = ab_assoc_normalize_key($province);
  $targetCity = ab_assoc_normalize_key($city);
  $hasLocationContext = ($targetRegion !== '' || $targetProv !== '' || $targetCity !== '');

  if (!$hasLocationContext) {
    return count($candidateIds) === 1 ? (int)$candidateIds[0] : 0;
  }

  $bestId = (int)$candidateIds[0];
  $bestScore = -1;
  foreach ($candidateIds as $candidateId) {
    $candidateId = (int)$candidateId;
    if ($candidateId <= 0) continue;

    $regionMeta = ab_assoc_normalize_key(ab_assoc_first_meta_value($candidateId, ['region', 'regione', '_ab_csv_region']));
    $prov = ab_assoc_normalize_key((string)get_post_meta($candidateId, 'province', true));
    $comune = ab_assoc_normalize_key((string)get_post_meta($candidateId, 'comune', true));
    $cityMeta = ab_assoc_normalize_key((string)get_post_meta($candidateId, 'city', true));
    $candCity = $comune !== '' ? $comune : $cityMeta;

    $score = 0;
    if ($targetRegion !== '' && $regionMeta === $targetRegion) $score++;
    if ($targetProv !== '' && $prov === $targetProv) $score++;
    if ($targetCity !== '' && $candCity === $targetCity) $score++;

    if ($score > $bestScore) {
      $bestScore = $score;
      $bestId = $candidateId;
      if ($score >= 3) break;
    }
  }

  if ($bestScore <= 0) {
    return 0;
  }

  return $bestId;
}

function ab_assoc_find_post_id(array $row): int {
  static $initialized = false;
  static $sourceMap = [];
  static $rowKeyMap = [];
  static $nameMap = [];

  if (!post_type_exists('association')) return 0;

  global $wpdb;
  if (!$initialized) {
    $initialized = true;

    $metaKey = '_ab_source_key';
    $sqlMeta = "
      SELECT pm.meta_value AS source_key, pm.post_id
      FROM {$wpdb->postmeta} pm
      INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
      WHERE pm.meta_key = %s
        AND p.post_type = 'association'
        AND p.post_status NOT IN ('trash', 'auto-draft')
    ";
    $metaRows = $wpdb->get_results($wpdb->prepare($sqlMeta, [$metaKey]), ARRAY_A);
    foreach ($metaRows as $metaRow) {
      $sourceKey = trim((string)($metaRow['source_key'] ?? ''));
      $postId = (int)($metaRow['post_id'] ?? 0);
      if ($sourceKey !== '' && $postId > 0) {
        $sourceMap[$sourceKey] = $postId;
      }
    }

    $rowMetaKey = '_ab_row_key';
    $sqlRowMeta = "
      SELECT pm.meta_value AS row_key, pm.post_id
      FROM {$wpdb->postmeta} pm
      INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
      WHERE pm.meta_key = %s
        AND p.post_type = 'association'
        AND p.post_status NOT IN ('trash', 'auto-draft')
    ";
    $rowMetaRows = $wpdb->get_results($wpdb->prepare($sqlRowMeta, [$rowMetaKey]), ARRAY_A);
    foreach ($rowMetaRows as $rowMeta) {
      $rowKey = trim((string)($rowMeta['row_key'] ?? ''));
      $postId = (int)($rowMeta['post_id'] ?? 0);
      if ($rowKey !== '' && $postId > 0) {
        $rowKeyMap[$rowKey] = $postId;
      }
    }

    $sqlNames = "
      SELECT ID, post_title
      FROM {$wpdb->posts}
      WHERE post_type = 'association'
        AND post_status NOT IN ('trash', 'auto-draft')
    ";
    $nameRows = $wpdb->get_results($sqlNames, ARRAY_A);
    foreach ($nameRows as $nameRow) {
      $postId = (int)($nameRow['ID'] ?? 0);
      if ($postId <= 0) continue;
      $rowKey = ab_assoc_post_row_key($postId, (string)($nameRow['post_title'] ?? ''));
      if ($rowKey !== '' && !isset($rowKeyMap[$rowKey])) {
        $rowKeyMap[$rowKey] = $postId;
      }
      $nameKey = ab_assoc_normalize_key((string)($nameRow['post_title'] ?? ''));
      if ($nameKey === '') continue;
      if (!isset($nameMap[$nameKey])) $nameMap[$nameKey] = [];
      $nameMap[$nameKey][] = $postId;
    }
  }

  $organization = trim((string)($row['organization'] ?? ''));
  $province = trim((string)($row['province'] ?? ''));
  $city = trim((string)($row['city'] ?? ''));
  if ($organization === '') return 0;

  $explicitRowKey = trim((string)($row['row_key'] ?? ''));
  $rowKey = $explicitRowKey;
  if ($rowKey === '') {
    $rowKey = ab_assoc_row_key($row);
  }
  if ($rowKey !== '' && isset($rowKeyMap[$rowKey])) {
    $postId = (int)$rowKeyMap[$rowKey];
    if ($postId > 0 && get_post_type($postId) === 'association') {
      return $postId;
    }
  }

  $region = trim((string)($row['region'] ?? ''));
  $sourceKey = ab_assoc_source_key($organization, $province, $city, $region);
  if ($sourceKey !== '' && isset($sourceMap[$sourceKey])) {
    $candidateId = (int)$sourceMap[$sourceKey];
    $candidateRowKey = $candidateId > 0 ? ab_assoc_post_row_key($candidateId) : '';
    if ($candidateId > 0 && get_post_type($candidateId) === 'association' && ($explicitRowKey === '' || ($candidateRowKey !== '' && $candidateRowKey === $rowKey))) {
      if ($rowKey !== '') $rowKeyMap[$rowKey] = $candidateId;
      return $candidateId;
    }
  }

  if ($explicitRowKey !== '') {
    return 0;
  }

  $nameKey = ab_assoc_normalize_key($organization);
  if ($nameKey === '' || empty($nameMap[$nameKey])) return 0;

  $candidateIds = $nameMap[$nameKey];
  $postId = ab_assoc_pick_best_candidate($candidateIds, $region, $province, $city);
  if ($postId > 0) {
    if ($sourceKey !== '') {
      $sourceMap[$sourceKey] = $postId;
    }
    $resolvedRowKey = ab_assoc_post_row_key($postId);
    if ($resolvedRowKey !== '') {
      $rowKeyMap[$resolvedRowKey] = $postId;
    }
    return $postId;
  }

  return 0;
}

function ab_assoc_post_matches_row(int $postId, string $rowKey, string $sourceKey = ''): bool {
  if ($postId <= 0) return false;
  if (get_post_type($postId) !== 'association') return false;

  if ($rowKey === '') return true;

  $candidateRowKey = trim((string)get_post_meta($postId, '_ab_row_key', true));
  if ($candidateRowKey === '') {
    $candidateRowKey = ab_assoc_post_row_key($postId);
  }

  if ($candidateRowKey !== '' && $candidateRowKey === $rowKey) {
    return true;
  }

  // Legacy fallback: if an old source key matches and row key is not available,
  // allow updating the existing record once.
  if ($candidateRowKey === '' && $sourceKey !== '') {
    $candidateSource = trim((string)get_post_meta($postId, '_ab_source_key', true));
    if ($candidateSource !== '' && $candidateSource === $sourceKey) {
      return true;
    }
  }

  return false;
}

function ab_assoc_build_profile(array $row): array {
  $organization = trim((string)($row['organization'] ?? ''));
  $category = ab_assoc_resolve_category_from_row($row);
  $region = trim((string)($row['region'] ?? ''));
  $province = trim((string)($row['province'] ?? ''));
  $city = trim((string)($row['city'] ?? ''));
  $locationRaw = trim((string)($row['location_raw'] ?? ''));
  $emails = trim((string)($row['emails'] ?? ''));
  $notes = trim((string)($row['notes'] ?? ''));
  $urlsRaw = trim((string)($row['urls'] ?? ''));

  $links = ab_assoc_parse_urls($urlsRaw);
  $website = $links['website'];
  $facebook = $links['facebook'];
  $instagram = $links['instagram'];

  $address = '';
  $phone = '';
  $logoHtml = '';
  $logoUrl = '';
  $associationPermalink = '';
  $allCategories = trim((string)($row['all_categories'] ?? ''));
  if ($allCategories === '') {
    $allCategories = $category;
  }

  $categoryPaths = ab_assoc_category_paths_from_row($row);
  $categorySets = ab_assoc_collect_category_activity_labels($categoryPaths);
  $categoryGroups = $categorySets['categories'];
  $activities = $categorySets['activities'];
  $categoryGroupsLabel = implode(' | ', $categoryGroups);
  $activityCategories = implode(', ', $activities);

  $associationId = ab_assoc_find_post_id($row);
  if ($associationId > 0) {
    $title = trim((string)get_the_title($associationId));
    if ($title !== '') $organization = $title;

    $metaComune = ab_assoc_first_meta_value($associationId, ['comune', 'city', 'citta', 'comune_citta']);
    $metaCity = ab_assoc_first_meta_value($associationId, ['city', 'citta', 'comune']);
    $metaProvince = ab_assoc_first_meta_value($associationId, ['province', 'provincia']);
    $metaEmail = ab_assoc_first_meta_value($associationId, ['email', 'contact_email', 'email_address', 'mail', '_ab_csv_email']);
    $metaWebsite = ab_assoc_first_meta_value($associationId, ['website', 'sito', 'sito_web', 'web', 'url', '_ab_csv_website']);
    $metaFacebook = ab_assoc_first_meta_value($associationId, ['facebook', 'facebook_url', 'fb', '_ab_csv_facebook']);
    $metaInstagram = ab_assoc_first_meta_value($associationId, ['instagram', 'instagram_url', 'ig', '_ab_csv_instagram']);

    if ($metaComune !== '') {
      $city = $metaComune;
    } elseif ($metaCity !== '') {
      $city = $metaCity;
    }
    if ($metaProvince !== '') $province = $metaProvince;
    if ($metaEmail !== '') $emails = $metaEmail;
    if ($metaWebsite !== '') $website = ab_assoc_normalize_url($metaWebsite);
    if ($metaFacebook !== '') $facebook = ab_assoc_normalize_url($metaFacebook);
    if ($metaInstagram !== '') $instagram = ab_assoc_normalize_url($metaInstagram);

    $address = ab_assoc_first_meta_value($associationId, ['address', 'indirizzo']);
    $phone = ab_assoc_first_meta_value($associationId, ['phone', 'telefono', 'tel', 'mobile', 'cellulare']);
    $logoHtml = get_the_post_thumbnail($associationId, 'thumbnail', ['loading' => 'lazy']);
    $logoUrl = (string)get_the_post_thumbnail_url($associationId, 'large');
    $associationPermalink = (string)get_permalink($associationId);
  }

  $emails = ab_assoc_merge_emails($emails, '');

  if ($website !== '') {
    $websiteClassified = ab_assoc_parse_urls($website);
    if ($websiteClassified['facebook'] !== '') {
      if ($facebook === '') $facebook = $websiteClassified['facebook'];
      $website = '';
    } elseif ($websiteClassified['instagram'] !== '') {
      if ($instagram === '') $instagram = $websiteClassified['instagram'];
      $website = '';
    }
  }

  $eventsUrl = home_url('/calendario/');
  $newsUrl = home_url('/notizie/');
  if ($associationId > 0) {
    $eventsUrl = add_query_arg(['associazione_id' => $associationId], $eventsUrl);
    $newsUrl = add_query_arg(['associazione_id' => $associationId], $newsUrl);
  }

  $locParts = array_values(array_filter([$city, $province, $region], fn($v) => $v !== ''));
  $location = implode(', ', $locParts);

  $buttonLinks = [];
  $website = ab_assoc_is_external_url($website) ? $website : '';
  if ($website !== '') $buttonLinks[] = ['label' => 'Sito', 'url' => $website];
  if ($facebook !== '') $buttonLinks[] = ['label' => 'Facebook', 'url' => $facebook];
  if ($instagram !== '') $buttonLinks[] = ['label' => 'Instagram', 'url' => $instagram];

  $mapParts = array_values(array_filter([$address, $city, $province, $region], fn($v) => $v !== ''));
  if (empty($mapParts) && $locationRaw !== '') {
    $mapParts[] = $locationRaw;
  }
  if (empty($mapParts) && $location !== '') {
    $mapParts[] = $location;
  }
  if (empty($mapParts) && $organization !== '') {
    $mapParts[] = $organization;
  }
  $mapQuery = implode(', ', $mapParts);
  $mapEmbedUrl = $mapQuery !== '' ? 'https://www.google.com/maps?q=' . rawurlencode($mapQuery) . '&output=embed' : '';

  return [
    'association_id' => $associationId,
    'organization' => $organization,
    'category' => $category,
    'all_categories' => $allCategories,
    'category_groups' => $categoryGroups,
    'category_groups_label' => $categoryGroupsLabel,
    'activity_categories' => $activityCategories,
    'activities' => $activities,
    'activities_label' => $activityCategories,
    'region' => $region,
    'province' => $province,
    'city' => $city,
    'location' => $location,
    'location_raw' => $locationRaw,
    'address' => $address,
    'phone' => $phone,
    'emails' => $emails,
    'notes' => $notes,
    'website' => $website,
    'facebook' => $facebook,
    'instagram' => $instagram,
    'youtube' => '',
    'tiktok' => '',
    'x' => '',
    'links' => $buttonLinks,
    'logo_html' => $logoHtml,
    'logo_url' => $logoUrl,
    'permalink' => $associationPermalink,
    'events_url' => (string)$eventsUrl,
    'news_url' => (string)$newsUrl,
    'map_query' => $mapQuery,
    'map_embed_url' => $mapEmbedUrl,
  ];
}

function ab_assoc_render_card(array $row, string $cardClass = 'ab-card', bool $showCategoryPath = false): string {
  $profile = ab_assoc_build_profile($row);
  $organization = (string)$profile['organization'];
  $category = (string)$profile['category'];
  $allCategories = (string)$profile['all_categories'];
  $activities = is_array($profile['activities'] ?? null) ? $profile['activities'] : [];
  $activitiesLabel = (string)($profile['activities_label'] ?? '');
  $location = (string)$profile['location'];
  $address = (string)$profile['address'];
  $phone = (string)$profile['phone'];
  $emails = (string)$profile['emails'];
  $notes = (string)$profile['notes'];
  $logoHtml = (string)$profile['logo_html'];
  $buttonLinks = is_array($profile['links']) ? $profile['links'] : [];

  $modalData = [
    'organization' => $organization,
    'logo_url' => (string)$profile['logo_url'],
    'location' => $location,
    'category' => $category,
    'all_categories' => $allCategories,
    'category_groups' => is_array($profile['category_groups'] ?? null) ? $profile['category_groups'] : [],
    'category_groups_label' => (string)($profile['category_groups_label'] ?? ''),
    'activity_categories' => (string)$profile['activity_categories'],
    'activities' => $activities,
    'activities_label' => $activitiesLabel,
    'region' => (string)$profile['region'],
    'province' => (string)$profile['province'],
    'city' => (string)$profile['city'],
    'location_raw' => (string)$profile['location_raw'],
    'address' => $address,
    'phone' => $phone,
    'emails' => $emails,
    'notes' => $notes,
    'website' => (string)$profile['website'],
    'facebook' => (string)$profile['facebook'],
    'instagram' => (string)$profile['instagram'],
    'youtube' => (string)$profile['youtube'],
    'tiktok' => (string)$profile['tiktok'],
    'x' => (string)$profile['x'],
    'links' => $buttonLinks,
    'permalink' => (string)$profile['permalink'],
    'events_url' => (string)$profile['events_url'],
    'news_url' => (string)$profile['news_url'],
    'map_embed_url' => (string)$profile['map_embed_url'],
    'map_query' => (string)$profile['map_query'],
  ];

  $out = '<article class="' . esc_attr($cardClass . ' ab-assoc-card') . '" data-ab-assoc-card="1" tabindex="0" role="button" aria-label="' . esc_attr('Apri dettagli di ' . $organization) . '" aria-haspopup="dialog">';

  if ($logoHtml !== '') {
    $out .= '<div class="ab-assoc-head">';
    $out .= '<div class="ab-assoc-logo">' . $logoHtml . '</div>';
    $out .= '<div class="ab-assoc-name-wrap">';
    $out .= '<h4>' . ab_h($organization) . '</h4>';
    if ($location !== '') $out .= '<div class="ab-muted">' . ab_h($location) . '</div>';
    $out .= '</div></div>';
  } else {
    $out .= '<h4>' . ab_h($organization) . '</h4>';
    if ($location !== '') $out .= '<div class="ab-muted">' . ab_h($location) . '</div>';
  }

  if (!empty($activities)) {
    $out .= '<div class="ab-assoc-tags">';
    foreach ($activities as $activity) {
      $out .= '<span class="ab-assoc-tag">' . ab_h((string)$activity) . '</span>';
    }
    $out .= '</div>';
  }

  if ($address !== '') {
    $out .= '<div style="margin-top:8px"><strong>Indirizzo:</strong> ' . ab_h($address) . '</div>';
  }
  if ($phone !== '') {
    $out .= '<div style="margin-top:8px"><strong>Telefono:</strong> ' . ab_h($phone) . '</div>';
  }

  if ($notes !== '') {
    $out .= '<div style="margin-top:8px">' . ab_h($notes) . '</div>';
  }
  if ($showCategoryPath) {
    $pathLabel = $allCategories !== '' ? $allCategories : $category;
    if ($pathLabel !== '') {
      $out .= '<div class="abf-path">' . ab_h($pathLabel) . '</div>';
    }
  }

  $json = wp_json_encode($modalData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
  if (is_string($json) && $json !== '') {
    $out .= '<script type="application/json" class="ab-assoc-data">' . $json . '</script>';
  }

  $out .= '</article>';
  return $out;
}

function ab_css(): string {
  return "
  .ab-wrap{max-width:1100px}
  .ab-breadcrumb{margin:0 0 12px 0; font-size:14px; display:flex; flex-wrap:wrap; gap:6px; align-items:center}
  .ab-breadcrumb a{text-decoration:none}
  .ab-crumb-sep{opacity:.6}
  .ab-grid{display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:12px}
  .ab-card{border:1px solid rgba(0,0,0,.12); border-radius:12px; padding:12px; background:#fff}
  .ab-card h4{margin:0 0 6px 0; font-size:16px}
  .ab-assoc-card{cursor:pointer; transition:border-color .14s ease, box-shadow .14s ease}
  .ab-assoc-card:hover{border-color:var(--abf-accent-color, var(--global-palette1, #2B6CB0)); box-shadow:0 14px 24px -18px rgba(26, 32, 44, .55)}
  .ab-assoc-card:focus-visible{outline:none; border-color:var(--abf-accent-color, var(--global-palette1, #2B6CB0)); box-shadow:0 0 0 3px rgba(43,108,176,.18)}
  .ab-assoc-head{display:flex; align-items:flex-start; gap:10px}
  .ab-assoc-logo{width:64px; min-width:64px; height:64px; border-radius:10px; overflow:hidden; border:1px solid rgba(0,0,0,.12); background:#fff; display:flex; align-items:center; justify-content:center}
  .ab-assoc-logo img{width:100%; height:100%; object-fit:cover; display:block}
  .ab-assoc-name-wrap{min-width:0}
  .ab-assoc-name-wrap h4{margin:0 0 6px 0}
  .ab-assoc-tags{display:flex; flex-wrap:wrap; gap:6px; margin-top:10px}
  .ab-assoc-tag{
    display:inline-flex;
    align-items:center;
    padding:3px 8px;
    border-radius:999px;
    border:1px solid var(--abf-border-color, var(--global-palette7, #E2E8F0));
    background:var(--abf-control-bg-soft, var(--global-palette8, #F7FAFC));
    color:var(--abf-text-color, var(--global-palette3, #1A202C));
    font-size:.74rem;
    font-weight:600;
    line-height:1.25
  }
  .ab-assoc-links{display:flex; flex-wrap:wrap; gap:6px; margin-top:8px}
  .ab-assoc-links .ab-btn{padding:7px 10px}
  .ab-muted{opacity:.75; font-size:13px}
  .ab-list{margin:0; padding-left:18px}
  .ab-toolbar{display:flex; flex-wrap:wrap; gap:8px; align-items:end; margin:10px 0 14px}
  .ab-field{display:flex; flex-direction:column; gap:4px}
  .ab-field label{font-size:12px; opacity:.8}
  .ab-field input, .ab-field select{padding:8px 10px; border:1px solid rgba(0,0,0,.2); border-radius:10px; min-width:220px}
  .ab-btn{display:inline-block; padding:9px 12px; border-radius:10px; border:1px solid rgba(0,0,0,.2); text-decoration:none; background:#fff}
  .ab-btn-primary{font-weight:600}
  .ab-pager{display:flex; gap:10px; align-items:center; margin-top:14px}

  /* Region list / accordion styled like your screenshot (rows with a left bar + divider lines) */
  .ab-regions{border:1px solid rgba(0,0,0,.12); border-radius:12px; overflow:hidden; background:#fff; margin:10px 0}
  .ab-regions details{border-top:1px solid rgba(0,0,0,.08)}
  .ab-regions details:first-child{border-top:0}
  .ab-regions summary{cursor:pointer; list-style:none; display:flex; align-items:center; justify-content:space-between; gap:12px; padding:12px 14px; position:relative}
  .ab-regions summary::-webkit-details-marker{display:none}
  .ab-regions summary::before{content:''; position:absolute; left:0; top:0; bottom:0; width:4px; background:#0b4aa2;} /* accent bar */
  .ab-regions .ab-reg-name{padding-left:10px; font-weight:600}
  .ab-regions .ab-reg-count{font-size:13px; opacity:.7}
  .ab-regions .ab-reg-body{padding:12px 14px; background:rgba(0,0,0,.02)}
  .ab-regions .ab-inline-actions{margin-top:10px}

  /* Settori dropdown filter UI */
  .abf-wrap{
    --abf-accent-color: var(--abf-accent, var(--global-palette1, #2B6CB0));
    --abf-text-color: var(--global-palette3, #1A202C);
    --abf-muted-color: var(--global-palette5, #4A5568);
    --abf-border-color: var(--global-palette7, #E2E8F0);
    --abf-control-bg: var(--global-palette9, #ffffff);
    --abf-control-bg-soft: var(--global-palette8, #F7FAFC);
    max-width:var(--abf-max-width,1100px);
    color:var(--abf-text-color);
    font-family:var(--global-body-font-family, inherit);
    font-size:var(--global-body-font-size, 1rem);
    line-height:var(--global-body-line-height, 1.65)
  }
  .abf-wrap h3{
    margin:0 0 var(--wp--preset--spacing--40, 1rem) 0;
    color:var(--abf-accent-color);
    font-size:clamp(1.2rem, 1.1rem + .35vw, 1.45rem);
    font-weight:700
  }
  .abf-selected-breadcrumb{
    display:flex;
    flex-wrap:wrap;
    align-items:center;
    gap:8px;
    margin:0 0 var(--wp--preset--spacing--40, 1rem) 0;
    padding:10px 12px;
    border:1px solid var(--abf-border-color);
    border-radius:10px;
    background:var(--abf-control-bg);
    color:var(--abf-text-color);
    font-size:.92rem
  }
  .abf-selected-breadcrumb .abf-selected-label{
    color:var(--abf-muted-color);
    font-weight:600
  }
  .abf-selected-breadcrumb .abf-selected-sep{
    color:var(--abf-muted-color);
    opacity:.85
  }
  .abf-selected-breadcrumb .abf-selected-item{
    display:inline-flex;
    align-items:center;
    padding:3px 8px;
    border:1px solid var(--abf-border-color);
    border-radius:999px;
    background:var(--abf-control-bg-soft);
    color:var(--abf-text-color);
    font-size:.84rem;
    line-height:1.2;
    font-weight:600
  }
  .abf-toolbar{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(190px,1fr));
    gap:var(--wp--preset--spacing--40, 1rem);
    margin:var(--wp--preset--spacing--40, 1rem) 0 var(--wp--preset--spacing--50, 1.5rem);
    padding:var(--wp--preset--spacing--40, 1rem);
    border:1px solid var(--abf-border-color);
    border-radius:12px;
    background:var(--abf-control-bg-soft)
  }
  .abf-field{display:flex; flex-direction:column; gap:6px}
  .abf-field label{font-size:12px; color:var(--abf-muted-color); font-weight:600; letter-spacing:.02em; text-transform:uppercase}
  .abf-field select{
    min-height:44px;
    padding:10px 38px 10px 12px;
    border:1px solid var(--abf-border-color);
    border-radius:8px;
    min-width:0;
    width:100%;
    color:var(--abf-text-color);
    background-color:var(--abf-control-bg);
    background-image:url(\"data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' viewBox='0 0 12 8'%3E%3Cpath d='M1 1l5 5 5-5' fill='none' stroke='%23222' stroke-width='1.8' stroke-linecap='round'/%3E%3C/svg%3E\");
    background-repeat:no-repeat;
    background-position:right 12px center;
    background-size:12px 8px;
    appearance:none;
    -webkit-appearance:none;
    cursor:pointer;
    transition:border-color .18s ease, box-shadow .18s ease, background-color .18s ease
  }
  .abf-field select:focus{outline:none; border-color:var(--abf-accent-color); box-shadow:0 0 0 3px rgba(43,108,176,.14)}
  .abf-field select:disabled{opacity:.6; cursor:not-allowed; background-color:var(--abf-control-bg-soft)}
  .abf-field-reset{justify-content:flex-end}
  .abf-field-reset label{visibility:hidden}
  .abf-field-reset .abf-btn{
    min-height:44px;
    display:flex;
    align-items:center;
    justify-content:center
  }
  .abf-btn{
    display:inline-block;
    padding:.62em 1.05em;
    border-radius:8px;
    border:1px solid var(--abf-border-color);
    text-decoration:none;
    background:var(--abf-control-bg);
    color:var(--abf-text-color);
    font-family:inherit;
    font-size:.95rem;
    font-weight:600;
    cursor:pointer;
    transition:all .2s ease
  }
  .abf-btn:hover,.abf-btn:focus-visible{outline:none; border-color:var(--abf-accent-color); color:var(--abf-accent-color)}
  .abf-btn-primary{
    color:var(--global-palette-btn, var(--global-palette9, #fff));
    background:var(--global-palette-btn-bg, var(--abf-accent-color));
    border-color:var(--global-palette-btn-bg, var(--abf-accent-color))
  }
  .abf-btn-primary:hover,.abf-btn-primary:focus-visible{
    color:var(--global-palette-btn-hover, var(--global-palette9, #fff));
    background:var(--global-palette2, #215387);
    border-color:var(--global-palette2, #215387)
  }
  .abf-results{margin-top:var(--wp--preset--spacing--40, 1rem)}
  .abf-results > .ab-muted{color:var(--abf-muted-color); font-weight:600}
  .abf-grid{display:grid; grid-template-columns:repeat(auto-fill,minmax(270px,1fr)); gap:var(--wp--preset--spacing--40, 1rem)}
  .abf-card{
    border:1px solid var(--abf-card-border, var(--abf-border-color));
    border-radius:var(--abf-card-radius,12px);
    padding:14px;
    background:var(--abf-card-bg, var(--abf-control-bg));
    box-shadow:0 10px 18px -16px rgba(26,32,44,.55)
  }
  .abf-card h4{margin:0 0 6px 0; font-size:1.02rem; color:var(--abf-text-color)}
  .abf-meta{font-size:.86rem; color:var(--abf-muted-color)}
  .abf-path{font-size:.78rem; color:var(--abf-muted-color); margin-top:9px}
  .abf-wrap .ab-assoc-links .ab-btn{
    border-color:var(--abf-border-color);
    background:var(--abf-control-bg-soft);
    color:var(--abf-text-color)
  }
  .abf-wrap .ab-assoc-links .ab-btn:hover,
  .abf-wrap .ab-assoc-links .ab-btn:focus-visible{
    outline:none;
    border-color:var(--abf-accent-color);
    color:var(--abf-accent-color);
    background:var(--abf-control-bg)
  }
  .abf-pager{display:flex; flex-direction:column; align-items:center; gap:10px; margin-top:var(--wp--preset--spacing--50, 1.5rem); padding-top:6px}
  .abf-pager-nav{display:flex; flex-wrap:wrap; align-items:center; justify-content:center; gap:10px}
  .abf-pager .ab-muted{color:var(--abf-muted-color)}
  .abf-page-list{display:flex; flex-wrap:wrap; align-items:center; justify-content:center; gap:12px}
  .abf-page-dot{
    width:12px;
    height:12px;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border-radius:999px;
    border:1px solid var(--global-palette6, #A0AEC0);
    background:var(--global-palette7, #E2E8F0);
    text-decoration:none;
    transition:all .18s ease
  }
  .abf-page-dot:hover,.abf-page-dot:focus-visible{
    outline:none;
    border-color:var(--abf-accent-color);
    background:color-mix(in srgb, var(--abf-accent-color) 18%, var(--abf-control-bg-soft))
  }
  .abf-page-dot.is-current{
    border-color:var(--abf-accent-color);
    background:var(--abf-accent-color)
  }
  .abf-sr-only{
    position:absolute!important;
    width:1px;
    height:1px;
    padding:0;
    margin:-1px;
    overflow:hidden;
    clip:rect(0,0,0,0);
    white-space:nowrap;
    border:0
  }
  .abf-wrap.abf-loading{opacity:.62; transition:opacity .16s ease}
  .abf-wrap.abf-loading *{pointer-events:none}

  /* Association modal */
  body.ab-assoc-modal-open{overflow:hidden}
  .ab-assoc-modal-backdrop{position:fixed; inset:0; background:rgba(0,0,0,.56); z-index:2147483001 !important; display:none; align-items:center; justify-content:center; padding:14px}
  .ab-assoc-modal-backdrop.is-open{display:flex}
  .ab-assoc-modal{width:min(920px,calc(100vw - 28px)); max-height:calc(100vh - 28px); overflow:auto; background:var(--global-palette9, #fff); border-radius:14px; box-shadow:0 20px 40px rgba(0,0,0,.26); color:var(--global-palette3, #1A202C); font-family:var(--global-body-font-family, inherit)}
  .ab-assoc-modal-header{display:flex; justify-content:space-between; align-items:flex-start; gap:10px; padding:14px 16px; border-bottom:1px solid var(--global-palette7, #E2E8F0)}
  .ab-assoc-modal-title-wrap{display:flex; gap:10px; align-items:flex-start}
  .ab-assoc-modal-logo{width:64px; height:64px; border-radius:10px; overflow:hidden; border:1px solid rgba(0,0,0,.12); background:#fff; display:none}
  .ab-assoc-modal-logo.is-visible{display:block}
  .ab-assoc-modal-logo img{width:100%; height:100%; object-fit:cover; display:block}
  .ab-assoc-modal-title{margin:0; font-size:20px; line-height:1.2; color:var(--global-palette1, #2B6CB0)}
  .ab-assoc-modal-subtitle{margin-top:4px; color:var(--global-palette5, #4A5568); font-size:13px}
  .ab-assoc-modal-close{
    border:1px solid var(--global-palette6, #CBD5E0);
    background:var(--global-palette8, #F7FAFC);
    border-radius:8px;
    padding:6px 10px;
    cursor:pointer;
    color:var(--global-palette3, #1A202C);
    font-weight:700
  }
  .ab-assoc-modal-close:hover,.ab-assoc-modal-close:focus-visible{
    outline:none;
    border-color:var(--global-palette1, #2B6CB0);
    color:var(--global-palette1, #2B6CB0);
    background:var(--global-palette9, #fff)
  }
  .ab-assoc-modal-body{display:grid; grid-template-columns:1fr 1fr; gap:14px; padding:14px 16px 16px}
  .ab-assoc-modal-box{border:1px solid var(--global-palette7, #E2E8F0); border-radius:12px; padding:12px; background:var(--global-palette8, #F7FAFC)}
  .ab-assoc-modal-box h5{margin:0 0 10px 0; font-size:14px}
  .ab-assoc-modal-fields{display:grid; grid-template-columns:1fr; gap:7px}
  .ab-assoc-modal-field{display:flex; gap:8px; font-size:14px}
  .ab-assoc-modal-field-label{font-weight:600; min-width:130px}
  .ab-assoc-modal-links{display:flex; flex-wrap:wrap; gap:7px; margin-top:8px}
  .ab-assoc-modal-links:empty{display:none}
  .ab-assoc-modal-map iframe{width:100%; min-height:280px; border:0; border-radius:10px}
  @media (max-width: 860px){
    .ab-assoc-modal-body{grid-template-columns:1fr}
    .ab-assoc-modal-field{flex-direction:column; gap:2px}
    .ab-assoc-modal-field-label{min-width:0}
  }
  ";
}

/** Enqueue assets only when shortcode renders (avoids repeated inline <style>). */
function ab_enqueue_assets(): void {
  static $done = false;
  if ($done) return;
  $done = true;

  $handle = 'associazioni-browser';
  wp_register_style($handle, false, [], '1.6.0');
  wp_enqueue_style($handle);
  wp_add_inline_style($handle, ab_css());

  $scriptHandle = 'associazioni-browser-live';
  wp_register_script(
    $scriptHandle,
    plugins_url('assets/js/settori-browser-live.js', __FILE__),
    [],
    '1.7.8',
    true
  );
  wp_enqueue_script($scriptHandle);

  $heroMap = abf_get_hero_image_map();
  $heroOverrides = abf_get_hero_override_url_map();
  $heroCfg = [
    'enabled' => !empty($heroMap) || !empty($heroOverrides),
    'map' => $heroMap,
    'overrides' => $heroOverrides,
  ];
  wp_add_inline_script(
    $scriptHandle,
    'window.AB_SETTORI_HERO = ' . wp_json_encode($heroCfg) . ';',
    'before'
  );
}

/** Query-key helper to allow multiple shortcodes on one page without collisions. */
function ab_qkey(string $id, string $suffix): string {
  $id = preg_replace('~[^a-zA-Z0-9_]~', '_', $id);
  if ($id === '') $id = 'ab';
  return $id . '_' . $suffix; // e.g. ab_path, ab_region
}

/**
 * Breadcrumb: Home > Root > Seg1 > Seg2 > ... > (Region)
 * Root and segment links are stable and always clear region/filters/page.
 */
function ab_render_breadcrumb(string $id, string $root, array $selected_segments, string $path_rel, string $region): string {
  $k_path   = ab_qkey($id, 'path');
  $k_region = ab_qkey($id, 'region');
  $k_q      = ab_qkey($id, 'q');
  $k_city   = ab_qkey($id, 'city');
  $k_prov   = ab_qkey($id, 'prov');
  $k_page   = ab_qkey($id, 'page');

  $parts = [];

  // Home always clears all (for this instance only)
  $parts[] = '<a href="' . ab_link([
    $k_path => null, $k_region => null, $k_q => null, $k_city => null, $k_prov => null, $k_page => null
  ]) . '">Inizio</a>';

  // Root crumb
  if ($root !== '') {
    $parts[] = '<span class="ab-crumb-sep">›</span>';
    $parts[] = '<a href="' . ab_link([
      $k_path => null, $k_region => null, $k_q => null, $k_city => null, $k_prov => null, $k_page => null
    ]) . '">' . ab_h($root) . '</a>';
  }

  // Each segment crumb
  $acc = [];
  foreach ($selected_segments as $seg) {
    $acc[] = $seg;
    $p = ab_join_category($acc);

    $parts[] = '<span class="ab-crumb-sep">›</span>';
    $parts[] = '<a href="' . ab_link([
      $k_path => $p,
      $k_region => null, $k_q => null, $k_city => null, $k_prov => null, $k_page => null
    ]) . '">' . ab_h($seg) . '</a>';
  }

  if ($region !== '') {
    $parts[] = '<span class="ab-crumb-sep">›</span>';
    $parts[] = '<span class="ab-muted">' . ab_h($region) . '</span>';
  }

  return '<div class="ab-breadcrumb">' . implode('', $parts) . '</div>';
}

/**
 * Render Regions for a subtree rooted at $leaf_full (full DB prefix),
 * linking to region view using relative UI path $path_rel.
 *
 * $mode:
 *   - "links"  : simple list of region links
 *   - "rows"   : rows list (no expand)
 *   - "accordion": expandable rows with preview cards + "View all" button
 */
function ab_render_regions_subtree(
  string $id,
  string $table_sql,
  string $leaf_full,
  string $leaf_like,
  string $path_rel,
  string $mode,
  int $accordion_limit
): string {
  global $wpdb;

  $k_path   = ab_qkey($id, 'path');
  $k_region = ab_qkey($id, 'region');
  $k_q      = ab_qkey($id, 'q');
  $k_city   = ab_qkey($id, 'city');
  $k_prov   = ab_qkey($id, 'prov');
  $k_page   = ab_qkey($id, 'page');

  if (trim($leaf_full) === '') {
    return '<h3 style="margin-top:18px">Regioni</h3><p>Nessuna regione trovata per questa categoria.</p>';
  }

  $sql_regions = "
    SELECT TRIM(region) AS region, COUNT(*) as cnt
    FROM {$table_sql}
    WHERE (category = %s OR category LIKE %s)
      AND TRIM(COALESCE(region,'')) <> ''
    GROUP BY TRIM(region)
    ORDER BY TRIM(region) ASC
  ";
  $regions = $wpdb->get_results($wpdb->prepare($sql_regions, [$leaf_full, $leaf_like]), ARRAY_A);

  $out = '<h3 style="margin-top:18px">Regioni</h3>';
  if (empty($regions)) {
    $out .= '<p>Nessuna regione trovata per questa categoria.</p>';
    return $out;
  }

  if ($mode === 'links') {
    $out .= '<ul class="ab-list">';
    foreach ($regions as $r) {
      $reg = (string)$r['region'];
      $cnt = (int)$r['cnt'];
      $out .= '<li><a href="' . ab_link([
        $k_path => $path_rel ?: null,
        $k_region => $reg,
        $k_q => null, $k_city => null, $k_prov => null, $k_page => null
      ]) . '">' . ab_h($reg) . '</a> <span class="ab-muted">(' . ab_h((string)$cnt) . ')</span></li>';
    }
    $out .= '</ul>';
    return $out;
  }

  $out .= '<div class="ab-regions">';

  if ($mode === 'rows') {
    foreach ($regions as $r) {
      $reg = (string)$r['region'];
      $cnt = (int)$r['cnt'];

      $out .= '<div style="border-top:1px solid rgba(0,0,0,.08)">';
      $out .= '<a style="display:flex; justify-content:space-between; gap:12px; padding:12px 14px; position:relative; text-decoration:none" href="' . ab_link([
        $k_path => $path_rel ?: null,
        $k_region => $reg,
        $k_q => null, $k_city => null, $k_prov => null, $k_page => null
      ]) . '">';
      $out .= '<span class="ab-reg-name" style="position:relative">';
      $out .= '<span style="position:absolute; left:-14px; top:-12px; bottom:-12px; width:4px; background:#0b4aa2;"></span>';
      $out .= ab_h($reg) . '</span>';
      $out .= '<span class="ab-reg-count">' . ab_h((string)$cnt) . '</span>';
      $out .= '</a>';
      $out .= '</div>';
    }
    $out .= '</div>';
    return $out;
  }

  // Accordion (expandable)
  foreach ($regions as $r) {
    $reg = (string)$r['region'];
    $cnt = (int)$r['cnt'];

    $sql_preview = "
      SELECT organization, category, region, city, province, location_raw, urls, emails, notes, source_block
      FROM {$table_sql}
      WHERE (category = %s OR category LIKE %s)
        AND TRIM(region) = %s
      ORDER BY organization ASC
      LIMIT %d
    ";
    $preview = $wpdb->get_results($wpdb->prepare($sql_preview, [$leaf_full, $leaf_like, $reg, $accordion_limit]), ARRAY_A);

    $out .= '<details>';
    $out .= '<summary><span class="ab-reg-name">' . ab_h($reg) . '</span><span class="ab-reg-count">' . ab_h((string)$cnt) . '</span></summary>';
    $out .= '<div class="ab-reg-body">';

    if (!empty($preview)) {
      $out .= '<div class="ab-grid">';
      foreach ($preview as $row) {
        $out .= ab_assoc_render_card($row, 'ab-card', false);
      }
      $out .= '</div>';
    } else {
      $out .= '<div class="ab-muted">Nessuna associazione.</div>';
    }

    $out .= '<div class="ab-inline-actions">';
    $out .= '<a class="ab-btn ab-btn-primary" href="' . ab_link([
      $k_path => $path_rel ?: null,
      $k_region => $reg,
      $k_q => null, $k_city => null, $k_prov => null, $k_page => null
    ]) . '">Vedi tutte in ' . ab_h($reg) . '</a>';
    $out .= '</div>';

    $out .= '</div></details>';
  }

  $out .= '</div>';
  return $out;
}

function ab_shortcode($atts): string {
  global $wpdb;

  $atts = shortcode_atts([
    'id' => 'ab',                 // NEW: instance id to avoid query-arg collisions
    'root' => '',                 // e.g. "Arte"
    'title' => '',
    'per_page' => 25,             // associations per page (region view)
    'mode' => 'rows',             // "rows" (recommended), "accordion", or "links"
    'accordion_limit' => 10,      // preview cards per region (accordion mode)
    'show_regions_always' => 1,   // 1: show Regions even when subcategories exist
    'show_leaf_siblings' => 1,    // 1: at leaf level, show sibling categories list
  ], $atts, 'associazioni_browser');

  $id = (string)$atts['id'];
  if ($id === '') $id = 'ab';

  // Query keys for this instance
  $k_path   = ab_qkey($id, 'path');
  $k_region = ab_qkey($id, 'region');
  $k_q      = ab_qkey($id, 'q');
  $k_city   = ab_qkey($id, 'city');
  $k_prov   = ab_qkey($id, 'prov');
  $k_page   = ab_qkey($id, 'page');

  // Enqueue CSS properly
  ab_enqueue_assets();

  $table_sql = ab_table_sql();
  $has_hierarchy = ab_has_hierarchy();

  // Navigation query args
  $path   = ab_get_qs($k_path);     // relative path when root used
  $region = ab_get_qs($k_region);

  // UI query args (filters)
  $q      = ab_get_qs($k_q);
  $city   = ab_get_qs($k_city);
  $prov   = ab_get_qs($k_prov);
  $page   = ab_get_int_qs($k_page, 1);

  $per_page = (int)$atts['per_page'];
  if ($per_page < 5) $per_page = 5;
  if ($per_page > 200) $per_page = 200;

  $mode = strtolower(trim((string)$atts['mode']));
  if (!in_array($mode, ['links', 'accordion', 'rows'], true)) $mode = 'rows';
  // Performance: 'accordion' fires one SQL query per region (N+1 problem) which
  // causes 30+ second timeouts when the associazioni table is large.
  // Remap it to 'rows' which uses a single lightweight query for all regions.
  if ($mode === 'accordion') $mode = 'rows';

  $accordion_limit = (int)$atts['accordion_limit'];
  if ($accordion_limit < 1) $accordion_limit = 10;
  if ($accordion_limit > 200) $accordion_limit = 200;

  $show_regions_always = ((string)$atts['show_regions_always'] !== '0');
  $show_leaf_siblings  = ((string)$atts['show_leaf_siblings'] !== '0');

  $root = trim((string)$atts['root']);

  // ab_path segments are RELATIVE to root if root is provided
  $selected_segments = $path ? ab_split_category($path) : [];
  $full_segments = $selected_segments;
  if ($root !== '') array_unshift($full_segments, $root);

  $current_prefix = $selected_segments ? ab_join_category($selected_segments) : '';   // relative
  $current_full_prefix = $full_segments ? ab_join_category($full_segments) : '';      // DB full

  // Base where for subtree (for association view)
  $where = "1=1";
  $params = [];

  if ($root !== '') {
    if ($has_hierarchy) {
      $where .= " AND (category = %s OR category LIKE %s)";
      $params[] = $root;
      $params[] = $wpdb->esc_like($root . ' > ') . '%';
    } else {
      $where .= " AND category LIKE %s";
      $params[] = $wpdb->esc_like($root) . '%';
    }
  }

  if ($current_full_prefix !== '') {
    if ($has_hierarchy) {
      $where .= " AND (category = %s OR category LIKE %s)";
      $params[] = $current_full_prefix;
      $params[] = $wpdb->esc_like($current_full_prefix . ' > ') . '%';
    } else {
      $where .= " AND category = %s";
      $params[] = $current_full_prefix;
    }
  }

  $out = '<div class="ab-wrap">';

  if (!empty($atts['title'])) {
    $out .= '<h2>' . ab_h((string)$atts['title']) . '</h2>';
  }

  // Breadcrumb
  $out .= ab_render_breadcrumb($id, $root, $selected_segments, $path, $region);

  // -----------------------
  // Associations view (region selected)
  // -----------------------
  if ($region !== '') {

    $where2 = $where . " AND TRIM(region) = %s";
    $params2 = array_merge($params, [$region]);

    if ($q !== '') {
      $like = '%' . $wpdb->esc_like($q) . '%';
      $where2 .= " AND (organization LIKE %s OR city LIKE %s OR province LIKE %s OR notes LIKE %s)";
      array_push($params2, $like, $like, $like, $like);
    }
    if ($city !== '') {
      $where2 .= " AND TRIM(city) = %s";
      $params2[] = $city;
    }
    if ($prov !== '') {
      $where2 .= " AND TRIM(province) = %s";
      $params2[] = $prov;
    }

    $sql_count = "SELECT COUNT(*) FROM {$table_sql} WHERE {$where2}";
    $total = (int)$wpdb->get_var($wpdb->prepare($sql_count, $params2));

    $pages = max(1, (int)ceil($total / $per_page));
    if ($page > $pages) $page = $pages;
    $offset = ($page - 1) * $per_page;

    $sql_rows = "
      SELECT organization, category, region, city, province, location_raw, urls, emails, notes, source_block
      FROM {$table_sql}
      WHERE {$where2}
      ORDER BY organization ASC
      LIMIT %d OFFSET %d
    ";
    $params_rows = array_merge($params2, [$per_page, $offset]);
    $rows = $wpdb->get_results($wpdb->prepare($sql_rows, $params_rows), ARRAY_A);

    $sql_cities = "SELECT DISTINCT TRIM(city) AS city FROM {$table_sql} WHERE {$where2} AND TRIM(COALESCE(city,'')) <> '' ORDER BY TRIM(city) ASC";
    $cities = $wpdb->get_col($wpdb->prepare($sql_cities, $params2));

    $sql_prov = "SELECT DISTINCT TRIM(province) AS province FROM {$table_sql} WHERE {$where2} AND TRIM(COALESCE(province,'')) <> '' ORDER BY TRIM(province) ASC";
    $provs = $wpdb->get_col($wpdb->prepare($sql_prov, $params2));

    $out .= '<h3>' . ab_h($region) . '</h3>';
    $out .= '<div class="ab-muted" style="margin:-6px 0 10px 0;">' . ab_h((string)$total) . ' associazioni</div>';

    // Search toolbar
    $out .= '<form method="get" class="ab-toolbar">';
    $out .= '<input type="hidden" name="' . esc_attr($k_path) . '" value="' . esc_attr($path) . '">';
    $out .= '<input type="hidden" name="' . esc_attr($k_region) . '" value="' . esc_attr($region) . '">';
    $out .= '<input type="hidden" name="' . esc_attr($k_page) . '" value="1">';

    $out .= '<div class="ab-field"><label>Cerca</label><input name="' . esc_attr($k_q) . '" value="' . esc_attr($q) . '" placeholder="Nome, citta, note..."></div>';

    $out .= '<div class="ab-field"><label>Citta</label><select name="' . esc_attr($k_city) . '"><option value="">Tutte</option>';
    foreach ($cities as $c) {
      $sel = ($c === $city) ? ' selected' : '';
      $out .= '<option value="' . esc_attr($c) . '"' . $sel . '>' . ab_h($c) . '</option>';
    }
    $out .= '</select></div>';

    $out .= '<div class="ab-field"><label>Provincia</label><select name="' . esc_attr($k_prov) . '"><option value="">Tutte</option>';
    foreach ($provs as $p) {
      $sel = ($p === $prov) ? ' selected' : '';
      $out .= '<option value="' . esc_attr($p) . '"' . $sel . '>' . ab_h($p) . '</option>';
    }
    $out .= '</select></div>';

    $out .= '<div class="ab-field"><label>&nbsp;</label><button class="ab-btn ab-btn-primary" type="submit">Applica</button></div>';

    $out .= '<div class="ab-field"><label>&nbsp;</label><a class="ab-btn" href="' . ab_link([
      $k_path => $path ?: null, $k_region => $region, $k_q => null, $k_city => null, $k_prov => null, $k_page => null
    ]) . '">Pulisci</a></div>';

    $out .= '</form>';

    if (empty($rows)) {
      $out .= '<p>Nessuna associazione trovata.</p></div>';
      return $out;
    }

    $out .= '<div class="ab-grid">';
    foreach ($rows as $r) {
      $out .= ab_assoc_render_card($r, 'ab-card', false);
    }
    $out .= '</div>';

    if ($pages > 1) {
      $out .= '<div class="ab-pager">';
      if ($page > 1) {
        $out .= '<a class="ab-btn" href="' . ab_link([
          $k_path => $path ?: null, $k_region => $region,
          $k_q => $q ?: null, $k_city => $city ?: null, $k_prov => $prov ?: null,
          $k_page => $page - 1
        ]) . '">Prec</a>';
      } else {
        $out .= '<span class="ab-muted">Prec</span>';
      }

      $out .= '<span class="ab-muted">Pagina ' . ab_h((string)$page) . ' di ' . ab_h((string)$pages) . '</span>';

      if ($page < $pages) {
        $out .= '<a class="ab-btn" href="' . ab_link([
          $k_path => $path ?: null, $k_region => $region,
          $k_q => $q ?: null, $k_city => $city ?: null, $k_prov => $prov ?: null,
          $k_page => $page + 1
        ]) . '">Succ</a>';
      } else {
        $out .= '<span class="ab-muted">Succ</span>';
      }

      $out .= '</div>';
    }

    $out .= '</div>';
    return $out;
  }

  // -----------------------
  // No region yet: hierarchy navigation
  // -----------------------
  if ($has_hierarchy) {

    // Cache DISTINCT categories for this where+params
    $cache_key = ab_cache_key('ab_cats_', [
      get_current_blog_id(),
      ab_table(),
      $where,
      $params,
    ]);
    $cats = ab_cache_get($cache_key);
    if ($cats === false) {
      $sql = "SELECT DISTINCT category FROM {$table_sql} WHERE {$where}";
      $cats = !empty($params) ? $wpdb->get_col($wpdb->prepare($sql, $params)) : $wpdb->get_col($sql);
      ab_cache_set($cache_key, $cats, 1 * HOUR_IN_SECONDS);
    }

    $depth = count($selected_segments);
    $next = [];

    foreach ($cats as $cat) {
      $parts = ab_split_category((string)$cat);

      // Drop root for display depth
      if ($root !== '' && isset($parts[0]) && $parts[0] === $root) {
        $parts = array_slice($parts, 1);
      }

      if (isset($parts[$depth])) {
        $next[$parts[$depth]] = true;
      }
    }

    $next_keys = array_keys($next);
    sort($next_keys, SORT_NATURAL | SORT_FLAG_CASE);

    // Subtree anchor (full DB prefix) for regions rendering
    $leaf_full = $current_full_prefix;
    if ($root !== '' && $leaf_full === '') $leaf_full = $root;
    $leaf_like = $wpdb->esc_like($leaf_full . ' > ') . '%';

    // If there ARE deeper children: show Categories (+ maybe Regions)
    if (!empty($next_keys)) {
      $out .= '<h3>Categorie</h3>';
      $out .= '<ul class="ab-list">';
      foreach ($next_keys as $seg) {
        $new_path = ab_join_category(array_merge($selected_segments, [$seg])); // relative
        $out .= '<li><a href="' . ab_link([
          $k_path => $new_path,
          $k_region => null, $k_q => null, $k_city => null, $k_prov => null, $k_page => null
        ]) . '">' . ab_h($seg) . '</a></li>';
      }
      $out .= '</ul>';

      if ($show_regions_always) {
        $out .= ab_render_regions_subtree($id, $table_sql, $leaf_full, $leaf_like, $current_prefix, $mode, $accordion_limit);
      }

      $out .= '</div>';
      return $out;
    }

    // Leaf (no more children): show sibling categories list + ALWAYS show regions at leaf
    if ($show_leaf_siblings && count($selected_segments) > 0) {
      $parent_segments = array_slice($selected_segments, 0, -1); // relative parent
      $parent_full_segments = $parent_segments;
      if ($root !== '') array_unshift($parent_full_segments, $root);

      $parent_full = $parent_full_segments ? ab_join_category($parent_full_segments) : ($root !== '' ? $root : '');
      $parent_like = $wpdb->esc_like($parent_full . ' > ') . '%';

      $sql_parent_cats = "
        SELECT DISTINCT category
        FROM {$table_sql}
        WHERE (category = %s OR category LIKE %s)
      ";
      $parent_cats = $wpdb->get_col($wpdb->prepare($sql_parent_cats, [$parent_full, $parent_like]));

      $siblings = [];
      $leaf_depth = count($selected_segments);
      foreach ($parent_cats as $cat) {
        $parts = ab_split_category((string)$cat);
        if ($root !== '' && isset($parts[0]) && $parts[0] === $root) $parts = array_slice($parts, 1);
        if (isset($parts[$leaf_depth - 1])) $siblings[$parts[$leaf_depth - 1]] = true;
      }

      $sib_keys = array_keys($siblings);
      sort($sib_keys, SORT_NATURAL | SORT_FLAG_CASE);

      if (!empty($sib_keys)) {
        $current_leaf = $selected_segments[$leaf_depth - 1] ?? '';

        $out .= '<h3>Categorie</h3>';
        $out .= '<ul class="ab-list">';
        foreach ($sib_keys as $seg) {
          $new_path = ab_join_category(array_merge($parent_segments, [$seg]));
          $label = ($seg === $current_leaf) ? ('<strong>' . ab_h($seg) . '</strong>') : ab_h($seg);

          $out .= '<li><a href="' . ab_link([
            $k_path => $new_path,
            $k_region => null, $k_q => null, $k_city => null, $k_prov => null, $k_page => null
          ]) . '">' . $label . '</a></li>';
        }
        $out .= '</ul>';
      }
    }

    // ALWAYS show regions at leaf level
    $out .= ab_render_regions_subtree($id, $table_sql, $leaf_full, $leaf_like, $current_prefix, $mode, $accordion_limit);
    $out .= '</div>';
    return $out;

  } else {
    // Non-hierarchy fallback
    if ($current_prefix === '') {
      $sql = "SELECT DISTINCT category FROM {$table_sql} WHERE {$where} AND COALESCE(category,'') <> '' ORDER BY category ASC";
      $cats = !empty($params) ? $wpdb->get_col($wpdb->prepare($sql, $params)) : $wpdb->get_col($sql);

      $out .= '<h3>Categorie</h3>';
      if (empty($cats)) {
        $out .= '<p>Nessuna categoria trovata.</p></div>';
        return $out;
      }

      $out .= '<ul class="ab-list">';
      foreach ($cats as $c) {
        $out .= '<li><a href="' . ab_link([
          $k_path => (string)$c, $k_region => null, $k_q => null, $k_city => null, $k_prov => null, $k_page => null
        ]) . '">' . ab_h((string)$c) . '</a></li>';
      }
      $out .= '</ul></div>';
      return $out;
    }

    $sql_regions = "
      SELECT TRIM(region) AS region, COUNT(*) as cnt
      FROM {$table_sql}
      WHERE category = %s AND TRIM(COALESCE(region,'')) <> ''
      GROUP BY TRIM(region)
      ORDER BY TRIM(region) ASC
    ";
    $regions = $wpdb->get_results($wpdb->prepare($sql_regions, [$current_prefix]), ARRAY_A);

    $out .= '<h3>Regioni</h3>';
    if (empty($regions)) {
      $out .= '<p>Nessuna regione trovata per questa categoria.</p></div>';
      return $out;
    }

    $out .= '<ul class="ab-list">';
    foreach ($regions as $r) {
      $reg = (string)$r['region'];
      $cnt = (int)$r['cnt'];
      $out .= '<li><a href="' . ab_link([
        $k_path => $current_prefix, $k_region => $reg, $k_q => null, $k_city => null, $k_prov => null, $k_page => null
      ]) . '">' . ab_h($reg) . '</a> <span class="ab-muted">(' . ab_h((string)$cnt) . ')</span></li>';
    }
    $out .= '</ul></div>';
    return $out;
  }
}

add_shortcode('associazioni_browser', 'ab_shortcode');

/**
 * Dropdown filters for Settori page:
 * [associazioni_filtri id="settori" per_page="24" title="Categorie Attivita"]
 */
function abf_category_prefix(string $macro, string $settore, string $settore2): string {
  $segments = [];
  if ($macro !== '') {
    $segments[] = $macro;
    if ($settore !== '') {
      $segments[] = $settore;
      if ($settore2 !== '') {
        $segments[] = $settore2;
      }
    }
  }
  return ab_join_category($segments);
}

function abf_build_where(
  string $macro,
  string $settore,
  string $settore2,
  string $regione = '',
  string $provincia = '',
  string $comune = ''
): array {
  global $wpdb;
  $where = "1=1";
  $params = [];
  $hasMacroCol = ab_table_has_column('macro');
  $hasSettoreCol = ab_table_has_column('settore');
  $hasSettore2Col = ab_table_has_column('settore2');

  if ($macro !== '') {
    if ($hasMacroCol) {
      $where .= " AND TRIM(COALESCE(`macro`,'')) = %s";
      $params[] = $macro;
    } else {
      $where .= " AND (category = %s OR category LIKE %s)";
      $params[] = $macro;
      $params[] = $wpdb->esc_like($macro . ' > ') . '%';
    }
  }

  if ($settore !== '') {
    if ($hasSettoreCol) {
      $where .= " AND TRIM(COALESCE(`settore`,'')) = %s";
      $params[] = $settore;
    } else {
      $where .= " AND (category LIKE %s OR category LIKE %s)";
      $params[] = '% > ' . $wpdb->esc_like($settore);
      $params[] = '% > ' . $wpdb->esc_like($settore . ' > ') . '%';
    }
  }

  if ($settore2 !== '') {
    if ($hasSettore2Col) {
      $where .= " AND TRIM(COALESCE(`settore2`,'')) = %s";
      $params[] = $settore2;
    } else {
      $where .= " AND (category LIKE %s OR category LIKE %s)";
      $params[] = '% > % > ' . $wpdb->esc_like($settore2);
      $params[] = '% > % > ' . $wpdb->esc_like($settore2 . ' > ') . '%';
    }
  }

  if ($regione !== '') {
    $where .= " AND TRIM(region) = %s";
    $params[] = $regione;
  }
  if ($provincia !== '') {
    $where .= " AND TRIM(province) = %s";
    $params[] = $provincia;
  }
  if ($comune !== '') {
    $where .= " AND TRIM(city) = %s";
    $params[] = $comune;
  }

  return [$where, $params];
}

function abf_distinct_level_values(string $field, array $filters = []): array {
  global $wpdb;
  $allowed = ['macro', 'settore', 'settore2'];
  if (!in_array($field, $allowed, true)) return [];
  if (!ab_table_has_column($field)) return [];

  $normalizedFilters = [];
  foreach ($filters as $filterField => $filterValue) {
    if (!in_array((string)$filterField, $allowed, true)) continue;
    $filterValue = trim((string)$filterValue);
    if ($filterValue === '') continue;
    $normalizedFilters[(string)$filterField] = $filterValue;
  }
  ksort($normalizedFilters);

  $cacheKey = ab_cache_key('abf_level_' . $field . '_', [
    get_current_blog_id(),
    ab_table(),
    $normalizedFilters,
  ]);
  $cached = ab_cache_get($cacheKey);
  if ($cached !== false && is_array($cached)) return $cached;

  $table = ab_table_sql();
  $where = "1=1";
  $params = [];

  foreach ($normalizedFilters as $filterField => $filterValue) {
    if (!ab_table_has_column($filterField)) continue;
    $where .= " AND TRIM(COALESCE(`{$filterField}`,'')) = %s";
    $params[] = $filterValue;
  }

  $sql = "SELECT DISTINCT TRIM(COALESCE(`{$field}`,'')) AS v
          FROM {$table}
          WHERE {$where}
            AND TRIM(COALESCE(`{$field}`,'')) <> ''
          ORDER BY TRIM(COALESCE(`{$field}`,'')) ASC";
  $values = !empty($params) ? $wpdb->get_col($wpdb->prepare($sql, $params)) : $wpdb->get_col($sql);

  $out = [];
  foreach ((array)$values as $valueRaw) {
    $value = trim((string)$valueRaw);
    if ($value === '' || ab_assoc_is_placeholder_label($value)) continue;
    $out[$value] = $value;
  }

  $result = array_values($out);
  sort($result, SORT_NATURAL | SORT_FLAG_CASE);
  ab_cache_set($cacheKey, $result, 1 * HOUR_IN_SECONDS);
  return $result;
}

function abf_all_categories(): array {
  $cacheKey = ab_cache_key('abf_categories_', [get_current_blog_id(), ab_table()]);
  $cached = ab_cache_get($cacheKey);
  if ($cached !== false && is_array($cached)) return $cached;

  global $wpdb;
  $table = ab_table_sql();
  $rows = $wpdb->get_col("SELECT DISTINCT category FROM {$table} WHERE TRIM(COALESCE(category,'')) <> ''");
  $rows = array_values(array_filter(array_map(fn($v) => trim((string)$v), $rows), fn($v) => $v !== ''));
  sort($rows, SORT_NATURAL | SORT_FLAG_CASE);

  ab_cache_set($cacheKey, $rows, 1 * HOUR_IN_SECONDS);
  return $rows;
}

function abf_category_options(string $selectedMacro, string $selectedSettore): array {
  $macroFromCols = abf_distinct_level_values('macro');
  $settoreFromCols = abf_distinct_level_values('settore', ['macro' => $selectedMacro]);
  $settore2FromCols = abf_distinct_level_values('settore2', ['macro' => $selectedMacro, 'settore' => $selectedSettore]);

  if (!empty($macroFromCols) || !empty($settoreFromCols) || !empty($settore2FromCols)) {
    return [
      'macro' => $macroFromCols,
      'settore' => $settoreFromCols,
      'settore2' => $settore2FromCols,
    ];
  }

  $cats = abf_all_categories();

  $macros = [];
  $settori = [];
  $settori2 = [];

  foreach ($cats as $cat) {
    $parts = ab_split_category((string)$cat);
    if (empty($parts)) continue;

    $macro = $parts[0];
    $macros[$macro] = true;

    if (isset($parts[1]) && trim((string)$parts[1]) !== '') {
      if ($selectedMacro === '' || $macro === $selectedMacro) {
        $settori[$parts[1]] = true;
      }
    }

    if (isset($parts[2]) && trim((string)$parts[2]) !== '') {
      $matchesMacro = ($selectedMacro === '' || $macro === $selectedMacro);
      $matchesSettore = ($selectedSettore === '' || (isset($parts[1]) && $parts[1] === $selectedSettore));
      if ($matchesMacro && $matchesSettore) {
        $settori2[$parts[2]] = true;
      }
    }
  }

  $macroVals = array_keys($macros);
  $settoreVals = array_keys($settori);
  $settore2Vals = array_keys($settori2);

  sort($macroVals, SORT_NATURAL | SORT_FLAG_CASE);
  sort($settoreVals, SORT_NATURAL | SORT_FLAG_CASE);
  sort($settore2Vals, SORT_NATURAL | SORT_FLAG_CASE);

  return [
    'macro' => $macroVals,
    'settore' => $settoreVals,
    'settore2' => $settore2Vals,
  ];
}

function abf_distinct_values(string $field, string $where, array $params = []): array {
  global $wpdb;
  $allowed = ['region', 'province', 'city'];
  if (!in_array($field, $allowed, true)) return [];

  $table = ab_table_sql();
  $sql = "SELECT DISTINCT TRIM({$field}) AS v FROM {$table} WHERE {$where} AND TRIM(COALESCE({$field},'')) <> '' ORDER BY TRIM({$field}) ASC";
  $values = !empty($params) ? $wpdb->get_col($wpdb->prepare($sql, $params)) : $wpdb->get_col($sql);
  return array_values(array_filter(array_map(fn($v) => trim((string)$v), $values), fn($v) => $v !== ''));
}

function abf_collect_hero_label_map(): array {
  $map = [];

  $addLabel = static function(string $label) use (&$map): void {
    $label = trim($label);
    if ($label === '' || ab_assoc_is_placeholder_label($label)) return;
    $key = ab_assoc_normalize_key($label);
    if ($key === '') return;
    if (!isset($map[$key])) {
      $map[$key] = [];
    }
    $map[$key][$label] = true;
  };

  foreach (['macro', 'settore', 'settore2'] as $levelField) {
    foreach (abf_distinct_level_values($levelField) as $levelValue) {
      $addLabel((string)$levelValue);
    }
  }

  foreach (abf_all_categories() as $category) {
    $parts = ab_split_category((string)$category);
    foreach ($parts as $part) {
      $addLabel((string)$part);
    }
  }

  $result = [];
  foreach ($map as $key => $labelsMap) {
    $labels = array_keys($labelsMap);
    sort($labels, SORT_NATURAL | SORT_FLAG_CASE);
    $result[$key] = $labels;
  }

  ksort($result, SORT_NATURAL | SORT_FLAG_CASE);
  return $result;
}

function abf_collect_hero_keys(): array {
  return array_keys(abf_collect_hero_label_map());
}

function abf_hero_manual_aliases(): array {
  $raw = [
    'AUTO / MOTO STORICHE' => 'Auto storiche e moto d’epoca.jpg',
    "ATTIVITA' VELISTICCHE / SURFING" => 'Surfing, Windsurfing e Kayak.jpg',
    "ATTIVITA' VELISTICCHE" => 'Surfing, Windsurfing e Kayak.jpg',
    'COLLEZIONISMO / MODELLISMO' => 'Collezionismo.jpg',
    '(COLLEZIONISMO / MODELLISMO)' => 'Collezionismo.jpg',
    "ATTIVITA' SUBACQUEE" => 'Attività Culturali e Ricreative.jpg',
    "ATTIVITA' SUBAQUEE" => 'Attività Culturali e Ricreative.jpg',
    "SURFING /KAYAC" => 'Surfing, Windsurfing e Kayak.jpg',
    'CULTURA' => 'Attività Culturali e Ricreative.jpg',
    'ARTE' => 'Attività Culturali e Ricreative.jpg',
    'DANZA' => 'Attività Culturali e Ricreative.jpg',
    'EQUITAZIONE' => 'Attività Culturali e Ricreative.jpg',
    'MOTORI' => 'Attività Culturali e Ricreative.jpg',
    'BENESSERE' => 'Attività Culturali e Ricreative.jpg',
    'VOLONTARIATO' => 'Attività Culturali e Ricreative.jpg',
    "(CULTURA / ARTE / DANZA / ATTIVITA' SUBACQUEE / EQUITAZIONE / MOTORI / BENESSERE / VOLONTARIATO )" => 'Attività Culturali e Ricreative.jpg',
  ];

  $aliases = [];
  foreach ($raw as $sourceLabel => $targetImage) {
    $sourceKey = ab_assoc_normalize_key((string)$sourceLabel);
    $targetImage = trim((string)$targetImage);
    if ($sourceKey === '' || $targetImage === '') continue;
    $aliases[$sourceKey] = $targetImage;
  }

  return $aliases;
}

function abf_get_hero_image_overrides(): array {
  $raw = get_option(AB_SETTORI_HERO_OVERRIDES_OPTION, []);
  if (!is_array($raw)) return [];

  $overrides = [];
  foreach ($raw as $rawKey => $rawAttachmentId) {
    $key = ab_assoc_normalize_key((string)$rawKey);
    $attachmentId = (int)$rawAttachmentId;
    if ($key === '' || $attachmentId <= 0) continue;
    $overrides[$key] = $attachmentId;
  }

  ksort($overrides, SORT_NATURAL | SORT_FLAG_CASE);
  return $overrides;
}

function abf_get_hero_override_url_map(): array {
  $overrides = abf_get_hero_image_overrides();
  if (empty($overrides)) return [];

  $urls = [];
  foreach ($overrides as $key => $attachmentId) {
    $attachmentId = (int)$attachmentId;
    if ($key === '' || $attachmentId <= 0) continue;
    if (!wp_attachment_is_image($attachmentId)) continue;
    $url = wp_get_attachment_image_url($attachmentId, 'full');
    if (!is_string($url) || trim($url) === '') continue;
    $urls[(string)$key] = $url;
  }

  ksort($urls, SORT_NATURAL | SORT_FLAG_CASE);
  return $urls;
}

function abf_sanitize_hero_image_overrides(array $raw): array {
  $clean = [];
  foreach ($raw as $rawKey => $rawAttachmentId) {
    $key = ab_assoc_normalize_key((string)$rawKey);
    $attachmentId = (int)$rawAttachmentId;
    if ($key === '' || $attachmentId <= 0) continue;
    if (!wp_attachment_is_image($attachmentId)) continue;
    $clean[$key] = $attachmentId;
  }

  ksort($clean, SORT_NATURAL | SORT_FLAG_CASE);
  return $clean;
}

function abf_get_hero_image_map(): array {
  $keys = abf_collect_hero_keys();
  if (empty($keys)) return [];
  $overrides = abf_get_hero_image_overrides();
  $heroVersion = (int)get_option('ab_settori_hero_cache_version', 1);

  global $wpdb;
  $postsTable = $wpdb->posts;
  $heroSignature = $wpdb->get_row("
    SELECT COUNT(*) AS cnt, MAX(post_modified_gmt) AS max_mod
    FROM {$postsTable}
    WHERE post_type = 'attachment'
      AND post_status = 'inherit'
      AND post_mime_type LIKE 'image/%'
  ", ARRAY_A);
  $mediaCount = (int)($heroSignature['cnt'] ?? 0);
  $mediaMaxModified = trim((string)($heroSignature['max_mod'] ?? ''));

  $cacheKey = ab_cache_key('ab_hero_map_', [
    get_current_blog_id(),
    'matcher_v4',
    md5(wp_json_encode($keys)),
    md5(wp_json_encode($overrides)),
    $heroVersion,
    $mediaCount,
    $mediaMaxModified,
  ]);
  $cached = ab_cache_get($cacheKey);
  if ($cached !== false && is_array($cached)) {
    return $cached;
  }

  $map = [];
  $keySet = array_fill_keys($keys, true);
  $scoreByKey = [];
  $attachmentCandidates = [];

  $keyVariants = static function(string $raw): array {
    $normalized = ab_assoc_normalize_key($raw);
    if ($normalized === '') return [];
    $variants = [$normalized => 100];
    if (preg_match('/^(.*)-\d+$/', $normalized, $matches)) {
      $base = trim((string)($matches[1] ?? ''));
      if ($base !== '') $variants[$base] = 75;
    }
    return $variants;
  };

  $assign = static function(string $candidateRaw, int $baseScore, int $attachmentId, string $url, int $timestamp) use (&$map, &$scoreByKey, $keySet, $keyVariants): void {
    foreach ($keyVariants($candidateRaw) as $candidateKey => $weight) {
      if (!isset($keySet[$candidateKey])) continue;
      $score = $baseScore + $weight;
      if (!isset($scoreByKey[$candidateKey])) {
        $scoreByKey[$candidateKey] = ['score' => $score, 'ts' => $timestamp, 'id' => $attachmentId];
        $map[$candidateKey] = $url;
        continue;
      }
      $best = $scoreByKey[$candidateKey];
      $bestScore = (int)($best['score'] ?? -1);
      $bestTs = (int)($best['ts'] ?? 0);
      $bestId = (int)($best['id'] ?? 0);
      if ($score > $bestScore || ($score === $bestScore && ($timestamp > $bestTs || ($timestamp === $bestTs && $attachmentId > $bestId)))) {
        $scoreByKey[$candidateKey] = ['score' => $score, 'ts' => $timestamp, 'id' => $attachmentId];
        $map[$candidateKey] = $url;
      }
    }
  };

  $compactKey = static function(string $value): string {
    return str_replace('-', '', ab_assoc_normalize_key($value));
  };

  $softKey = static function(string $value) use ($compactKey): string {
    $key = $compactKey($value);
    if ($key === '') return '';
    // Tolerate minor spelling/typing differences by collapsing repeated letters.
    $key = preg_replace('/(.)\1+/', '$1', $key);
    return (string)$key;
  };

  $tokenizeKey = static function(string $value): array {
    $normalized = ab_assoc_normalize_key($value);
    if ($normalized === '') return [];
    $stop = [
      'di' => true, 'del' => true, 'della' => true, 'delle' => true, 'dei' => true, 'degli' => true,
      'e' => true, 'ed' => true, 'il' => true, 'lo' => true, 'la' => true, 'le' => true, 'i' => true, 'gli' => true,
      'attivita' => true, 'attivitae' => true, 'sport' => true, 'sportive' => true,
    ];
    $parts = preg_split('/-+/', $normalized);
    if (!is_array($parts)) return [];
    $out = [];
    foreach ($parts as $part) {
      $token = trim((string)$part);
      if ($token === '' || isset($stop[$token])) continue;
      $out[$token] = true;
    }
    return array_keys($out);
  };

  $matchScore = static function(string $wantedKey, string $candidateKey) use ($compactKey, $softKey, $tokenizeKey): int {
    $wanted = ab_assoc_normalize_key($wantedKey);
    $candidate = ab_assoc_normalize_key($candidateKey);
    if ($wanted === '' || $candidate === '') return 0;
    if ($wanted === $candidate) return 100;

    $wantedCompact = $compactKey($wanted);
    $candidateCompact = $compactKey($candidate);
    if ($wantedCompact !== '' && $wantedCompact === $candidateCompact) return 98;

    $wantedSoft = $softKey($wanted);
    $candidateSoft = $softKey($candidate);
    if ($wantedSoft !== '' && $wantedSoft === $candidateSoft) return 95;

    $maxLen = max(strlen($wantedSoft), strlen($candidateSoft));
    if ($maxLen > 0) {
      $distance = levenshtein($wantedSoft, $candidateSoft);
      if ($distance <= 1 && $maxLen >= 6) return 93;
      if ($distance <= 2 && $maxLen >= 8) return 90;
      if ($distance <= 3 && $maxLen >= 11) return 86;
    }

    if (
      $wantedCompact !== '' && $candidateCompact !== '' &&
      (strpos($candidateCompact, $wantedCompact) !== false || strpos($wantedCompact, $candidateCompact) !== false)
    ) {
      $shortLen = min(strlen($wantedCompact), strlen($candidateCompact));
      if ($shortLen >= 9) return 88;
      if ($shortLen >= 6) return 84;
    }

    $wantedTokens = $tokenizeKey($wanted);
    $candidateTokens = $tokenizeKey($candidate);
    if (!empty($wantedTokens) && !empty($candidateTokens)) {
      $wantedSet = array_fill_keys($wantedTokens, true);
      $candidateSet = array_fill_keys($candidateTokens, true);
      $inter = array_intersect_key($wantedSet, $candidateSet);
      $interCount = count($inter);
      if ($interCount > 0) {
        $union = count($wantedSet + $candidateSet);
        $ratio = $union > 0 ? ($interCount / $union) : 0.0;
        if ($ratio >= 0.80 && $interCount >= 2) return 89;
        if ($ratio >= 0.65 && $interCount >= 2) return 85;
      }
    }

    return 0;
  };

  $sqlAll = "
    SELECT p.ID, p.post_name, p.post_title, p.post_date_gmt, p.post_date, pm.meta_value AS file_path
    FROM {$postsTable} p
    LEFT JOIN {$wpdb->postmeta} pm
      ON pm.post_id = p.ID
     AND pm.meta_key = '_wp_attached_file'
    WHERE p.post_type = 'attachment'
      AND p.post_status = 'inherit'
      AND p.post_mime_type LIKE 'image/%'
    ORDER BY p.post_date_gmt DESC, p.ID DESC
    LIMIT 10000
  ";
  $rowsAll = $wpdb->get_results($sqlAll, ARRAY_A);
  foreach ($rowsAll as $row) {
    $id = (int)($row['ID'] ?? 0);
    if ($id <= 0) continue;
    $url = wp_get_attachment_image_url($id, 'full');
    if (!$url) continue;

    $timestamp = strtotime((string)($row['post_date_gmt'] ?? ''));
    if (!$timestamp) $timestamp = strtotime((string)($row['post_date'] ?? ''));
    if (!$timestamp) $timestamp = 0;

    $assign((string)($row['post_title'] ?? ''), 300, $id, $url, $timestamp);
    $assign((string)($row['post_name'] ?? ''), 260, $id, $url, $timestamp);

    $candidateKeys = [];
    foreach ([(string)($row['post_title'] ?? ''), (string)($row['post_name'] ?? '')] as $candidateRaw) {
      foreach ($keyVariants($candidateRaw) as $candidateKey => $weight) {
        if ($candidateKey !== '') $candidateKeys[$candidateKey] = true;
      }
    }
    $filePath = trim((string)($row['file_path'] ?? ''));
    if ($filePath !== '') {
      $basename = pathinfo(wp_basename($filePath), PATHINFO_FILENAME);
      $assign((string)$basename, 240, $id, $url, $timestamp);
      foreach ($keyVariants((string)$basename) as $candidateKey => $weight) {
        if ($candidateKey !== '') $candidateKeys[$candidateKey] = true;
      }
    }

    if (!empty($candidateKeys)) {
      $attachmentCandidates[] = [
        'id' => $id,
        'ts' => $timestamp,
        'url' => $url,
        'keys' => array_keys($candidateKeys),
      ];
    }
  }

  $missing = array_values(array_filter($keys, fn($key) => !isset($map[$key])));
  if (!empty($missing) && !empty($attachmentCandidates)) {
    foreach ($missing as $missingKey) {
      $bestScore = 0;
      $bestTs = 0;
      $bestId = 0;
      $bestUrl = '';

      foreach ($attachmentCandidates as $candidate) {
        $candidateUrl = (string)($candidate['url'] ?? '');
        $candidateTs = (int)($candidate['ts'] ?? 0);
        $candidateId = (int)($candidate['id'] ?? 0);
        $candidateKeys = (array)($candidate['keys'] ?? []);
        if ($candidateUrl === '' || empty($candidateKeys)) continue;

        $rowBestScore = 0;
        foreach ($candidateKeys as $candidateKey) {
          $score = $matchScore($missingKey, (string)$candidateKey);
          if ($score > $rowBestScore) $rowBestScore = $score;
          if ($rowBestScore >= 100) break;
        }

        if (
          $rowBestScore > $bestScore ||
          ($rowBestScore === $bestScore && ($candidateTs > $bestTs || ($candidateTs === $bestTs && $candidateId > $bestId)))
        ) {
          $bestScore = $rowBestScore;
          $bestTs = $candidateTs;
          $bestId = $candidateId;
          $bestUrl = $candidateUrl;
        }
      }

      // Keep fuzzy fallback conservative to avoid wrong image assignments.
      if ($bestScore >= 89 && $bestUrl !== '') {
        $map[$missingKey] = $bestUrl;
      }
    }
  }

  $manualAliases = abf_hero_manual_aliases();
  if (!empty($manualAliases) && !empty($attachmentCandidates)) {
    $resolveTargetImageUrl = static function(string $targetImageLabel) use ($attachmentCandidates, $matchScore): string {
      $targetImageLabel = trim($targetImageLabel);
      if ($targetImageLabel === '') return '';
      $targetBase = pathinfo($targetImageLabel, PATHINFO_FILENAME);
      if (!is_string($targetBase) || trim($targetBase) === '') {
        $targetBase = $targetImageLabel;
      }

      $bestScore = 0;
      $bestTs = 0;
      $bestId = 0;
      $bestUrl = '';

      foreach ($attachmentCandidates as $candidate) {
        $candidateUrl = (string)($candidate['url'] ?? '');
        $candidateTs = (int)($candidate['ts'] ?? 0);
        $candidateId = (int)($candidate['id'] ?? 0);
        $candidateKeys = (array)($candidate['keys'] ?? []);
        if ($candidateUrl === '' || empty($candidateKeys)) continue;

        $rowBestScore = 0;
        foreach ($candidateKeys as $candidateKey) {
          $score = $matchScore($targetBase, (string)$candidateKey);
          if ($score > $rowBestScore) $rowBestScore = $score;
          if ($rowBestScore >= 100) break;
        }

        if (
          $rowBestScore > $bestScore ||
          ($rowBestScore === $bestScore && ($candidateTs > $bestTs || ($candidateTs === $bestTs && $candidateId > $bestId)))
        ) {
          $bestScore = $rowBestScore;
          $bestTs = $candidateTs;
          $bestId = $candidateId;
          $bestUrl = $candidateUrl;
        }
      }

      return ($bestScore >= 89) ? $bestUrl : '';
    };

    foreach ($manualAliases as $sourceKey => $targetImageLabel) {
      if (!isset($keySet[$sourceKey])) continue;
      $resolvedUrl = $resolveTargetImageUrl((string)$targetImageLabel);
      if ($resolvedUrl !== '') {
        $map[$sourceKey] = $resolvedUrl;
      }
    }
  }

  if (!empty($overrides)) {
    foreach ($overrides as $sourceKey => $attachmentId) {
      if (!isset($keySet[$sourceKey])) continue;
      $attachmentId = (int)$attachmentId;
      if ($attachmentId <= 0) continue;
      if (!wp_attachment_is_image($attachmentId)) continue;
      $overrideUrl = wp_get_attachment_image_url($attachmentId, 'full');
      if (is_string($overrideUrl) && trim($overrideUrl) !== '') {
        $map[$sourceKey] = $overrideUrl;
      }
    }
  }

  ab_cache_set($cacheKey, $map, 6 * HOUR_IN_SECONDS);
  return $map;
}

function abf_settori_click_lookup_map(): array {
  $cacheKey = ab_cache_key('abf_settori_click_map_', [
    get_current_blog_id(),
    ab_table(),
  ]);
  $cached = ab_cache_get($cacheKey);
  if ($cached !== false && is_array($cached)) {
    return $cached;
  }

  $map = [];
  $scores = [];

  $assign = static function(string $label, string $macro, string $settore, string $settore2, int $score) use (&$map, &$scores): void {
    $label = trim($label);
    if ($label === '' || ab_assoc_is_placeholder_label($label)) return;

    $key = ab_assoc_normalize_key($label);
    if ($key === '') return;

    if (!isset($scores[$key]) || $score > (int)$scores[$key]) {
      $scores[$key] = $score;
      $map[$key] = [
        'macro' => trim($macro),
        'settore' => trim($settore),
        'settore2' => trim($settore2),
      ];
    }
  };

  foreach (abf_all_categories() as $category) {
    $parts = ab_split_category((string)$category);
    $macro = isset($parts[0]) ? trim((string)$parts[0]) : '';
    $settore = isset($parts[1]) ? trim((string)$parts[1]) : '';
    $settore2 = isset($parts[2]) ? trim((string)$parts[2]) : '';

    if ($macro !== '') {
      $assign($macro, $macro, '', '', 10);
    }
    if ($settore !== '') {
      $assign($settore, $macro, $settore, '', 20);
    }
    if ($settore2 !== '') {
      $assign($settore2, $macro, $settore, $settore2, 30);
    }
  }

  foreach (abf_distinct_level_values('macro') as $macro) {
    $assign((string)$macro, (string)$macro, '', '', 8);
  }
  foreach (abf_distinct_level_values('settore') as $settore) {
    $assign((string)$settore, '', (string)$settore, '', 12);
  }
  foreach (abf_distinct_level_values('settore2') as $settore2) {
    $assign((string)$settore2, '', '', (string)$settore2, 16);
  }

  ab_cache_set($cacheKey, $map, 1 * HOUR_IN_SECONDS);
  return $map;
}

function abf_enqueue_settori_pattern_links_script(): void {
  if (is_admin()) return;
  if (!(is_front_page() || is_home())) return;

  $settoriPage = get_page_by_path('settori');
  $settoriUrl = ($settoriPage instanceof WP_Post)
    ? get_permalink((int)$settoriPage->ID)
    : home_url('/settori/');

  if (!is_string($settoriUrl) || trim($settoriUrl) === '') {
    $settoriUrl = home_url('/settori/');
  }

  $handle = 'abf-settori-pattern-links';
  wp_register_script(
    $handle,
    plugins_url('assets/js/settori-pattern-links.js', __FILE__),
    [],
    '1.0.0',
    true
  );
  wp_enqueue_script($handle);

  $config = [
    'baseUrl' => (string)$settoriUrl,
    'queryKeys' => [
      'macro' => ab_qkey('settori', 'macro'),
      'settore' => ab_qkey('settori', 'settore'),
      'settore2' => ab_qkey('settori', 'settore2'),
    ],
    'lookup' => abf_settori_click_lookup_map(),
  ];

  wp_add_inline_script(
    $handle,
    'window.AB_SETTORI_PATTERN_LINKS = ' . wp_json_encode($config) . ';',
    'before'
  );
}
add_action('wp_enqueue_scripts', 'abf_enqueue_settori_pattern_links_script', 30);

function abf_sanitize_class_list(string $classList): string {
  $items = preg_split('/\s+/', trim($classList));
  if (!is_array($items)) return '';
  $clean = [];
  foreach ($items as $item) {
    $item = sanitize_html_class($item);
    if ($item !== '') $clean[] = $item;
  }
  return implode(' ', array_values(array_unique($clean)));
}

function abf_sanitize_css_size(string $size): string {
  $size = trim($size);
  if ($size === '') return '';
  if (preg_match('/^[0-9]+(?:\.[0-9]+)?(px|rem|em|vw|vh|%)$/i', $size)) return $size;
  return '';
}

function abf_sanitize_css_color(string $color): string {
  $color = trim($color);
  if ($color === '') return '';

  $hex = sanitize_hex_color($color);
  if ($hex) return $hex;

  if (preg_match('/^(rgb|rgba|hsl|hsla)\(([-0-9\.,%\s]+)\)$/i', $color)) return $color;
  if (preg_match('/^var\(--[a-z0-9\-_]+\)$/i', $color)) return $color;
  return '';
}

function abf_render_selected_breadcrumb(array $selectedMap): string {
  $items = [];
  foreach ($selectedMap as $value) {
    $value = trim((string)$value);
    if ($value === '') continue;
    $items[] = $value;
  }

  $out = '<div class="abf-selected-breadcrumb" aria-live="polite">';
  $out .= '<span class="abf-selected-label">Percorso selezionato:</span>';

  if (empty($items)) {
    $out .= '<span class="abf-selected-item">Tutti i settori</span>';
    $out .= '</div>';
    return $out;
  }

  foreach ($items as $idx => $item) {
    if ($idx > 0) {
      $out .= '<span class="abf-selected-sep" aria-hidden="true">&rsaquo;</span>';
    }
    $out .= '<span class="abf-selected-item">' . ab_h($item) . '</span>';
  }

  $out .= '</div>';
  return $out;
}

function ab_assoc_extract_activity_from_category(string $category): string {
  $parts = ab_split_category($category);
  if (!isset($parts[2])) return '';
  $activity = trim((string)$parts[2]);
  if ($activity === '' || ab_assoc_is_placeholder_label($activity)) return '';
  return $activity;
}

function ab_assoc_normalize_emails_key(string $emailsRaw): string {
  $tokens = preg_split('/[|,;\s]+/', strtolower(trim($emailsRaw)));
  if (!is_array($tokens)) return '';
  $emails = [];
  foreach ($tokens as $token) {
    $email = sanitize_email(trim((string)$token));
    if ($email !== '') $emails[$email] = true;
  }
  $list = array_keys($emails);
  sort($list, SORT_NATURAL | SORT_FLAG_CASE);
  return implode(',', $list);
}

function ab_assoc_identity_key_from_row(array $row): string {
  $associationId = ab_assoc_find_post_id($row);
  $city = ab_assoc_normalize_key((string)($row['city'] ?? ''));
  $province = ab_assoc_normalize_key((string)($row['province'] ?? ''));
  $region = ab_assoc_normalize_key((string)($row['region'] ?? ''));
  $locationRaw = ab_assoc_normalize_key((string)($row['location_raw'] ?? ''));
  if ($associationId > 0) {
    return 'post:' . $associationId . '|' . implode('|', [$city, $province, $region, $locationRaw]);
  }

  $organization = ab_assoc_normalize_key((string)($row['organization'] ?? ''));
  return 'raw:' . implode('|', [$organization, $city, $province, $region, $locationRaw]);
}

function ab_assoc_merge_duplicate_rows(array $rows): array {
  $groups = [];
  $fillFields = ['region', 'province', 'city', 'location_raw', 'urls', 'emails', 'notes', 'source_block'];

  foreach ($rows as $row) {
    if (!is_array($row)) continue;
    $key = ab_assoc_identity_key_from_row($row);
    if (!isset($groups[$key])) {
      $base = $row;
      $base['_ab_categories'] = [];
      $base['_ab_activities'] = [];
      $groups[$key] = $base;
    }

    $category = ab_assoc_resolve_category_from_row($row);
    if ($category !== '') {
      $groups[$key]['_ab_categories'][$category] = true;
      $activity = ab_assoc_extract_activity_from_category($category);
      if ($activity !== '') {
        $groups[$key]['_ab_activities'][$activity] = true;
      }
    }

    foreach ($fillFields as $field) {
      $current = trim((string)($groups[$key][$field] ?? ''));
      $incoming = trim((string)($row[$field] ?? ''));
      if ($field === 'emails' && $incoming !== '') {
        $mergedEmails = ab_assoc_merge_emails($current, $incoming);
        if ($mergedEmails !== '') {
          $groups[$key][$field] = $mergedEmails;
          continue;
        }
      }
      if ($field === 'urls' && $incoming !== '') {
        $mergedUrls = ab_assoc_merge_urls($current, $incoming);
        if ($mergedUrls !== '') {
          $groups[$key][$field] = $mergedUrls;
          continue;
        }
      }
      if ($field === 'notes' && $incoming !== '' && $current !== '' && strcasecmp($current, $incoming) !== 0) {
        $groups[$key][$field] = $current . "\n" . $incoming;
        continue;
      }
      if ($current === '' && $incoming !== '') {
        $groups[$key][$field] = $incoming;
      }
    }
  }

  $merged = [];
  foreach ($groups as $group) {
    $resolvedCategory = ab_assoc_resolve_category_from_row($group);
    if ($resolvedCategory !== '') {
      $group['_ab_categories'][$resolvedCategory] = true;
      $group['category'] = $resolvedCategory;
    }

    $activities = array_keys((array)($group['_ab_activities'] ?? []));
    sort($activities, SORT_NATURAL | SORT_FLAG_CASE);

    $categories = array_keys((array)($group['_ab_categories'] ?? []));
    sort($categories, SORT_NATURAL | SORT_FLAG_CASE);

    $group['activity_tags'] = implode('|', $activities);
    $group['activity_tags_label'] = implode(', ', $activities);
    $group['all_categories'] = implode(' | ', $categories);
    if ((trim((string)($group['category'] ?? '')) === '' || ab_assoc_is_placeholder_label((string)$group['category'])) && !empty($categories)) {
      $group['category'] = (string)$categories[0];
    }

    unset($group['_ab_categories'], $group['_ab_activities']);
    $merged[] = $group;
  }

  usort($merged, function($a, $b): int {
    $aName = trim((string)($a['organization'] ?? ''));
    $bName = trim((string)($b['organization'] ?? ''));
    return strcasecmp($aName, $bName);
  });

  return $merged;
}

function abf_shortcode($atts): string {
  global $wpdb;

  $atts = shortcode_atts([
    'id' => 'abf',
    'title' => 'Categorie Attivita',
    'per_page' => 24,
    'class' => '',
    'max_width' => '',
    'accent' => '',
    'card_radius' => '',
    'card_bg' => '',
    'card_border' => '',
    'show_title' => '1',
  ], $atts, 'associazioni_filtri');

  $id = preg_replace('~[^a-zA-Z0-9_]~', '_', (string)$atts['id']);
  if ($id === '') $id = 'abf';

  $k_macro    = ab_qkey($id, 'macro');
  $k_settore  = ab_qkey($id, 'settore');
  $k_settore2 = ab_qkey($id, 'settore2');
  $k_regione  = ab_qkey($id, 'regione');
  $k_prov     = ab_qkey($id, 'provincia');
  $k_comune   = ab_qkey($id, 'comune');
  $k_page     = ab_qkey($id, 'page');

  $macro = ab_get_qs($k_macro);
  $settore = ab_get_qs($k_settore);
  $settore2 = ab_get_qs($k_settore2);
  $regione = ab_get_qs($k_regione);
  $provincia = ab_get_qs($k_prov);
  $comune = ab_get_qs($k_comune);
  $page = ab_get_int_qs($k_page, 1);

  $perPage = (int)$atts['per_page'];
  if ($perPage < 10) $perPage = 10;
  if ($perPage > 200) $perPage = 200;

  $extraClass = abf_sanitize_class_list((string)$atts['class']);
  $wrapperClass = 'abf-wrap';
  if ($extraClass !== '') {
    $wrapperClass .= ' ' . $extraClass;
  }

  $styleVars = [];
  $maxWidth = abf_sanitize_css_size((string)$atts['max_width']);
  $accent = abf_sanitize_css_color((string)$atts['accent']);
  $cardRadius = abf_sanitize_css_size((string)$atts['card_radius']);
  $cardBg = abf_sanitize_css_color((string)$atts['card_bg']);
  $cardBorder = abf_sanitize_css_color((string)$atts['card_border']);
  if ($maxWidth !== '') $styleVars[] = '--abf-max-width:' . $maxWidth;
  if ($accent !== '') $styleVars[] = '--abf-accent:' . $accent;
  if ($cardRadius !== '') $styleVars[] = '--abf-card-radius:' . $cardRadius;
  if ($cardBg !== '') $styleVars[] = '--abf-card-bg:' . $cardBg;
  if ($cardBorder !== '') $styleVars[] = '--abf-card-border:' . $cardBorder;

  $wrapperStyle = '';
  if (!empty($styleVars)) {
    $wrapperStyle = ' style="' . esc_attr(implode(';', $styleVars)) . '"';
  }

  ab_enqueue_assets();

  $categoryOptions = abf_category_options($macro, $settore);

  [$whereCat, $paramsCat] = abf_build_where($macro, $settore, $settore2);
  $regionOptions = abf_distinct_values('region', $whereCat, $paramsCat);

  [$whereProv, $paramsProv] = abf_build_where($macro, $settore, $settore2, $regione);
  $provinceOptions = abf_distinct_values('province', $whereProv, $paramsProv);

  [$whereCom, $paramsCom] = abf_build_where($macro, $settore, $settore2, $regione, $provincia);
  $comuneOptions = abf_distinct_values('city', $whereCom, $paramsCom);

  [$whereRows, $paramsRows] = abf_build_where($macro, $settore, $settore2, $regione, $provincia, $comune);
  $table = ab_table_sql();

  $selectFields = ['organization', 'category', 'region', 'province', 'city', 'location_raw', 'urls', 'emails', 'notes', 'source_block'];
  foreach (['macro', 'settore', 'settore2'] as $optionalField) {
    if (ab_table_has_column($optionalField)) {
      $selectFields[] = $optionalField;
    }
  }
  $selectSql = implode(', ', $selectFields);

  $sqlRows = "
    SELECT {$selectSql}
    FROM {$table}
    WHERE {$whereRows}
    ORDER BY organization ASC
  ";
  $rawRows = !empty($paramsRows)
    ? $wpdb->get_results($wpdb->prepare($sqlRows, $paramsRows), ARRAY_A)
    : $wpdb->get_results($sqlRows, ARRAY_A);

  // Keep one card per imported row. Do not collapse duplicates.
  $allRows = is_array($rawRows) ? array_values($rawRows) : [];
  $total = count($allRows);
  $pages = max(1, (int)ceil($total / $perPage));
  if ($page > $pages) $page = $pages;
  $offset = ($page - 1) * $perPage;
  $rows = array_slice($allRows, $offset, $perPage);

  $out = '<div id="abf-wrap-' . esc_attr($id) . '" class="' . esc_attr($wrapperClass) . '" data-abf-live="1" data-abf-instance="' . esc_attr($id) . '"' . $wrapperStyle . '>';
  if ((string)$atts['show_title'] !== '0' && trim((string)$atts['title']) !== '') {
    $out .= '<h3>' . ab_h((string)$atts['title']) . '</h3>';
  }
  $out .= abf_render_selected_breadcrumb([
    'macro' => $macro,
    'settore' => $settore,
    'settore2' => $settore2,
    'regione' => $regione,
    'provincia' => $provincia,
    'comune' => $comune,
  ]);

  $out .= '<style>
    .abf-toolbar {
        background-color: #336dac !important;
        color: white !important;
        display: flex;
        flex-direction: column;
    }
    .abf-toolbar .abf-field label {
        color: white !important;
    }
    .abf-toolbar .abf-field select {
        background-color: #d6e2ee !important;
        color: black !important;
    }
    .abf-toolbar .abf-field-reset .abf-btn {
        background-color: #193a5b !important;
        color: white !important;
        border-color: #193a5b !important;
    }
    .abf-toolbar-row.top-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
    }
    .abf-toolbar-row.bottom-row {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 1rem;
    }
  </style>';
  $out .= '<form method="get" class="abf-form">';
  $out .= '<div class="abf-toolbar">';
  $out .= '<div class="abf-toolbar-row top-row">';

  // Macro categoria.
  $out .= '<div class="abf-field"><label>Macro categoria</label><select name="' . esc_attr($k_macro) . '" data-abf-role="macro">';
  $out .= '<option value="">Tutte</option>';
  foreach ($categoryOptions['macro'] as $opt) {
    $sel = ($opt === $macro) ? ' selected' : '';
    $out .= '<option value="' . esc_attr($opt) . '"' . $sel . '>' . ab_h($opt) . '</option>';
  }
  $out .= '</select></div>';

  // Settore.
  $out .= '<div class="abf-field"><label>Settore</label><select name="' . esc_attr($k_settore) . '" data-abf-role="settore">';
  $out .= '<option value="">Tutti</option>';
  foreach ($categoryOptions['settore'] as $opt) {
    $sel = ($opt === $settore) ? ' selected' : '';
    $out .= '<option value="' . esc_attr($opt) . '"' . $sel . '>' . ab_h($opt) . '</option>';
  }
  $out .= '</select></div>';

  // Settore 2.
  $out .= '<div class="abf-field"><label>Settore 2</label><select name="' . esc_attr($k_settore2) . '" data-abf-role="settore2">';
  $out .= '<option value="">Tutti</option>';
  foreach ($categoryOptions['settore2'] as $opt) {
    $sel = ($opt === $settore2) ? ' selected' : '';
    $out .= '<option value="' . esc_attr($opt) . '"' . $sel . '>' . ab_h($opt) . '</option>';
  }
  $out .= '</select></div>';
  $out .= '</div>';

  $out .= '<div class="abf-toolbar-row bottom-row">';
  // Regione.
  $out .= '<div class="abf-field"><label>Regione</label><select name="' . esc_attr($k_regione) . '" data-abf-role="regione"><option value="">Tutte</option>';
  foreach ($regionOptions as $opt) {
    $sel = ($opt === $regione) ? ' selected' : '';
    $out .= '<option value="' . esc_attr($opt) . '"' . $sel . '>' . ab_h($opt) . '</option>';
  }
  $out .= '</select></div>';

  // Provincia.
  $out .= '<div class="abf-field"><label>Provincia</label><select name="' . esc_attr($k_prov) . '" data-abf-role="provincia"><option value="">Tutte</option>';
  foreach ($provinceOptions as $opt) {
    $sel = ($opt === $provincia) ? ' selected' : '';
    $out .= '<option value="' . esc_attr($opt) . '"' . $sel . '>' . ab_h($opt) . '</option>';
  }
  $out .= '</select></div>';

  // Comune / Citta.
  $out .= '<div class="abf-field"><label>Comune / Citta</label><select name="' . esc_attr($k_comune) . '" data-abf-role="comune"><option value="">Tutti</option>';
  foreach ($comuneOptions as $opt) {
    $sel = ($opt === $comune) ? ' selected' : '';
    $out .= '<option value="' . esc_attr($opt) . '"' . $sel . '>' . ab_h($opt) . '</option>';
  }
  $out .= '</select></div>';

  // Azzera next to the last dropdown.
  $out .= '<div class="abf-field abf-field-reset"><label>&nbsp;</label><a class="abf-btn" data-abf-reset="1" href="' . ab_link([
    $k_macro => null, $k_settore => null, $k_settore2 => null,
    $k_regione => null, $k_prov => null, $k_comune => null, $k_page => null
  ]) . '">Azzera</a></div>';
  $out .= '</div>';
  
  $out .= '</div>'; // .abf-toolbar

  $out .= '<input type="hidden" name="' . esc_attr($k_page) . '" value="1" data-abf-role="page">';
  $out .= '</form>';

  $out .= '<div class="abf-results">';
  $out .= '<div class="ab-muted">' . ab_h((string)$total) . ' associazioni trovate</div>';

  if (empty($rows)) {
    $out .= '<p>Nessuna associazione trovata con i filtri selezionati.</p>';
  } else {
    $out .= '<div class="abf-grid">';
    foreach ($rows as $row) {
      $out .= ab_assoc_render_card($row, 'abf-card', true);
    }
    $out .= '</div>';

    if ($pages > 1) {
      $out .= '<div class="abf-pager">';
      $out .= '<div class="abf-pager-nav">';
      if ($page > 1) {
        $out .= '<a class="abf-btn" href="' . ab_link([
          $k_macro => $macro ?: null,
          $k_settore => $settore ?: null,
          $k_settore2 => $settore2 ?: null,
          $k_regione => $regione ?: null,
          $k_prov => $provincia ?: null,
          $k_comune => $comune ?: null,
          $k_page => $page - 1
        ]) . '">Prec</a>';
      } else {
        $out .= '<span class="ab-muted">Prec</span>';
      }

      $out .= '<span class="ab-muted">Pagina ' . ab_h((string)$page) . ' di ' . ab_h((string)$pages) . '</span>';

      if ($page < $pages) {
        $out .= '<a class="abf-btn" href="' . ab_link([
          $k_macro => $macro ?: null,
          $k_settore => $settore ?: null,
          $k_settore2 => $settore2 ?: null,
          $k_regione => $regione ?: null,
          $k_prov => $provincia ?: null,
          $k_comune => $comune ?: null,
          $k_page => $page + 1
        ]) . '">Succ</a>';
      } else {
        $out .= '<span class="ab-muted">Succ</span>';
      }
      $out .= '</div>';

      $out .= '<div class="abf-page-list" aria-label="Pagine">';
      for ($p = 1; $p <= $pages; $p++) {
        if ($p === $page) {
          $out .= '<span class="abf-page-dot is-current" aria-current="page" aria-label="Pagina ' . ab_h((string)$p) . '"><span class="abf-sr-only">Pagina ' . ab_h((string)$p) . '</span></span>';
        } else {
          $out .= '<a class="abf-page-dot" href="' . ab_link([
            $k_macro => $macro ?: null,
            $k_settore => $settore ?: null,
            $k_settore2 => $settore2 ?: null,
            $k_regione => $regione ?: null,
            $k_prov => $provincia ?: null,
            $k_comune => $comune ?: null,
            $k_page => $p
          ]) . '" aria-label="Vai alla pagina ' . ab_h((string)$p) . '"><span class="abf-sr-only">Pagina ' . ab_h((string)$p) . '</span></a>';
        }
      }
      $out .= '</div>';
      $out .= '</div>';
    }
  }

  $out .= '</div></div>';
  return $out;
}
add_shortcode('associazioni_filtri', 'abf_shortcode');

function abf_render_settori_block(array $attributes = []): string {
  $instanceId = isset($attributes['instanceId']) ? (string)$attributes['instanceId'] : 'settori';
  $title = isset($attributes['title']) ? (string)$attributes['title'] : 'Categorie Attivita';
  $perPage = isset($attributes['perPage']) ? (int)$attributes['perPage'] : 24;
  $showTitle = isset($attributes['showTitle']) ? (bool)$attributes['showTitle'] : true;
  $customClass = isset($attributes['customClass']) ? (string)$attributes['customClass'] : '';
  $coreClass = isset($attributes['className']) ? (string)$attributes['className'] : '';

  $atts = [
    'id' => $instanceId !== '' ? $instanceId : 'settori',
    'title' => $title,
    'per_page' => $perPage > 0 ? $perPage : 24,
    'class' => trim($customClass . ' ' . $coreClass),
    'max_width' => isset($attributes['maxWidth']) ? (string)$attributes['maxWidth'] : '',
    'accent' => isset($attributes['accent']) ? (string)$attributes['accent'] : '',
    'card_radius' => isset($attributes['cardRadius']) ? (string)$attributes['cardRadius'] : '',
    'card_bg' => isset($attributes['cardBg']) ? (string)$attributes['cardBg'] : '',
    'card_border' => isset($attributes['cardBorder']) ? (string)$attributes['cardBorder'] : '',
    'show_title' => $showTitle ? '1' : '0',
  ];

  return abf_shortcode($atts);
}

function abf_register_settori_block(): void {
  if (!function_exists('register_block_type')) return;

  $handle = 'abf-settori-browser-block';
  wp_register_script(
    $handle,
    plugins_url('assets/js/settori-browser-block.js', __FILE__),
    ['wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-i18n', 'wp-server-side-render'],
    '1.6.1',
    true
  );

  register_block_type('culturacsi/settori-browser', [
    'api_version' => 2,
    'editor_script' => $handle,
    'render_callback' => 'abf_render_settori_block',
    'attributes' => [
      'title' => ['type' => 'string', 'default' => 'Categorie Attivita'],
      'instanceId' => ['type' => 'string', 'default' => 'settori'],
      'perPage' => ['type' => 'number', 'default' => 24],
      'showTitle' => ['type' => 'boolean', 'default' => true],
      'customClass' => ['type' => 'string', 'default' => ''],
      'maxWidth' => ['type' => 'string', 'default' => ''],
      'accent' => ['type' => 'string', 'default' => ''],
      'cardRadius' => ['type' => 'string', 'default' => ''],
      'cardBg' => ['type' => 'string', 'default' => ''],
      'cardBorder' => ['type' => 'string', 'default' => ''],
    ],
    'supports' => [
      'html' => false,
    ],
  ]);
}
add_action('init', 'abf_register_settori_block');

function abf_inject_into_settori_page(string $content): string {
  if (is_admin()) return $content;
  if (!is_page()) return $content;
  if (!in_the_loop() || !is_main_query()) return $content;

  $post = get_post();
  if (!$post instanceof WP_Post) return $content;

  $autoInject = (bool)apply_filters('abf_auto_inject_settori_page', true, $post);
  if (!$autoInject) return $content;

  if ($post->post_name !== 'settori') return $content;
  if (function_exists('has_block') && has_block('culturacsi/settori-browser', $post)) return $content;
  if (has_shortcode($content, 'associazioni_filtri')) return $content;

  return $content . "\n\n" . do_shortcode('[associazioni_filtri id=\"settori\"]');
}
add_filter('the_content', 'abf_inject_into_settori_page', 30);

/**
 * ---------------------------
 * CSV / Google Sheet Sync
 * ---------------------------
 *
 * Maps CSV columns like:
 * - MACRO CATEGORIA
 * - SETTORE
 * - SETTORE 2
 * - REGIONE
 * - PROVINCIA
 * - COMUNE-CITÀ
 * - NOME ASSOCIAZIONE
 * - CONTATTI (EMIAL)
 * - SITIO WEB
 * - FACEBOOK
 * - INSTAGRAM
 *
 * Into wp_associazioni-compatible rows.
 */

if (!defined('AB_SYNC_HOOK')) {
  define('AB_SYNC_HOOK', 'ab_sync_associazioni_from_csv');
}
if (!defined('AB_SYNC_URL_OPTION')) {
  define('AB_SYNC_URL_OPTION', 'ab_sync_csv_source_url');
}
if (!defined('AB_SYNC_LAST_OPTION')) {
  define('AB_SYNC_LAST_OPTION', 'ab_sync_last_result');
}

function ab_sync_normalize_text(string $value): string {
  $value = trim($value);
  if ($value === '') return '';
  $value = preg_replace('/\s+/u', ' ', $value);
  return trim((string)$value);
}

function ab_sync_norm_header(string $header): string {
  $header = strtolower(trim($header));
  if (function_exists('remove_accents')) {
    $header = remove_accents($header);
  }
  $header = preg_replace('/[^a-z0-9]+/', '_', $header);
  return trim((string)$header, '_');
}

function ab_sync_detect_delimiter(string $line): string {
  $candidates = [',', ';', "\t", '|'];
  $best = ',';
  $bestCount = -1;
  foreach ($candidates as $delimiter) {
    $count = count(str_getcsv($line, $delimiter));
    if ($count > $bestCount) {
      $bestCount = $count;
      $best = $delimiter;
    }
  }
  return $best;
}

function ab_sync_google_sheet_export_url(string $url): string {
  $url = trim($url);
  if ($url === '') return '';

  // Convert Google Drive file URL to direct download.
  if (preg_match('~drive\.google\.com/file/d/([a-zA-Z0-9_-]+)~', $url, $mDrive)) {
    $fileId = $mDrive[1];
    return "https://drive.google.com/uc?export=download&id={$fileId}";
  }

  // Convert Google Sheets edit URL to CSV export URL when possible.
  if (preg_match('~docs\.google\.com/spreadsheets/d/([a-zA-Z0-9-_]+)~', $url, $m)) {
    $sheetId = $m[1];
    $gid = '0';
    if (preg_match('/[?&]gid=([0-9]+)/', $url, $gm)) {
      $gid = $gm[1];
    } elseif (preg_match('/#gid=([0-9]+)/', $url, $gm2)) {
      $gid = $gm2[1];
    }
    return "https://docs.google.com/spreadsheets/d/{$sheetId}/export?format=csv&gid={$gid}";
  }

  return $url;
}

function ab_sync_map_index(array $headerIndex, array $aliases): ?int {
  foreach ($aliases as $alias) {
    if (isset($headerIndex[$alias])) return (int)$headerIndex[$alias];
  }
  return null;
}

function ab_sync_map_index_by_tokens(array $headerIndex, array $requiredTokens, array $forbiddenTokens = []): ?int {
  foreach ($headerIndex as $header => $index) {
    $ok = true;
    foreach ($requiredTokens as $token) {
      if ($token === '') continue;
      if (strpos((string)$header, (string)$token) === false) {
        $ok = false;
        break;
      }
    }
    if (!$ok) continue;

    foreach ($forbiddenTokens as $token) {
      if ($token === '') continue;
      if (strpos((string)$header, (string)$token) !== false) {
        $ok = false;
        break;
      }
    }

    if ($ok) return (int)$index;
  }
  return null;
}

function ab_sync_get_col(array $row, ?int $idx): string {
  if ($idx === null) return '';
  if (!isset($row[$idx])) return '';
  return ab_sync_normalize_text((string)$row[$idx]);
}

function ab_sync_parse_csv(string $csv) {
  $csv = preg_replace('/^\xEF\xBB\xBF/', '', $csv);
  if ($csv === null || trim($csv) === '') {
    return new WP_Error('ab_sync_empty', 'CSV vuoto o non valido.');
  }

  $firstLine = strtok($csv, "\n");
  $delimiter = ab_sync_detect_delimiter((string)$firstLine);

  $fp = fopen('php://temp', 'r+');
  if (!$fp) {
    return new WP_Error('ab_sync_stream', 'Impossibile aprire stream temporaneo per CSV.');
  }
  fwrite($fp, $csv);
  rewind($fp);

  $header = fgetcsv($fp, 0, $delimiter);
  if (!$header || !is_array($header)) {
    fclose($fp);
    return new WP_Error('ab_sync_header', 'Intestazione CSV non trovata.');
  }

  $headerIndex = [];
  foreach ($header as $i => $h) {
    $key = ab_sync_norm_header((string)$h);
    if ($key !== '') $headerIndex[$key] = $i;
  }

  $idxMacro = ab_sync_map_index($headerIndex, [
    'macro_categoria',
    'macro_categorie',
    'macrocategoria',
    'macro_category',
    'macro_categoria_acsi',
    'macro',
  ]);
  if ($idxMacro === null) {
    $idxMacro = ab_sync_map_index_by_tokens($headerIndex, ['macro', 'categor']);
  }

  $idxSettore = ab_sync_map_index($headerIndex, [
    'settore',
    'settore_1',
    'settore1',
    'settori',
    'settori_1',
    'settori1',
  ]);
  if ($idxSettore === null) {
    $idxSettore = ab_sync_map_index_by_tokens($headerIndex, ['settor'], ['2']);
  }

  $idxSettore2 = ab_sync_map_index($headerIndex, [
    'settore_2',
    'settore2',
    'settori_2',
    'settori2',
    'sotto_settore',
    'sottosettore',
  ]);
  if ($idxSettore2 === null) {
    $idxSettore2 = ab_sync_map_index_by_tokens($headerIndex, ['settor', '2']);
  }
  $idxRegione = ab_sync_map_index($headerIndex, ['regione', 'region']);
  $idxProvincia = ab_sync_map_index($headerIndex, ['provincia', 'province']);
  $idxComune = ab_sync_map_index($headerIndex, ['comune_citta', 'comune_cita', 'comune', 'citta']);
  $idxNome = ab_sync_map_index($headerIndex, ['nome_associazione', 'associazione', 'nome']);
  $idxContatti = ab_sync_map_index($headerIndex, ['contatti']);
  $idxEmail = ab_sync_map_index($headerIndex, ['contatti_emial', 'contatti_email', 'email', 'emails']);
  $idxSito = ab_sync_map_index($headerIndex, ['sitio_web', 'sito_web', 'sito', 'website', 'web']);
  $idxSocialFacebook = ab_sync_map_index($headerIndex, ['social_facebook']);
  $idxSocialInstagram = ab_sync_map_index($headerIndex, ['social_instagram']);
  $idxFacebook = ab_sync_map_index($headerIndex, ['facebook', 'fb']);
  $idxInstagram = ab_sync_map_index($headerIndex, ['instagram', 'insta']);

  if ($idxNome === null) {
    fclose($fp);
    return new WP_Error('ab_sync_missing_col', 'Colonna "NOME ASSOCIAZIONE" non trovata nel CSV.');
  }

  $rows = [];
  $rowKeyCounts = [];
  $line = 1;
  while (($data = fgetcsv($fp, 0, $delimiter)) !== false) {
    $line++;
    if (!is_array($data)) continue;

    $macro = ab_sync_get_col($data, $idxMacro);
    $settore = ab_sync_get_col($data, $idxSettore);
    $settore2 = ab_sync_get_col($data, $idxSettore2);
    $regione = ab_sync_get_col($data, $idxRegione);
    $provincia = ab_sync_get_col($data, $idxProvincia);
    $comune = ab_sync_get_col($data, $idxComune);
    $nome = ab_sync_get_col($data, $idxNome);
    $contatti = ab_sync_get_col($data, $idxContatti);
    $email = ab_assoc_extract_emails_from_text($contatti);
    if ($email === '') {
      $emailFromEmailCol = ab_sync_get_col($data, $idxEmail);
      $email = ab_assoc_extract_emails_from_text($emailFromEmailCol);
      if ($email === '' && $idxContatti === null) {
        $email = $emailFromEmailCol;
      }
    }
    $sito = ab_sync_get_col($data, $idxSito);
    $facebook = ab_sync_get_col($data, $idxSocialFacebook);
    if ($facebook === '') $facebook = ab_sync_get_col($data, $idxFacebook);
    $instagram = ab_sync_get_col($data, $idxSocialInstagram);
    if ($instagram === '') $instagram = ab_sync_get_col($data, $idxInstagram);

    if ($nome === '') continue;

    $category = ab_assoc_category_from_levels($macro, $settore, $settore2);
    $baseRowKey = ab_assoc_row_key_from_values($nome, $regione, $provincia, $comune, $category);
    $rowKey = $baseRowKey;
    if ($baseRowKey !== '') {
      if (!isset($rowKeyCounts[$baseRowKey])) {
        $rowKeyCounts[$baseRowKey] = 0;
      }
      $rowKeyCounts[$baseRowKey]++;
      if ($rowKeyCounts[$baseRowKey] > 1) {
        $rowKey .= '|dup:' . (string)$rowKeyCounts[$baseRowKey];
      }
    }

    $locationParts = array_values(array_filter([$comune, $provincia], fn($v) => $v !== ''));
    $locationRaw = implode(', ', $locationParts);

    $urls = array_values(array_unique(array_filter([$sito, $facebook, $instagram], fn($v) => $v !== '')));
    $urlsStr = implode(' | ', $urls);

    $source = $nome;
    if ($locationRaw !== '') $source .= ' - ' . $locationRaw;
    if ($urlsStr !== '') $source .= "\n" . $urlsStr;

    $rows[] = [
      'category'     => $category,
      'region'       => $regione,
      'organization' => $nome,
      'city'         => $comune,
      'province'     => $provincia,
      'macro'        => $macro,
      'settore'      => $settore,
      'settore2'     => $settore2,
      'website'      => $sito,
      'facebook'     => $facebook,
      'instagram'    => $instagram,
      'location_raw' => $locationRaw,
      'urls'         => $urlsStr,
      'emails'       => $email,
      'notes'        => '',
      'source_block' => $source,
      'source_key'   => ab_assoc_source_key($nome, $provincia, $comune, $regione),
      'row_key'      => $rowKey,
      '_line'        => $line,
    ];
  }

  fclose($fp);
  return $rows;
}

function ab_sync_first_token(string $raw): string {
  $parts = preg_split('/[|;,]+/', $raw);
  if (!is_array($parts)) return '';
  foreach ($parts as $part) {
    $clean = ab_sync_normalize_text((string)$part);
    if ($clean !== '') return $clean;
  }
  return '';
}

function ab_sync_set_meta_if_empty(int $postId, string $metaKey, string $value, string $type = 'text'): void {
  $value = ab_sync_normalize_text($value);
  if ($value === '') return;

  if ($type === 'url') {
    $value = ab_assoc_normalize_url($value);
    if ($value === '') return;
  } elseif ($type === 'email') {
    $value = sanitize_email($value);
    if ($value === '') return;
  }

  update_post_meta($postId, '_ab_csv_' . $metaKey, $value);
  $existing = trim((string)get_post_meta($postId, $metaKey, true));
  if ($existing === '') {
    update_post_meta($postId, $metaKey, $value);
  }
}

function ab_sync_set_external_url_meta(int $postId, array $candidates): void {
  $externalUrl = '';

  foreach ($candidates as $candidateRaw) {
    $candidate = ab_assoc_normalize_url((string)$candidateRaw);
    if ($candidate === '') continue;
    if (!ab_assoc_is_external_url($candidate)) continue;
    $externalUrl = $candidate;
    break;
  }

  if ($externalUrl !== '') {
    update_post_meta($postId, '_hebeae_external_url', $externalUrl);
    update_post_meta($postId, '_hebeae_external_enabled', '1');
    return;
  }

  update_post_meta($postId, '_hebeae_external_enabled', '0');
  delete_post_meta($postId, '_hebeae_external_url');
}

function ab_sync_set_activity_category_if_empty(int $postId, string $categoryPath): void {
  if (!taxonomy_exists('activity_category')) return;
  $categoryPath = trim($categoryPath);
  if ($categoryPath === '' || ab_assoc_is_placeholder_label($categoryPath)) return;

  $segments = ab_split_category($categoryPath);
  if (empty($segments)) return;

  $parentId = 0;
  $leafId = 0;

  foreach ($segments as $segmentRaw) {
    $segment = ab_sync_normalize_text((string)$segmentRaw);
    if ($segment === '') continue;

    $term = term_exists($segment, 'activity_category', $parentId);
    if (is_wp_error($term)) {
      continue;
    }

    $termId = 0;
    if (is_array($term) && isset($term['term_id'])) {
      $termId = (int)$term['term_id'];
    } elseif (is_numeric($term)) {
      $termId = (int)$term;
    } else {
      $inserted = wp_insert_term($segment, 'activity_category', ['parent' => $parentId]);
      if (is_wp_error($inserted) || !isset($inserted['term_id'])) {
        continue;
      }
      $termId = (int)$inserted['term_id'];
    }

    if ($termId > 0) {
      $leafId = $termId;
      $parentId = $termId;
    }
  }

  if ($leafId > 0) {
    $existingIds = wp_get_post_terms($postId, 'activity_category', ['fields' => 'ids']);
    if (is_wp_error($existingIds) || !is_array($existingIds)) {
      $existingIds = [];
    }
    $termIds = array_values(array_unique(array_filter(array_merge($existingIds, [$leafId]), fn($id) => (int)$id > 0)));
    wp_set_post_terms($postId, $termIds, 'activity_category', false);
  }
}

function ab_sync_prune_stale_association_posts(array $keepPostIds): array {
  $keepPostIds = array_values(array_filter(array_map('intval', $keepPostIds), fn($id) => $id > 0));
  if (empty($keepPostIds)) {
    return ['trashed' => 0, 'errors' => 0];
  }

  global $wpdb;

  $placeholders = implode(',', array_fill(0, count($keepPostIds), '%d'));
  $params = array_merge(['_ab_source_key', '_ab_row_key'], $keepPostIds);

  $sql = "
    SELECT DISTINCT p.ID
    FROM {$wpdb->posts} p
    INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
    WHERE p.post_type = 'association'
      AND p.post_status NOT IN ('trash', 'auto-draft')
      AND pm.meta_key IN (%s, %s)
      AND p.ID NOT IN ({$placeholders})
  ";

  $ids = $wpdb->get_col($wpdb->prepare($sql, $params));
  if (!is_array($ids) || empty($ids)) {
    return ['trashed' => 0, 'errors' => 0];
  }

  $trashed = 0;
  $errors = 0;
  foreach ($ids as $id) {
    $id = (int)$id;
    if ($id <= 0) continue;
    $result = wp_trash_post($id);
    if ($result instanceof WP_Post) {
      $trashed++;
    } else {
      $errors++;
    }
  }

  return ['trashed' => $trashed, 'errors' => $errors];
}

function ab_sync_upsert_association_posts(array $rows) {
  if (!post_type_exists('association')) {
    return [
      'processed' => count($rows),
      'linked' => 0,
      'created' => 0,
      'matched' => 0,
      'errors' => 0,
      'pruned' => 0,
      'prune_errors' => 0,
    ];
  }

  global $wpdb;

  $sourceMap = [];
  $rowKeyMap = [];

  $metaKey = '_ab_source_key';
  $sqlMeta = "
    SELECT pm.meta_value AS source_key, pm.post_id
    FROM {$wpdb->postmeta} pm
    INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
    WHERE pm.meta_key = %s
      AND p.post_type = 'association'
      AND p.post_status NOT IN ('trash', 'auto-draft')
  ";
  $metaRows = $wpdb->get_results($wpdb->prepare($sqlMeta, [$metaKey]), ARRAY_A);
  foreach ($metaRows as $metaRow) {
    $sourceKey = trim((string)($metaRow['source_key'] ?? ''));
    $postId = (int)($metaRow['post_id'] ?? 0);
    if ($sourceKey !== '' && $postId > 0) {
      $sourceMap[$sourceKey] = $postId;
    }
  }

  $rowMetaKey = '_ab_row_key';
  $sqlRowMeta = "
    SELECT pm.meta_value AS row_key, pm.post_id
    FROM {$wpdb->postmeta} pm
    INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
    WHERE pm.meta_key = %s
      AND p.post_type = 'association'
      AND p.post_status NOT IN ('trash', 'auto-draft')
  ";
  $rowMetaRows = $wpdb->get_results($wpdb->prepare($sqlRowMeta, [$rowMetaKey]), ARRAY_A);
  foreach ($rowMetaRows as $rowMeta) {
    $rowKey = trim((string)($rowMeta['row_key'] ?? ''));
    $postId = (int)($rowMeta['post_id'] ?? 0);
    if ($rowKey !== '' && $postId > 0) {
      $rowKeyMap[$rowKey] = $postId;
    }
  }

  $sqlNames = "
    SELECT ID, post_title
    FROM {$wpdb->posts}
    WHERE post_type = 'association'
      AND post_status NOT IN ('trash', 'auto-draft')
  ";
  $nameRows = $wpdb->get_results($sqlNames, ARRAY_A);
  foreach ($nameRows as $nameRow) {
    $postId = (int)($nameRow['ID'] ?? 0);
    if ($postId <= 0) continue;
    $rowKey = ab_assoc_post_row_key($postId, (string)($nameRow['post_title'] ?? ''));
    if ($rowKey !== '' && !isset($rowKeyMap[$rowKey])) {
      $rowKeyMap[$rowKey] = $postId;
    }
  }

  $processed = 0;
  $linked = 0;
  $created = 0;
  $matched = 0;
  $errors = 0;
  $touchedPostIds = [];

  foreach ($rows as $row) {
    $organization = trim((string)($row['organization'] ?? ''));
    if ($organization === '') continue;
    $processed++;
    $resolvedCategory = ab_assoc_resolve_category_from_row($row);

    $province = trim((string)($row['province'] ?? ''));
    $city = trim((string)($row['city'] ?? ''));
    $region = trim((string)($row['region'] ?? ''));
    $rowKey = trim((string)($row['row_key'] ?? ''));
    if ($rowKey === '') {
      $rowKey = ab_assoc_row_key_from_values($organization, $region, $province, $city, $resolvedCategory);
    }
    $sourceKey = trim((string)($row['source_key'] ?? ''));
    if ($sourceKey === '') {
      $sourceKey = ab_assoc_source_key($organization, $province, $city, $region);
    }

    $postId = 0;
    if ($rowKey !== '' && isset($rowKeyMap[$rowKey])) {
      $candidateId = (int)$rowKeyMap[$rowKey];
      if (ab_assoc_post_matches_row($candidateId, $rowKey, $sourceKey)) {
        $postId = $candidateId;
      }
    }

    if ($postId <= 0 && $sourceKey !== '' && isset($sourceMap[$sourceKey])) {
      $candidateId = (int)$sourceMap[$sourceKey];
      if (ab_assoc_post_matches_row($candidateId, $rowKey, $sourceKey)) {
        $postId = $candidateId;
      }
    }

    if ($postId > 0) {
      $matched++;
    } else {
      $postId = wp_insert_post([
        'post_type' => 'association',
        'post_status' => 'publish',
        'post_title' => $organization,
      ], true);

      if (is_wp_error($postId)) {
        $errors++;
        continue;
      }

      $postId = (int)$postId;
      if ($postId <= 0) {
        $errors++;
        continue;
      }

      $created++;
    }

    $linked++;
    $touchedPostIds[$postId] = true;

    if ($rowKey !== '') {
      update_post_meta($postId, '_ab_row_key', $rowKey);
      $rowKeyMap[$rowKey] = $postId;
    }
    if ($sourceKey !== '') {
      update_post_meta($postId, '_ab_source_key', $sourceKey);
      $sourceMap[$sourceKey] = $postId;
    }

    $urlsParsed = ab_assoc_parse_urls((string)($row['urls'] ?? ''));
    $website = trim((string)($row['website'] ?? ''));
    $facebook = trim((string)($row['facebook'] ?? ''));
    $instagram = trim((string)($row['instagram'] ?? ''));
    if ($website === '') $website = (string)$urlsParsed['website'];
    if ($facebook === '') $facebook = (string)$urlsParsed['facebook'];
    if ($instagram === '') $instagram = (string)$urlsParsed['instagram'];

    $emailPrimary = ab_sync_first_token((string)($row['emails'] ?? ''));
    $externalUrlCandidates = [$website, $facebook, $instagram];
    $rawUrlParts = ab_assoc_extract_urls_from_text((string)($row['urls'] ?? ''));
    if (!empty($rawUrlParts)) {
      $externalUrlCandidates = array_merge($externalUrlCandidates, $rawUrlParts);
    }

    ab_sync_set_meta_if_empty($postId, 'comune', $city);
    ab_sync_set_meta_if_empty($postId, 'city', $city);
    ab_sync_set_meta_if_empty($postId, 'province', $province);
    ab_sync_set_meta_if_empty($postId, 'region', $region);
    ab_sync_set_meta_if_empty($postId, 'email', $emailPrimary, 'email');
    ab_sync_set_meta_if_empty($postId, 'website', $website, 'url');
    ab_sync_set_meta_if_empty($postId, 'facebook', $facebook, 'url');
    ab_sync_set_meta_if_empty($postId, 'instagram', $instagram, 'url');
    update_post_meta($postId, '_ab_csv_category', $resolvedCategory);
    update_post_meta($postId, '_ab_csv_macro', (string)($row['macro'] ?? ''));
    update_post_meta($postId, '_ab_csv_settore', (string)($row['settore'] ?? ''));
    update_post_meta($postId, '_ab_csv_settore2', (string)($row['settore2'] ?? ''));
    update_post_meta($postId, '_ab_csv_region', (string)($row['region'] ?? ''));
    update_post_meta($postId, '_ab_csv_last_sync', current_time('mysql'));
    ab_sync_set_external_url_meta($postId, $externalUrlCandidates);

    ab_sync_set_activity_category_if_empty($postId, $resolvedCategory);
  }

  $pruned = 0;
  $pruneErrors = 0;
  $shouldPrune = (bool)apply_filters('ab_sync_prune_stale_association_posts', true, $rows);
  if ($shouldPrune && $processed > 0 && !empty($touchedPostIds)) {
    $prune = ab_sync_prune_stale_association_posts(array_keys($touchedPostIds));
    $pruned = (int)($prune['trashed'] ?? 0);
    $pruneErrors = (int)($prune['errors'] ?? 0);
  }

  return [
    'processed' => $processed,
    'linked' => $linked,
    'created' => $created,
    'matched' => $matched,
    'errors' => $errors,
    'pruned' => $pruned,
    'prune_errors' => $pruneErrors,
  ];
}

function ab_sync_ensure_table(): void {
  global $wpdb;
  $table = ab_table();
  $tableSql = ab_table_sql();
  $charset = $wpdb->get_charset_collate();
  $sql = "CREATE TABLE IF NOT EXISTS `{$table}` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category` TEXT NOT NULL,
    `macro` VARCHAR(191) DEFAULT '',
    `settore` VARCHAR(191) DEFAULT '',
    `settore2` VARCHAR(191) DEFAULT '',
    `region` VARCHAR(191) DEFAULT '',
    `organization` VARCHAR(255) DEFAULT '',
    `city` VARCHAR(191) DEFAULT '',
    `province` VARCHAR(191) DEFAULT '',
    `location_raw` VARCHAR(255) DEFAULT '',
    `urls` TEXT NULL,
    `emails` TEXT NULL,
    `notes` LONGTEXT NULL,
    `source_block` LONGTEXT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_region` (`region`),
    KEY `idx_province` (`province`),
    KEY `idx_org` (`organization`),
    KEY `idx_macro` (`macro`),
    KEY `idx_settore` (`settore`),
    KEY `idx_settore2` (`settore2`)
  ) {$charset};";
  $wpdb->query($sql);

  $columnDefs = [
    'macro' => "VARCHAR(191) DEFAULT ''",
    'settore' => "VARCHAR(191) DEFAULT ''",
    'settore2' => "VARCHAR(191) DEFAULT ''",
  ];
  foreach ($columnDefs as $column => $definition) {
    delete_transient(ab_cache_key('ab_table_col_', [get_current_blog_id(), ab_table(), $column]));
    $exists = (bool)$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$tableSql} LIKE %s", [$column]));
    if (!$exists) {
      $wpdb->query("ALTER TABLE {$tableSql} ADD COLUMN `{$column}` {$definition}");
    }
  }

  $indexDefs = [
    'idx_macro' => 'macro',
    'idx_settore' => 'settore',
    'idx_settore2' => 'settore2',
  ];
  foreach ($indexDefs as $indexName => $columnName) {
    $indexExists = (bool)$wpdb->get_var($wpdb->prepare("SHOW INDEX FROM {$tableSql} WHERE Key_name = %s", [$indexName]));
    if (!$indexExists && ab_table_has_column($columnName)) {
      $wpdb->query("ALTER TABLE {$tableSql} ADD KEY `{$indexName}` (`{$columnName}`)");
    }
  }
}

function ab_sync_clear_cache(): void {
  global $wpdb;
  $wpdb->query("
    DELETE FROM {$wpdb->options}
    WHERE option_name LIKE '_transient_ab_%'
       OR option_name LIKE '_transient_timeout_ab_%'
       OR option_name LIKE '_transient_abf_%'
       OR option_name LIKE '_transient_timeout_abf_%'
       OR option_name LIKE '_transient_ab_table_col_%'
       OR option_name LIKE '_transient_timeout_ab_table_col_%'
  ");
}

function ab_sync_import_rows(array $rows) {
  global $wpdb;
  $tableSql = ab_table_sql();
  $table = ab_table();

  ab_sync_ensure_table();

  $wpdb->query("TRUNCATE TABLE {$tableSql}");
  if (!empty($wpdb->last_error)) {
    $wpdb->query("DELETE FROM {$tableSql}");
    if (!empty($wpdb->last_error)) {
      return new WP_Error('ab_sync_truncate', 'Impossibile svuotare tabella: ' . $wpdb->last_error);
    }
  }

  $inserted = 0;
  foreach ($rows as $row) {
    $resolvedCategory = ab_assoc_resolve_category_from_row($row);
    $ok = $wpdb->insert(
      $table,
      [
        'category'     => $resolvedCategory,
        'macro'        => $row['macro'] ?? '',
        'settore'      => $row['settore'] ?? '',
        'settore2'     => $row['settore2'] ?? '',
        'region'       => $row['region'] ?? '',
        'organization' => $row['organization'] ?? '',
        'city'         => $row['city'] ?? '',
        'province'     => $row['province'] ?? '',
        'location_raw' => $row['location_raw'] ?? '',
        'urls'         => $row['urls'] ?? '',
        'emails'       => $row['emails'] ?? '',
        'notes'        => $row['notes'] ?? '',
        'source_block' => $row['source_block'] ?? '',
      ],
      ['%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s','%s']
    );
    if ($ok === false) {
      return new WP_Error('ab_sync_insert', 'Errore insert riga CSV (linea ' . (int)($row['_line'] ?? 0) . '): ' . $wpdb->last_error);
    }
    $inserted++;
  }

  ab_sync_clear_cache();

  return [
    'inserted' => $inserted,
    'total' => count($rows),
  ];
}

function ab_sync_fetch_remote_csv(string $rawUrl) {
  $url = ab_sync_google_sheet_export_url($rawUrl);
  if ($url === '') {
    return new WP_Error('ab_sync_no_url', 'URL CSV non impostato.');
  }

  $response = wp_remote_get($url, ['timeout' => 35, 'redirection' => 5]);
  if (is_wp_error($response)) return $response;

  $code = wp_remote_retrieve_response_code($response);
  if ($code < 200 || $code >= 300) {
    return new WP_Error('ab_sync_http', 'HTTP non valido durante download CSV: ' . $code);
  }

  $body = wp_remote_retrieve_body($response);
  if (!is_string($body) || trim($body) === '') {
    return new WP_Error('ab_sync_empty_remote', 'CSV remoto vuoto.');
  }

  return $body;
}

function ab_sync_run_import_from_csv_text(string $csvText, string $source = 'manual') {
  $rows = ab_sync_parse_csv($csvText);
  if (is_wp_error($rows)) return $rows;

  $assocResult = ab_sync_upsert_association_posts($rows);
  if (is_wp_error($assocResult)) return $assocResult;

  $result = ab_sync_import_rows($rows);
  if (is_wp_error($result)) return $result;

  update_option(AB_SYNC_LAST_OPTION, [
    'ok' => true,
    'source' => $source,
    'inserted' => (int)$result['inserted'],
    'total' => (int)$result['total'],
    'assoc_processed' => (int)($assocResult['processed'] ?? 0),
    'assoc_linked' => (int)($assocResult['linked'] ?? 0),
    'assoc_created' => (int)($assocResult['created'] ?? 0),
    'assoc_matched' => (int)($assocResult['matched'] ?? 0),
    'assoc_errors' => (int)($assocResult['errors'] ?? 0),
    'assoc_pruned' => (int)($assocResult['pruned'] ?? 0),
    'assoc_prune_errors' => (int)($assocResult['prune_errors'] ?? 0),
    'time' => current_time('mysql'),
  ], false);

  $result['associations'] = $assocResult;
  return $result;
}

function ab_sync_run_import_from_url() {
  $rawUrl = (string)get_option(AB_SYNC_URL_OPTION, '');
  $csv = ab_sync_fetch_remote_csv($rawUrl);
  if (is_wp_error($csv)) {
    update_option(AB_SYNC_LAST_OPTION, [
      'ok' => false,
      'source' => 'cron_url',
      'error' => $csv->get_error_message(),
      'time' => current_time('mysql'),
    ], false);
    return $csv;
  }
  return ab_sync_run_import_from_csv_text($csv, 'url');
}

function ab_sync_admin_menu(): void {
  add_management_page(
    'Associazioni Sync CSV',
    'Associazioni Sync CSV',
    'manage_options',
    'ab-sync-csv',
    'ab_sync_admin_page'
  );
  add_management_page(
    'Settori Immagini',
    'Settori Immagini',
    'manage_options',
    'ab-settori-images',
    'ab_settori_images_admin_page'
  );
}
add_action('admin_menu', 'ab_sync_admin_menu');

function ab_sync_admin_page(): void {
  if (!current_user_can('manage_options')) return;

  $message = isset($_GET['ab_msg']) ? sanitize_text_field((string)$_GET['ab_msg']) : '';
  $status = isset($_GET['ab_status']) ? sanitize_key((string)$_GET['ab_status']) : '';
  $last = get_option(AB_SYNC_LAST_OPTION, []);
  $url = (string)get_option(AB_SYNC_URL_OPTION, '');
  ?>
  <div class="wrap">
    <h1>Associazioni - Sync CSV</h1>
    <p>Importa associazioni da CSV con struttura: MACRO CATEGORIA, SETTORE, SETTORE 2, REGIONE, PROVINCIA, COMUNE-CITÀ, NOME ASSOCIAZIONE, CONTATTI (EMIAL), SITIO WEB, FACEBOOK, INSTAGRAM.</p>

    <?php if ($message !== ''): ?>
      <div class="notice <?php echo $status === 'ok' ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <h2>Sorgente Google Sheet / CSV URL</h2>
    <form method="post">
      <?php wp_nonce_field('ab_sync_save_url'); ?>
      <input type="hidden" name="ab_sync_action" value="save_url">
      <table class="form-table">
        <tr>
          <th scope="row"><label for="ab_csv_url">URL CSV</label></th>
          <td>
            <input type="url" name="ab_csv_url" id="ab_csv_url" class="regular-text" value="<?php echo esc_attr($url); ?>" placeholder="https://docs.google.com/spreadsheets/d/.../edit#gid=0">
            <p class="description">Puoi incollare anche URL Google Sheet standard: verra convertito in export CSV.</p>
          </td>
        </tr>
      </table>
      <?php submit_button('Salva URL'); ?>
    </form>

    <form method="post" style="margin-top:8px;">
      <?php wp_nonce_field('ab_sync_run_url'); ?>
      <input type="hidden" name="ab_sync_action" value="sync_url">
      <?php submit_button('Sync adesso da URL', 'secondary'); ?>
    </form>

    <hr>
    <h2>Import manuale file CSV</h2>
    <form method="post" enctype="multipart/form-data">
      <?php wp_nonce_field('ab_sync_upload_csv'); ?>
      <input type="hidden" name="ab_sync_action" value="upload_csv">
      <input type="file" name="ab_csv_file" accept=".csv,text/csv" required>
      <?php submit_button('Importa CSV', 'primary'); ?>
    </form>

    <hr>
    <h2>Ultimo Sync</h2>
    <?php if (is_array($last) && !empty($last)): ?>
      <p><strong>Esito:</strong> <?php echo !empty($last['ok']) ? 'OK' : 'Errore'; ?></p>
      <p><strong>Origine:</strong> <?php echo esc_html((string)($last['source'] ?? 'n/d')); ?></p>
      <p><strong>Righe importate:</strong> <?php echo (int)($last['inserted'] ?? 0); ?> / <?php echo (int)($last['total'] ?? 0); ?></p>
      <p><strong>Associazioni collegate:</strong> <?php echo (int)($last['assoc_linked'] ?? 0); ?> (nuove: <?php echo (int)($last['assoc_created'] ?? 0); ?>, esistenti: <?php echo (int)($last['assoc_matched'] ?? 0); ?>)</p>
      <?php if (!empty($last['assoc_pruned'])): ?><p><strong>Associazioni obsolete rimosse:</strong> <?php echo (int)$last['assoc_pruned']; ?></p><?php endif; ?>
      <?php if (!empty($last['assoc_errors'])): ?><p><strong>Errori sync associazioni:</strong> <?php echo (int)$last['assoc_errors']; ?></p><?php endif; ?>
      <?php if (!empty($last['assoc_prune_errors'])): ?><p><strong>Errori rimozione obsolete:</strong> <?php echo (int)$last['assoc_prune_errors']; ?></p><?php endif; ?>
      <?php if (!empty($last['error'])): ?><p><strong>Errore:</strong> <?php echo esc_html((string)$last['error']); ?></p><?php endif; ?>
      <p><strong>Data:</strong> <?php echo esc_html((string)($last['time'] ?? 'n/d')); ?></p>
    <?php else: ?>
      <p>Nessun sync eseguito finora.</p>
    <?php endif; ?>
  </div>
  <?php
}

function ab_settori_images_admin_assets(string $hook): void {
  if (!is_admin()) return;
  $page = isset($_GET['page']) ? sanitize_key((string)$_GET['page']) : '';
  if ($page !== 'ab-settori-images') return;

  wp_enqueue_media();
  wp_enqueue_script(
    'ab-settori-images-admin',
    plugins_url('assets/js/settori-hero-admin.js', __FILE__),
    ['jquery'],
    '1.0.0',
    true
  );
}
add_action('admin_enqueue_scripts', 'ab_settori_images_admin_assets');

function ab_settori_images_admin_page(): void {
  if (!current_user_can('manage_options')) return;

  $message = isset($_GET['ab_img_msg']) ? sanitize_text_field((string)$_GET['ab_img_msg']) : '';
  $status = isset($_GET['ab_img_status']) ? sanitize_key((string)$_GET['ab_img_status']) : '';
  $labelMap = abf_collect_hero_label_map();
  $overrides = abf_get_hero_image_overrides();
  $effectiveMap = abf_get_hero_image_map();
  $total = count($labelMap);
  $assigned = 0;
  foreach ($effectiveMap as $url) {
    if (is_string($url) && trim($url) !== '') {
      $assigned++;
    }
  }
  $missing = max(0, $total - $assigned);
  ?>
  <div class="wrap">
    <h1>Settori - Gestione Immagini</h1>
    <p>Verifica l'immagine assegnata a ogni settore e imposta un override manuale quando necessario.</p>

    <?php if ($message !== ''): ?>
      <div class="notice <?php echo $status === 'ok' ? 'notice-success' : 'notice-error'; ?> is-dismissible"><p><?php echo esc_html($message); ?></p></div>
    <?php endif; ?>

    <p>
      <strong>Settori totali:</strong> <?php echo (int)$total; ?>
      &nbsp; | &nbsp;
      <strong>Con immagine:</strong> <?php echo (int)$assigned; ?>
      &nbsp; | &nbsp;
      <strong>Senza immagine:</strong> <?php echo (int)$missing; ?>
      &nbsp; | &nbsp;
      <strong>Override manuali:</strong> <?php echo (int)count($overrides); ?>
    </p>

    <?php if (empty($labelMap)): ?>
      <p>Nessun settore trovato. Verifica i dati importati.</p>
    <?php else: ?>
      <style>
        .abf-hero-table td{vertical-align:top}
        .abf-hero-key{font-family:monospace;color:#50575e;font-size:12px}
        .abf-hero-labels{margin-top:4px;color:#50575e;font-size:12px}
        .abf-hero-preview{width:120px;height:80px;border:1px solid #dcdcde;background:#f6f7f7;display:flex;align-items:center;justify-content:center;overflow:hidden}
        .abf-hero-preview img{width:100%;height:100%;object-fit:cover;display:block}
        .abf-hero-preview-empty{font-size:11px;color:#787c82;padding:6px;text-align:center}
        .abf-hero-actions{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
        .abf-hero-override-meta{font-size:12px;color:#50575e}
        .abf-hero-row-missing{background:#fff8f8}
      </style>

      <form method="post">
        <?php wp_nonce_field('ab_settori_images_save'); ?>
        <input type="hidden" name="ab_sync_action" value="save_settori_images">

        <table class="widefat striped abf-hero-table">
          <thead>
            <tr>
              <th style="width:32%;">Settore</th>
              <th style="width:24%;">Immagine assegnata</th>
              <th style="width:30%;">Override manuale</th>
              <th style="width:14%;">Azioni</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($labelMap as $key => $labels): ?>
              <?php
              $labels = is_array($labels) ? $labels : [];
              $title = !empty($labels) ? (string)$labels[0] : (string)$key;
              $effectiveUrl = trim((string)($effectiveMap[$key] ?? ''));
              $overrideId = (int)($overrides[$key] ?? 0);
              $overrideThumb = $overrideId > 0 ? (string)wp_get_attachment_image_url($overrideId, 'thumbnail') : '';
              $overrideFull = $overrideId > 0 ? (string)wp_get_attachment_image_url($overrideId, 'full') : '';
              if ($overrideThumb === '' && $overrideFull !== '') {
                $overrideThumb = $overrideFull;
              }
              $rowClass = ($effectiveUrl === '') ? 'abf-hero-row-missing' : '';
              ?>
              <tr class="<?php echo esc_attr($rowClass); ?>" data-hero-key="<?php echo esc_attr($key); ?>">
                <td>
                  <strong><?php echo esc_html($title); ?></strong>
                  <div class="abf-hero-key"><?php echo esc_html($key); ?></div>
                  <?php if (count($labels) > 1): ?>
                    <div class="abf-hero-labels"><?php echo esc_html(implode(' | ', $labels)); ?></div>
                  <?php endif; ?>
                </td>
                <td>
                  <div class="abf-hero-preview">
                    <?php if ($effectiveUrl !== ''): ?>
                      <img src="<?php echo esc_url($effectiveUrl); ?>" alt="">
                    <?php else: ?>
                      <div class="abf-hero-preview-empty">Nessuna immagine</div>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <input type="hidden" class="abf-hero-override-id" name="ab_settori_override[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr((string)$overrideId); ?>">
                  <div class="abf-hero-preview abf-hero-override-preview-box">
                    <?php if ($overrideThumb !== ''): ?>
                      <img class="abf-hero-override-preview" src="<?php echo esc_url($overrideThumb); ?>" alt="">
                    <?php else: ?>
                      <div class="abf-hero-preview-empty abf-hero-override-preview-empty">Nessun override</div>
                      <img class="abf-hero-override-preview" src="" alt="" style="display:none;">
                    <?php endif; ?>
                  </div>
                  <div class="abf-hero-override-meta" style="margin-top:6px;">
                    <span class="abf-hero-override-id-label">
                      <?php echo $overrideId > 0 ? 'Attachment ID: ' . (int)$overrideId : 'Override non impostato'; ?>
                    </span>
                    <?php if ($overrideFull !== ''): ?>
                      &nbsp;|&nbsp;
                      <a class="abf-hero-override-open" href="<?php echo esc_url($overrideFull); ?>" target="_blank" rel="noopener noreferrer">Apri</a>
                    <?php else: ?>
                      <a class="abf-hero-override-open" href="#" target="_blank" rel="noopener noreferrer" style="display:none;">Apri</a>
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <div class="abf-hero-actions">
                    <button type="button" class="button abf-hero-select-image">Seleziona</button>
                    <button type="button" class="button-link-delete abf-hero-clear-image">Rimuovi</button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <?php submit_button('Salva Immagini Settori', 'primary', 'submit', true, ['style' => 'margin-top:16px;']); ?>
      </form>
    <?php endif; ?>
  </div>
  <?php
}

function ab_sync_handle_admin_post(): void {
  if (!is_admin() || !current_user_can('manage_options')) return;
  if (!isset($_POST['ab_sync_action'])) return;

  $action = sanitize_key((string)$_POST['ab_sync_action']);

  if ($action === 'save_settori_images') {
    check_admin_referer('ab_settori_images_save');
    $redirectImages = admin_url('tools.php?page=ab-settori-images');
    $rawOverrides = isset($_POST['ab_settori_override']) && is_array($_POST['ab_settori_override'])
      ? (array)wp_unslash($_POST['ab_settori_override'])
      : [];
    $cleanOverrides = abf_sanitize_hero_image_overrides($rawOverrides);
    update_option(AB_SETTORI_HERO_OVERRIDES_OPTION, $cleanOverrides, false);
    update_option('ab_settori_hero_cache_version', time(), false);

    $msg = sprintf('Immagini settori salvate. Override attivi: %d.', count($cleanOverrides));
    wp_safe_redirect(add_query_arg(['ab_img_status' => 'ok', 'ab_img_msg' => $msg], $redirectImages));
    exit;
  }

  $redirect = admin_url('tools.php?page=ab-sync-csv');

  if ($action === 'save_url') {
    check_admin_referer('ab_sync_save_url');
    $raw = isset($_POST['ab_csv_url']) ? esc_url_raw((string)wp_unslash($_POST['ab_csv_url'])) : '';
    update_option(AB_SYNC_URL_OPTION, $raw, false);
    wp_safe_redirect(add_query_arg(['ab_status' => 'ok', 'ab_msg' => 'URL salvato.'], $redirect));
    exit;
  }

  if ($action === 'sync_url') {
    check_admin_referer('ab_sync_run_url');
    $result = ab_sync_run_import_from_url();
    if (is_wp_error($result)) {
      wp_safe_redirect(add_query_arg(['ab_status' => 'err', 'ab_msg' => $result->get_error_message()], $redirect));
      exit;
    }
    $msg = sprintf(
      'Sync completato. Importate %d righe. Associazioni collegate: %d (nuove: %d).',
      (int)$result['inserted'],
      (int)($result['associations']['linked'] ?? 0),
      (int)($result['associations']['created'] ?? 0)
    );
    wp_safe_redirect(add_query_arg(['ab_status' => 'ok', 'ab_msg' => $msg], $redirect));
    exit;
  }

  if ($action === 'upload_csv') {
    check_admin_referer('ab_sync_upload_csv');
    if (!isset($_FILES['ab_csv_file']) || !is_uploaded_file($_FILES['ab_csv_file']['tmp_name'])) {
      wp_safe_redirect(add_query_arg(['ab_status' => 'err', 'ab_msg' => 'File CSV non valido.'], $redirect));
      exit;
    }
    $content = file_get_contents($_FILES['ab_csv_file']['tmp_name']);
    if ($content === false) {
      wp_safe_redirect(add_query_arg(['ab_status' => 'err', 'ab_msg' => 'Impossibile leggere il file CSV.'], $redirect));
      exit;
    }
    $result = ab_sync_run_import_from_csv_text($content, 'upload');
    if (is_wp_error($result)) {
      wp_safe_redirect(add_query_arg(['ab_status' => 'err', 'ab_msg' => $result->get_error_message()], $redirect));
      exit;
    }
    $msg = sprintf(
      'Import completato. Importate %d righe. Associazioni collegate: %d (nuove: %d).',
      (int)$result['inserted'],
      (int)($result['associations']['linked'] ?? 0),
      (int)($result['associations']['created'] ?? 0)
    );
    wp_safe_redirect(add_query_arg(['ab_status' => 'ok', 'ab_msg' => $msg], $redirect));
    exit;
  }
}
add_action('admin_init', 'ab_sync_handle_admin_post');

function ab_sync_maybe_schedule(): void {
  if (!wp_next_scheduled(AB_SYNC_HOOK)) {
    wp_schedule_event(time() + 600, 'hourly', AB_SYNC_HOOK);
  }
}
add_action('init', 'ab_sync_maybe_schedule');

function ab_sync_cron_runner(): void {
  $url = (string)get_option(AB_SYNC_URL_OPTION, '');
  if (trim($url) === '') return;
  ab_sync_run_import_from_url();
}
add_action(AB_SYNC_HOOK, 'ab_sync_cron_runner');

