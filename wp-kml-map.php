<?php
/**
 * Plugin Name: KML Map Viewer
 * Description: Sube uno o varios archivos KML y muestra mapas interactivos con capas de colores y filtro por NUM_SOCIO.
 * Version:     2.0.1
 * Author:      Glocal Saino
 * Text Domain: kml-map
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'KML_MAP_VERSION', '2.0.1' );
define( 'KML_MAP_DIR',     plugin_dir_path( __FILE__ ) );
define( 'KML_MAP_URL',     plugin_dir_url( __FILE__ ) );

// ---------------------------------------------------------------------------
// Permitir archivos KML en WordPress
// ---------------------------------------------------------------------------
add_filter( 'upload_mimes', function ( $mimes ) {
    $mimes['kml'] = 'application/vnd.google-earth.kml+xml';
    return $mimes;
} );

add_filter( 'wp_check_filetype_and_ext', function ( $data, $_file, $filename, $_mimes ) {
    if ( strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) ) === 'kml' ) {
        $data['ext']  = 'kml';
        $data['type'] = 'application/vnd.google-earth.kml+xml';
    }
    return $data;
}, 10, 4 );

// ---------------------------------------------------------------------------
// Custom Post Type
// ---------------------------------------------------------------------------
add_action( 'init', function () {
    register_post_type( 'kml_map', [
        'public'   => false,
        'show_ui'  => false,
        'supports' => [ 'title' ],
    ] );
} );

// ---------------------------------------------------------------------------
// Menú de administración
// ---------------------------------------------------------------------------
add_action( 'admin_menu', function () {
    add_menu_page(
        'Mapas KML',
        'Mapas KML',
        'upload_files',
        'kml-maps',
        function () { include KML_MAP_DIR . 'admin/admin-page.php'; },
        'dashicons-location-alt',
        30
    );
} );

// ---------------------------------------------------------------------------
// Helper: sube un array de archivos KML y devuelve los datos de cada capa
// ---------------------------------------------------------------------------
function kml_map_upload_files( $files_array, $colors = [] ) {
    require_once ABSPATH . 'wp-admin/includes/file.php';

    $layers      = [];
    $count       = count( $files_array['name'] );
    $color_index = 0;

    for ( $i = 0; $i < $count; $i++ ) {
        if ( $files_array['error'][ $i ] !== UPLOAD_ERR_OK ) continue;

        $ext = strtolower( pathinfo( $files_array['name'][ $i ], PATHINFO_EXTENSION ) );
        if ( $ext !== 'kml' ) continue;

        $single = [
            'name'     => $files_array['name'][ $i ],
            'type'     => $files_array['type'][ $i ],
            'tmp_name' => $files_array['tmp_name'][ $i ],
            'error'    => $files_array['error'][ $i ],
            'size'     => $files_array['size'][ $i ],
        ];

        $upload = wp_handle_upload( $single, [ 'test_form' => false ] );
        if ( isset( $upload['error'] ) ) continue;

        $color = isset( $colors[ $color_index ] )
            ? sanitize_hex_color( $colors[ $color_index ] )
            : '';

        $layers[] = [
            'url'   => esc_url_raw( $upload['url'] ),
            'name'  => pathinfo( $files_array['name'][ $i ], PATHINFO_FILENAME ),
            'color' => $color,
        ];
        $color_index++;
    }

    return $layers;
}

// ---------------------------------------------------------------------------
// Helper: extrae los nombres de campo de un archivo KML
// ---------------------------------------------------------------------------
function kml_map_parse_fields( $path ) {
    // Campos técnicos de KML/QGIS que nunca se muestran
    $hidden = [
        'timestamp', 'begin', 'end', 'altitudeMode', 'drawOrder',
        'stroke', 'stroke-opacity', 'stroke-width',
        'fill', 'fill-opacity', 'fill-color',
        'tessellate', 'extrude', 'visibility', 'icon',
    ];

    $content = @file_get_contents( $path );
    if ( ! $content ) return [];

    $fields = [];

    // Fuente 1: <SimpleField name="..."> dentro del Schema (más fiable en KML de QGIS)
    if ( preg_match_all( '/<SimpleField[^>]+name=["\']([^"\']+)["\']/', $content, $m ) ) {
        foreach ( $m[1] as $name ) {
            if ( ! in_array( $name, $hidden ) && ! in_array( $name, $fields ) ) {
                $fields[] = $name;
            }
        }
    }

    // Fuente 2: <SimpleData name="..."> del primer Placemark (fallback)
    if ( empty( $fields ) && preg_match_all( '/<SimpleData[^>]+name=["\']([^"\']+)["\']/', $content, $m ) ) {
        foreach ( $m[1] as $name ) {
            if ( ! in_array( $name, $hidden ) && ! in_array( $name, $fields ) ) {
                $fields[] = $name;
            }
        }
    }

    return $fields;
}

// Helper: convierte una URL del directorio de subidas a ruta absoluta en disco
function kml_map_url_to_path( $url ) {
    $upload_dir  = wp_upload_dir();
    $base_path   = parse_url( $upload_dir['baseurl'], PHP_URL_PATH );
    $file_path   = parse_url( $url, PHP_URL_PATH );
    $relative    = substr( $file_path, strlen( $base_path ) );
    return $upload_dir['basedir'] . $relative;
}

// Helper: actualiza los campos disponibles y visibles de un mapa
function kml_map_refresh_fields( $post_id, $new_layers ) {
    $existing = json_decode( get_post_meta( $post_id, '_kml_fields_available', true ), true ) ?: [];

    foreach ( $new_layers as $layer ) {
        $path = kml_map_url_to_path( $layer['url'] );
        if ( ! file_exists( $path ) ) continue;
        foreach ( kml_map_parse_fields( $path ) as $f ) {
            if ( ! in_array( $f, $existing ) ) $existing[] = $f;
        }
    }

    update_post_meta( $post_id, '_kml_fields_available', wp_json_encode( $existing ) );

    // Si aún no hay configuración de campos visibles, mostrar todos por defecto
    if ( ! get_post_meta( $post_id, '_kml_fields_visible', true ) ) {
        update_post_meta( $post_id, '_kml_fields_visible', wp_json_encode( $existing ) );
    }
}

// ---------------------------------------------------------------------------
// Acción: crear nuevo mapa (con uno o varios KML)
// ---------------------------------------------------------------------------
add_action( 'admin_post_kml_map_add', function () {
    if ( ! current_user_can( 'upload_files' ) ) wp_die( 'Sin permiso.' );
    check_admin_referer( 'kml_map_add' );

    $title = sanitize_text_field( $_POST['map_title'] ?? '' );
    if ( empty( $title ) ) {
        wp_redirect( admin_url( 'admin.php?page=kml-maps&error=notitle' ) ); exit;
    }
    if ( empty( $_FILES['kml_files']['name'][0] ) ) {
        wp_redirect( admin_url( 'admin.php?page=kml-maps&error=nofile' ) ); exit;
    }

    $colors = array_map( 'sanitize_text_field', (array) ( $_POST['kml_colors'] ?? [] ) );
    $layers = kml_map_upload_files( $_FILES['kml_files'], $colors );
    if ( empty( $layers ) ) {
        wp_redirect( admin_url( 'admin.php?page=kml-maps&error=badext' ) ); exit;
    }

    $post_id = wp_insert_post( [
        'post_title'  => $title,
        'post_type'   => 'kml_map',
        'post_status' => 'publish',
    ] );

    update_post_meta( $post_id, '_kml_layers', wp_json_encode( $layers ) );
    kml_map_refresh_fields( $post_id, $layers );

    wp_redirect( admin_url( 'admin.php?page=kml-maps&added=1' ) ); exit;
} );

// ---------------------------------------------------------------------------
// Acción: añadir más capas KML a un mapa existente
// ---------------------------------------------------------------------------
add_action( 'admin_post_kml_map_add_layers', function () {
    if ( ! current_user_can( 'upload_files' ) ) wp_die( 'Sin permiso.' );

    $map_id = intval( $_POST['map_id'] ?? 0 );
    check_admin_referer( 'kml_map_add_layers_' . $map_id );

    if ( empty( $_FILES['kml_files']['name'][0] ) ) {
        wp_redirect( admin_url( 'admin.php?page=kml-maps&error=nofile' ) ); exit;
    }

    $colors     = array_map( 'sanitize_text_field', (array) ( $_POST['kml_colors'] ?? [] ) );
    $new_layers = kml_map_upload_files( $_FILES['kml_files'], $colors );
    if ( empty( $new_layers ) ) {
        wp_redirect( admin_url( 'admin.php?page=kml-maps&error=badext' ) ); exit;
    }

    $existing = json_decode( get_post_meta( $map_id, '_kml_layers', true ), true ) ?: [];
    $merged   = array_merge( $existing, $new_layers );
    update_post_meta( $map_id, '_kml_layers', wp_json_encode( $merged ) );
    kml_map_refresh_fields( $map_id, $new_layers );

    wp_redirect( admin_url( 'admin.php?page=kml-maps&added_layer=1' ) ); exit;
} );

// ---------------------------------------------------------------------------
// Acción: guardar campos visibles en el popup
// ---------------------------------------------------------------------------
add_action( 'admin_post_kml_map_save_fields', function () {
    if ( ! current_user_can( 'upload_files' ) ) wp_die( 'Sin permiso.' );

    $map_id = intval( $_POST['map_id'] ?? 0 );
    check_admin_referer( 'kml_map_save_fields_' . $map_id );

    $visible      = array_map( 'sanitize_text_field', (array) ( $_POST['kml_visible_fields'] ?? [] ) );
    $filter_field = sanitize_text_field( $_POST['kml_filter_field'] ?? '' );

    update_post_meta( $map_id, '_kml_fields_visible', wp_json_encode( $visible ) );
    if ( $filter_field ) {
        update_post_meta( $map_id, '_kml_filter_field', $filter_field );
    }

    wp_redirect( admin_url( 'admin.php?page=kml-maps&saved_fields=1' ) ); exit;
} );

// ---------------------------------------------------------------------------
// Acción: eliminar una capa individual de un mapa
// ---------------------------------------------------------------------------
add_action( 'admin_post_kml_map_del_layer', function () {
    if ( ! current_user_can( 'upload_files' ) ) wp_die( 'Sin permiso.' );

    $map_id = intval( $_GET['map_id'] ?? 0 );
    $idx    = intval( $_GET['layer_idx'] ?? -1 );
    check_admin_referer( 'kml_map_del_layer_' . $map_id . '_' . $idx );

    $layers = json_decode( get_post_meta( $map_id, '_kml_layers', true ), true ) ?: [];

    if ( isset( $layers[ $idx ] ) ) {
        // Borrar archivo físico
        $upload_dir = wp_upload_dir();
        $path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $layers[ $idx ]['url'] );
        if ( file_exists( $path ) ) @unlink( $path );

        array_splice( $layers, $idx, 1 );
        update_post_meta( $map_id, '_kml_layers', wp_json_encode( array_values( $layers ) ) );
    }

    wp_redirect( admin_url( 'admin.php?page=kml-maps&deleted_layer=1' ) ); exit;
} );

// ---------------------------------------------------------------------------
// Acción: eliminar mapa completo
// ---------------------------------------------------------------------------
add_action( 'admin_post_kml_map_delete', function () {
    if ( ! current_user_can( 'upload_files' ) ) wp_die( 'Sin permiso.' );

    $id = intval( $_GET['id'] ?? 0 );
    check_admin_referer( 'kml_map_delete_' . $id );

    $layers     = json_decode( get_post_meta( $id, '_kml_layers', true ), true ) ?: [];
    $upload_dir = wp_upload_dir();
    foreach ( $layers as $layer ) {
        $path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $layer['url'] );
        if ( file_exists( $path ) ) @unlink( $path );
    }

    wp_delete_post( $id, true );
    wp_redirect( admin_url( 'admin.php?page=kml-maps&deleted=1' ) ); exit;
} );

// ---------------------------------------------------------------------------
// Shortcode [kml_map id="X"] o [kml_map id="X" height="600px"]
// ---------------------------------------------------------------------------
add_shortcode( 'kml_map', function ( $atts ) {
    $atts    = shortcode_atts( [ 'id' => 0, 'height' => '520px' ], $atts );
    $post_id = intval( $atts['id'] );
    $height  = sanitize_text_field( $atts['height'] );

    if ( ! $post_id ) return '<!-- kml_map: falta el atributo id -->';

    $layers_json  = get_post_meta( $post_id, '_kml_layers', true );
    $layers       = json_decode( $layers_json, true );
    if ( empty( $layers ) ) return '<!-- kml_map: sin capas KML -->';

    $fields_visible = json_decode( get_post_meta( $post_id, '_kml_fields_visible', true ), true );
    $filter_field   = get_post_meta( $post_id, '_kml_filter_field', true ) ?: 'NUM_SOCIO';

    wp_enqueue_style(
        'leaflet-css',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css',
        [], '1.9.4'
    );
    wp_enqueue_script(
        'leaflet-js',
        'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js',
        [], '1.9.4', true
    );
    wp_enqueue_script(
        'leaflet-omnivore',
        'https://api.mapbox.com/mapbox.js/plugins/leaflet-omnivore/v0.3.1/leaflet-omnivore.min.js',
        [ 'leaflet-js' ], '0.3.1', true
    );
    wp_enqueue_style(
        'kml-map-css',
        KML_MAP_URL . 'assets/css/map.css',
        [ 'leaflet-css' ], KML_MAP_VERSION
    );
    wp_enqueue_script(
        'kml-map-js',
        KML_MAP_URL . 'assets/js/kml-map.js',
        [ 'leaflet-js', 'leaflet-omnivore' ], KML_MAP_VERSION, true
    );

    static $instance = 0;
    $instance++;
    $uid = 'kmlmap_' . $post_id . '_' . $instance;

    ob_start();
    ?>
    <div class="kml-map-wrapper" style="height:<?php echo esc_attr( $height ); ?>">
        <div class="kml-map-canvas"
             id="<?php echo esc_attr( $uid ); ?>"
             data-kml-layers="<?php echo esc_attr( wp_json_encode( $layers ) ); ?>"
             data-kml-fields="<?php echo esc_attr( wp_json_encode( $fields_visible ) ); ?>"
             data-kml-filter-field="<?php echo esc_attr( $filter_field ); ?>">
        </div>
        <div class="kml-map-bar" id="<?php echo esc_attr( $uid ); ?>-bar">
            <div class="kml-filter-group">
                <span class="kml-filter-label">Filtrar por titular:</span>
                <select class="kml-filter-select"
                        id="<?php echo esc_attr( $uid ); ?>-sel"
                        multiple size="4"></select>
                <button class="kml-clear-btn"
                        id="<?php echo esc_attr( $uid ); ?>-clear">
                    &#x2715; Limpiar filtro
                </button>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
} );
