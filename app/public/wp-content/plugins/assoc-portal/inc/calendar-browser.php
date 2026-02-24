<?php
if ( ! defined( 'WPINC' ) ) {
    die;
}

function assoc_portal_calendar_browser_meta_first( int $post_id, array $keys ): string {
    foreach ( $keys as $key ) {
        $value = trim( (string) get_post_meta( $post_id, (string) $key, true ) );
        if ( $value !== '' ) {
            return $value;
        }
    }
    return '';
}

function assoc_portal_calendar_browser_get_activity_levels( int $association_id ): array {
    $empty = [ 'macro' => '', 'settore' => '', 'settore2' => '' ];
    if ( $association_id <= 0 || ! taxonomy_exists( 'activity_category' ) ) return $empty;

    $terms = wp_get_post_terms( $association_id, 'activity_category' );
    if ( is_wp_error( $terms ) || empty( $terms ) ) return $empty;

    $best = null;
    $best_depth = -1;
    foreach ( $terms as $term ) {
        if ( ! ( $term instanceof WP_Term ) ) continue;
        $depth = count( get_ancestors( $term->term_id, 'activity_category' ) );
        if ( $depth > $best_depth ) {
            $best = $term;
            $best_depth = $depth;
        }
    }
    if ( ! ( $best instanceof WP_Term ) ) return $empty;

    $lineage = array_reverse( get_ancestors( $best->term_id, 'activity_category' ) );
    $lineage[] = $best->term_id;
    $segments = [];
    foreach ( $lineage as $term_id ) {
        $term = get_term( (int) $term_id, 'activity_category' );
        if ( $term instanceof WP_Term ) {
            $name = trim( (string) $term->name );
            if ( $name !== '' ) $segments[] = $name;
        }
    }

    return [
        'macro' => $segments[0] ?? '',
        'settore' => $segments[1] ?? '',
        'settore2' => $segments[2] ?? '',
    ];
}

function assoc_portal_calendar_browser_get_association_data( int $association_id ): array {
    static $cache = [];
    if ( isset( $cache[ $association_id ] ) ) return $cache[ $association_id ];

    $data = [
        'association_id' => 0,
        'association' => '',
        'macro' => '',
        'settore' => '',
        'settore2' => '',
        'regione' => '',
        'provincia' => '',
        'comune' => '',
    ];

    if ( $association_id <= 0 || ! in_array( get_post_type( $association_id ), [ 'association', 'tribe_organizer' ], true ) ) {
        $cache[ $association_id ] = $data;
        return $data;
    }

    $data['association_id'] = $association_id;
    $data['association'] = trim( (string) get_the_title( $association_id ) );
    $data['macro'] = assoc_portal_calendar_browser_meta_first( $association_id, [ '_ab_csv_macro', 'macro', 'macro_categoria', 'category', 'assoc_macro' ] );
    $data['settore'] = assoc_portal_calendar_browser_meta_first( $association_id, [ '_ab_csv_settore', 'settore', 'settore_1', 'subcategory', 'settore_primario', 'assoc_settore' ] );
    $data['settore2'] = assoc_portal_calendar_browser_meta_first( $association_id, [ '_ab_csv_settore2', 'settore2', 'settore_2', 'sotto_settore', 'settore_secondario', 'assoc_settore2' ] );
    $data['regione'] = assoc_portal_calendar_browser_meta_first( $association_id, [ '_ab_csv_region', 'regione', 'region', 'reg' ] );
    $data['provincia'] = assoc_portal_calendar_browser_meta_first( $association_id, [ '_ab_csv_province', 'provincia', 'province', 'prov', 'pr' ] );
    $data['comune'] = assoc_portal_calendar_browser_meta_first( $association_id, [ '_ab_csv_city', 'comune', 'city', 'citta', 'city_name' ] );

    $levels = assoc_portal_calendar_browser_get_activity_levels( $association_id );
    if ( $data['macro'] === '' ) $data['macro'] = (string) $levels['macro'];
    if ( $data['settore'] === '' ) $data['settore'] = (string) $levels['settore'];
    if ( $data['settore2'] === '' ) $data['settore2'] = (string) $levels['settore2'];

    $cache[ $association_id ] = $data;
    return $data;
}

