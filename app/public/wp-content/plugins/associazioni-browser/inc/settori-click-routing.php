<?php
if (!defined('ABSPATH')) exit;

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

  $treeNodes = (array)abf_get_authoritative_tree_nodes();

  if (!empty($treeNodes)) {
    foreach ((array)$treeNodes as $macro => $settoriMap) {
      $macroLabel = trim((string)$macro);
      if ($macroLabel === '' || ab_assoc_is_placeholder_label($macroLabel)) continue;

      $assign($macroLabel, $macroLabel, '', '', 10);

      foreach ((array)$settoriMap as $settore => $settore2List) {
        $settoreLabel = trim((string)$settore);
        if ($settoreLabel === '' || ab_assoc_is_placeholder_label($settoreLabel)) continue;

        $assign($settoreLabel, $macroLabel, $settoreLabel, '', 20);

        foreach ((array)$settore2List as $settore2) {
          $settore2Label = trim((string)$settore2);
          if ($settore2Label === '' || ab_assoc_is_placeholder_label($settore2Label)) continue;
          $assign($settore2Label, $macroLabel, $settoreLabel, $settore2Label, 30);
        }
      }
    }

    ab_cache_set($cacheKey, $map, 1 * HOUR_IN_SECONDS);
    return $map;
  }

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

  $settoriPage = get_page_by_path('settori');
  $settoriUrl = ($settoriPage instanceof WP_Post)
    ? get_permalink((int)$settoriPage->ID)
    : home_url('/settori/');

  if (!is_string($settoriUrl) || trim($settoriUrl) === '') {
    $settoriUrl = home_url('/settori/');
  }

  $handle = 'abf-settori-pattern-links';
  $scriptPath = plugin_dir_path(ABF_PLUGIN_FILE) . 'assets/js/settori-pattern-links.js';
  $scriptVersion = file_exists($scriptPath) ? (string)filemtime($scriptPath) : '1.0.1';
  wp_register_script(
    $handle,
    plugins_url('assets/js/settori-pattern-links.js', ABF_PLUGIN_FILE),
    [],
    $scriptVersion,
    true
  );
  wp_enqueue_script($handle);

  $translate = static function(string $label): string {
    if (function_exists('culturacsi_translate_visual_label')) {
      return (string)culturacsi_translate_visual_label($label);
    }
    return $label;
  };
  $lookup = abf_settori_click_lookup_map();
  $captionMap = [];
  $labelTranslations = [];
  foreach ((array)$lookup as $key => $entry) {
    if (!is_array($entry)) continue;
    $label = trim((string)($entry['settore2'] ?? ''));
    if ($label === '') $label = trim((string)($entry['settore'] ?? ''));
    if ($label === '') $label = trim((string)($entry['macro'] ?? ''));
    if ($label === '') continue;
    $translated = $translate($label);
    if ($translated === '') continue;
    $captionMap[(string)$key] = $translated;
  }
  if (function_exists('culturacsi_runtime_visual_label_phrases')) {
    $lang = function_exists('culturacsi_get_current_language') ? (string)culturacsi_get_current_language() : 'it';
    foreach ((array)culturacsi_runtime_visual_label_phrases() as $source => $variants) {
      if (!is_array($variants)) continue;
      $src = trim((string)$source);
      if ($src === '') continue;
      $target = trim((string)($variants[$lang] ?? $variants['it'] ?? $src));
      if ($target === '') continue;
      $labelTranslations[$src] = $target;
    }
  }

  $config = [
    'baseUrl' => (string)$settoriUrl,
    'heroParam' => 'abf_hero',
    'queryKeys' => [
      'macro' => ab_qkey('settori', 'macro'),
      'settore' => ab_qkey('settori', 'settore'),
      'settore2' => ab_qkey('settori', 'settore2'),
    ],
    'heroUrlMap' => abf_get_hero_image_map(),
    'lookup' => $lookup,
    'captionMap' => $captionMap,
    'labelTranslations' => $labelTranslations,
    // Render-level UI translations for dynamic Settori JS components.
    'i18n' => [
      'Chiudi' => $translate('Chiudi'),
      'Dettagli associazione' => $translate('Dettagli associazione'),
      'Mappa' => $translate('Mappa'),
      'Associazione' => $translate('Associazione'),
      'Attivita' => $translate('Attività'),
      'Macro > Settore > Settore 2' => $translate('Macro > Settore > Settore 2'),
      'Regione' => $translate('Regione'),
      'Provincia' => $translate('Provincia'),
      'Comune / Citta' => $translate('Comune / Citta'),
      'Indirizzo' => $translate('Indirizzo'),
      'Telefono' => $translate('Telefono'),
      'Email' => $translate('Email'),
      'Sito web' => $translate('Sito web'),
      'Facebook' => $translate('Facebook'),
      'Instagram' => $translate('Instagram'),
      'Note' => $translate('Note'),
      'Localita (sorgente)' => $translate('Localita (sorgente)'),
      'Eventi' => $translate('Eventi'),
      'Notizie' => $translate('Notizie'),
      'Mappa associazione' => $translate('Mappa associazione'),
      'Mappa non disponibile per questa associazione.' => $translate('Mappa non disponibile per questa associazione.'),
    ],
  ];

  wp_add_inline_script(
    $handle,
    'window.AB_SETTORI_PATTERN_LINKS = ' . wp_json_encode($config) . ';',
    'before'
  );
}
add_action('wp_enqueue_scripts', 'abf_enqueue_settori_pattern_links_script', 30);
