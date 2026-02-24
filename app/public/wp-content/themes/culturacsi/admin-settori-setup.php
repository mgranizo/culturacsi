<?php
/**
 * Admin Page for Settori Setup
 * 
 * This creates an admin page to run the Settori menu and pages setup.
 * 
 * @package CulturaCSI
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add admin menu page
 */
function culturacsi_add_settori_setup_page() {
    // Add to Appearance menu
    add_theme_page(
        __( 'Settori Setup', 'culturacsi' ),
        __( 'Settori Setup', 'culturacsi' ),
        'manage_options',
        'settori-setup',
        'culturacsi_settori_setup_page'
    );
    
    // Also add to Tools menu as backup
    add_management_page(
        __( 'Settori Setup', 'culturacsi' ),
        __( 'Settori Setup', 'culturacsi' ),
        'manage_options',
        'settori-setup-tools',
        'culturacsi_settori_setup_page'
    );
}
add_action( 'admin_menu', 'culturacsi_add_settori_setup_page', 20 );

/**
 * Render the setup page
 */
function culturacsi_settori_setup_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
    }
    
    $message = '';
    $message_type = 'info';
    
    // Handle form submission
    if ( isset( $_POST['culturacsi_setup_settori'] ) && check_admin_referer( 'culturacsi_setup_settori' ) ) {
        $results = culturacsi_setup_settori_menu();
        
        if ( is_wp_error( $results ) ) {
            $message = $results->get_error_message();
            $message_type = 'error';
        } else {
            $message = sprintf(
                __( 'Setup completed successfully! Created %d new pages, reused %d existing pages, and added %d menu items.', 'culturacsi' ),
                $results['pages_created'],
                $results['pages_reused'],
                $results['menu_items_added']
            );
            $message_type = 'success';
        }
    }
    
    ?>
    <!-- Stitch Admin Dashboard Design -->
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;900&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script id="tailwind-config">
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#0a3a75",
                        "background-light": "#f1f1f1",
                        "background-dark": "#101822",
                    },
                    fontFamily: {
                        "display": ["Inter", "sans-serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.25rem",
                        "lg": "0.5rem",
                        "xl": "0.75rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
    <style>
        .culturacsi-admin-wrap {
            font-family: 'Inter', sans-serif;
            margin-top: 20px;
        }
        .culturacsi-admin-wrap .material-symbols-outlined {
            font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24;
        }
    </style>

    <div class="wrap culturacsi-admin-wrap">
        <?php if ( $message ) : ?>
            <div class="notice notice-<?php echo esc_attr( $message_type ); ?> is-dismissible" style="margin-bottom: 20px;">
                <p><?php echo esc_html( $message ); ?></p>
            </div>
        <?php endif; ?>
        
        <div class="max-w-5xl">
            <!-- Main Configuration Card -->
            <div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
                <div class="p-10">
                    <!-- Card Header -->
                    <div class="flex flex-col md:flex-row md:items-start justify-between gap-6 mb-8">
                        <div class="max-w-2xl">
                            <h2 class="text-3xl font-black text-slate-900 tracking-tight mb-3"><?php echo esc_html( get_admin_page_title() ); ?></h2>
                            <p class="text-slate-500 text-lg leading-relaxed">
                                <?php _e( 'This will create all Settori pages and build the mega-menu structure under the existing "Settori" menu item.', 'culturacsi' ); ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Alert Box -->
                    <div class="bg-primary/5 border border-primary/20 rounded-xl p-5 mb-10 flex items-start gap-4">
                        <div class="text-primary mt-0.5">
                            <span class="material-symbols-outlined text-2xl">info</span>
                        </div>
                        <div>
                            <h4 class="text-primary font-bold text-base mb-1"><?php _e( 'What will be created:', 'culturacsi' ); ?></h4>
                            <ul class="text-primary/80 text-sm leading-relaxed list-disc ml-5 mt-2">
                                <li><?php _e( 'All Settori category pages (Arte, Ambiente, ecc)', 'culturacsi' ); ?></li>
                                <li><?php _e( 'All subcategory and leaf pages with proper hierarchy', 'culturacsi' ); ?></li>
                                <li><?php _e( 'Menu structure nested under "Settori" in the primary navigation', 'culturacsi' ); ?></li>
                                <li><?php _e( 'Page content with H1, intro paragraph, and child links', 'culturacsi' ); ?></li>
                            </ul>
                            <p class="text-primary/80 text-sm leading-relaxed mt-2 font-semibold">
                                <?php _e( 'Note:', 'culturacsi' ); ?> <?php _e( 'If pages with the same titles already exist, they will be reused.', 'culturacsi' ); ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Status Section -->
                    <div class="mb-10">
                        <h3 class="text-lg font-bold text-slate-900 mb-6 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary">analytics</span>
                            <?php _e( 'Current Menu Status', 'culturacsi' ); ?>
                        </h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php
                            $menu_locations = get_nav_menu_locations();
                            $has_menu = false;
                            $has_settori = false;
                            $settori_items_count = 0;
                            
                            if ( isset( $menu_locations['primary'] ) ) {
                                $menu = wp_get_nav_menu_object( $menu_locations['primary'] );
                                if ( $menu ) {
                                    $has_menu = true;
                                    $menu_items = wp_get_nav_menu_items( $menu->term_id );
                                    if( $menu_items ) {
                                        foreach ( $menu_items as $item ) {
                                            if ( stripos( $item->title, 'settori' ) !== false ) {
                                                $settori_items_count++;
                                            }
                                        }
                                    }
                                    if ( $settori_items_count > 0 ) $has_settori = true;
                                }
                            }
                            ?>
                            
                            <!-- Menu Assigned Status -->
                            <div class="flex items-center justify-between p-4 bg-background-light/50 rounded-lg border border-slate-100">
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 rounded-full <?php echo $has_menu ? 'bg-emerald-100 text-emerald-600' : 'bg-red-100 text-red-600'; ?> flex items-center justify-center">
                                        <span class="material-symbols-outlined text-xl font-bold"><?php echo $has_menu ? 'check_circle' : 'cancel'; ?></span>
                                    </div>
                                    <span class="text-sm font-medium text-slate-700"><?php _e( 'Primary Menu Assigned', 'culturacsi' ); ?></span>
                                </div>
                                <span class="text-xs font-bold <?php echo $has_menu ? 'text-emerald-600 bg-emerald-50' : 'text-red-600 bg-red-50'; ?> px-2.5 py-1 rounded-full uppercase tracking-wider"><?php echo $has_menu ? 'Yes' : 'No'; ?></span>
                            </div>
                            
                            <!-- Settori Items Status -->
                            <div class="flex items-center justify-between p-4 bg-background-light/50 rounded-lg border border-slate-100">
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 rounded-full <?php echo $has_settori ? 'bg-emerald-100 text-emerald-600' : 'bg-slate-100 text-slate-400'; ?> flex items-center justify-center">
                                        <span class="material-symbols-outlined text-xl font-bold"><?php echo $has_settori ? 'check_circle' : 'hourglass_empty'; ?></span>
                                    </div>
                                    <span class="text-sm font-medium <?php echo $has_settori ? 'text-slate-700' : 'text-slate-400'; ?>"><?php _e( 'Settori Items Found', 'culturacsi' ); ?></span>
                                </div>
                                <span class="text-xs font-bold <?php echo $has_settori ? 'text-emerald-600 bg-emerald-50' : 'text-slate-400 bg-slate-50'; ?> px-2.5 py-1 rounded-full uppercase tracking-wider"><?php echo $has_settori ? $settori_items_count : '0'; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Final Action Area -->
                    <div class="flex items-center justify-between pt-8 border-t border-slate-100">
                        <div class="text-sm text-slate-500">
                            <p><?php _e( 'Ready to configure the Settori pages and menu structure.', 'culturacsi' ); ?></p>
                        </div>
                        <form method="post" action="" class="m-0">
                            <?php wp_nonce_field( 'culturacsi_setup_settori' ); ?>
                            <button type="submit" name="culturacsi_setup_settori" class="bg-primary hover:bg-primary/90 text-white font-bold px-8 py-3 rounded-lg shadow-lg shadow-primary/20 flex items-center gap-2 transition-all transform active:scale-[0.98]">
                                <span class="material-symbols-outlined">rocket_launch</span>
                                <?php esc_attr_e( 'Run Setup', 'culturacsi' ); ?>
                            </button>
                        </form>
                    </div>
                    
                </div>
                
                <!-- Footer Badge -->
                <div class="bg-background-light px-10 py-4 flex items-center justify-between">
                    <span class="text-xs font-medium text-slate-500 uppercase tracking-widest">Powered by CulturaCSI</span>
                </div>
            </div>
        </div>
    </div>
    <?php
}