function assoc_portal_calendar_browser_collect_events(): array {
    $query = new WP_Query( [
        'post_type' => [ 'event', 'tribe_events' ],
        'post_status' => [ 'publish', 'future' ],
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC',
    ] );

    $rows = [];
    if ( $query->have_posts() ) {
        while ( $query->have_posts() ) {
            $query->the_post();
            $event_id = get_the_ID();
            $start_raw = trim( (string) get_post_meta( $event_id, 'start_date', true ) );
            if ( $start_raw === '' ) $start_raw = trim( (string) get_post_meta( $event_id, '_EventStartDate', true ) );
            if ( $start_raw === '' ) $start_raw = trim( (string) get_post_field( 'post_date', $event_id ) );
            if ( $start_raw === '' ) continue;

            $start_ts = strtotime( $start_raw );
            if ( ! $start_ts ) continue;

            $end_raw = trim( (string) get_post_meta( $event_id, 'end_date', true ) );
            $end_ts = $end_raw !== '' ? strtotime( $end_raw ) : false;

            $association_id = 0;
            foreach ( [ 'organizer_association_id', 'association_id', 'association_post_id', '_assoc_id', '_association_id', 'assoc_id', '_EventOrganizerID', '_tribe_event_organizer', 'organizer_id' ] as $meta_key ) {
                $candidate = (int) get_post_meta( $event_id, $meta_key, true );
                if ( $candidate > 0 && in_array( get_post_type( $candidate ), [ 'association', 'tribe_organizer' ], true ) ) {
                    $association_id = $candidate;
                    break;
                }
            }

            // Fallback: check if the author manages an association
            if ( $association_id <= 0 ) {
                $author_id = (int) get_post_field( 'post_author', $event_id );
                if ( $author_id > 0 ) {
                    $candidate = (int) get_user_meta( $author_id, 'association_post_id', true );
                    if ( $candidate > 0 && 'association' === get_post_type( $candidate ) ) {
                        $association_id = $candidate;
                    }
                }
            }

            $assoc = assoc_portal_calendar_browser_get_association_data( $association_id );
            $event_city = trim( (string) get_post_meta( $event_id, 'city', true ) );
            if ( $event_city === '' ) $event_city = trim( (string) get_post_meta( $event_id, 'comune', true ) );
            $event_province = trim( (string) get_post_meta( $event_id, 'province', true ) );

            $city = $assoc['comune'] !== '' ? $assoc['comune'] : $event_city;
            $province = $assoc['provincia'] !== '' ? $assoc['provincia'] : $event_province;

            $address_parts = array_values( array_filter( [
                trim( (string) get_post_meta( $event_id, 'address', true ) ),
                $event_city,
                trim( (string) get_post_meta( $event_id, 'comune', true ) ),
                $event_province,
            ] ) );

            $image_url = (string) get_the_post_thumbnail_url( $event_id, 'large' );
            if ( $image_url === '' && function_exists( 'assoc_portal_ensure_event_featured_image' ) ) {
                assoc_portal_ensure_event_featured_image( $event_id );
                $image_url = (string) get_the_post_thumbnail_url( $event_id, 'large' );
            }

            $rows[] = [
                'event_id' => $event_id,
                'title' => (string) get_the_title( $event_id ),
                'description' => wp_strip_all_tags( (string) get_post_field( 'post_content', $event_id ) ),
                'image' => $image_url,
                'start_ts' => (int) $start_ts,
                'start_date' => date_i18n( 'Y-m-d H:i', $start_ts ),
                'start_time' => date_i18n( 'H:i', $start_ts ),
                'date_key' => date_i18n( 'Y-m-d', $start_ts ),
                'date_label' => date_i18n( 'd/m/Y', $start_ts ),
                'end_date' => $end_ts ? date_i18n( 'Y-m-d H:i', (int) $end_ts ) : '',
                'venue' => trim( (string) get_post_meta( $event_id, 'venue_name', true ) ),
                'address' => implode( ', ', $address_parts ),
                'registration_url' => trim( (string) get_post_meta( $event_id, 'registration_url', true ) ),
                'association_id' => (int) $assoc['association_id'],
                'association' => (string) $assoc['association'],
                'macro' => (string) $assoc['macro'],
                'settore' => (string) $assoc['settore'],
                'settore2' => (string) $assoc['settore2'],
                'regione' => (string) $assoc['regione'],
                'provincia' => (string) $province,
                'comune' => (string) $city,
            ];
        }
    }
    wp_reset_postdata();

    usort( $rows, static function( array $a, array $b ): int {
        return (int) ( $a['start_ts'] ?? 0 ) <=> (int) ( $b['start_ts'] ?? 0 );
    } );
    return $rows;
}

function assoc_portal_calendar_browser_row_matches( array $row, array $selected, string $exclude_field = '' ): bool {
    $map = [
        'macro' => 'macro',
        'settore' => 'settore',
        'settore2' => 'settore2',
        'regione' => 'regione',
        'provincia' => 'provincia',
        'comune' => 'comune',
        'associazione' => 'association_id',
    ];

    foreach ( $map as $field => $row_key ) {
        if ( $exclude_field === $field ) continue;
        $sel = $selected[ $field ] ?? '';

        if ( $field === 'associazione' ) {
            $sel_id = (int) $sel;
            if ( $sel_id > 0 && (int) ( $row[ $row_key ] ?? 0 ) !== $sel_id ) return false;
            continue;
        }

        $sel = trim( (string) $sel );
        if ( $sel === '' ) continue;
        if ( strcasecmp( trim( (string) ( $row[ $row_key ] ?? '' ) ), $sel ) !== 0 ) return false;
    }

    $selected_q = trim( (string) ( $selected['q'] ?? '' ) );
    if ( $exclude_field !== 'q' && $selected_q !== '' ) {
        $haystack = implode( ' ', array_filter( [
            (string) ( $row['title'] ?? '' ),
            (string) ( $row['description'] ?? '' ),
            (string) ( $row['association'] ?? '' ),
            (string) ( $row['venue'] ?? '' ),
            (string) ( $row['address'] ?? '' ),
            (string) ( $row['macro'] ?? '' ),
            (string) ( $row['settore'] ?? '' ),
            (string) ( $row['settore2'] ?? '' ),
            (string) ( $row['regione'] ?? '' ),
            (string) ( $row['provincia'] ?? '' ),
            (string) ( $row['comune'] ?? '' ),
        ] ) );

        $matches_query = false;
        if ( function_exists( 'mb_stripos' ) ) {
            $matches_query = mb_stripos( $haystack, $selected_q, 0, 'UTF-8' ) !== false;
        } else {
            $matches_query = stripos( $haystack, $selected_q ) !== false;
        }
        if ( ! $matches_query ) return false;
    }

    $start_ts = (int) ( $row['start_ts'] ?? 0 );
    if ( $start_ts <= 0 ) {
        return false;
    }

    $selected_day = isset( $selected['data_day'] ) ? absint( $selected['data_day'] ) : 0;
    if ( $exclude_field !== 'data_day' && $selected_day > 0 ) {
        if ( (int) date_i18n( 'j', $start_ts ) !== $selected_day ) {
            return false;
        }
    }

    $selected_month = isset( $selected['data_month'] ) ? absint( $selected['data_month'] ) : 0;
    if ( $exclude_field !== 'data_month' && $selected_month > 0 ) {
        if ( (int) date_i18n( 'n', $start_ts ) !== $selected_month ) {
            return false;
        }
    }

    $selected_year = isset( $selected['data_year'] ) ? absint( $selected['data_year'] ) : 0;
    if ( $exclude_field !== 'data_year' && $selected_year > 0 ) {
        if ( (int) date_i18n( 'Y', $start_ts ) !== $selected_year ) {
            return false;
        }
    }

    return true;
}

