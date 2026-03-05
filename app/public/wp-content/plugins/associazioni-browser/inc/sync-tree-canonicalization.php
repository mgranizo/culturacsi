<?php
if (!defined('ABSPATH')) exit;

function ab_sync_tree_match_keys_for_level(string $level, string $value): array {
  $value = trim($value);
  if ($value === '') return [];

  $rawCandidates = [];
  $rawCandidates[] = $value;
  if ($level === 'settore2') {
    $rawCandidates[] = str_replace('/', ' e ', $value);
    $rawCandidates[] = str_replace('&', ' e ', $value);
    $rawCandidates[] = preg_replace('/\be\b/iu', '/', $value);
  }

  $keys = [];
  foreach ($rawCandidates as $candidateRaw) {
    $candidate = trim((string)$candidateRaw);
    if ($candidate === '') continue;
    $key = ab_assoc_normalize_key($candidate);
    if ($key === '') continue;
    $keys[$key] = true;
  }

  return array_keys($keys);
}

function ab_sync_tree_match_keys(string $value): array {
  return ab_sync_tree_match_keys_for_level('settore2', $value);
}

function ab_sync_tree_level_label_maps(): array {
  static $cache = null;
  if (is_array($cache)) return $cache;

  $cache = [
    'macro' => [],
    'settore' => [],
    'settore2' => [],
  ];

  $nodes = (array)abf_get_authoritative_tree_nodes();

  $register = static function(string $level, string $label) use (&$cache): void {
    $label = trim($label);
    if ($label === '') return;
    foreach (ab_sync_tree_match_keys_for_level($level, $label) as $key) {
      if (!isset($cache[$level][$key])) {
        $cache[$level][$key] = $label;
      }
    }
  };

  foreach ((array)$nodes as $macro => $settoriMap) {
    $macroLabel = trim((string)$macro);
    if ($macroLabel === '') continue;
    $register('macro', $macroLabel);

    foreach ((array)$settoriMap as $settore => $settore2List) {
      $settoreLabel = trim((string)$settore);
      if ($settoreLabel === '') continue;
      $register('settore', $settoreLabel);

      foreach ((array)$settore2List as $settore2) {
        $settore2Label = trim((string)$settore2);
        if ($settore2Label === '') continue;
        $register('settore2', $settore2Label);
      }
    }
  }

  return $cache;
}

// Backward-compatible helper kept for call sites expecting bool key maps.
function ab_sync_tree_level_label_keys(): array {
  $maps = ab_sync_tree_level_label_maps();
  $keys = ['macro' => [], 'settore' => [], 'settore2' => []];
  foreach (['macro', 'settore', 'settore2'] as $level) {
    foreach ((array)($maps[$level] ?? []) as $k => $label) {
      $keys[$level][(string)$k] = true;
    }
  }
  return $keys;
}

function ab_sync_tree_level_resolve_maps(): array {
  static $cache = null;
  if (is_array($cache)) return $cache;

  $cache = [
    'settore_to_macros' => [],
    'settore2_to_paths' => [],
    'macro_settore2_to_settori' => [],
  ];

  $nodes = (array)abf_get_authoritative_tree_nodes();

  foreach ((array)$nodes as $macro => $settoriMap) {
    $macroLabel = trim((string)$macro);
    if ($macroLabel === '') continue;
    $macroKey = ab_assoc_normalize_key($macroLabel);
    if ($macroKey === '') continue;

    foreach ((array)$settoriMap as $settore => $settore2List) {
      $settoreLabel = trim((string)$settore);
      if ($settoreLabel === '') continue;
      $settoreKey = ab_assoc_normalize_key($settoreLabel);
      if ($settoreKey === '') continue;

      if (!isset($cache['settore_to_macros'][$settoreKey])) {
        $cache['settore_to_macros'][$settoreKey] = [];
      }
      $cache['settore_to_macros'][$settoreKey][$macroLabel] = true;

      foreach ((array)$settore2List as $settore2) {
        $settore2Label = trim((string)$settore2);
        if ($settore2Label === '') continue;
        $settore2Key = ab_assoc_normalize_key($settore2Label);
        if ($settore2Key === '') continue;

        if (!isset($cache['settore2_to_paths'][$settore2Key])) {
          $cache['settore2_to_paths'][$settore2Key] = [];
        }
        $cache['settore2_to_paths'][$settore2Key][] = [
          'macro' => $macroLabel,
          'macro_key' => $macroKey,
          'settore' => $settoreLabel,
          'settore_key' => $settoreKey,
          'settore2' => $settore2Label,
          'settore2_key' => $settore2Key,
        ];

        if (!isset($cache['macro_settore2_to_settori'][$macroKey])) {
          $cache['macro_settore2_to_settori'][$macroKey] = [];
        }
        if (!isset($cache['macro_settore2_to_settori'][$macroKey][$settore2Key])) {
          $cache['macro_settore2_to_settori'][$macroKey][$settore2Key] = [];
        }
        $cache['macro_settore2_to_settori'][$macroKey][$settore2Key][$settoreLabel] = true;
      }
    }
  }

  return $cache;
}

