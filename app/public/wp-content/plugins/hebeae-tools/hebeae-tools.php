<?php
/**
 * Plugin Name: Hebeae Tools
 * Description: Site-specific tweaks for hebeae.com (External URL for posts + safe hero overlay contrast CSS).
 * Version: 0.2.0
 * Author: Hebeae
 */

if (!defined('ABSPATH')) exit;

class HebeaeTools {
  const META_URL = '_hebeae_external_url';
  const META_ENABLED = '_hebeae_external_enabled';

  public static function init(): void {
    add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_assets']);

    // Editor UI
    add_action('add_meta_boxes', [__CLASS__, 'add_metabox']);
    add_action('save_post', [__CLASS__, 'save_metabox'], 10, 2);

    // Link behavior
    add_filter('post_link', [__CLASS__, 'filter_post_link'], 10, 3);
    add_filter('page_link', [__CLASS__, 'filter_page_link'], 10, 2);
    add_filter('post_type_link', [__CLASS__, 'filter_post_type_link'], 10, 4);
    add_filter('the_permalink', [__CLASS__, 'filter_the_permalink'], 10, 1);

    // Add target/rel for external links when enabled
    add_filter('the_content', [__CLASS__, 'decorate_external_anchors'], 20);

    // Admin list column
    add_filter('manage_posts_columns', [__CLASS__, 'admin_columns']);
    add_action('manage_posts_custom_column', [__CLASS__, 'admin_column_value'], 10, 2);
    add_filter('manage_pages_columns', [__CLASS__, 'admin_columns']);
    add_action('manage_pages_custom_column', [__CLASS__, 'admin_column_value'], 10, 2);
  }

  public static function enqueue_assets(): void {
    // Only frontend
    if (is_admin()) return;

    $css = plugin_dir_url(__FILE__) . 'assets/hebeae-tools.css';
    $ver = '0.2.0';
    wp_enqueue_style('hebeae-tools', $css, [], $ver);
  }

  public static function add_metabox(): void {
    $screens = ['post', 'page'];
    foreach ($screens as $screen) {
      add_meta_box(
        'hebeae_external_url',
        'Hebeae: External URL',
        [__CLASS__, 'render_metabox'],
        $screen,
        'side',
        'high'
      );
    }
  }

  public static function render_metabox(\WP_Post $post): void {
    $enabled = get_post_meta($post->ID, self::META_ENABLED, true) === '1';
    $url = (string) get_post_meta($post->ID, self::META_URL, true);

    wp_nonce_field('hebeae_external_url_save', 'hebeae_external_url_nonce');

    echo '<p style="margin:0 0 8px 0;">If enabled, this post/page will link to the external URL instead of its WordPress permalink (Kadence grids, menus, etc.).</p>';

    echo '<p style="margin:0 0 8px 0;">';
    echo '<label><input type="checkbox" name="hebeae_external_enabled" value="1" ' . checked($enabled, true, false) . '> Enable external link</label>';
    echo '</p>';

    echo '<p style="margin:0;">';
    echo '<label for="hebeae_external_url" style="display:block;font-weight:600;margin-bottom:4px;">External URL</label>';
    echo '<input type="url" id="hebeae_external_url" name="hebeae_external_url" value="' . esc_attr($url) . '" placeholder="https://..." style="width:100%;">';
    echo '</p>';

    echo '<p style="margin:8px 0 0 0;color:#555;">Tip: Use full URL including https://</p>';
  }

  public static function save_metabox(int $post_id, \WP_Post $post): void {
    // Autosave / revisions
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (wp_is_post_revision($post_id)) return;

    // Permissions
    $ptype = $post->post_type;
    if ($ptype === 'page' && !current_user_can('edit_page', $post_id)) return;
    if ($ptype !== 'page' && !current_user_can('edit_post', $post_id)) return;

    // Nonce
    if (!isset($_POST['hebeae_external_url_nonce']) || !wp_verify_nonce($_POST['hebeae_external_url_nonce'], 'hebeae_external_url_save')) {
      return;
    }

    $enabled = isset($_POST['hebeae_external_enabled']) ? '1' : '0';
    $url = isset($_POST['hebeae_external_url']) ? trim((string) $_POST['hebeae_external_url']) : '';

    // Basic validation/sanitization
    if ($url !== '') {
      $url = esc_url_raw($url);
      // If user pasted without scheme, try to fix
      if ($url && !preg_match('#^https?://#i', $url)) {
        $url = 'https://' . ltrim($url, '/');
        $url = esc_url_raw($url);
      }
    }

    update_post_meta($post_id, self::META_ENABLED, $enabled);

    if ($url !== '') {
      update_post_meta($post_id, self::META_URL, $url);
    } else {
      delete_post_meta($post_id, self::META_URL);
      // If URL empty, disable to avoid broken links
      if ($enabled === '1') update_post_meta($post_id, self::META_ENABLED, '0');
    }
  }