function assoc_portal_calendar_browser_collect_filter_options( array $rows, array $selected ): array {
    $options = [
        'macro' => [],
        'settore' => [],
        'settore2' => [],
        'regione' => [],
        'provincia' => [],
        'comune' => [],
        'associazione' => [],
        'data_day' => [],
        'data_month' => [],
        'data_year' => [],
    ];

    foreach ( array_keys( $options ) as $field ) {
        foreach ( $rows as $row ) {
            if ( ! assoc_portal_calendar_browser_row_matches( $row, $selected, $field ) ) continue;

            if ( $field === 'associazione' ) {
                $id = (int) ( $row['association_id'] ?? 0 );
                $name = trim( (string) ( $row['association'] ?? '' ) );
                if ( $id > 0 && $name !== '' ) $options['associazione'][ $id ] = $name;
                continue;
            }

            if ( in_array( $field, [ 'data_day', 'data_month', 'data_year' ], true ) ) {
                $start_ts = (int) ( $row['start_ts'] ?? 0 );
                if ( $start_ts <= 0 ) {
                    continue;
                }

                if ( $field === 'data_day' ) {
                    $day = (int) date_i18n( 'j', $start_ts );
                    if ( $day > 0 ) {
                        $options['data_day'][ $day ] = sprintf( '%02d', $day );
                    }
                    continue;
                }

                if ( $field === 'data_month' ) {
                    $month = (int) date_i18n( 'n', $start_ts );
                    if ( $month > 0 ) {
                        $month_name = ucfirst( (string) date_i18n( 'F', mktime( 0, 0, 0, $month, 1, 2000 ) ) );
                        $options['data_month'][ $month ] = sprintf( '%02d - %s', $month, $month_name );
                    }
                    continue;
                }

                $year = (int) date_i18n( 'Y', $start_ts );
                if ( $year > 0 ) {
                    $options['data_year'][ $year ] = (string) $year;
                }
                continue;
            }

            $value = trim( (string) ( $row[ $field ] ?? '' ) );
            if ( $value !== '' ) {
                $options[ $field ][ $value ] = $value;
            }
        }
    }

    foreach ( [ 'macro', 'settore', 'settore2', 'regione', 'provincia', 'comune' ] as $field ) {
        if ( ! empty( $options[ $field ] ) ) natcasesort( $options[ $field ] );
    }
    if ( ! empty( $options['associazione'] ) ) natcasesort( $options['associazione'] );
    if ( ! empty( $options['data_day'] ) ) ksort( $options['data_day'] );
    if ( ! empty( $options['data_month'] ) ) ksort( $options['data_month'] );
    if ( ! empty( $options['data_year'] ) ) krsort( $options['data_year'] );

    return $options;
}