function ab_sync_resolve_levels_from_tree(string $macro, string $settore, string $settore2): array {
  $macro = trim($macro);
  $settore = trim($settore);
  $settore2 = trim($settore2);

  $macroKey = ab_assoc_normalize_key($macro);
  $settoreKey = ab_assoc_normalize_key($settore);
  $settore2Key = ab_assoc_normalize_key($settore2);
  $maps = ab_sync_tree_level_resolve_maps();

  // Infer macro from settore (or settore+settore2) when Macro Categoria is missing.
  if ($macro === '' && $settoreKey !== '') {
    $macroCandidates = isset($maps['settore_to_macros'][$settoreKey]) ? (array)$maps['settore_to_macros'][$settoreKey] : [];
    if (count($macroCandidates) === 1) {
      $macro = (string)array_key_first($macroCandidates);
      $macroKey = ab_assoc_normalize_key($macro);
    } elseif ($settore2Key !== '') {
      $matchingMacros = [];
      $paths = isset($maps['settore2_to_paths'][$settore2Key]) ? (array)$maps['settore2_to_paths'][$settore2Key] : [];
      foreach ($paths as $path) {
        if (!is_array($path)) continue;
        if (($path['settore_key'] ?? '') !== $settoreKey) continue;
        $m = trim((string)($path['macro'] ?? ''));
        if ($m === '') continue;
        $matchingMacros[$m] = true;
      }
      if (count($matchingMacros) === 1) {
        $macro = (string)array_key_first($matchingMacros);
        $macroKey = ab_assoc_normalize_key($macro);
      }
    }
  }

  // Infer settore under a known macro from settore2 when Settore is missing.
  if ($macroKey !== '' && $settore === '' && $settore2Key !== '') {
    $settoreCandidates = isset($maps['macro_settore2_to_settori'][$macroKey][$settore2Key])
      ? (array)$maps['macro_settore2_to_settori'][$macroKey][$settore2Key]
      : [];
    if (count($settoreCandidates) === 1) {
      $settore = (string)array_key_first($settoreCandidates);
      $settoreKey = ab_assoc_normalize_key($settore);
    }
  }

  // Infer macro+settore from settore2 only if both are unambiguous.
  if ($macro === '' && $settore === '' && $settore2Key !== '') {
    $paths = isset($maps['settore2_to_paths'][$settore2Key]) ? (array)$maps['settore2_to_paths'][$settore2Key] : [];
    $macros = [];
    foreach ($paths as $path) {
      if (!is_array($path)) continue;
      $m = trim((string)($path['macro'] ?? ''));
      if ($m === '') continue;
      $macros[$m] = true;
    }
    if (count($macros) === 1) {
      $macro = (string)array_key_first($macros);
      $macroKey = ab_assoc_normalize_key($macro);
      $settori = [];
      foreach ($paths as $path) {
        if (!is_array($path)) continue;
        if (trim((string)($path['macro'] ?? '')) !== $macro) continue;
        $s = trim((string)($path['settore'] ?? ''));
        if ($s === '') continue;
        $settori[$s] = true;
      }
      if (count($settori) === 1) {
        $settore = (string)array_key_first($settori);
        $settoreKey = ab_assoc_normalize_key($settore);
      }
    }
  }

  return [$macro, $settore, $settore2];
}

function ab_sync_canonical_tree_label(string $level, string $value): string {
  $value = trim($value);
  if ($value === '') return '';

  $maps = ab_sync_tree_level_label_maps();
  $levelMap = isset($maps[$level]) && is_array($maps[$level]) ? $maps[$level] : [];
  if (empty($levelMap)) return $value;

  foreach (ab_sync_tree_match_keys_for_level($level, $value) as $key) {
    if (isset($levelMap[$key]) && trim((string)$levelMap[$key]) !== '') {
      return trim((string)$levelMap[$key]);
    }
  }
  return $value;
}