  private static function get_external_url_if_enabled(int $post_id): ?string {
    $enabled = get_post_meta($post_id, self::META_ENABLED, true) === '1';
    if (!$enabled) return null;

    $url = (string) get_post_meta($post_id, self::META_URL, true);
    $url = trim($url);
    if ($url === '') return null;

    return $url;
  }

  public static function filter_post_link(string $permalink, \WP_Post $post, bool $leavename): string {
    $url = self::get_external_url_if_enabled((int)$post->ID);
    return $url ?: $permalink;
  }

  public static function filter_page_link(string $link, int $post_id): string {
    $url = self::get_external_url_if_enabled($post_id);
    return $url ?: $link;
  }

  public static function filter_post_type_link(string $post_link, \WP_Post $post, bool $leavename, bool $sample): string {
    $url = self::get_external_url_if_enabled((int)$post->ID);
    return $url ?: $post_link;
  }

  public static function filter_the_permalink(string $permalink): string {
    $post = get_post();
    if (!$post) return $permalink;

    $url = self::get_external_url_if_enabled((int)$post->ID);
    return $url ?: $permalink;
  }

  /**
   * Add target=_blank and rel=noopener for anchors that point to external_url-enabled posts.
   * This makes Kadence grids behave correctly without needing block-specific hacks.
   */
  public static function decorate_external_anchors(string $content): string {
    if (is_admin() || trim($content) === '') return $content;

    // Only run if the current post is using external URL (common for "News" grids),
    // OR if the page contains a kadence post grid/carousel markup (cheap heuristic).
    $maybe_kadence = (strpos($content, 'kadence-post-grid') !== false) || (strpos($content, 'kb-post-grid') !== false);
    if (!$maybe_kadence) return $content;

    // DOMDocument is safer than regex for attributes.
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $wrapped = '<!doctype html><html><body>' . $content . '</body></html>';
    if (!$dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD)) {
      libxml_clear_errors();
      return $content;
    }

    $anchors = $dom->getElementsByTagName('a');
    // Iterate backwards because DOMNodeList is live
    for ($i = $anchors->length - 1; $i >= 0; $i--) {
      $a = $anchors->item($i);
      if (!$a) continue;

      $href = $a->getAttribute('href');
      if (!$href) continue;

      // If it's already external, leave it (but ensure rel)
      $is_external = self::is_external_url($href);

      if ($is_external) {
        $a->setAttribute('target', '_blank');
        $existing_rel = (string)$a->getAttribute('rel');
        $a->setAttribute('rel', self::merge_rel($existing_rel, 'noopener noreferrer'));
        continue;
      }

      // If it's internal, we still may want target blank IF it actually maps to an external-url post.
      // Hard mapping without parsing IDs is unreliable; we skip to avoid breaking internal navigation.
    }

    $out = $dom->saveHTML($dom->documentElement);
    // Extract only body inner HTML
    $body = $dom->getElementsByTagName('body')->item(0);
    if (!$body) return $content;

    $html = '';
    foreach ($body->childNodes as $child) $html .= $dom->saveHTML($child);

    libxml_clear_errors();
    return $html ?: $content;
  }

  private static function is_external_url(string $href): bool {
    $href = trim($href);
    if ($href === '') return false;
    // protocol-relative or absolute http(s)
    if (preg_match('#^(https?:)?//#i', $href)) {
      $host = wp_parse_url($href, PHP_URL_HOST);
      if (!$host) return false;
      $site_host = wp_parse_url(home_url(), PHP_URL_HOST);
      return $site_host && strcasecmp($host, $site_host) !== 0;
    }
    return false;
  }

  private static function merge_rel(string $existing, string $add): string {
    $parts = preg_split('/\s+/', trim($existing . ' ' . $add));
    $parts = array_filter(array_unique(array_map('strtolower', $parts)));
    return implode(' ', $parts);
  }

  public static function admin_columns(array $columns): array {
    $columns['hebeae_ext'] = 'External URL';
    return $columns;
  }

  public static function admin_column_value(string $column, int $post_id): void {
    if ($column !== 'hebeae_ext') return;

    $enabled = get_post_meta($post_id, self::META_ENABLED, true) === '1';
    $url = (string) get_post_meta($post_id, self::META_URL, true);
    $url = trim($url);

    if (!$enabled || $url === '') {
      echo '<span style="color:#999;">—</span>';
      return;
    }

    $short = esc_html(mb_strimwidth($url, 0, 42, '…'));
    echo '<span style="color:#2271b1;font-weight:600;">ON</span><br>';
    echo '<a href="' . esc_url($url) . '" target="_blank" rel="noopener noreferrer">' . $short . '</a>';
  }
}

HebeaeTools::init();
