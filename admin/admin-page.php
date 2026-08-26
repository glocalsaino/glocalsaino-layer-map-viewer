<?php
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
// Esta plantilla se incluye siempre dentro de una función anónima (ver el
// callback de add_menu_page en glocalsaino-layer-map-viewer.php), así que ninguna variable de
// aquí es realmente global en tiempo de ejecución: son locales a esa
// función, aunque el comprobador (que analiza el archivo de forma aislada,
// sin saber desde dónde se incluye) las marque como si lo fueran.
if ( ! defined( 'ABSPATH' ) ) exit;

$maps = get_posts( [
    'post_type'   => 'glocalsaino_map',
    'numberposts' => -1,
    'orderby'     => 'date',
    'order'       => 'DESC',
] );

// Tamaño máximo de subida (mismo límite que usa la Biblioteca de medios de
// WordPress: lo marca la configuración de PHP del hosting)
$max_upload_size = size_format( wp_max_upload_size() );

$errors = [
    'notitle' => __( 'You must enter a name for the map.', 'glocalsaino-layer-map-viewer' ),
    'nofile'  => __( 'You must select at least one KML file.', 'glocalsaino-layer-map-viewer' ),
    'badext'  => __( 'Files must have the .kml extension.', 'glocalsaino-layer-map-viewer' ),
    'toobig'  => sprintf(
        /* translators: %s: maximum upload size, already formatted (e.g. "64 MB") */
        __( 'One or more files exceed the maximum upload size allowed by your hosting (%s). Ask whoever manages the server to increase upload_max_filesize/post_max_size, or split the KML into smaller files.', 'glocalsaino-layer-map-viewer' ),
        $max_upload_size
    ),
    'corrupt' => __( "This map's layer data could not be read, so nothing was changed to avoid losing your existing layers. Please contact support.", 'glocalsaino-layer-map-viewer' ),
];