function ab_sync_split_settore2_tokens(string $value): array {
  $value = trim($value);
  if ($value === '') return [''];
  if (strpos($value, '/') === false) return [$value];

  $parts = preg_split('~/+~', $value);
  if (!is_array($parts)) return [$value];

  $tokens = [];
  foreach ($parts as $part) {
    $part = trim((string)$part);
    if ($part === '') continue;
    $tokens[$part] = true;
  }
  $list = array_keys($tokens);
  return !empty($list) ? $list : [''];
}

function ab_sync_build_category_variants(string $macro, string $settore, string $settore2): array {
  // Per import rules:
  // - Macro and Settore keep slash as literal text (no splitting)
  // - Settore 2 uses slash as separator for multiple activities
  $macroValue = ab_sync_canonical_tree_label('macro', $macro);
  $settoreValue = ab_sync_canonical_tree_label('settore', $settore);
  $settore2TokensRaw = ab_sync_split_settore2_tokens($settore2);

  $settore2Tokens = [];
  foreach ($settore2TokensRaw as $tokenRaw) {
    $token = ab_sync_canonical_tree_label('settore2', (string)$tokenRaw);
    $token = trim((string)$token);
    if ($token === '') {
      $token = '';
    }
    $settore2Tokens[$token] = true;
  }
  $settore2Tokens = array_keys($settore2Tokens);
  if (empty($settore2Tokens)) $settore2Tokens = [''];

  $variants = [];
  $seen = [];
  foreach ($settore2Tokens as $settore2Token) {
    [$resolvedMacro, $resolvedSettore, $resolvedSettore2] = ab_sync_resolve_levels_from_tree(
      (string)$macroValue,
      (string)$settoreValue,
      (string)$settore2Token
    );
    $path = ab_assoc_category_from_levels((string)$resolvedMacro, (string)$resolvedSettore, (string)$resolvedSettore2);
    if ($path === '' || isset($seen[$path])) continue;
    $seen[$path] = true;
    $variants[] = [
      'macro' => (string)$resolvedMacro,
      'settore' => (string)$resolvedSettore,
      'settore2' => (string)$resolvedSettore2,
      'category' => $path,
    ];
  }

  if (empty($variants)) {
    [$resolvedMacro, $resolvedSettore, $resolvedSettore2] = ab_sync_resolve_levels_from_tree(
      (string)$macroValue,
      (string)$settoreValue,
      ''
    );
    $single = ab_assoc_category_from_levels((string)$resolvedMacro, (string)$resolvedSettore, (string)$resolvedSettore2);
    if ($single !== '') {
      $variants[] = [
        'macro' => (string)$resolvedMacro,
        'settore' => (string)$resolvedSettore,
        'settore2' => (string)$resolvedSettore2,
        'category' => $single,
      ];
    }
  }

  return $variants;
}

function ab_sync_filter_category_paths_to_tree(array $paths, bool $allowParentFallback = true): array {
  $normalized = [];
  foreach ($paths as $pathRaw) {
    $path = trim((string)$pathRaw);
    if ($path === '' || ab_assoc_is_placeholder_label($path)) continue;
    $normalized[$path] = true;
  }
  $normalizedPaths = array_keys($normalized);
  if (empty($normalizedPaths)) return [];

  if (function_exists('culturacsi_activity_tree_filter_paths')) {
    $filtered = culturacsi_activity_tree_filter_paths($normalizedPaths, $allowParentFallback);
    if (is_array($filtered)) {
      $filtered = array_values(array_filter(array_map('trim', $filtered), fn($v) => $v !== ''));
      if (!empty($filtered)) {
        return $filtered;
      }
    }
    return [];
  }

  return $normalizedPaths;
}

function ab_sync_row_category_paths(array $row): array {
  if (isset($row['category_paths']) && is_array($row['category_paths'])) {
    $paths = [];
    foreach ((array)$row['category_paths'] as $path) {
      $path = trim((string)$path);
      if ($path === '' || ab_assoc_is_placeholder_label($path)) continue;
      $paths[$path] = true;
    }
    if (!empty($paths)) {
      return ab_sync_filter_category_paths_to_tree(array_keys($paths), true);
    }
  }

  $all = trim((string)($row['all_categories'] ?? ''));
  if ($all !== '') {
    $parts = preg_split('/\s*\|\s*/', $all);
    if (is_array($parts)) {
      $paths = [];
      foreach ($parts as $part) {
        $part = trim((string)$part);
        if ($part === '' || ab_assoc_is_placeholder_label($part)) continue;
        $paths[$part] = true;
      }
      if (!empty($paths)) {
        return ab_sync_filter_category_paths_to_tree(array_keys($paths), true);
      }
    }
  }

  $single = ab_assoc_resolve_category_from_row($row);
  return $single !== '' ? ab_sync_filter_category_paths_to_tree([$single], true) : [];
}
