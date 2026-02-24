<?php
/**
 * Plugin Name: ACSI Settori Menu & Pages Builder
 * Description: Creates Settori pages and builds a nested navigation structure under the existing "Settori" menu item (classic menus and block Navigation).
 * Version: 0.3.0
 * Author: ACSI
 */

if (!defined('ABSPATH')) { exit; }

class ACSI_Settori_Builder {

    const OPTION_LAST_RUN = 'acsi_settori_builder_last_run';
    const OPTION_LAST_HASH = 'acsi_settori_builder_last_hash';

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'register_tools_page'));
        add_action('admin_post_acsi_settori_run', array(__CLASS__, 'handle_run'));
        register_activation_hook(__FILE__, array(__CLASS__, 'activation_run'));
    }

    public static function register_tools_page() {
        add_management_page(
            'ACSI Settori Builder',
            'ACSI Settori Builder',
            'manage_options',
            'acsi-settori-builder',
            array(__CLASS__, 'render_tools_page')
        );
    }

    public static function render_tools_page() {
        if (!current_user_can('manage_options')) { return; }

        $last_run = get_option(self::OPTION_LAST_RUN);
        $nonce = wp_create_nonce('acsi_settori_run');

        echo '<div class="wrap">';
        echo '<h1>ACSI Settori Builder</h1>';
        if ($last_run) {
            echo '<p><strong>Last run:</strong> ' . esc_html($last_run) . '</p>';
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="acsi_settori_run" />';
        echo '<input type="hidden" name="_wpnonce" value="' . esc_attr($nonce) . '" />';
        echo '<label><input type="checkbox" name="force" value="1" /> Force rebuild (recreate menu items and regenerate placeholder content)</label>';
        echo '<p><button class="button button-primary" type="submit">Run builder now</button></p>';
        echo '</form>';
        echo '</div>';
    }

    public static function handle_run() {
        if (!current_user_can('manage_options')) { wp_die('Insufficient permissions.'); }
        check_admin_referer('acsi_settori_run');

        $force = isset($_POST['force']) && $_POST['force'] === '1';
        $result = self::run($force);

        // Store last run timestamp
        update_option(self::OPTION_LAST_RUN, gmdate('c'));

        // Redirect back with a notice
        $url = add_query_arg(array(
            'page' => 'acsi-settori-builder',
            'acsi_settori_done' => 1,
            'created' => (int) $result['pages_created'],
            'reused' => (int) $result['pages_reused'],
            'menu' => (int) $result['menu_items_created'],
            'nav' => (int) $result['nav_updated'],
        ), admin_url('tools.php'));

        wp_safe_redirect($url);
        exit;
    }

    public static function activation_run() {
        // Do nothing heavy on activation; many environments block remote calls.
        // If you want auto-run on activation, uncomment:
        // self::run(false);
    }

    private static function structure() {
        return array(
            array('title' => 'Arte', 'children' => array(
                array('title' => 'Arti Visive', 'children' => array(
                    array('title' => 'Fotografia e Pittura'),
                    array('title' => 'Scultura'),
                    array('title' => 'Arte Digitale'),
                )),
                array('title' => 'Arti Performative / Danza e Movimento', 'children' => array(
                    array('title' => 'Danza'),
                    array('title' => 'Danza Aerea'),
                    array('title' => 'Break Dance'),
                    array('title' => 'Ginnastica Ritmica'),
                    array('title' => 'Pattinaggio Artistico'),
                    array('title' => 'Tango'),
                )),
                array('title' => 'Musica e Canto', 'children' => array(
                    array('title' => 'Musica'),
                    array('title' => 'Canto'),
                )),
                array('title' => 'Teatro e Spettacolo'),
                array('title' => 'Letteratura, Poesia ed Editoria', 'children' => array(
                    array('title' => 'Poesia'),
                    array('title' => 'Editoria'),
                )),
                array('title' => 'Attività Culturali e Locali / Ricreative', 'children' => array(
                    array('title' => 'Attività Culturali e Ricreative'),
                    array('title' => 'Pro Loco'),
                )),
                array('title' => 'Attività Educative e Ricreative per Persone con Disabilità', 'children' => array(
                    array('title' => 'Danza con disabili'),
                    array('title' => 'Attività subacquee inclusive'),
                    array('title' => 'Altre attività artistiche o culturali adattate'),
                )),
                array('title' => 'Attività di Supporto e Volontariato', 'children' => array(
                    array('title' => 'Volontariato, Beneficenza & Protezione Civile'),
                )),
                array('title' => 'Attività Terapeutiche e di Benessere', 'children' => array(
                    array('title' => 'Equitazione e Benessere'),
                )),
            )),
            array('title' => 'Ambiente', 'children' => array(
                array('title' => 'Ambiente acquatico', 'children' => array(
                    array('title' => 'Attività subacquee'),
                    array('title' => 'Attività velistiche e Surfing'),
                    array('title' => 'Surfing & Kayak'),
                )),
                array('title' => 'Ambiente terrestre', 'children' => array(
                    array('title' => 'Cicloturismo'),
                    array('title' => 'Escursionismo e Trekking'),
                    array('title' => 'Nord Walking'),
                    array('title' => 'Sci & Alpinismo'),
                )),
                array('title' => 'Attività aeree', 'children' => array(
                    array('title' => 'Parapendio e Paracadutismo'),
                    array('title' => 'Volo'),
                )),
            )),
            array('title' => 'Valorizzazione del Territorio', 'children' => array(
                array('title' => 'Tradizioni Popolari e Identità Locale', 'children' => array(
                    array('title' => 'Attività folkloristiche'),
                    array('title' => 'Rievocazioni Storiche'),
                )),
                array('title' => 'Arti Marziali Storiche e Tradizionali', 'children' => array(
                    array('title' => 'Scherma Antica'),
                )),
                array('title' => 'Giochi di Tradizione e Cultura Strategica', 'children' => array(
                    array('title' => 'Bridge'),
                    array('title' => 'Backgammon'),
                    array('title' => 'Burraco'),
                    array('title' => 'Dama e Scacchi'),
                )),
                array('title' => 'Giochi Storici e Identitari Moderni', 'children' => array(
                    array('title' => 'Subbuteo'),
                )),
            )),
            array('title' => 'Culture di nicchia', 'children' => array(
                array('title' => 'Cultura Motoristica Storica', 'children' => array(
                    array('title' => 'Auto Storiche'),
                    array('title' => "Moto d'Epoca"),
                )),
                array('title' => 'Collezionismo e Cultura del Dettaglio', 'children' => array(
                    array('title' => 'Collezionismo'),
                    array('title' => 'Modellismo (statico e dinamico)'),
                )),
                array('title' => 'Cultura Enogastronomica Identitaria', 'children' => array(
                    array('title' => 'Enogastronomia'),
                )),
            )),
        );
    }

    public static function run($force = false) {
        $structure = self::structure();
        $hash = md5(wp_json_encode($structure));

        if (!$force) {
            $last_hash = get_option(self::OPTION_LAST_HASH);
            if ($last_hash && $last_hash === $hash) {
                // Still update menus in case theme changed
            }
        }
        update_option(self::OPTION_LAST_HASH, $hash);

        $stats = array(
            'pages_created' => 0,
            'pages_reused' => 0,
            'menu_items_created' => 0,
            'nav_updated' => 0,
        );

        // Root "Settori" page: reuse if exists by path, otherwise create.
        $settori_id = self::get_or_create_page('Settori', 0, 'settori', $force, $stats);

        // Build page tree under Settori
        foreach ($structure as $node) {
            self::create_pages_recursive($node, $settori_id, $force, $stats);
        }

        // Update classic menu if possible
        $stats['menu_items_created'] += self::update_classic_menu($settori_id, $structure, $force);

        // Update block navigation if applicable
        $stats['nav_updated'] += self::update_block_navigation($settori_id, $structure, $force);

        return $stats;
    }

    private static function sanitize_slug($title) {
        $slug = sanitize_title($title);
        // Ensure non-empty
        if (!$slug) { $slug = 'item-' . wp_generate_password(6, false, false); }
        return $slug;
    }

    private static function get_or_create_page($title, $parent_id, $slug, $force, &$stats) {
        $slug = $slug ? $slug : self::sanitize_slug($title);

        // Try exact path lookup (fast & reliable for hierarchical pages)
        $path = $slug;
        if ($parent_id) {
            $parent = get_post($parent_id);
            if ($parent && $parent->post_name) {
                $path = $parent->post_name . '/' . $slug;
                // For deeper levels, compute full path:
                $anc = get_post_ancestors($parent_id);
                if (!empty($anc)) {
                    $anc = array_reverse($anc);
                    $parts = array();
                    foreach ($anc as $aid) {
                        $ap = get_post($aid);
                        if ($ap && $ap->post_name) { $parts[] = $ap->post_name; }
                    }
                    $parts[] = $parent->post_name;
                    $parts[] = $slug;
                    $path = implode('/', $parts);
                }
            }
        }

        $existing = get_page_by_path($path, OBJECT, 'page');
        if ($existing && isset($existing->ID)) {
            $stats['pages_reused']++;
            $post_id = (int) $existing->ID;
            if ($force) {
                self::ensure_placeholder_content($post_id, $title, $force);
            }
            // Ensure correct parent if mismatched
            if ((int) $existing->post_parent !== (int) $parent_id) {
                wp_update_post(array('ID' => $post_id, 'post_parent' => (int) $parent_id));
            }
            return $post_id;
        }

        // Fallback: search by title under same parent
        $maybe = get_pages(array(
            'title' => $title,
            'post_status' => array('publish', 'draft', 'private'),
            'parent' => $parent_id,
            'number' => 1,
        ));
        if (!empty($maybe) && isset($maybe[0]->ID)) {
            $stats['pages_reused']++;
            $post_id = (int) $maybe[0]->ID;
            if ($force) {
                self::ensure_placeholder_content($post_id, $title, $force);
            }
            return $post_id;
        }

        $post_id = wp_insert_post(array(
            'post_type' => 'page',
            'post_status' => 'publish',
            'post_title' => $title,
            'post_parent' => (int) $parent_id,
            'post_name' => $slug,
        ), true);

        if (is_wp_error($post_id)) {
            return 0;
        }

        $stats['pages_created']++;
        self::ensure_placeholder_content((int) $post_id, $title, true);
        return (int) $post_id;
    }

    private static function ensure_placeholder_content($post_id, $title, $overwrite) {
        $post = get_post($post_id);
        if (!$post) { return; }

        if (!$overwrite && !empty($post->post_content)) {
            return;
        }

        $children = get_pages(array(
            'post_type' => 'page',
            'post_status' => array('publish', 'draft', 'private'),
            'parent' => $post_id,
            'sort_column' => 'menu_order,post_title',
            'sort_order' => 'ASC',
        ));

        $intro = 'Pagina di settore: ' . esc_html($title) . '.';
        $html = '<p>' . $intro . '</p>';

        if (!empty($children)) {
            $html .= '<h2>Sezioni</h2><ul>';
            foreach ($children as $ch) {
                $html .= '<li><a href="' . esc_url(get_permalink($ch->ID)) . '">' . esc_html(get_the_title($ch->ID)) . '</a></li>';
            }
            $html .= '</ul>';
        }

        wp_update_post(array(
            'ID' => $post_id,
            'post_content' => $html,
        ));
    }

    private static function create_pages_recursive($node, $parent_id, $force, &$stats) {
        $title = $node['title'];
        $slug = isset($node['slug']) ? $node['slug'] : self::sanitize_slug($title);

        $page_id = self::get_or_create_page($title, $parent_id, $slug, $force, $stats);
        if (!$page_id) { return; }

        if (isset($node['children']) && is_array($node['children'])) {
            foreach ($node['children'] as $child) {
                self::create_pages_recursive($child, $page_id, $force, $stats);
            }
            // After children exist, refresh placeholder so it lists them
            self::ensure_placeholder_content($page_id, $title, $force);
        }
    }

    private static function pick_menu_id() {
        $locations = get_nav_menu_locations();
        $preferred_keys = array('primary', 'menu-1', 'main', 'main-menu', 'header', 'top', 'top-menu');

        foreach ($preferred_keys as $key) {
            if (isset($locations[$key]) && $locations[$key]) {
                return (int) $locations[$key];
            }
        }

        // Any assigned location
        if (!empty($locations)) {
            foreach ($locations as $mid) {
                if ($mid) { return (int) $mid; }
            }
        }

        // First existing menu
        $menus = wp_get_nav_menus();
        if (!empty($menus) && isset($menus[0]->term_id)) {
            return (int) $menus[0]->term_id;
        }

        // Create one
        $menu_id = wp_create_nav_menu('Main Menu');
        return (int) $menu_id;
    }

    private static function find_menu_item_by_title($menu_items, $title, $parent_item_id = 0) {
        foreach ($menu_items as $it) {
            if ((int) $it->menu_item_parent === (int) $parent_item_id && trim($it->title) === $title) {
                return (int) $it->ID;
            }
        }
        return 0;
    }

    private static function find_menu_item_by_object_id($menu_items, $object_id, $parent_item_id = 0) {
        foreach ($menu_items as $it) {
            if ((int) $it->menu_item_parent === (int) $parent_item_id && (int) $it->object_id === (int) $object_id && $it->object === 'page') {
                return (int) $it->ID;
            }
        }
        return 0;
    }

    private static function update_classic_menu($settori_page_id, $structure, $force) {
        if (!function_exists('wp_get_nav_menu_items')) { return 0; }

        $menu_id = self::pick_menu_id();
        if (!$menu_id) { return 0; }

        $menu_items = wp_get_nav_menu_items($menu_id);
        if (!is_array($menu_items)) { $menu_items = array(); }

        // Ensure "Settori" top item exists
        $settori_item_id = self::find_menu_item_by_title($menu_items, 'Settori', 0);
        if (!$settori_item_id) {
            // Prefer linking to Settori page
            $settori_item_id = wp_update_nav_menu_item($menu_id, 0, array(
                'menu-item-title' => 'Settori',
                'menu-item-object' => 'page',
                'menu-item-object-id' => (int) $settori_page_id,
                'menu-item-type' => 'post_type',
                'menu-item-status' => 'publish',
                'menu-item-parent-id' => 0,
            ));
            $menu_items = wp_get_nav_menu_items($menu_id);
            if (!is_array($menu_items)) { $menu_items = array(); }
        }

        $created = 0;
        foreach ($structure as $node) {
            $created += self::add_menu_node_recursive($menu_id, $menu_items, $node, $settori_item_id, $force);
            // refresh snapshot so parent lookups work reliably
            $menu_items = wp_get_nav_menu_items($menu_id);
            if (!is_array($menu_items)) { $menu_items = array(); }
        }

        return (int) $created;
    }

    private static function add_menu_node_recursive($menu_id, $menu_items, $node, $parent_item_id, $force) {
        $title = $node['title'];
        $page_id = self::locate_page_id_by_title_under_settori($title);

        $existing_item_id = 0;
        if ($page_id) {
            $existing_item_id = self::find_menu_item_by_object_id($menu_items, $page_id, $parent_item_id);
        }
        if (!$existing_item_id) {
            $existing_item_id = self::find_menu_item_by_title($menu_items, $title, $parent_item_id);
        }

        $item_id = $existing_item_id;

        if (!$item_id || $force) {
            $args = array(
                'menu-item-title' => $title,
                'menu-item-status' => 'publish',
                'menu-item-parent-id' => (int) $parent_item_id,
            );

            if ($page_id) {
                $args['menu-item-object'] = 'page';
                $args['menu-item-object-id'] = (int) $page_id;
                $args['menu-item-type'] = 'post_type';
            } else {
                $args['menu-item-type'] = 'custom';
                $args['menu-item-url'] = '#';
            }

            $item_id = wp_update_nav_menu_item($menu_id, (int) $existing_item_id, $args);
        }

        $created = ($existing_item_id ? 0 : 1);

        if (isset($node['children']) && is_array($node['children'])) {
            $menu_items = wp_get_nav_menu_items($menu_id);
            if (!is_array($menu_items)) { $menu_items = array(); }
            foreach ($node['children'] as $child) {
                $created += self::add_menu_node_recursive($menu_id, $menu_items, $child, (int) $item_id, $force);
                $menu_items = wp_get_nav_menu_items($menu_id);
                if (!is_array($menu_items)) { $menu_items = array(); }
            }
        }

        return $created;
    }

    private static function locate_page_id_by_title_under_settori($title) {
        $p = get_page_by_title($title, OBJECT, 'page');
        if ($p && isset($p->ID)) {
            return (int) $p->ID;
        }
        return 0;
    }


    private static function update_block_navigation($settori_page_id, $structure, $force) {
        if (!function_exists('wp_is_block_theme') || !wp_is_block_theme()) {
            return 0;
        }

        $nav_posts = get_posts(array(
            'post_type' => 'wp_navigation',
            'post_status' => array('publish', 'draft', 'private'),
            'numberposts' => 5,
        ));
        if (empty($nav_posts)) { return 0; }

        // Pick the first navigation post
        $nav = $nav_posts[0];
        $blocks = parse_blocks($nav->post_content);

        // Build the "Settori" link block with nested children
        $settori_url = get_permalink($settori_page_id);
        $settori_block = self::build_navigation_link_block('Settori', $settori_url, $settori_page_id, $structure);

        // Insert or replace
        $updated = false;
        foreach ($blocks as $idx => $blk) {
            if (isset($blk['blockName']) && $blk['blockName'] === 'core/navigation-link') {
                $label = isset($blk['attrs']['label']) ? $blk['attrs']['label'] : '';
                if ($label === 'Settori') {
                    $blocks[$idx] = $settori_block;
                    $updated = true;
                    break;
                }
            }
        }
        if (!$updated) {
            $blocks[] = $settori_block;
        }

        $content = serialize_blocks($blocks);
        wp_update_post(array(
            'ID' => $nav->ID,
            'post_content' => $content,
        ));

        return 1;
    }

    private static function build_navigation_link_block($label, $url, $page_id, $children_structure) {
        $block = array(
            'blockName' => 'core/navigation-link',
            'attrs' => array(
                'label' => $label,
                'url' => $url,
                'kind' => 'post-type',
                'type' => 'page',
                'id' => (int) $page_id,
            ),
            'innerBlocks' => array(),
            'innerHTML' => '',
            'innerContent' => array(),
        );

        if (!empty($children_structure) && is_array($children_structure)) {
            foreach ($children_structure as $node) {
                $child_title = $node['title'];
                $child_id = self::locate_page_id_by_title_under_settori($child_title);
                $child_url = $child_id ? get_permalink($child_id) : '#';
                $grand = isset($node['children']) ? $node['children'] : array();

                $block['innerBlocks'][] = self::build_navigation_link_block($child_title, $child_url, $child_id, $grand);
            }
        }

        return $block;
    }
}

ACSI_Settori_Builder::init();
