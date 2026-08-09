<?php
/**
 * Plugin Name:       KML Map Viewer
 * Description:       Sube uno o varios archivos KML y muestra mapas interactivos con capas de colores y filtro por campo configurable.
 * Version:           4.3.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Glocal Saino
 * Text Domain:       kml-map
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'KML_MAP_VERSION', '4.3.0' );
define( 'KML_MAP_DIR',     plugin_dir_path( __FILE__ ) );
define( 'KML_MAP_URL',     plugin_dir_url( __FILE__ ) );

// Nº máximo de mapas que permite crear la versión gratuita; la premium no
// tiene límite (ver la comprobación en admin_post_kml_map_add).
define( 'KML_MAP_FREE_MAP_LIMIT', 3 );

// ---------------------------------------------------------------------------
// Freemius: gestiona la licencia de la versión premium (mapas ilimitados,
// capas adicionales por mapa, configuración de campos del popup y aspecto
// de la caja de filtro). La versión gratuita funciona por completo sin
// esto: crear hasta 3 mapas con varias capas KML a la vez, verlos,
// filtrarlos, etc. Solo lo que se añade DESPUÉS de crear un mapa (más capas,
// elegir qué campos mostrar, personalizar el aspecto) o crear más de 3
// mapas requiere una licencia activa.
//
// IMPORTANTE: 'id' y 'public_key' son de ejemplo. Hay que sustituirlos por
// los que da el panel de Freemius al crear el producto (freemius.com), o el
// SDK se queda inactivo (kml_map_fs()->can_use_premium_code() se comporta
// como si no hubiera premium, así que las funciones de pago no aparecen ni
// se pueden activar hasta que se rellenen estos datos reales).
// ---------------------------------------------------------------------------
if ( ! function_exists( 'kml_map_fs' ) ) {
    function kml_map_fs() {
        global $kml_map_fs;

        if ( ! isset( $kml_map_fs ) ) {
            require_once KML_MAP_DIR . 'freemius/start.php';

            $kml_map_fs = fs_dynamic_init( [
                'id'                  => '0000',                                       // TODO: Plugin ID real de Freemius
                'slug'                => 'kml-map',                                     // TODO: debe coincidir con el slug final en WordPress.org y con el Text Domain de arriba
                'type'                => 'plugin',
                'public_key'          => 'pk_00000000000000000000000000000',            // TODO: clave pública real de Freemius
                'is_premium'          => false,
                'premium_suffix'      => 'Premium',
                'has_premium_version' => true,
                'has_addons'          => false,
                'has_paid_plans'      => true,
                'menu'                => [
                    'slug'    => 'kml-maps',
                    'support' => false,
                ],
            ] );
        }

        return $kml_map_fs;
    }

    kml_map_fs();
    do_action( 'kml_map_fs_loaded' );
}

// Punto único desde el que se consulta si hay premium activo: así se puede
// forzar para pruebas (ver más abajo) sin tocar cada comprobación una a una.
//
// Para probar las funciones premium ANTES de tener cuenta de Freemius (o en
// un WordPress de pruebas sin conexión real), añade esta línea a
// wp-config.php, encima de "That's all, stop editing":
//     define( 'KML_MAP_FORCE_PREMIUM', true );
// Quítala cuando termines de probar: con ella puesta, TODO el mundo ve la
// versión premium sin licencia, así que no debe quedar activa en un sitio
// real.
function kml_map_is_premium() {
    if ( defined( 'KML_MAP_FORCE_PREMIUM' ) && KML_MAP_FORCE_PREMIUM ) return true;
    return kml_map_fs()->can_use_premium_code();
}

// Tamaño de celda (en grados) de la cuadrícula usada para repartir cada capa
// en varios archivos .json pequeños en vez de uno solo enorme, así construir
// y leer el índice no requiere cargar en memoria los objetos de golpe.
define( 'KML_MAP_TILE_CELL_SIZE', 0.05 );

// Versión del formato del índice espacial (ver kml_map_build_feature_index).
// Subir este número fuerza a reconstruir en segundo plano el índice de todas
// las capas ya analizadas con un formato antiguo, sin depender de que
// alguien pulse "Analizar ahora" a mano.
define( 'KML_MAP_TILE_SCHEMA_VERSION', 4 );

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
// Helper: analiza un KML en una única pasada en streaming (XMLReader), sin
// cargar el archivo entero en memoria ni leerlo varias veces. Extrae a la vez
// el rectángulo delimitador, los nombres de campo y los valores únicos del
// campo de filtrado. Con archivos de decenas de miles de objetos, escanear el
// contenido completo con expresiones regulares —y encima hacerlo varias veces
// por archivo, una por cada dato que se necesitaba— podía agotar el tiempo de
// ejecución o la memoria de PHP y dejar el admin en blanco.
// ---------------------------------------------------------------------------
function kml_map_analyze_kml( $path, $filter_field = '' ) {
    $result = [ 'bounds' => null, 'fields' => [], 'filter_values' => [] ];

    if ( ! file_exists( $path ) || ! class_exists( 'XMLReader' ) ) return $result;

    // Campos técnicos de KML/QGIS que nunca se muestran
    $hidden_fields = [
        'timestamp', 'begin', 'end', 'altitudeMode', 'drawOrder',
        'stroke', 'stroke-opacity', 'stroke-width',
        'fill', 'fill-opacity', 'fill-color',
        'tessellate', 'extrude', 'visibility', 'icon',
    ];

    $reader           = new XMLReader();
    $prev_use_errors  = libxml_use_internal_errors( true );

    if ( ! @$reader->open( $path, null, LIBXML_NOWARNING | LIBXML_NOERROR ) ) {
        libxml_use_internal_errors( $prev_use_errors );
        return $result;
    }

    $south = $west = $north = $east = null;
    $schema_fields     = [];   // <SimpleField name="..."> (fuente fiable)
    $simpledata_fields = [];   // <SimpleData name="..."> (fallback si no hay Schema)
    $filter_values     = [];

    while ( @$reader->read() ) {
        if ( $reader->nodeType !== XMLReader::ELEMENT ) continue;

        $local = $reader->localName;

        if ( $local === 'coordinates' ) {
            $text = $reader->readString();
            foreach ( preg_split( '/\s+/', trim( $text ) ) as $tuple ) {
                if ( $tuple === '' ) continue;
                $parts = explode( ',', $tuple );
                if ( count( $parts ) < 2 ) continue;

                $lon = floatval( $parts[0] );
                $lat = floatval( $parts[1] );

                if ( $south === null || $lat < $south ) $south = $lat;
                if ( $north === null || $lat > $north ) $north = $lat;
                if ( $west  === null || $lon < $west  ) $west  = $lon;
                if ( $east  === null || $lon > $east  ) $east  = $lon;
            }
        } elseif ( $local === 'SimpleField' ) {
            $name = $reader->getAttribute( 'name' );
            if ( $name && ! in_array( $name, $hidden_fields, true ) && ! in_array( $name, $schema_fields, true ) ) {
                $schema_fields[] = $name;
            }
        } elseif ( $local === 'SimpleData' ) {
            $name = $reader->getAttribute( 'name' );
            if ( $name && ! in_array( $name, $hidden_fields, true ) && ! in_array( $name, $simpledata_fields, true ) ) {
                $simpledata_fields[] = $name;
            }
            // Solo se lee el texto (más costoso que leer el atributo) cuando
            // es el campo que realmente se está buscando.
            if ( $filter_field && $name === $filter_field ) {
                $value = trim( $reader->readString() );
                if ( $value !== '' && ! in_array( $value, $filter_values, true ) ) {
                    $filter_values[] = $value;
                }
            }
        }
    }

    $reader->close();
    libxml_use_internal_errors( $prev_use_errors );

    $result['bounds']        = $south === null ? null : [ $south, $west, $north, $east ];
    $result['fields']        = ! empty( $schema_fields ) ? $schema_fields : $simpledata_fields;
    $result['filter_values'] = $filter_values;

    return $result;
}

// ---------------------------------------------------------------------------
// Helpers de geometría: convierten la geometría de un <Placemark> KML (ya
// cargado como DOMElement, ver kml_map_build_feature_index) a geometría
// GeoJSON, y calculan su rectángulo delimitador.
// ---------------------------------------------------------------------------
function kml_map_parse_coordinates_text( $text ) {
    $coords = [];
    foreach ( preg_split( '/\s+/', trim( $text ) ) as $tuple ) {
        if ( $tuple === '' ) continue;
        $parts = explode( ',', $tuple );
        if ( count( $parts ) < 2 ) continue;
        $coords[] = [ floatval( $parts[0] ), floatval( $parts[1] ) ]; // GeoJSON: [lon, lat]
    }
    return $coords;
}

function kml_map_dom_geometry( DOMElement $el ) {
    $tag = $el->localName;

    if ( $tag === 'Point' || $tag === 'LineString' ) {
        $coordsEl = $el->getElementsByTagName( 'coordinates' )->item( 0 );
        if ( ! $coordsEl ) return null;
        $c = kml_map_parse_coordinates_text( $coordsEl->textContent );
        if ( ! $c ) return null;
        return $tag === 'Point'
            ? [ 'type' => 'Point', 'coordinates' => $c[0] ]
            : [ 'type' => 'LineString', 'coordinates' => $c ];
    }

    if ( $tag === 'Polygon' ) {
        $rings = [];
        foreach ( [ 'outerBoundaryIs', 'innerBoundaryIs' ] as $boundary_tag ) {
            foreach ( $el->getElementsByTagName( $boundary_tag ) as $boundary_el ) {
                $coordsEl = $boundary_el->getElementsByTagName( 'coordinates' )->item( 0 );
                if ( ! $coordsEl ) continue;
                $ring = kml_map_parse_coordinates_text( $coordsEl->textContent );
                if ( $ring ) $rings[] = $ring;
            }
        }
        return $rings ? [ 'type' => 'Polygon', 'coordinates' => $rings ] : null;
    }

    if ( $tag === 'MultiGeometry' ) {
        $geometries = [];
        foreach ( $el->childNodes as $child ) {
            if ( ! ( $child instanceof DOMElement ) ) continue;
            $g = kml_map_dom_geometry( $child );
            if ( $g ) $geometries[] = $g;
        }
        return $geometries ? [ 'type' => 'GeometryCollection', 'geometries' => $geometries ] : null;
    }

    return null;
}

function kml_map_dom_properties( DOMElement $placemark ) {
    $props = [];
    foreach ( $placemark->getElementsByTagName( 'SimpleData' ) as $sd ) {
        $name = $sd->getAttribute( 'name' );
        if ( $name ) $props[ $name ] = trim( $sd->textContent );
    }
    return $props;
}

function kml_map_geometry_bounds( $geometry ) {
    $bounds = [ 'south' => null, 'west' => null, 'north' => null, 'east' => null ];

    $walk_coords = function ( $coords ) use ( &$bounds, &$walk_coords ) {
        if ( isset( $coords[0] ) && is_numeric( $coords[0] ) ) {
            $lon = $coords[0]; $lat = $coords[1];
            if ( $bounds['south'] === null || $lat < $bounds['south'] ) $bounds['south'] = $lat;
            if ( $bounds['north'] === null || $lat > $bounds['north'] ) $bounds['north'] = $lat;
            if ( $bounds['west']  === null || $lon < $bounds['west']  ) $bounds['west']  = $lon;
            if ( $bounds['east']  === null || $lon > $bounds['east']  ) $bounds['east']  = $lon;
            return;
        }
        foreach ( $coords as $c ) $walk_coords( $c );
    };

    // Recorre también GeometryCollection anidadas dentro de otras (una
    // <MultiGeometry> con otra <MultiGeometry> dentro, caso raro pero válido
    // en KML), no solo un nivel.
    $walk_geometry = function ( $g ) use ( &$walk_coords, &$walk_geometry ) {
        if ( isset( $g['coordinates'] ) ) {
            $walk_coords( $g['coordinates'] );
        } elseif ( isset( $g['geometries'] ) ) {
            foreach ( $g['geometries'] as $sub ) $walk_geometry( $sub );
        }
    };

    $walk_geometry( $geometry );

    return $bounds['south'] === null ? null : [ $bounds['south'], $bounds['west'], $bounds['north'], $bounds['east'] ];
}

// Helper: carpeta de caché (índice espacial) de una capa, derivada de su URL.
// No depende de la posición de la capa en el array (que cambia si se borran
// otras capas antes), y el hash impide cualquier problema de ruta con el
// parámetro que llegue al endpoint público que sirve estas celdas.
function kml_map_tile_dir( $layer_url ) {
    $upload_dir = wp_upload_dir();
    return trailingslashit( $upload_dir['basedir'] ) . 'kml-map-tiles/' . md5( $layer_url );
}

// ¿El índice espacial de esta capa existe y está en el formato actual? Si
// KML_MAP_TILE_SCHEMA_VERSION ha subido (p.ej. porque se añadió un dato
// nuevo por objeto), esto devuelve false para índices antiguos, así se
// reconstruyen solos en segundo plano sin intervención manual.
function kml_map_tile_index_current( $tile_dir ) {
    $schema_file = trailingslashit( $tile_dir ) . '.schema';
    if ( ! is_dir( $tile_dir ) || ! file_exists( $schema_file ) ) return false;
    return trim( (string) file_get_contents( $schema_file ) ) === (string) KML_MAP_TILE_SCHEMA_VERSION;
}

function kml_map_delete_dir_recursive( $dir ) {
    if ( ! is_dir( $dir ) ) return;
    foreach ( scandir( $dir ) as $item ) {
        if ( $item === '.' || $item === '..' ) continue;
        $path = trailingslashit( $dir ) . $item;
        is_dir( $path ) ? kml_map_delete_dir_recursive( $path ) : @unlink( $path );
    }
    @rmdir( $dir );
}

// ---------------------------------------------------------------------------
// Construye el índice espacial de una capa: reparte sus objetos en una
// cuadrícula de celdas (una carpeta con un archivo .json por celda) para que
// tanto construir como servir la capa se haga leyendo/escribiendo varios
// archivos pequeños en vez de uno solo con los 63.000+ objetos de golpe
// (lo que agotaba la memoria de PHP). También calcula, de paso y sin coste
// adicional, el rectángulo que engloba los objetos de cada valor del campo
// de filtrado, para poder encuadrar el mapa a ellos sin descargar la capa.
// ---------------------------------------------------------------------------
function kml_map_build_feature_index( $path, $out_dir, $filter_field = '' ) {
    $value_bounds = [];

    if ( ! file_exists( $path ) || ! class_exists( 'XMLReader' ) || ! class_exists( 'DOMDocument' ) ) {
        return $value_bounds;
    }

    if ( ! file_exists( $out_dir ) ) wp_mkdir_p( $out_dir );
    foreach ( glob( trailingslashit( $out_dir ) . '*.json' ) ?: [] as $old ) @unlink( $old );

    $reader          = new XMLReader();
    $prev_use_errors = libxml_use_internal_errors( true );

    if ( ! @$reader->open( $path, null, LIBXML_NOWARNING | LIBXML_NOERROR ) ) {
        libxml_use_internal_errors( $prev_use_errors );
        return $value_bounds;
    }

    $cell_size = KML_MAP_TILE_CELL_SIZE;
    $cells     = [];

    while ( @$reader->read() ) {
        if ( $reader->nodeType !== XMLReader::ELEMENT || $reader->localName !== 'Placemark' ) continue;

        $node = $reader->expand();
        if ( ! $node ) continue;

        $dom         = new DOMDocument();
        $placemarkEl = $dom->appendChild( $dom->importNode( $node, true ) );

        $geometry = null;
        foreach ( $placemarkEl->childNodes as $child ) {
            if ( $child instanceof DOMElement
                && in_array( $child->localName, [ 'Point', 'LineString', 'Polygon', 'MultiGeometry' ], true ) ) {
                $geometry = kml_map_dom_geometry( $child );
                break;
            }
        }
        if ( ! $geometry ) continue;

        $bounds = kml_map_geometry_bounds( $geometry );
        if ( ! $bounds ) continue;

        $properties = kml_map_dom_properties( $placemarkEl );

        $cx  = (int) floor( ( ( $bounds[1] + $bounds[3] ) / 2 ) / $cell_size );
        $cy  = (int) floor( ( ( $bounds[0] + $bounds[2] ) / 2 ) / $cell_size );
        $key = $cx . '_' . $cy;

        $cells[ $key ][] = [
            'geometry'   => $geometry,
            'bounds'     => $bounds,
            'properties' => $properties,
        ];

        if ( $filter_field && isset( $properties[ $filter_field ] ) && $properties[ $filter_field ] !== '' ) {
            $v = $properties[ $filter_field ];
            if ( ! isset( $value_bounds[ $v ] ) ) {
                $value_bounds[ $v ] = $bounds;
            } else {
                $vb = $value_bounds[ $v ];
                $value_bounds[ $v ] = [
                    min( $vb[0], $bounds[0] ), min( $vb[1], $bounds[1] ),
                    max( $vb[2], $bounds[2] ), max( $vb[3], $bounds[3] ),
                ];
            }
        }
    }

    $reader->close();
    libxml_use_internal_errors( $prev_use_errors );

    foreach ( $cells as $key => $features ) {
        file_put_contents( trailingslashit( $out_dir ) . $key . '.json', wp_json_encode( $features, JSON_UNESCAPED_UNICODE ) );
    }

    file_put_contents( trailingslashit( $out_dir ) . '.schema', (string) KML_MAP_TILE_SCHEMA_VERSION );

    return $value_bounds;
}

// ---------------------------------------------------------------------------
// Helper: sube un array de archivos KML y crea la entrada de cada capa SIN
// analizarla todavía (bounds/campos/valores de filtro). El análisis se hace
// después en segundo plano (ver kml_map_run_analysis): así la subida responde
// al instante sin importar cuántos objetos tenga el KML, y sin depender de
// los límites de tiempo de ejecución del servidor.
// ---------------------------------------------------------------------------
function kml_map_upload_files( $files_array, $colors = [], $no_fill = [] ) {
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
            'url'      => esc_url_raw( $upload['url'] ),
            'name'     => pathinfo( $files_array['name'][ $i ], PATHINFO_FILENAME ),
            'color'    => $color,
            'fill'     => empty( $no_fill[ $color_index ] ),
            'bounds'   => null,
            'analyzed' => false,
        ];
        $color_index++;
    }

    return $layers;
}

// Helper: convierte una URL del directorio de subidas a ruta absoluta en disco
function kml_map_url_to_path( $url ) {
    $upload_dir  = wp_upload_dir();
    $base_path   = parse_url( $upload_dir['baseurl'], PHP_URL_PATH );
    $file_path   = parse_url( $url, PHP_URL_PATH );
    $relative    = substr( $file_path, strlen( $base_path ) );
    return $upload_dir['basedir'] . $relative;
}

// ---------------------------------------------------------------------------
// Procesamiento en segundo plano (WP-Cron): el análisis de cada KML (bounds,
// campos, valores de filtro) se hace fuera de la petición que ve el
// navegador. Un archivo de decenas de miles de objetos puede tardar más de
// lo que el servidor (PHP, Nginx/Apache o un proxy delante) permite para una
// sola petición HTTP; en segundo plano no hay ese límite.
// ---------------------------------------------------------------------------
add_action( 'kml_map_analyze_cron', 'kml_map_run_analysis' );

function kml_map_schedule_analysis( $post_id ) {
    if ( ! wp_next_scheduled( 'kml_map_analyze_cron', [ $post_id ] ) ) {
        wp_schedule_single_event( time(), 'kml_map_analyze_cron', [ $post_id ] );
    }
    // Intenta arrancar el cron ya mismo en vez de esperar a la próxima visita al sitio.
    if ( function_exists( 'spawn_cron' ) ) spawn_cron();
}

function kml_map_run_analysis( $post_id ) {
    if ( function_exists( 'set_time_limit' ) ) @set_time_limit( 0 );
    if ( function_exists( 'wp_raise_memory_limit' ) ) wp_raise_memory_limit( 'admin' );

    $layers = json_decode( get_post_meta( $post_id, '_kml_layers', true ), true ) ?: [];
    if ( empty( $layers ) ) return;

    // Sin campo de filtro configurado (versión gratuita, o premium que aún
    // no lo ha elegido en "Campos del popup"), no se filtra nada: no tiene
    // sentido buscar valores de un campo por defecto que puede ni existir
    // en el KML de quien lo use.
    $filter_field  = get_post_meta( $post_id, '_kml_filter_field', true ) ?: '';
    $fields        = json_decode( get_post_meta( $post_id, '_kml_fields_available', true ), true ) ?: [];
    $filter_values = json_decode( get_post_meta( $post_id, '_kml_filter_values', true ), true ) ?: [];
    $value_bounds  = json_decode( get_post_meta( $post_id, '_kml_filter_value_bounds', true ), true ) ?: [];

    foreach ( $layers as $idx => $layer ) {
        // Una capa que ya tenía 'analyzed' de antes de existir el índice
        // espacial, o cuyo índice está en un formato antiguo (ver
        // KML_MAP_TILE_SCHEMA_VERSION), no debe darse por completa: si no,
        // nunca se reconstruye y el endpoint sirve datos incompletos.
        $tile_dir = kml_map_tile_dir( $layer['url'] );
        if ( ! empty( $layer['analyzed'] ) && kml_map_tile_index_current( $tile_dir ) ) continue;

        $path     = kml_map_url_to_path( $layer['url'] );
        $analysis = file_exists( $path )
            ? kml_map_analyze_kml( $path, $filter_field )
            : [ 'bounds' => null, 'fields' => [], 'filter_values' => [] ];

        $layers[ $idx ]['bounds']   = $analysis['bounds'];
        $layers[ $idx ]['analyzed'] = true;

        foreach ( $analysis['fields'] as $f ) {
            if ( ! in_array( $f, $fields, true ) ) $fields[] = $f;
        }
        foreach ( $analysis['filter_values'] as $v ) {
            if ( ! in_array( $v, $filter_values, true ) ) $filter_values[] = $v;
        }

        // Índice de la capa: reparte sus objetos en varios archivos .json
        // pequeños (ver el endpoint REST más abajo, que los pagina) en vez de
        // uno solo con todos los objetos de golpe. De paso calcula el
        // rectángulo que engloba los objetos de cada valor del filtro.
        if ( file_exists( $path ) ) {
            $layer_value_bounds = kml_map_build_feature_index( $path, $tile_dir, $filter_field );
            foreach ( $layer_value_bounds as $v => $b ) {
                if ( ! isset( $value_bounds[ $v ] ) ) {
                    $value_bounds[ $v ] = $b;
                } else {
                    $vb = $value_bounds[ $v ];
                    $value_bounds[ $v ] = [
                        min( $vb[0], $b[0] ), min( $vb[1], $b[1] ),
                        max( $vb[2], $b[2] ), max( $vb[3], $b[3] ),
                    ];
                }
            }
        }

        // Se guarda el progreso capa a capa: si el proceso se interrumpiera a
        // mitad (otro límite de tiempo, aunque mucho más generoso aquí), no
        // se pierde lo ya analizado y se retoma donde quedó.
        update_post_meta( $post_id, '_kml_layers', wp_json_encode( $layers, JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $post_id, '_kml_fields_available', wp_json_encode( $fields, JSON_UNESCAPED_UNICODE ) );
        if ( ! get_post_meta( $post_id, '_kml_fields_visible', true ) ) {
            update_post_meta( $post_id, '_kml_fields_visible', wp_json_encode( $fields, JSON_UNESCAPED_UNICODE ) );
        }

        $sorted_values = $filter_values;
        sort( $sorted_values, SORT_NATURAL );
        update_post_meta( $post_id, '_kml_filter_values', wp_json_encode( $sorted_values, JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $post_id, '_kml_filter_values_field', $filter_field );
        update_post_meta( $post_id, '_kml_filter_value_bounds', wp_json_encode( $value_bounds, JSON_UNESCAPED_UNICODE ) );
    }
}

// ---------------------------------------------------------------------------
// Helper: marca para reanálisis en segundo plano las capas que lo necesiten
// —les falta el análisis (mapas migrados), el campo de filtrado ha
// cambiado, o su índice espacial no existe o está en un formato antiguo
// (ver KML_MAP_TILE_SCHEMA_VERSION)— y programa el cron. No repite trabajo
// si ya está todo al día.
// ---------------------------------------------------------------------------
function kml_map_ensure_layers_queued( $post_id, $layers, $filter_field ) {
    $needs_queue    = false;
    $tiles_outdated = false;

    foreach ( $layers as $idx => $layer ) {
        $layer_outdated = ! kml_map_tile_index_current( kml_map_tile_dir( $layer['url'] ) );
        if ( $layer_outdated ) $tiles_outdated = true;
        if ( empty( $layer['analyzed'] ) || $layer_outdated ) {
            $layers[ $idx ]['analyzed'] = false;
            $needs_queue = true;
        }
    }

    $filter_values_field = get_post_meta( $post_id, '_kml_filter_values_field', true );

    // El campo de filtro cambió (o nunca se calculó), o el índice espacial
    // está en un formato antiguo: en ambos casos los valores de filtro ya
    // guardados no sirven (los del formato antiguo podrían venir con
    // acentos/eñes corruptos de una versión previa del plugin) y hay que
    // recalcularlos desde cero para toda la capa, no solo acumular encima.
    if ( $filter_values_field !== $filter_field || $tiles_outdated ) {
        foreach ( $layers as $idx => $layer ) {
            $layers[ $idx ]['analyzed'] = false;
        }
        update_post_meta( $post_id, '_kml_filter_values', wp_json_encode( [], JSON_UNESCAPED_UNICODE ) );
        update_post_meta( $post_id, '_kml_filter_values_field', $filter_field );
        update_post_meta( $post_id, '_kml_filter_value_bounds', wp_json_encode( [], JSON_UNESCAPED_UNICODE ) );
        $needs_queue = true;
    }

    if ( $needs_queue ) {
        update_post_meta( $post_id, '_kml_layers', wp_json_encode( $layers, JSON_UNESCAPED_UNICODE ) );
        kml_map_schedule_analysis( $post_id );
    }

    return $layers;
}

// ---------------------------------------------------------------------------
// Endpoint REST: devuelve los objetos de una capa (opcionalmente filtrados
// por campo/valores), en páginas de KML_MAP_PAGE_SIZE objetos. El navegador
// pide la capa entera empezando por la página 0 en cuanto se abre el mapa, y
// sigue pidiendo páginas sucesivas mientras la respuesta diga 'has_more' (ver
// fetchLayerPage en el JS); así una capa de 63.000 objetos se sirve en varias
// peticiones ligeras en vez de una sola enorme que colgaría la página.
// ---------------------------------------------------------------------------
add_action( 'rest_api_init', function () {
    register_rest_route( 'kml-map/v1', '/features', [
        'methods'             => 'GET',
        'callback'            => 'kml_map_rest_get_features',
        'permission_callback' => '__return_true', // mismo dato que ya es público en el KML subido
        'args'                => [
            'url' => [ 'required' => true ],
        ],
    ] );
} );

// Nº de objetos que se devuelven como máximo en cada página.
define( 'KML_MAP_PAGE_SIZE', 5000 );

function kml_map_rest_get_features( WP_REST_Request $req ) {
    $url  = esc_url_raw( $req->get_param( 'url' ) );
    $page = max( 0, intval( $req->get_param( 'page' ) ) );

    $filter_field  = sanitize_text_field( (string) $req->get_param( 'filter_field' ) );
    $filter_values = array_filter( array_map( 'trim', explode( ',', (string) $req->get_param( 'filter_values' ) ) ) );

    $tile_dir       = kml_map_tile_dir( $url );
    $features       = [];
    $total_matching = 0; // cuántos objetos cumplen el filtro en total (de todas las páginas)
    $page_size      = KML_MAP_PAGE_SIZE;
    $skip           = $page * $page_size;

    if ( is_dir( $tile_dir ) ) {
        // Todos los archivos de celda de la capa, en orden estable: el mismo
        // orden en cada petición es lo que hace que las páginas sucesivas no
        // se salten ni repitan objetos.
        $files = glob( trailingslashit( $tile_dir ) . '*.json' ) ?: [];
        sort( $files );

        foreach ( $files as $file ) {
            $cell_features = json_decode( file_get_contents( $file ), true );
            if ( ! is_array( $cell_features ) ) continue;

            foreach ( $cell_features as $f ) {
                if ( $filter_field && $filter_values ) {
                    $v = $f['properties'][ $filter_field ] ?? null;
                    if ( $v === null || ! in_array( (string) $v, $filter_values, true ) ) continue;
                }

                $total_matching++;

                // Los objetos de páginas anteriores a la pedida se cuentan
                // (para el total) pero no se incluyen; los de esta página
                // sí, hasta llenarla.
                if ( $total_matching <= $skip || count( $features ) >= $page_size ) continue;

                $features[] = [
                    'type'       => 'Feature',
                    'geometry'   => $f['geometry'],
                    'properties' => $f['properties'],
                ];
            }
        }
    }

    // 'has_more' le dice al navegador que pida la siguiente página para
    // completar la capa; solo cuando ya no hay más páginas se da por
    // totalmente cargada (ver el JS).
    $response = new WP_REST_Response( [
        'type'     => 'FeatureCollection',
        'features' => $features,
        'has_more' => $total_matching > ( $skip + count( $features ) ),
        'total'    => $total_matching,
    ] );
    $response->header( 'Cache-Control', 'public, max-age=60' );
    return $response;
}

// ---------------------------------------------------------------------------
// Acción: crear nuevo mapa (con uno o varios KML)
// ---------------------------------------------------------------------------
add_action( 'admin_post_kml_map_add', function () {
    if ( ! current_user_can( 'upload_files' ) ) wp_die( 'Sin permiso.' );
    check_admin_referer( 'kml_map_add' );

    if ( ! kml_map_is_premium()
        && wp_count_posts( 'kml_map' )->publish >= KML_MAP_FREE_MAP_LIMIT ) {
        wp_redirect( admin_url( 'admin.php?page=kml-maps&error=maplimit' ) ); exit;
    }

    $title = sanitize_text_field( wp_unslash( $_POST['map_title'] ?? '' ) );
    if ( empty( $title ) ) {
        wp_redirect( admin_url( 'admin.php?page=kml-maps&error=notitle' ) ); exit;
    }
    if ( empty( $_FILES['kml_files']['name'][0] ) ) {
        wp_redirect( admin_url( 'admin.php?page=kml-maps&error=nofile' ) ); exit;
    }

    $colors  = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['kml_colors'] ?? [] ) ) );
    $no_fill = wp_unslash( (array) ( $_POST['kml_no_fill'] ?? [] ) );
    $layers  = kml_map_upload_files( $_FILES['kml_files'], $colors, $no_fill );
    if ( empty( $layers ) ) {
        wp_redirect( admin_url( 'admin.php?page=kml-maps&error=badext' ) ); exit;
    }

    $post_id = wp_insert_post( [
        'post_title'  => $title,
        'post_type'   => 'kml_map',
        'post_status' => 'publish',
    ] );

    update_post_meta( $post_id, '_kml_layers', wp_json_encode( $layers, JSON_UNESCAPED_UNICODE ) );
    update_post_meta( $post_id, '_kml_fields_available', wp_json_encode( [], JSON_UNESCAPED_UNICODE ) );
    update_post_meta( $post_id, '_kml_filter_values', wp_json_encode( [], JSON_UNESCAPED_UNICODE ) );
    update_post_meta( $post_id, '_kml_filter_values_field', '' );

    // El análisis (bounds, campos, valores de filtro) se hace en segundo
    // plano; hasta que termine, el mapa se ve en el front-end con una vista
    // de partida genérica y el filtro se completa según se cargan capas.
    kml_map_schedule_analysis( $post_id );

    wp_redirect( admin_url( 'admin.php?page=kml-maps&added=1' ) ); exit;
} );

// ---------------------------------------------------------------------------
// Acción: añadir más capas KML a un mapa existente
// ---------------------------------------------------------------------------
add_action( 'admin_post_kml_map_add_layers', function () {
    if ( ! current_user_can( 'upload_files' ) ) wp_die( 'Sin permiso.' );
    if ( ! kml_map_is_premium() ) wp_die( 'Añadir más capas a un mapa existente requiere la versión premium.' );

    $map_id = intval( $_POST['map_id'] ?? 0 );
    check_admin_referer( 'kml_map_add_layers_' . $map_id );

    if ( empty( $_FILES['kml_files']['name'][0] ) ) {
        wp_redirect( admin_url( 'admin.php?page=kml-maps&error=nofile' ) ); exit;
    }

    $colors     = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['kml_colors'] ?? [] ) ) );
    $no_fill    = wp_unslash( (array) ( $_POST['kml_no_fill'] ?? [] ) );
    $new_layers = kml_map_upload_files( $_FILES['kml_files'], $colors, $no_fill );
    if ( empty( $new_layers ) ) {
        wp_redirect( admin_url( 'admin.php?page=kml-maps&error=badext' ) ); exit;
    }

    $existing = json_decode( get_post_meta( $map_id, '_kml_layers', true ), true ) ?: [];
    $merged   = array_merge( $existing, $new_layers );
    update_post_meta( $map_id, '_kml_layers', wp_json_encode( $merged, JSON_UNESCAPED_UNICODE ) );

    // Las capas nuevas se suben sin analizar (bounds/campos/filtro); el
    // análisis se hace en segundo plano.
    kml_map_schedule_analysis( $map_id );

    wp_redirect( admin_url( 'admin.php?page=kml-maps&added_layer=1' ) ); exit;
} );

// ---------------------------------------------------------------------------
// Acción: guardar campos visibles en el popup
// ---------------------------------------------------------------------------
add_action( 'admin_post_kml_map_save_fields', function () {
    if ( ! current_user_can( 'upload_files' ) ) wp_die( 'Sin permiso.' );
    if ( ! kml_map_is_premium() ) wp_die( 'Configurar los campos del popup requiere la versión premium.' );

    $map_id = intval( $_POST['map_id'] ?? 0 );
    check_admin_referer( 'kml_map_save_fields_' . $map_id );

    $visible      = array_map( 'sanitize_text_field', wp_unslash( (array) ( $_POST['kml_visible_fields'] ?? [] ) ) );
    $filter_field = sanitize_text_field( wp_unslash( $_POST['kml_filter_field'] ?? '' ) );

    update_post_meta( $map_id, '_kml_fields_visible', wp_json_encode( $visible, JSON_UNESCAPED_UNICODE ) );
    if ( $filter_field ) {
        update_post_meta( $map_id, '_kml_filter_field', $filter_field );

        // El campo de filtrado ha cambiado: los valores cacheados eran de
        // otro campo. Se marca para reanalizar en segundo plano.
        $layers = json_decode( get_post_meta( $map_id, '_kml_layers', true ), true ) ?: [];
        kml_map_ensure_layers_queued( $map_id, $layers, $filter_field );
    }

    wp_redirect( admin_url( 'admin.php?page=kml-maps&saved_fields=1' ) ); exit;
} );

// ---------------------------------------------------------------------------
// Acción: forzar el análisis ahora mismo (por si WP-Cron no se dispara solo)
// ---------------------------------------------------------------------------
add_action( 'admin_post_kml_map_analyze_now', function () {
    if ( ! current_user_can( 'upload_files' ) ) wp_die( 'Sin permiso.' );

    $map_id = intval( $_GET['map_id'] ?? 0 );
    check_admin_referer( 'kml_map_analyze_now_' . $map_id );

    kml_map_run_analysis( $map_id );

    wp_redirect( admin_url( 'admin.php?page=kml-maps&analyzed=1' ) ); exit;
} );

// ---------------------------------------------------------------------------
// Acción: cambiar si una capa existente se pinta rellena o solo el borde
// ---------------------------------------------------------------------------
add_action( 'admin_post_kml_map_set_fill', function () {
    if ( ! current_user_can( 'upload_files' ) ) wp_die( 'Sin permiso.' );

    $map_id = intval( $_POST['map_id'] ?? 0 );
    $idx    = intval( $_POST['layer_idx'] ?? -1 );
    check_admin_referer( 'kml_map_set_fill_' . $map_id . '_' . $idx );

    $layers = json_decode( get_post_meta( $map_id, '_kml_layers', true ), true ) ?: [];

    if ( isset( $layers[ $idx ] ) ) {
        $layers[ $idx ]['fill'] = empty( $_POST['no_fill'] );
        update_post_meta( $map_id, '_kml_layers', wp_json_encode( $layers, JSON_UNESCAPED_UNICODE ) );
    }

    wp_redirect( admin_url( 'admin.php?page=kml-maps&saved_fill=1' ) ); exit;
} );

// ---------------------------------------------------------------------------
// Acción: cambiar el aspecto (colores) de la caja de filtro y el botón
// "Limpiar filtro" de un mapa. Función premium.
// ---------------------------------------------------------------------------
add_action( 'admin_post_kml_map_set_bar_style', function () {
    if ( ! current_user_can( 'upload_files' ) ) wp_die( 'Sin permiso.' );
    if ( ! kml_map_is_premium() ) wp_die( 'Personalizar el aspecto de la caja de filtro requiere la versión premium.' );

    $map_id = intval( $_POST['map_id'] ?? 0 );
    check_admin_referer( 'kml_map_set_bar_style_' . $map_id );

    $bar_style = [];
    if ( empty( $_POST['reset'] ) ) {
        foreach ( [ 'bar_bg', 'bar_text', 'btn_bg', 'btn_text' ] as $key ) {
            $value = sanitize_hex_color( wp_unslash( $_POST[ $key ] ?? '' ) );
            if ( $value ) $bar_style[ $key ] = $value;
        }
    }
    // 'reset' marcado: se guarda vacío y todo vuelve a los colores por
    // defecto de assets/css/map.css.

    update_post_meta( $map_id, '_kml_bar_style', wp_json_encode( $bar_style, JSON_UNESCAPED_UNICODE ) );

    wp_redirect( admin_url( 'admin.php?page=kml-maps&saved_bar_style=1' ) ); exit;
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
        // Borrar archivo físico y su índice espacial en caché
        $upload_dir = wp_upload_dir();
        $path = str_replace( $upload_dir['baseurl'], $upload_dir['basedir'], $layers[ $idx ]['url'] );
        if ( file_exists( $path ) ) @unlink( $path );
        kml_map_delete_dir_recursive( kml_map_tile_dir( $layers[ $idx ]['url'] ) );

        array_splice( $layers, $idx, 1 );
        update_post_meta( $map_id, '_kml_layers', wp_json_encode( array_values( $layers ), JSON_UNESCAPED_UNICODE ) );
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
        kml_map_delete_dir_recursive( kml_map_tile_dir( $layer['url'] ) );
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

    // 'bounds' y los valores del filtro se calculan y cachean al visitar el
    // admin (ver admin-page.php), nunca aquí: leer y escanear cada KML es
    // relativamente costoso, y hacerlo en el front-end en cada visita podría
    // ralentizar o incluso agotar el tiempo de ejecución de PHP en el
    // hosting. Si por lo que sea aún no hay caché, el mapa se degrada con
    // elegancia: sin bounds usa una vista de partida (ver fitAll() en el JS)
    // y sin valores de filtro este se va completando según se cargan capas.
    $fields_visible = json_decode( get_post_meta( $post_id, '_kml_fields_visible', true ), true );
    // Vacío por defecto (versión gratuita, o premium sin configurar):
    // el filtro no aparece hasta que se elige un campo en "Campos del
    // popup" (función premium).
    $filter_field   = get_post_meta( $post_id, '_kml_filter_field', true ) ?: '';

    $filter_values_field = get_post_meta( $post_id, '_kml_filter_values_field', true );
    $filter_values        = ( $filter_values_field === $filter_field )
        ? ( json_decode( get_post_meta( $post_id, '_kml_filter_values', true ), true ) ?: [] )
        : [];
    $filter_value_bounds = ( $filter_values_field === $filter_field )
        ? ( json_decode( get_post_meta( $post_id, '_kml_filter_value_bounds', true ), true ) ?: [] )
        : [];

    // Aspecto personalizado de la caja de filtro (función premium, ver
    // kml_map_set_bar_style); se aplica como variables CSS en el wrapper,
    // así que si no se ha personalizado nada el CSS usa sus colores de
    // siempre (ver assets/css/map.css).
    $bar_style      = json_decode( get_post_meta( $post_id, '_kml_bar_style', true ), true ) ?: [];
    $bar_style_vars = '';
    foreach ( [
        'bar_bg'   => '--kml-bar-bg',
        'bar_text' => '--kml-bar-text',
        'btn_bg'   => '--kml-btn-bg',
        'btn_text' => '--kml-btn-text',
    ] as $key => $css_var ) {
        if ( ! empty( $bar_style[ $key ] ) ) {
            $color = sanitize_hex_color( $bar_style[ $key ] );
            if ( $color ) $bar_style_vars .= $css_var . ':' . $color . ';';
        }
    }

    // Leaflet se sirve empaquetado con el propio plugin (no desde un CDN
    // externo): un CDN de terceros es un punto de fallo fuera de nuestro
    // control y WordPress.org no permite cargar librerías de terceros así.
    wp_enqueue_style(
        'leaflet-css',
        KML_MAP_URL . 'assets/vendor/leaflet/leaflet.css',
        [], '1.9.4'
    );
    wp_enqueue_script(
        'leaflet-js',
        KML_MAP_URL . 'assets/vendor/leaflet/leaflet.js',
        [], '1.9.4', true
    );
    wp_enqueue_style(
        'kml-map-css',
        KML_MAP_URL . 'assets/css/map.css',
        [ 'leaflet-css' ], KML_MAP_VERSION
    );
    wp_enqueue_script(
        'kml-map-js',
        KML_MAP_URL . 'assets/js/kml-map.js',
        [ 'leaflet-js' ], KML_MAP_VERSION, true
    );
    // El navegador ya no descarga los KML: pide solo los objetos visibles a
    // este endpoint (ver kml_map_rest_get_features), construido a partir del
    // índice espacial que se genera en segundo plano tras la subida.
    wp_localize_script( 'kml-map-js', 'KmlMapConfig', [
        'restUrl' => esc_url_raw( rest_url( 'kml-map/v1/features' ) ),
    ] );

    static $instance = 0;
    $instance++;
    $uid = 'kmlmap_' . $post_id . '_' . $instance;

    ob_start();
    ?>
    <div class="kml-map-wrapper" style="height:<?php echo esc_attr( $height ); ?>;<?php echo esc_attr( $bar_style_vars ); ?>">
        <div class="kml-map-canvas"
             id="<?php echo esc_attr( $uid ); ?>"
             data-kml-layers="<?php echo esc_attr( wp_json_encode( $layers, JSON_UNESCAPED_UNICODE ) ); ?>"
             data-kml-fields="<?php echo esc_attr( wp_json_encode( $fields_visible, JSON_UNESCAPED_UNICODE ) ); ?>"
             data-kml-filter-field="<?php echo esc_attr( $filter_field ); ?>"
             data-kml-filter-values="<?php echo esc_attr( wp_json_encode( $filter_values, JSON_UNESCAPED_UNICODE ) ); ?>"
             data-kml-filter-value-bounds="<?php echo esc_attr( wp_json_encode( $filter_value_bounds, JSON_UNESCAPED_UNICODE ) ); ?>">
        </div>
        <?php if ( $filter_field ) : ?>
        <div class="kml-map-bar" id="<?php echo esc_attr( $uid ); ?>-bar">
            <div class="kml-filter-group">
                <span class="kml-filter-label">Filtrar por:</span>
                <select class="kml-filter-select"
                        id="<?php echo esc_attr( $uid ); ?>-sel"
                        multiple size="4"></select>
                <button class="kml-clear-btn"
                        id="<?php echo esc_attr( $uid ); ?>-clear">
                    &#x2715; Limpiar filtro
                </button>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
} );