// Paleta de colores (misma que en el JS, para mostrar muestra visual)
$palette = [
    '#4daf4a', // verde
    '#4393c3', // azul
    '#f1a340', // naranja
    '#d73027', // rojo
    '#998ec3', // morado
    '#bf812d', // marrón
    '#35978f', // verde azulado
    '#e9a3c9', // rosa
];
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Layer Map Viewer', 'glocalsaino-layer-map-viewer' ); ?></h1>

    <?php
    // Los indicadores de redirección de este bloque (?added=1, ?error=..., etc.)
    // solo se leen para mostrar un aviso de éxito/error: no procesan ni guardan
    // nada (eso ya lo hace, con su propio nonce verificado, el admin_post_* que
    // redirige aquí después), así que no necesitan nonce.
    //
    // Se sacan a variables en su propia línea, con el phpcs:ignore ANTES de
    // cada una (nunca detrás): el empaquetador de Freemius, al generar la
    // versión gratuita, reengancha cualquier comentario que siga a una
    // sentencia como comentario de la SIGUIENTE sentencia (no se queda pegado
    // a la que comentaba), así que un phpcs:ignore al final de línea acaba
    // silenciando la sentencia de al lado en vez de la suya.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $kml_map_notice_added = isset( $_GET['added'] );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $kml_map_notice_added_layer = isset( $_GET['added_layer'] );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $kml_map_notice_deleted = isset( $_GET['deleted'] );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $kml_map_notice_deleted_layer = isset( $_GET['deleted_layer'] );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $kml_map_notice_saved_fields = isset( $_GET['saved_fields'] );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $kml_map_notice_analyzed = isset( $_GET['analyzed'] );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $kml_map_notice_saved_fill = isset( $_GET['saved_fill'] );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $kml_map_notice_saved_bar_style = isset( $_GET['saved_bar_style'] );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $kml_map_notice_error = isset( $_GET['error'] );
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    $kml_map_error_code = $kml_map_notice_error ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : '';
    ?>
    <?php if ( $kml_map_notice_added ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ <?php esc_html_e( 'Map created successfully.', 'glocalsaino-layer-map-viewer' ); ?></p></div>
    <?php endif; ?>
    <?php if ( $kml_map_notice_added_layer ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ <?php esc_html_e( 'Layer(s) added successfully.', 'glocalsaino-layer-map-viewer' ); ?></p></div>
    <?php endif; ?>
    <?php if ( $kml_map_notice_deleted ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ <?php esc_html_e( 'Map deleted.', 'glocalsaino-layer-map-viewer' ); ?></p></div>
    <?php endif; ?>
    <?php if ( $kml_map_notice_deleted_layer ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ <?php esc_html_e( 'Layer deleted.', 'glocalsaino-layer-map-viewer' ); ?></p></div>
    <?php endif; ?>
    <?php if ( $kml_map_notice_saved_fields ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ <?php esc_html_e( 'Field configuration saved.', 'glocalsaino-layer-map-viewer' ); ?></p></div>
    <?php endif; ?>
    <?php if ( $kml_map_notice_analyzed ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ <?php esc_html_e( 'Analysis re-triggered in the background. It may take a few minutes for layers with many objects; reload this page later to check.', 'glocalsaino-layer-map-viewer' ); ?></p></div>
    <?php endif; ?>
    <?php if ( $kml_map_notice_saved_fill ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ <?php esc_html_e( 'Fill updated.', 'glocalsaino-layer-map-viewer' ); ?></p></div>
    <?php endif; ?>
    <?php if ( $kml_map_notice_saved_bar_style ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ <?php esc_html_e( 'Filter box appearance updated.', 'glocalsaino-layer-map-viewer' ); ?></p></div>
    <?php endif; ?>
    <?php if ( $kml_map_notice_error ) : ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php
                printf(
                    /* translators: %s: error message */
                    esc_html__( 'Error: %s', 'glocalsaino-layer-map-viewer' ),
                    esc_html( $errors[ $kml_map_error_code ] ?? $kml_map_error_code )
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- ================================================================
         Formulario: crear nuevo mapa
    ================================================================ -->
    <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px 24px;max-width:640px;margin:20px 0">
        <h2 style="margin-top:0"><?php esc_html_e( 'Create new map', 'glocalsaino-layer-map-viewer' ); ?></h2>
        <form method="post"
              action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
              enctype="multipart/form-data">
            <?php wp_nonce_field( 'kml_map_add' ); ?>
            <input type="hidden" name="action" value="kml_map_add">

            <table class="form-table" role="presentation">
                <tr>
                    <th><label for="map_title"><?php esc_html_e( 'Map name', 'glocalsaino-layer-map-viewer' ); ?></label></th>
                    <td>
                        <input type="text" id="map_title" name="map_title"
                               class="regular-text"
                               placeholder="<?php echo esc_attr__( 'E.g.: Armental Plots', 'glocalsaino-layer-map-viewer' ); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th><label for="kml_files"><?php esc_html_e( 'KML files', 'glocalsaino-layer-map-viewer' ); ?></label></th>
                    <td>
                        <div class="kml-file-wrap">
                            <input type="file" class="kml-file-input" name="kml_files[]"
                                   accept=".kml" multiple>
                            <div class="kml-color-pickers"></div>
                        </div>
                        <p class="description">
                            <?php esc_html_e( 'Optional: you can create the map without any KML file and add layers later.', 'glocalsaino-layer-map-viewer' ); ?><br>
                            <?php esc_html_e( 'You can select several files at once (Ctrl+click or Cmd+click).', 'glocalsaino-layer-map-viewer' ); ?><br>
                            <?php esc_html_e( 'Choose the color for each layer before uploading; check "Outline only" so it displays without fill.', 'glocalsaino-layer-map-viewer' ); ?><br>
                            <?php
                            printf(
                                /* translators: %s: maximum upload size, already formatted (e.g. "64 MB") */
                                esc_html__( 'Maximum upload size: %s per file.', 'glocalsaino-layer-map-viewer' ),
                                '<strong>' . esc_html( $max_upload_size ) . '</strong>'
                            );
                            ?><br>
                            <?php esc_html_e( 'With very large files (tens of thousands of objects), the PHP memory limit and whether WP-Cron can run also matter — see "Frequently Asked Questions" on the plugin page.', 'glocalsaino-layer-map-viewer' ); ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Create map', 'glocalsaino-layer-map-viewer' ), 'primary', 'submit', false ); ?>
        </form>
    </div>

    <!-- ================================================================
         Listado de mapas existentes
    ================================================================ -->
    <h2 style="margin-top:30px"><?php esc_html_e( 'Available maps', 'glocalsaino-layer-map-viewer' ); ?></h2>

    <?php if ( empty( $maps ) ) : ?>
        <p style="color:#666"><?php esc_html_e( 'No maps yet. Use the form above to create the first one.', 'glocalsaino-layer-map-viewer' ); ?></p>
    <?php else : ?>
        <p style="color:#666;margin-bottom:12px">
            <?php esc_html_e( 'Paste the shortcode into any page or post.', 'glocalsaino-layer-map-viewer' ); ?>
            <?php esc_html_e( 'Optional parameters:', 'glocalsaino-layer-map-viewer' ); ?>
            <code>height</code> (<?php esc_html_e( 'px, default 550', 'glocalsaino-layer-map-viewer' ); ?>),
            <code>zoom</code> (<?php esc_html_e( 'initial zoom, default 8', 'glocalsaino-layer-map-viewer' ); ?>),
            <code>lat</code>/<code>lng</code> (<?php esc_html_e( 'initial center', 'glocalsaino-layer-map-viewer' ); ?>) —
            <?php esc_html_e( 'example:', 'glocalsaino-layer-map-viewer' ); ?>
            <code>[glocalsaino_map id="1" height="700" zoom="12" lat="40.4168" lng="-3.7038"]</code>
        </p>

        <?php foreach ( $maps as $map ) :
            // kml_map_get_layers() devuelve null (no []) si el meta no está
            // vacío pero no se puede decodificar (dato corrupto): en ese caso
            // NUNCA se llama a kml_map_ensure_layers_queued() más abajo, que
            // sí guarda de vuelta — hacerlo con un array vacío borraría de
            // golpe las capas ya existentes en vez de dejarlas intactas hasta
            // poder investigar. $layers se queda en [] solo para poder pintar
            // esta fila sin romper la plantilla; $layers_corrupt es lo que de
            // verdad decide qué se muestra y qué no se toca.
            $layers           = kml_map_get_layers( $map->ID );
            $layers_corrupt   = null === $layers;
            if ( $layers_corrupt ) $layers = [];
            $fields_available = json_decode( get_post_meta( $map->ID, '_glocalsaino_map_fields_available', true ), true ) ?: [];
            // A qué capa(s) pertenece cada campo, para mostrarlo junto al
            // nombre (ver más abajo); puede venir vacío en mapas analizados
            // antes de esta función, hasta que se reanalicen.
            $fields_by_layer  = json_decode( get_post_meta( $map->ID, '_glocalsaino_map_fields_by_layer', true ), true ) ?: [];
            $filter_field     = get_post_meta( $map->ID, '_glocalsaino_map_filter_field', true ) ?: '';

            // Si a este mapa le falta el análisis de alguna capa (recién
            // subida, o migrada de antes de esta mejora) o los valores de
            // filtro son de otro campo, se marca para analizar en segundo
            // plano (WP-Cron); esto es barato, solo guarda un estado y
            // programa el evento, nunca escanea los KML aquí en el admin.
            if ( ! $layers_corrupt ) {
                $layers = kml_map_ensure_layers_queued( $map->ID, $layers, $filter_field );
            }

            $pending = false;
            foreach ( $layers as $l ) {
                if ( empty( $l['analyzed'] ) ) { $pending = true; break; }
            }

            $fields_visible = json_decode( get_post_meta( $map->ID, '_glocalsaino_map_fields_visible', true ), true );
            $bar_style      = json_decode( get_post_meta( $map->ID, '_glocalsaino_map_bar_style', true ), true ) ?: [];
        ?>
        <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;margin-bottom:18px;max-width:860px">

            <!-- Cabecera del mapa -->
            <div style="display:flex;align-items:center;justify-content:space-between;
                        padding:14px 20px;border-bottom:1px solid #e0e0e0;flex-wrap:wrap;gap:10px">
                <div>
                    <strong style="font-size:15px"><?php echo esc_html( $map->post_title ); ?></strong>
                    <span style="color:#999;font-size:12px;margin-left:10px">
                        <?php echo esc_html( get_the_date( 'd/m/Y', $map ) ); ?>
                    </span>
                    <?php if ( $layers_corrupt ) : ?>
                        <span style="background:#fbeaea;color:#8a2424;font-size:11px;font-weight:600;
                                     padding:2px 8px;border-radius:10px;margin-left:8px;white-space:nowrap">
                            ⚠️ <?php esc_html_e( "This map's layer data could not be read — contact support before making changes.", 'glocalsaino-layer-map-viewer' ); ?>
                        </span>
                    <?php elseif ( $pending ) : ?>
                        <span style="background:#fcf0cd;color:#7a5b00;font-size:11px;font-weight:600;
                                     padding:2px 8px;border-radius:10px;margin-left:8px;white-space:nowrap">
                            ⏳ <?php esc_html_e( 'Processing layers in the background…', 'glocalsaino-layer-map-viewer' ); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <code style="background:#f0f0f1;padding:4px 8px;border-radius:3px">
                        [glocalsaino_map id="<?php echo (int) $map->ID; ?>"]
                    </code>
                    <button type="button" class="button button-small"
                            onclick="
                                navigator.clipboard.writeText('[glocalsaino_map id=&quot;<?php echo (int) $map->ID; ?>&quot;]');
                                this.textContent='<?php echo esc_js( __( 'Copied!', 'glocalsaino-layer-map-viewer' ) ); ?>';
                                var b=this; setTimeout(function(){b.textContent='<?php echo esc_js( __( 'Copy shortcode', 'glocalsaino-layer-map-viewer' ) ); ?>';},2000);
                            "><?php esc_html_e( 'Copy shortcode', 'glocalsaino-layer-map-viewer' ); ?></button>
                    <?php if ( $pending ) : ?>
                        <a href="<?php echo esc_url( wp_nonce_url(
                            admin_url( 'admin-post.php?action=kml_map_analyze_now&map_id=' . $map->ID ),
                            'kml_map_analyze_now_' . $map->ID
                        ) ); ?>"
                           class="button button-small"
                           title="<?php echo esc_attr__( "Forces the analysis right now, in case the background process (WP-Cron) hasn't triggered on its own. May take a while if the KML is very large.", 'glocalsaino-layer-map-viewer' ); ?>">
                            <?php esc_html_e( 'Analyze now', 'glocalsaino-layer-map-viewer' ); ?>
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( wp_nonce_url(
                        admin_url( 'admin-post.php?action=kml_map_delete&id=' . $map->ID ),
                        'kml_map_delete_' . $map->ID
                    ) ); ?>"
                       class="button button-small"
                       style="color:#b32d2e;border-color:#b32d2e"
                       onclick="return confirm('<?php echo esc_js( sprintf(
                           /* translators: %s: map name */
                           __( 'Delete the map "%s" and all its layers?', 'glocalsaino-layer-map-viewer' ),
                           $map->post_title
                       ) ); ?>')">
                        <?php esc_html_e( 'Delete map', 'glocalsaino-layer-map-viewer' ); ?>
                    </a>
                </div>
            </div>

            <!-- Capas KML del mapa -->
            <div style="padding:14px 20px">
                <p style="margin:0 0 10px;font-weight:600;color:#444">
                    <?php
                    printf(
                        /* translators: %d: number of layers in the map */
                        esc_html__( 'KML layers (%d):', 'glocalsaino-layer-map-viewer' ),
                        (int) count( $layers )
                    );
                    ?>
                </p>

                <?php if ( empty( $layers ) ) : ?>
                    <p style="color:#999;font-style:italic"><?php esc_html_e( 'No layers. Add KML files below.', 'glocalsaino-layer-map-viewer' ); ?></p>
                <?php else : ?>
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="border-bottom:1px solid #e0e0e0;text-align:left;font-size:12px;color:#777">
                                <th style="padding:4px 8px;width:24px">#</th>
                                <th style="padding:4px 8px;width:18px"><?php esc_html_e( 'Color', 'glocalsaino-layer-map-viewer' ); ?></th>
                                <th style="padding:4px 8px"><?php esc_html_e( 'Layer name', 'glocalsaino-layer-map-viewer' ); ?></th>
                                <th style="padding:4px 8px"><?php esc_html_e( 'File', 'glocalsaino-layer-map-viewer' ); ?></th>
                                <th style="padding:4px 8px;width:220px"><?php esc_html_e( 'Fill', 'glocalsaino-layer-map-viewer' ); ?></th>
                                <th style="padding:4px 8px;width:90px"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $layers as $idx => $layer ) :
                            $color   = ! empty( $layer['color'] )
                                ? $layer['color']
                                : $palette[ $idx % count( $palette ) ];
                            $fill    = isset( $layer['fill'] ) ? (bool) $layer['fill'] : true;
                            // Ausente (capas creadas antes de esta opción): mismo 60%
                            // que se usaba fijo hasta ahora.
                            $opacity_pct = isset( $layer['opacity'] ) ? (int) round( $layer['opacity'] * 100 ) : 60;
                        ?>
                            <tr style="border-bottom:1px solid #f0f0f0">
                                <td style="padding:6px 8px;color:#999;font-size:12px"><?php echo (int) ( $idx + 1 ); ?></td>
                                <td style="padding:6px 8px">
                                    <span style="display:inline-block;width:16px;height:16px;
                                                 background:<?php echo $fill ? esc_attr( $color ) : 'transparent'; ?>;
                                                 border-radius:3px;border:2px solid <?php echo esc_attr( $color ); ?>"></span>
                                </td>
                                <td style="padding:6px 8px;font-weight:500">
                                    <?php echo esc_html( $layer['name'] ); ?>
                                    <?php if ( empty( $layer['analyzed'] ) ) : ?>
                                        <span style="color:#b26b00;font-size:11px;font-weight:normal;white-space:nowrap">
                                            ⏳ <?php esc_html_e( 'processing', 'glocalsaino-layer-map-viewer' ); ?>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td style="padding:6px 8px;font-size:12px;color:#666">
                                    <?php echo esc_html( basename( $layer['url'] ) ); ?>
                                </td>
                                <td style="padding:6px 8px">
                                    <form method="post"
                                          action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                                          style="display:flex;align-items:center;gap:4px">
                                        <?php wp_nonce_field( 'kml_map_set_fill_' . $map->ID . '_' . $idx ); ?>
                                        <input type="hidden" name="action" value="kml_map_set_fill">
                                        <input type="hidden" name="map_id" value="<?php echo (int) $map->ID; ?>">
                                        <input type="hidden" name="layer_idx" value="<?php echo (int) $idx; ?>">
                                        <label style="font-size:12px;display:flex;align-items:center;gap:4px;cursor:pointer;white-space:nowrap">
                                            <input type="checkbox" name="no_fill" value="1" <?php checked( ! $fill ); ?>>
                                            <?php esc_html_e( 'Outline only', 'glocalsaino-layer-map-viewer' ); ?>
                                        </label>
                                        <label style="font-size:12px;display:flex;align-items:center;gap:4px;white-space:nowrap"
                                               title="<?php echo esc_attr__( 'Fill transparency', 'glocalsaino-layer-map-viewer' ); ?>">
                                            <input type="range" name="opacity" min="0" max="100" step="5"
                                                   value="<?php echo (int) $opacity_pct; ?>" style="width:60px">
                                        </label>
                                        <button type="submit" class="button button-small"><?php esc_html_e( 'OK', 'glocalsaino-layer-map-viewer' ); ?></button>
                                    </form>
                                </td>
                                <td style="padding:6px 8px">
                                    <a href="<?php echo esc_url( wp_nonce_url(
                                        admin_url( 'admin-post.php?action=kml_map_del_layer&map_id=' . $map->ID . '&layer_idx=' . $idx ),
                                        'kml_map_del_layer_' . $map->ID . '_' . $idx
                                    ) ); ?>"
                                       class="button button-small"
                                       style="color:#b32d2e;border-color:#b32d2e"
                                       onclick="return confirm('<?php echo esc_js( sprintf(
                                           /* translators: %s: layer name */
                                           __( 'Delete the layer "%s"?', 'glocalsaino-layer-map-viewer' ),
                                           $layer['name']
                                       ) ); ?>')">
                                        <?php esc_html_e( 'Delete layer', 'glocalsaino-layer-map-viewer' ); ?>
                                    </a>
                                </td>
                            </tr>
                            <?php
                            // Punto de extensión: permite a un add-on (p.ej.
                            // una capa alimentada por una fuente de datos
                            // externa vía URL, con su propio período de
                            // actualización) añadir una fila extra bajo esta
                            // con información/controles propios de ese tipo
                            // de capa, sin tener que reescribir esta tabla.
                            do_action( 'glocalsaino_map_layer_row_after', $layer, $idx, $map );
                            ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- Formulario para añadir más capas. -->
                <details style="margin-top:14px">
                    <summary style="cursor:pointer;color:#2271b1;font-size:13px;font-weight:500;
                                    list-style:none;display:inline-flex;align-items:center;gap:5px">
                        <span style="font-size:18px;line-height:1">+</span> <?php esc_html_e( 'Add more KML layers', 'glocalsaino-layer-map-viewer' ); ?>
                    </summary>
                    <div style="margin-top:10px;padding:14px;background:#f6f7f7;
                                border-radius:4px;border:1px solid #ddd">
                        <form method="post"
                              action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                              enctype="multipart/form-data">
                            <?php wp_nonce_field( 'kml_map_add_layers_' . $map->ID ); ?>
                            <input type="hidden" name="action" value="kml_map_add_layers">
                            <input type="hidden" name="map_id" value="<?php echo (int) $map->ID; ?>">
                            <div class="kml-file-wrap">
                                <input type="file" class="kml-file-input" name="kml_files[]"
                                       accept=".kml" multiple required>
                                <div class="kml-color-pickers"></div>
                            </div>
                            <p class="description" style="margin:6px 0 10px">
                                <?php esc_html_e( 'You can select several files. Choose the color for each layer before uploading; check "Outline only" so it displays without fill.', 'glocalsaino-layer-map-viewer' ); ?><br>
                                <?php
                                printf(
                                    /* translators: %s: maximum upload size, already formatted (e.g. "64 MB") */
                                    esc_html__( 'Maximum upload size: %s per file.', 'glocalsaino-layer-map-viewer' ),
                                    '<strong>' . esc_html( $max_upload_size ) . '</strong>'
                                );
                                ?>
                            </p>
                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Add layers', 'glocalsaino-layer-map-viewer' ); ?></button>
                        </form>
                    </div>
                </details>

                <?php
                // Punto de extensión para add-ons: permite añadir aquí su
                // propio bloque "Añadir capa de [otro tipo]" (por ejemplo,
                // una fuente de datos externa vía URL), con el mismo estilo
                // visual que el bloque de arriba, sin tocar este archivo.
                do_action( 'glocalsaino_map_after_add_layers_form', $map );
                ?>

                <!-- Configuración de campos del popup. -->
                <?php if ( ! empty( $fields_available ) ) : ?>
                        <details style="margin-top:10px">
                            <summary style="cursor:pointer;color:#2271b1;font-size:13px;font-weight:500;
                                            list-style:none;display:inline-flex;align-items:center;gap:5px">
                                <span style="font-size:16px;line-height:1">⚙</span> <?php esc_html_e( 'Popup fields', 'glocalsaino-layer-map-viewer' ); ?>
                            </summary>
                            <div style="margin-top:10px;padding:14px;background:#f6f7f7;
                                        border-radius:4px;border:1px solid #ddd">
                                <p style="margin:0 0 10px;font-size:13px;color:#555">
                                    <?php esc_html_e( 'Select the fields that will be shown when clicking a feature, and the field to filter by:', 'glocalsaino-layer-map-viewer' ); ?>
                                </p>
                                <form method="post"
                                      action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                    <?php wp_nonce_field( 'kml_map_save_fields_' . $map->ID ); ?>
                                    <input type="hidden" name="action" value="kml_map_save_fields">
                                    <input type="hidden" name="map_id" value="<?php echo (int) $map->ID; ?>">

                                <div style="margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid #ddd">
                                    <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px">
                                        <?php esc_html_e( 'Filter field:', 'glocalsaino-layer-map-viewer' ); ?>
                                    </label>
                                    <select name="kml_filter_field"
                                            style="font-size:13px;padding:4px 6px;border:1px solid #b0b4bb;border-radius:4px;min-width:180px">
                                        <option value="" <?php selected( $filter_field, '' ); ?>>
                                            <?php esc_html_e( '— No filter —', 'glocalsaino-layer-map-viewer' ); ?>
                                        </option>
                                        <?php foreach ( $fields_available as $field ) :
                                            $field_layers = $fields_by_layer[ $field ] ?? [];
                                            $option_label = $field_layers
                                                ? $field . ' (' . implode( ', ', $field_layers ) . ')'
                                                : $field;
                                        ?>
                                            <option value="<?php echo esc_attr( $field ); ?>"
                                                    <?php selected( $filter_field, $field ); ?>>
                                                <?php echo esc_html( $option_label ); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <p class="description" style="margin-top:4px">
                                        <?php esc_html_e( "The selector in the map's bottom bar will use this field.", 'glocalsaino-layer-map-viewer' ); ?>
                                    </p>
                                </div>
                                <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px">
                                    <?php esc_html_e( 'Fields visible in the popup:', 'glocalsaino-layer-map-viewer' ); ?>
                                </label>
                                <p class="description" style="margin:-4px 0 8px">
                                    <?php esc_html_e( 'In parentheses, the layer(s) where each field appears — useful if several layers have a field with the same name but a different meaning.', 'glocalsaino-layer-map-viewer' ); ?>
                                </p>

                                    <div style="display:flex;flex-wrap:wrap;gap:6px 20px;margin-bottom:12px">
                                        <?php foreach ( $fields_available as $field ) :
                                            // Si $fields_visible es null (sin configurar), todos visibles por defecto
                                            $checked      = ( $fields_visible === null || in_array( $field, $fields_visible ) );
                                            $field_layers = $fields_by_layer[ $field ] ?? [];
                                        ?>
                                        <label style="font-size:13px;display:flex;align-items:center;gap:5px;cursor:pointer">
                                            <input type="checkbox"
                                                   name="kml_visible_fields[]"
                                                   value="<?php echo esc_attr( $field ); ?>"
                                                   <?php checked( $checked ); ?>>
                                            <?php echo esc_html( $field ); ?>
                                            <?php if ( $field_layers ) : ?>
                                                <span style="color:#888;font-size:11px">(<?php echo esc_html( implode( ', ', $field_layers ) ); ?>)</span>
                                            <?php endif; ?>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="submit" class="button button-primary button-small">
                                        <?php esc_html_e( 'Save selection', 'glocalsaino-layer-map-viewer' ); ?>
                                    </button>
                                </form>
                            </div>
                        </details>
                <?php endif; ?>

                <!-- Aspecto de la caja de filtro. -->
                    <details style="margin-top:10px">
                        <summary style="cursor:pointer;color:#2271b1;font-size:13px;font-weight:500;
                                        list-style:none;display:inline-flex;align-items:center;gap:5px">
                            <span style="font-size:16px;line-height:1">🎨</span> <?php esc_html_e( 'Filter box appearance', 'glocalsaino-layer-map-viewer' ); ?>
                        </summary>
                        <div style="margin-top:10px;padding:14px;background:#f6f7f7;
                                    border-radius:4px;border:1px solid #ddd">
                            <p style="margin:0 0 10px;font-size:13px;color:#555">
                                <?php esc_html_e( 'Colors for the filter bar and the "Clear filter" button shown below the map.', 'glocalsaino-layer-map-viewer' ); ?>
                            </p>
                            <form method="post"
                                  action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <?php wp_nonce_field( 'kml_map_set_bar_style_' . $map->ID ); ?>
                                <input type="hidden" name="action" value="kml_map_set_bar_style">
                                <input type="hidden" name="map_id" value="<?php echo (int) $map->ID; ?>">

                                <div style="display:flex;flex-wrap:wrap;gap:16px 28px;margin-bottom:14px">
                                    <label style="font-size:13px;display:flex;flex-direction:column;gap:4px">
                                        <?php esc_html_e( 'Bar background', 'glocalsaino-layer-map-viewer' ); ?>
                                        <input type="color" name="bar_bg"
                                               value="<?php echo esc_attr( $bar_style['bar_bg'] ?? '#f0f2f5' ); ?>"
                                               style="width:48px;height:30px;border:1px solid #ccc;border-radius:3px;cursor:pointer">
                                    </label>
                                    <label style="font-size:13px;display:flex;flex-direction:column;gap:4px">
                                        <?php esc_html_e( 'Bar text', 'glocalsaino-layer-map-viewer' ); ?>
                                        <input type="color" name="bar_text"
                                               value="<?php echo esc_attr( $bar_style['bar_text'] ?? '#333333' ); ?>"
                                               style="width:48px;height:30px;border:1px solid #ccc;border-radius:3px;cursor:pointer">
                                    </label>
                                    <label style="font-size:13px;display:flex;flex-direction:column;gap:4px">
                                        <?php esc_html_e( 'Button background', 'glocalsaino-layer-map-viewer' ); ?>
                                        <input type="color" name="btn_bg"
                                               value="<?php echo esc_attr( $bar_style['btn_bg'] ?? '#c0392b' ); ?>"
                                               style="width:48px;height:30px;border:1px solid #ccc;border-radius:3px;cursor:pointer">
                                    </label>
                                    <label style="font-size:13px;display:flex;flex-direction:column;gap:4px">
                                        <?php esc_html_e( 'Button text', 'glocalsaino-layer-map-viewer' ); ?>
                                        <input type="color" name="btn_text"
                                               value="<?php echo esc_attr( $bar_style['btn_text'] ?? '#ffffff' ); ?>"
                                               style="width:48px;height:30px;border:1px solid #ccc;border-radius:3px;cursor:pointer">
                                    </label>
                                </div>
                                <label style="font-size:13px;display:flex;align-items:center;gap:5px;cursor:pointer;margin-bottom:10px">
                                    <input type="checkbox" name="reset" value="1">
                                    <?php esc_html_e( 'Reset to default colors', 'glocalsaino-layer-map-viewer' ); ?>
                                </label>
                                <button type="submit" class="button button-primary button-small">
                                    <?php esc_html_e( 'Save appearance', 'glocalsaino-layer-map-viewer' ); ?>
                                </button>
                            </form>
                        </div>
                    </details>

            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
<?php
// El JS de esta pantalla (assets/js/admin-page.js) se encola aparte, vía
// wp_enqueue_script en el callback de add_menu_page: WordPress.org no
// permite <script> sueltos en el HTML, hay que usar las funciones de
// encolado nativas.
?>