function assoc_portal_calendar_browser_base_url(): string {
    foreach ( [ 'calendar', 'calendario' ] as $page_path ) {
        $calendar_page = get_page_by_path( $page_path, OBJECT, 'page' );
        if ( $calendar_page instanceof WP_Post ) {
            return (string) get_permalink( $calendar_page );
        }
    }

    $request_path = trim( (string) wp_parse_url( (string) ( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
    $base = home_url( '/' . $request_path . '/' );
    if ( is_singular() ) {
        $base = (string) get_permalink();
    }
    return $base;
}

function assoc_portal_calendar_browser_render_rows_block( array $rows, string $container_classes ): string {
    ob_start();
    ?>
    <div class="<?php echo esc_attr( $container_classes ); ?>">
        <?php if ( empty( $rows ) ) : ?>
            <p class="calendar-empty">Nessun evento trovato con i filtri selezionati.</p>
        <?php else : ?>
            <?php foreach ( $rows as $event ) : ?>
                <?php
                $start_ts = (int) ( $event['start_ts'] ?? 0 );
                $day_label = $start_ts > 0 ? date_i18n( 'l d/m/Y', $start_ts ) : (string) ( $event['date_label'] ?? '' );
                $time_label = trim( (string) ( $event['start_time'] ?? '' ) );
                $end_time_label = '';
                if ( (string) ( $event['end_date'] ?? '' ) !== '' ) {
                    $end_ts = strtotime( (string) $event['end_date'] );
                    if ( $end_ts ) {
                        $end_time_label = date_i18n( 'H:i', $end_ts );
                    }
                }
                if ( $end_time_label !== '' && $time_label !== '' && $end_time_label !== $time_label ) {
                    $time_label .= ' - ' . $end_time_label;
                }

                $data_attrs = 'class="calendar-event-row event-item" ';
                $data_attrs .= 'data-event-id="' . esc_attr( (string) ( $event['event_id'] ?? '' ) ) . '" ';
                $data_attrs .= 'data-title="' . esc_attr( (string) $event['title'] ) . '" ';
                $data_attrs .= 'data-description="' . esc_attr( (string) $event['description'] ) . '" ';
                $data_attrs .= 'data-image="' . esc_attr( (string) $event['image'] ) . '" ';
                $data_attrs .= 'data-start-date="' . esc_attr( (string) $event['start_date'] ) . '" ';
                $data_attrs .= 'data-end-date="' . esc_attr( (string) $event['end_date'] ) . '" ';
                $data_attrs .= 'data-venue="' . esc_attr( (string) $event['venue'] ) . '" ';
                $data_attrs .= 'data-address="' . esc_attr( (string) $event['address'] ) . '" ';
                $data_attrs .= 'data-registration-url="' . esc_attr( (string) $event['registration_url'] ) . '"';
                ?>
                <article <?php echo $data_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                    <div class="calendar-event-row-when">
                        <div class="calendar-event-row-date"><?php echo esc_html( (string) $day_label ); ?></div>
                        <?php if ( $time_label !== '' ) : ?><div class="calendar-event-row-time"><?php echo esc_html( $time_label ); ?></div><?php endif; ?>
                    </div>
                    <div class="calendar-event-row-main">
                        <h3 class="calendar-event-row-title"><?php echo esc_html( (string) $event['title'] ); ?></h3>
                        <div class="calendar-event-row-meta calendar-event-row-extra">
                            <?php if ( (string) $event['association'] !== '' ) : ?><span><strong>Associazione:</strong> <?php echo esc_html( (string) $event['association'] ); ?></span><?php endif; ?>
                            <?php if ( (string) $event['venue'] !== '' ) : ?><span><strong>Sede:</strong> <?php echo esc_html( (string) $event['venue'] ); ?></span><?php endif; ?>
                            <?php if ( (string) $event['address'] !== '' ) : ?><span><strong>Luogo:</strong> <?php echo esc_html( (string) $event['address'] ); ?></span><?php endif; ?>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    <?php
    return (string) ob_get_clean();
}

function assoc_portal_events_calendar_browser_shortcode(): string {
    $legacy_association_id = function_exists( 'assoc_portal_get_requested_association_id' )
        ? assoc_portal_get_requested_association_id()
        : 0;

    $selected = [
        'q' => isset( $_GET['ev_q'] ) ? sanitize_text_field( wp_unslash( $_GET['ev_q'] ) ) : '',
        'macro' => isset( $_GET['ev_macro'] ) ? sanitize_text_field( wp_unslash( $_GET['ev_macro'] ) ) : '',
        'settore' => isset( $_GET['ev_settore'] ) ? sanitize_text_field( wp_unslash( $_GET['ev_settore'] ) ) : '',
        'settore2' => isset( $_GET['ev_settore2'] ) ? sanitize_text_field( wp_unslash( $_GET['ev_settore2'] ) ) : '',
        'regione' => isset( $_GET['ev_regione'] ) ? sanitize_text_field( wp_unslash( $_GET['ev_regione'] ) ) : '',
        'provincia' => isset( $_GET['ev_provincia'] ) ? sanitize_text_field( wp_unslash( $_GET['ev_provincia'] ) ) : '',
        'comune' => isset( $_GET['ev_comune'] ) ? sanitize_text_field( wp_unslash( $_GET['ev_comune'] ) ) : '',
        'associazione' => isset( $_GET['ev_associazione'] ) ? absint( wp_unslash( $_GET['ev_associazione'] ) ) : 0,
        'event_id' => isset( $_GET['ev_event_id'] ) ? absint( wp_unslash( $_GET['ev_event_id'] ) ) : 0,
        'data_day' => 0,
        'data_month' => 0,
        'data_year' => 0,
        'vista' => 'calendar',
    ];
    if ( isset( $_GET['ev_vista'] ) ) {
        $requested_view = sanitize_key( wp_unslash( $_GET['ev_vista'] ) );
        if ( in_array( $requested_view, [ 'calendar', 'rows', 'cards' ], true ) ) {
            $selected['vista'] = $requested_view;
        }
    }
    if ( $selected['associazione'] <= 0 && $legacy_association_id > 0 ) {
        $selected['associazione'] = $legacy_association_id;
    }
    if ( isset( $_GET['ev_data'] ) ) {
        $date_raw = sanitize_text_field( wp_unslash( $_GET['ev_data'] ) );
        if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_raw ) ) {
            $legacy_ts = strtotime( $date_raw . ' 00:00:00' );
            if ( $legacy_ts ) {
                $selected['data_day'] = (int) date( 'j', $legacy_ts );
                $selected['data_month'] = (int) date( 'n', $legacy_ts );
                $selected['data_year'] = (int) date( 'Y', $legacy_ts );
            }
        }
    }

    if ( isset( $_GET['ev_giorno'] ) ) {
        $selected_day = absint( wp_unslash( $_GET['ev_giorno'] ) );
        if ( $selected_day >= 1 && $selected_day <= 31 ) {
            $selected['data_day'] = $selected_day;
        } else {
            $selected['data_day'] = 0;
        }
    }

    if ( isset( $_GET['ev_mese'] ) ) {
        $selected_month = absint( wp_unslash( $_GET['ev_mese'] ) );
        if ( $selected_month >= 1 && $selected_month <= 12 ) {
            $selected['data_month'] = $selected_month;
        } else {
            $selected['data_month'] = 0;
        }
    }

    if ( isset( $_GET['ev_anno'] ) ) {
        $selected_year = absint( wp_unslash( $_GET['ev_anno'] ) );
        if ( $selected_year >= 1970 && $selected_year <= 2100 ) {
            $selected['data_year'] = $selected_year;
        } else {
            $selected['data_year'] = 0;
        }
    }

    // Use ev_y / ev_m to avoid clashing with WP core query var "m" that can trigger 404s.
    $year_param = '';
    if ( isset( $_GET['ev_y'] ) ) {
        $year_param = wp_unslash( $_GET['ev_y'] );
    } elseif ( isset( $_GET['y'] ) ) {
        // Backward compatibility with older links.
        $year_param = wp_unslash( $_GET['y'] );
    }

    $month_param = '';
    if ( isset( $_GET['ev_m'] ) ) {
        $month_param = wp_unslash( $_GET['ev_m'] );
    } elseif ( isset( $_GET['m'] ) ) {
        // Backward compatibility with older links.
        $month_param = wp_unslash( $_GET['m'] );
    }

    $today_ts = (int) current_time( 'timestamp' );
    $today_day = (int) date_i18n( 'j', $today_ts );
    $today_month = (int) date_i18n( 'n', $today_ts );
    $today_year = (int) date_i18n( 'Y', $today_ts );

    $current_year = $year_param !== '' ? max( 1970, min( 2100, intval( $year_param ) ) ) : $today_year;
    $current_month = $month_param !== '' ? max( 1, min( 12, intval( $month_param ) ) ) : $today_month;
    if ( (int) $selected['data_year'] > 0 ) $current_year = (int) $selected['data_year'];
    if ( (int) $selected['data_month'] > 0 ) $current_month = (int) $selected['data_month'];

    $timestamp = mktime( 0, 0, 0, $current_month, 1, $current_year );
    $calendar_base_url = assoc_portal_calendar_browser_base_url();

    $all_rows = assoc_portal_calendar_browser_collect_events();
    $filter_options = assoc_portal_calendar_browser_collect_filter_options( $all_rows, $selected );
    if ( $today_day >= 1 && $today_day <= 31 && ! isset( $filter_options['data_day'][ $today_day ] ) ) {
        $filter_options['data_day'][ $today_day ] = sprintf( '%02d', $today_day );
    }
    if ( $today_month >= 1 && $today_month <= 12 && ! isset( $filter_options['data_month'][ $today_month ] ) ) {
        $month_label = ucfirst( (string) date_i18n( 'F', mktime( 0, 0, 0, $today_month, 1, $today_year ) ) );
        $filter_options['data_month'][ $today_month ] = sprintf( '%02d - %s', $today_month, $month_label );
    }
    if ( $today_year >= 1970 && $today_year <= 2100 && ! isset( $filter_options['data_year'][ $today_year ] ) ) {
        $filter_options['data_year'][ $today_year ] = (string) $today_year;
    }
    ksort( $filter_options['data_day'], SORT_NUMERIC );
    ksort( $filter_options['data_month'], SORT_NUMERIC );
    ksort( $filter_options['data_year'], SORT_NUMERIC );

    $filtered_rows = array_values( array_filter( $all_rows, static function( array $row ) use ( $selected ): bool {
        return assoc_portal_calendar_browser_row_matches( $row, $selected );
    } ) );

    $first_day_ts = strtotime( date( 'Y-m-01 00:00:00', $timestamp ) );
    $last_day_ts = strtotime( date( 'Y-m-t 23:59:59', $timestamp ) );
    $rows_month = array_values( array_filter( $filtered_rows, static function( array $row ) use ( $first_day_ts, $last_day_ts ): bool {
        $ts = (int) ( $row['start_ts'] ?? 0 );
        return $ts >= $first_day_ts && $ts <= $last_day_ts;
    } ) );

    $events_by_day = [];
    foreach ( $rows_month as $row ) {
        $day = (int) date_i18n( 'j', (int) $row['start_ts'] );
        if ( ! isset( $events_by_day[ $day ] ) ) $events_by_day[ $day ] = [];
        $events_by_day[ $day ][] = $row;
    }

    // Keep all result views scoped to the month/year selected in the top header picker.
    $rows_for_list = $filtered_rows;
    $rows_for_display = $rows_month;
    $results_total = count( $rows_for_list );
    $per_page = 50;
    $current_page = isset( $_GET['ev_page'] ) ? max( 1, absint( wp_unslash( $_GET['ev_page'] ) ) ) : 1;
    $total_pages = max( 1, (int) ceil( $results_total / $per_page ) );
    if ( $current_page > $total_pages ) {
        $current_page = 1;
    }
    $results_offset = ( $current_page - 1 ) * $per_page;
    $rows_for_page = array_slice( $rows_for_list, $results_offset, $per_page );
    $results_range_start = $results_total > 0 ? $results_offset + 1 : 0;
    $results_range_end = min( $results_offset + $per_page, $results_total );

    $base_args = [
        'ev_q' => $selected['q'] !== '' ? $selected['q'] : null,
        'ev_macro' => $selected['macro'] !== '' ? $selected['macro'] : null,
        'ev_settore' => $selected['settore'] !== '' ? $selected['settore'] : null,
        'ev_settore2' => $selected['settore2'] !== '' ? $selected['settore2'] : null,
        'ev_regione' => $selected['regione'] !== '' ? $selected['regione'] : null,
        'ev_provincia' => $selected['provincia'] !== '' ? $selected['provincia'] : null,
        'ev_comune' => $selected['comune'] !== '' ? $selected['comune'] : null,
        'ev_associazione' => (int) $selected['associazione'] > 0 ? (int) $selected['associazione'] : null,
        'ev_event_id' => (int) $selected['event_id'] > 0 ? (int) $selected['event_id'] : null,
        'ev_giorno' => (int) $selected['data_day'] > 0 ? (int) $selected['data_day'] : null,
        'ev_mese' => (int) $selected['data_month'] > 0 ? (int) $selected['data_month'] : null,
        'ev_anno' => (int) $selected['data_year'] > 0 ? (int) $selected['data_year'] : null,
        'ev_vista' => in_array( $selected['vista'], [ 'rows', 'cards' ], true ) ? $selected['vista'] : null,
    ];

    $prev_ts = strtotime( '-1 month', $timestamp );
    $next_ts = strtotime( '+1 month', $timestamp );
    $prev_link = esc_url( add_query_arg( array_merge( $base_args, [ 'ev_y' => date( 'Y', $prev_ts ), 'ev_m' => date( 'n', $prev_ts ) ] ), $calendar_base_url ) );
    $next_link = esc_url( add_query_arg( array_merge( $base_args, [ 'ev_y' => date( 'Y', $next_ts ), 'ev_m' => date( 'n', $next_ts ) ] ), $calendar_base_url ) );
    $calendar_nav_args = array_merge(
        $base_args,
        array(
            'ev_y' => null,
            'ev_m' => null,
        )
    );
    $calendar_nav_args = array_filter(
        $calendar_nav_args,
        static function( $value ): bool {
            return null !== $value && '' !== $value;
        }
    );
    $year_options = array_map(
        'intval',
        array_keys( (array) $filter_options['data_year'] )
    );
    if ( ! in_array( (int) $current_year, $year_options, true ) ) {
        $year_options[] = (int) $current_year;
    }
    $year_options = array_values( array_unique( $year_options ) );
    sort( $year_options, SORT_NUMERIC );

    $clear_link = esc_url( add_query_arg( [
        'ev_q' => null,
        'ev_macro' => null,
        'ev_settore' => null,
        'ev_settore2' => null,
        'ev_regione' => null,
        'ev_provincia' => null,
        'ev_comune' => null,
        'ev_associazione' => null,
        'ev_event_id' => null,
        'ev_giorno' => null,
        'ev_mese' => null,
        'ev_anno' => null,
        'ev_data' => null,
        'ev_vista' => null,
        'ev_page' => null,
        'ev_y' => null,
        'ev_m' => null,
        'y' => null,
        'm' => null,
        'associazione' => null,
        'associazione_id' => null,
    ], $calendar_base_url ) );
    $pagination_links = [];
    if ( $total_pages > 1 ) {
        $pagination_base = add_query_arg(
            array_merge(
                $base_args,
                [
                    'ev_y' => $current_year,
                    'ev_m' => $current_month,
                    'ev_page' => 999999999,
                ]
            ),
            $calendar_base_url
        );
        $pagination_links = paginate_links(
            [
                'base'      => str_replace( '999999999', '%#%', (string) $pagination_base ),
                'format'    => '',
                'current'   => $current_page,
                'total'     => $total_pages,
                'type'      => 'array',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
            ]
        );
        if ( ! is_array( $pagination_links ) ) {
            $pagination_links = [];
        }
    }

    ob_start();
    ?>
    <div class="assoc-portal-calendar-browser">
        <div class="calendar-header">
            <a class="prev-month" href="<?php echo $prev_link; ?>">&laquo; Precedente</a>
            <form class="calendar-header-picker" method="get" action="<?php echo esc_url( $calendar_base_url ); ?>">
                <?php foreach ( $calendar_nav_args as $arg_key => $arg_value ) : ?>
                    <input type="hidden" name="<?php echo esc_attr( (string) $arg_key ); ?>" value="<?php echo esc_attr( (string) $arg_value ); ?>">
                <?php endforeach; ?>
                <label class="calendar-header-select">
                    <span class="screen-reader-text">Mese</span>
                    <select name="ev_m" aria-label="Mese calendario" onchange="if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                        <?php for ( $month_index = 1; $month_index <= 12; $month_index++ ) : ?>
                            <?php $month_label = ucfirst( (string) date_i18n( 'F', mktime( 0, 0, 0, $month_index, 1, $current_year ) ) ); ?>
                            <option value="<?php echo esc_attr( (string) $month_index ); ?>" <?php selected( $current_month, $month_index ); ?>>
                                <?php echo esc_html( $month_label ); ?>
                            </option>
                        <?php endfor; ?>
                    </select>
                </label>
                <label class="calendar-header-select">
                    <span class="screen-reader-text">Anno</span>
                    <select name="ev_y" aria-label="Anno calendario" onchange="if(this.form.requestSubmit){this.form.requestSubmit();}else{this.form.submit();}">
                        <?php foreach ( $year_options as $year_option ) : ?>
                            <option value="<?php echo esc_attr( (string) $year_option ); ?>" <?php selected( $current_year, $year_option ); ?>>
                                <?php echo esc_html( (string) $year_option ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </form>
            <a class="next-month" href="<?php echo $next_link; ?>">Successivo &raquo;</a>
        </div>

        <form class="assoc-portal-calendar-filters" method="get" action="<?php echo esc_url( $calendar_base_url ); ?>">
            <input type="hidden" name="ev_y" value="<?php echo esc_attr( $current_year ); ?>">
            <input type="hidden" name="ev_m" value="<?php echo esc_attr( $current_month ); ?>">
            <input type="hidden" name="ev_vista" value="<?php echo esc_attr( (string) $selected['vista'] ); ?>">

            <div class="calendar-view-mode" role="group" aria-label="Modalita visualizzazione">
                <span class="calendar-view-mode-label">Visualizzazione</span>
                <div class="calendar-view-mode-buttons">
                    <button type="submit" class="calendar-view-mode-button<?php echo $selected['vista'] === 'calendar' ? ' is-active' : ''; ?>" name="ev_vista" value="calendar">Calendario</button>
                    <button type="submit" class="calendar-view-mode-button<?php echo $selected['vista'] === 'rows' ? ' is-active' : ''; ?>" name="ev_vista" value="rows">Righe</button>
                    <button type="submit" class="calendar-view-mode-button<?php echo $selected['vista'] === 'cards' ? ' is-active' : ''; ?>" name="ev_vista" value="cards">Schede</button>
                </div>
            </div>

            <div class="calendar-filter-grid">
                <label class="calendar-filter-field">
                    <span>Ricerca</span>
                    <input type="search" name="ev_q" value="<?php echo esc_attr( (string) $selected['q'] ); ?>" placeholder="Titolo, associazione, luogo...">
                </label>
                <label class="calendar-filter-field"><span>Macro categoria</span><select name="ev_macro"><option value="">Tutte</option><?php foreach ( (array) $filter_options['macro'] as $value => $label ) : ?><option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $selected['macro'], (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option><?php endforeach; ?></select></label>
                <label class="calendar-filter-field"><span>Settore</span><select name="ev_settore"><option value="">Tutti</option><?php foreach ( (array) $filter_options['settore'] as $value => $label ) : ?><option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $selected['settore'], (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option><?php endforeach; ?></select></label>
                <label class="calendar-filter-field"><span>Settore 2</span><select name="ev_settore2"><option value="">Tutti</option><?php foreach ( (array) $filter_options['settore2'] as $value => $label ) : ?><option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $selected['settore2'], (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option><?php endforeach; ?></select></label>
                <label class="calendar-filter-field"><span>Regione</span><select name="ev_regione"><option value="">Tutte</option><?php foreach ( (array) $filter_options['regione'] as $value => $label ) : ?><option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $selected['regione'], (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option><?php endforeach; ?></select></label>
                <label class="calendar-filter-field"><span>Provincia</span><select name="ev_provincia"><option value="">Tutte</option><?php foreach ( (array) $filter_options['provincia'] as $value => $label ) : ?><option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $selected['provincia'], (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option><?php endforeach; ?></select></label>
                <label class="calendar-filter-field"><span>Comune / Citta</span><select name="ev_comune"><option value="">Tutti</option><?php foreach ( (array) $filter_options['comune'] as $value => $label ) : ?><option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( $selected['comune'], (string) $value ); ?>><?php echo esc_html( (string) $label ); ?></option><?php endforeach; ?></select></label>
                <label class="calendar-filter-field"><span>Associazione</span><select name="ev_associazione"><option value="">Tutte</option><?php foreach ( (array) $filter_options['associazione'] as $value => $label ) : ?><option value="<?php echo esc_attr( (string) $value ); ?>" <?php selected( (int) $selected['associazione'], (int) $value ); ?>><?php echo esc_html( (string) $label ); ?></option><?php endforeach; ?></select></label>
                <label class="calendar-filter-field">
                    <span>Giorno</span>
                    <select name="ev_giorno">
                        <option value="">Tutti</option>
                        <?php foreach ( (array) $filter_options['data_day'] as $value => $label ) : ?>
                            <?php
                            $is_today_day_option = ( (int) $value === $today_day );
                            $day_label = (string) $label . ( $is_today_day_option ? ' (Oggi)' : '' );
                            ?>
                            <option value="<?php echo esc_attr( (string) $value ); ?>" class="<?php echo $is_today_day_option ? 'calendar-option-current' : ''; ?>" data-is-current-date="<?php echo $is_today_day_option ? '1' : '0'; ?>" <?php selected( (int) $selected['data_day'], (int) $value ); ?>>
                                <?php echo esc_html( $day_label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="calendar-filter-field">
                    <span>Mese</span>
                    <select name="ev_mese">
                        <option value="">Tutti</option>
                        <?php foreach ( (array) $filter_options['data_month'] as $value => $label ) : ?>
                            <?php
                            $is_today_month_option = ( (int) $value === $today_month );
                            $month_label = (string) $label . ( $is_today_month_option ? ' (Corrente)' : '' );
                            ?>
                            <option value="<?php echo esc_attr( (string) $value ); ?>" class="<?php echo $is_today_month_option ? 'calendar-option-current' : ''; ?>" data-is-current-date="<?php echo $is_today_month_option ? '1' : '0'; ?>" <?php selected( (int) $selected['data_month'], (int) $value ); ?>>
                                <?php echo esc_html( $month_label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="calendar-filter-field">
                    <span>Anno</span>
                    <select name="ev_anno">
                        <option value="">Tutti</option>
                        <?php foreach ( (array) $filter_options['data_year'] as $value => $label ) : ?>
                            <?php
                            $is_today_year_option = ( (int) $value === $today_year );
                            $year_label = (string) $label . ( $is_today_year_option ? ' (Corrente)' : '' );
                            ?>
                            <option value="<?php echo esc_attr( (string) $value ); ?>" class="<?php echo $is_today_year_option ? 'calendar-option-current' : ''; ?>" data-is-current-date="<?php echo $is_today_year_option ? '1' : '0'; ?>" <?php selected( (int) $selected['data_year'], (int) $value ); ?>>
                                <?php echo esc_html( $year_label ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <div class="calendar-filter-field calendar-filter-field-reset">
                    <a href="<?php echo $clear_link; ?>" class="calendar-filter-reset">Azzera</a>
                </div>
            </div>
        </form>

        <div class="calendar-results-meta">
            <?php
            echo esc_html( sprintf( '%d risultati della ricerca', $results_total ) );
            if ( $results_total > 0 ) {
                echo esc_html( sprintf( ' - Mostrati %d-%d di %d', $results_range_start, $results_range_end, $results_total ) );
            }
            echo esc_html( sprintf( ' - Nel mese selezionato: %d', count( $rows_month ) ) );
            ?>
        </div>

        <?php if ( $selected['vista'] === 'calendar' ) : ?>
            <?php
            $start_of_week = (int) get_option( 'start_of_week', 1 );
            $weekdays_mon_first = [ 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom' ];
            $weekdays_sun_first = [ 'Dom', 'Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab' ];
            $weekday_labels = $start_of_week === 0 ? $weekdays_sun_first : $weekdays_mon_first;

            $first_day_weekday = (int) date_i18n( 'w', $timestamp );
            $blank_cells = ( $first_day_weekday - $start_of_week + 7 ) % 7;
            $days_in_month = (int) date( 't', $timestamp );
            $total_cells = $blank_cells + $days_in_month;
            $total_rows = (int) ceil( $total_cells / 7 );
            $cell_index = 0;
            $day = 1;
            ?>
            <div class="assoc-portal-calendar calendar-primary-display">
                <table class="calendar-grid">
                    <thead>
                        <tr>
                            <?php foreach ( $weekday_labels as $weekday_label ) : ?>
                                <th><?php echo esc_html( $weekday_label ); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ( $row_index = 0; $row_index < $total_rows; $row_index++ ) : ?>
                            <tr>
                                <?php for ( $col_index = 0; $col_index < 7; $col_index++ ) : ?>
                                    <?php
                                    $is_empty = $cell_index < $blank_cells || $day > $days_in_month;
                                    if ( $is_empty ) :
                                    ?>
                                        <td class="empty"></td>
                                    <?php else : ?>
                                        <?php
                                        $is_today = $current_year === $today_year && $current_month === $today_month && $day === $today_day;
                                        ?>
                                        <td class="<?php echo $is_today ? 'today' : ''; ?>">
                                            <div class="day-number"><?php echo esc_html( (string) $day ); ?></div>
                                            <?php if ( isset( $events_by_day[ $day ] ) ) : ?>
                                                <ul class="events-in-day">
                                                    <?php foreach ( $events_by_day[ $day ] as $event ) : ?>
                                                        <?php
                                                        $data_attrs = 'class="event-item" ';
                                                        $data_attrs .= 'data-event-id="' . esc_attr( (string) ( $event['event_id'] ?? '' ) ) . '" ';
                                                        $data_attrs .= 'data-title="' . esc_attr( (string) $event['title'] ) . '" ';
                                                        $data_attrs .= 'data-description="' . esc_attr( (string) $event['description'] ) . '" ';
                                                        $data_attrs .= 'data-image="' . esc_attr( (string) $event['image'] ) . '" ';
                                                        $data_attrs .= 'data-start-date="' . esc_attr( (string) $event['start_date'] ) . '" ';
                                                        $data_attrs .= 'data-end-date="' . esc_attr( (string) $event['end_date'] ) . '" ';
                                                        $data_attrs .= 'data-venue="' . esc_attr( (string) $event['venue'] ) . '" ';
                                                        $data_attrs .= 'data-address="' . esc_attr( (string) $event['address'] ) . '" ';
                                                        $data_attrs .= 'data-registration-url="' . esc_attr( (string) $event['registration_url'] ) . '"';
                                                        ?>
                                                        <li <?php echo $data_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                                                            <?php if ( (string) $event['start_time'] !== '' ) : ?>
                                                                <span class="event-time"><?php echo esc_html( (string) $event['start_time'] ); ?></span>
                                                            <?php endif; ?>
                                                            <strong class="event-title"><?php echo esc_html( (string) $event['title'] ); ?></strong>
                                                            <?php if ( (string) $event['venue'] !== '' ) : ?>
                                                                <span class="event-venue"> @ <?php echo esc_html( (string) $event['venue'] ); ?></span>
                                                            <?php endif; ?>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </td>
                                        <?php $day++; ?>
                                    <?php endif; ?>
                                    <?php $cell_index++; ?>
                                <?php endfor; ?>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        <?php elseif ( $selected['vista'] === 'cards' ) : ?>
            <div class="assoc-portal-events-cards calendar-primary-display">
                <?php if ( empty( $rows_for_display ) ) : ?>
                    <p class="calendar-empty">Nessun evento trovato con i filtri selezionati.</p>
                <?php else : ?>
                    <?php foreach ( $rows_for_display as $event ) : ?>
                        <?php
                        $data_attrs = 'class="assoc-event-card event-item" ';
                        $data_attrs .= 'data-event-id="' . esc_attr( (string) ( $event['event_id'] ?? '' ) ) . '" ';
                        $data_attrs .= 'data-title="' . esc_attr( (string) $event['title'] ) . '" ';
                        $data_attrs .= 'data-description="' . esc_attr( (string) $event['description'] ) . '" ';
                        $data_attrs .= 'data-image="' . esc_attr( (string) $event['image'] ) . '" ';
                        $data_attrs .= 'data-start-date="' . esc_attr( (string) $event['start_date'] ) . '" ';
                        $data_attrs .= 'data-end-date="' . esc_attr( (string) $event['end_date'] ) . '" ';
                        $data_attrs .= 'data-venue="' . esc_attr( (string) $event['venue'] ) . '" ';
                        $data_attrs .= 'data-address="' . esc_attr( (string) $event['address'] ) . '" ';
                        $data_attrs .= 'data-registration-url="' . esc_attr( (string) $event['registration_url'] ) . '"';
                        ?>
                        <article <?php echo $data_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
                            <?php if ( (string) $event['image'] !== '' ) : ?>
                                <div class="assoc-event-card-thumb">
                                    <img src="<?php echo esc_url( (string) $event['image'] ); ?>" alt="<?php echo esc_attr( (string) $event['title'] ); ?>" loading="lazy">
                                </div>
                            <?php endif; ?>
                            <div class="assoc-event-card-body">
                                <h3 class="assoc-event-card-title"><?php echo esc_html( (string) $event['title'] ); ?></h3>
                                <div class="assoc-event-card-when"><strong>Data:</strong> <?php echo esc_html( (string) $event['date_label'] . ' ' . (string) $event['start_time'] ); ?></div>
                                <div class="assoc-event-card-meta assoc-event-card-extra">
                                    <?php if ( (string) $event['association'] !== '' ) : ?><div><strong>Associazione:</strong> <?php echo esc_html( (string) $event['association'] ); ?></div><?php endif; ?>
                                    <?php if ( (string) $event['venue'] !== '' ) : ?><div><strong>Sede:</strong> <?php echo esc_html( (string) $event['venue'] ); ?></div><?php endif; ?>
                                    <?php if ( (string) $event['address'] !== '' ) : ?><div><strong>Luogo:</strong> <?php echo esc_html( (string) $event['address'] ); ?></div><?php endif; ?>
                                </div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        <?php else : ?>
            <?php echo assoc_portal_calendar_browser_render_rows_block( $rows_for_display, 'assoc-portal-calendar assoc-portal-calendar-rows calendar-primary-display' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php endif; ?>

        <?php echo assoc_portal_calendar_browser_render_rows_block( $rows_for_page, 'assoc-portal-calendar assoc-portal-calendar-rows calendar-search-results-list' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
        <?php if ( ! empty( $pagination_links ) ) : ?>
            <nav class="calendar-results-pagination" aria-label="Paginazione risultati eventi">
                <ul class="calendar-results-pagination-list">
                    <?php foreach ( $pagination_links as $link_html ) : ?>
                        <?php $is_current = false !== strpos( (string) $link_html, 'current' ); ?>
                        <li class="calendar-results-pagination-item<?php echo $is_current ? ' is-current' : ''; ?>">
                            <?php echo $link_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        <?php endif; ?>

    </div>
    <script id="assoc-portal-calendar-autosubmit">
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('.assoc-portal-calendar-filters, .calendar-header-picker');
            forms.forEach(form => {
                const elements = form.querySelectorAll('input, select');
                let debounceTimer;
                elements.forEach(el => {
                    if (el.tagName === 'SELECT') {
                        el.addEventListener('change', () => {
                            if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
                        });
                    }
                    if (el.tagName === 'INPUT') {
                        if (el.type === 'text' || el.type === 'search') {
                            el.addEventListener('input', () => {
                                clearTimeout(debounceTimer);
                                debounceTimer = setTimeout(() => {
                                    if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
                                }, 600);
                            });
                        } else if (el.type === 'checkbox' || el.type === 'radio') {
                            el.addEventListener('change', () => {
                                if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
                            });
                        }
                    }
                });
            });

            // Hero script removed, moved to WP Snippets
        });
    </script>
    <?php
    return (string) ob_get_clean();
}

// Override legacy [events_calendar] renderer with the filter browser.
add_shortcode( 'events_calendar', 'assoc_portal_events_calendar_browser_shortcode' );
