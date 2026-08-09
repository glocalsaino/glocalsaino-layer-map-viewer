<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$maps = get_posts( [
    'post_type'   => 'kml_map',
    'numberposts' => -1,
    'orderby'     => 'date',
    'order'       => 'DESC',
] );

$errors = [
    'notitle'  => __( 'Debes introducir un nombre para el mapa.', 'kml-map' ),
    'nofile'   => __( 'Debes seleccionar al menos un archivo KML.', 'kml-map' ),
    'badext'   => __( 'Los archivos deben tener extensión .kml.', 'kml-map' ),
    'maplimit' => sprintf(
        /* translators: %d: número máximo de mapas de la versión gratuita */
        __( 'La versión gratuita permite crear hasta %d mapas. Activa la versión premium para crear mapas ilimitados.', 'kml-map' ),
        KML_MAP_FREE_MAP_LIMIT
    ),
];

// Tamaño máximo de subida (mismo límite que usa la Biblioteca de medios de
// WordPress: lo marca la configuración de PHP del hosting)
$max_upload_size = size_format( wp_max_upload_size() );

// Funciones premium (ver kml_map_is_premium() en wp-kml-map.php): mapas
// ilimitados, añadir más capas a un mapa ya creado, configurar los campos
// del popup y personalizar el aspecto de la caja de filtro. La gratuita
// permite crear hasta KML_MAP_FREE_MAP_LIMIT mapas, cada uno con varias
// capas KML a la vez.
$kml_map_is_premium    = kml_map_is_premium();
$kml_map_maps_count    = wp_count_posts( 'kml_map' )->publish;
$kml_map_limit_reached = ( ! $kml_map_is_premium && $kml_map_maps_count >= KML_MAP_FREE_MAP_LIMIT );

// Aviso reutilizable para las funciones premium de esta página.
$kml_map_premium_notice = function ( $title, $description ) {
    ?>
    <div style="margin-top:14px;padding:14px;background:#fbf7ec;border:1px solid #e9dcb0;border-radius:4px;
                display:flex;align-items:flex-start;gap:10px">
        <span style="font-size:16px;line-height:1.3">🔒</span>
        <div>
            <p style="margin:0 0 6px;font-size:13px;font-weight:600;color:#6b5a1e">
                <?php
                printf(
                    /* translators: %s: nombre de la función premium */
                    esc_html__( '%s — función premium', 'kml-map' ),
                    esc_html( $title )
                );
                ?>
            </p>
            <p style="margin:0 0 10px;font-size:13px;color:#7a6a30">
                <?php echo esc_html( $description ); ?>
            </p>
            <a href="<?php echo esc_url( kml_map_fs()->get_upgrade_url() ); ?>"
               class="button button-primary button-small">
                <?php esc_html_e( 'Ver planes premium', 'kml-map' ); ?>
            </a>
        </div>
    </div>
    <?php
};

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
    <h1><?php esc_html_e( 'Mapas KML', 'kml-map' ); ?></h1>

    <?php if ( isset( $_GET['added'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ <?php esc_html_e( 'Mapa creado correctamente.', 'kml-map' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['added_layer'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ <?php esc_html_e( 'Capa(s) añadida(s) correctamente.', 'kml-map' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['deleted'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ <?php esc_html_e( 'Mapa eliminado.', 'kml-map' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['deleted_layer'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ <?php esc_html_e( 'Capa eliminada.', 'kml-map' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['saved_fields'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ <?php esc_html_e( 'Configuración de campos guardada.', 'kml-map' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['analyzed'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ <?php esc_html_e( 'Análisis completado.', 'kml-map' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['saved_fill'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ <?php esc_html_e( 'Relleno actualizado.', 'kml-map' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['saved_bar_style'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ <?php esc_html_e( 'Aspecto de la caja de filtro actualizado.', 'kml-map' ); ?></p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['error'] ) ) :
        $kml_map_error_code = sanitize_text_field( wp_unslash( $_GET['error'] ) );
    ?>
        <div class="notice notice-error is-dismissible">
            <p>
                <?php
                printf(
                    /* translators: %s: mensaje de error */
                    esc_html__( 'Error: %s', 'kml-map' ),
                    esc_html( $errors[ $kml_map_error_code ] ?? $kml_map_error_code )
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- ================================================================
         Formulario: crear nuevo mapa (limitado a KML_MAP_FREE_MAP_LIMIT
         mapas en la versión gratuita)
    ================================================================ -->
    <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px 24px;max-width:640px;margin:20px 0">
        <h2 style="margin-top:0"><?php esc_html_e( 'Crear nuevo mapa', 'kml-map' ); ?></h2>
        <?php if ( $kml_map_limit_reached ) : ?>
            <?php $kml_map_premium_notice(
                __( 'Límite de mapas alcanzado', 'kml-map' ),
                sprintf(
                    /* translators: 1: número máximo de mapas gratuitos, 2: mapas que tiene ahora mismo */
                    __( 'La versión gratuita permite crear hasta %1$d mapas (tienes %2$d). Activa la versión premium para crear mapas ilimitados.', 'kml-map' ),
                    KML_MAP_FREE_MAP_LIMIT,
                    (int) $kml_map_maps_count
                )
            ); ?>
        <?php else : ?>
        <form method="post"
              action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
              enctype="multipart/form-data">
            <?php wp_nonce_field( 'kml_map_add' ); ?>
            <input type="hidden" name="action" value="kml_map_add">

            <table class="form-table" role="presentation">
                <tr>
                    <th><label for="map_title"><?php esc_html_e( 'Nombre del mapa', 'kml-map' ); ?></label></th>
                    <td>
                        <input type="text" id="map_title" name="map_title"
                               class="regular-text"
                               placeholder="<?php echo esc_attr__( 'Ej: Parcelas Armental', 'kml-map' ); ?>" required>
                    </td>
                </tr>
                <tr>
                    <th><label for="kml_files"><?php esc_html_e( 'Archivos KML', 'kml-map' ); ?></label></th>
                    <td>
                        <div class="kml-file-wrap">
                            <input type="file" class="kml-file-input" name="kml_files[]"
                                   accept=".kml" multiple required>
                            <div class="kml-color-pickers"></div>
                        </div>
                        <p class="description">
                            <?php esc_html_e( 'Puedes seleccionar varios archivos a la vez (Ctrl+clic o Cmd+clic).', 'kml-map' ); ?><br>
                            <?php esc_html_e( 'Elige el color de cada capa antes de subir; marca "Solo borde" para que se vea sin relleno.', 'kml-map' ); ?><br>
                            <?php
                            printf(
                                /* translators: %s: tamaño máximo de subida, ya formateado (p.ej. "64 MB") */
                                esc_html__( 'Tamaño máximo de subida: %s por archivo.', 'kml-map' ),
                                '<strong>' . esc_html( $max_upload_size ) . '</strong>'
                            );
                            ?>
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button( __( 'Crear mapa', 'kml-map' ), 'primary', 'submit', false ); ?>
        </form>
        <?php endif; ?>
    </div>

    <!-- ================================================================
         Listado de mapas existentes
    ================================================================ -->
    <h2 style="margin-top:30px"><?php esc_html_e( 'Mapas disponibles', 'kml-map' ); ?></h2>

    <?php if ( empty( $maps ) ) : ?>
        <p style="color:#666"><?php esc_html_e( 'Todavía no hay mapas. Usa el formulario de arriba para crear el primero.', 'kml-map' ); ?></p>
    <?php else : ?>
        <p style="color:#666;margin-bottom:12px">
            <?php esc_html_e( 'Pega el shortcode en cualquier página o entrada.', 'kml-map' ); ?>
            <?php esc_html_e( 'Puedes personalizar la altura:', 'kml-map' ); ?> <code>[kml_map id="1" height="700px"]</code>
        </p>

        <?php foreach ( $maps as $map ) :
            $layers           = json_decode( get_post_meta( $map->ID, '_kml_layers', true ), true ) ?: [];
            $fields_available = json_decode( get_post_meta( $map->ID, '_kml_fields_available', true ), true ) ?: [];
            $filter_field     = get_post_meta( $map->ID, '_kml_filter_field', true ) ?: '';

            // Si a este mapa le falta el análisis de alguna capa (recién
            // subida, o migrada de antes de esta mejora) o los valores de
            // filtro son de otro campo, se marca para analizar en segundo
            // plano (WP-Cron); esto es barato, solo guarda un estado y
            // programa el evento, nunca escanea los KML aquí en el admin.
            $layers = kml_map_ensure_layers_queued( $map->ID, $layers, $filter_field );

            $pending = false;
            foreach ( $layers as $l ) {
                if ( empty( $l['analyzed'] ) ) { $pending = true; break; }
            }

            $fields_visible = json_decode( get_post_meta( $map->ID, '_kml_fields_visible', true ), true );
            $bar_style      = json_decode( get_post_meta( $map->ID, '_kml_bar_style', true ), true ) ?: [];
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
                    <?php if ( $pending ) : ?>
                        <span style="background:#fcf0cd;color:#7a5b00;font-size:11px;font-weight:600;
                                     padding:2px 8px;border-radius:10px;margin-left:8px;white-space:nowrap">
                            ⏳ <?php esc_html_e( 'Procesando capas en segundo plano…', 'kml-map' ); ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <code style="background:#f0f0f1;padding:4px 8px;border-radius:3px">
                        [kml_map id="<?php echo (int) $map->ID; ?>"]
                    </code>
                    <button type="button" class="button button-small"
                            onclick="
                                navigator.clipboard.writeText('[kml_map id=&quot;<?php echo (int) $map->ID; ?>&quot;]');
                                this.textContent='<?php echo esc_js( __( '¡Copiado!', 'kml-map' ) ); ?>';
                                var b=this; setTimeout(function(){b.textContent='<?php echo esc_js( __( 'Copiar shortcode', 'kml-map' ) ); ?>';},2000);
                            "><?php esc_html_e( 'Copiar shortcode', 'kml-map' ); ?></button>
                    <?php if ( $pending ) : ?>
                        <a href="<?php echo esc_url( wp_nonce_url(
                            admin_url( 'admin-post.php?action=kml_map_analyze_now&map_id=' . $map->ID ),
                            'kml_map_analyze_now_' . $map->ID
                        ) ); ?>"
                           class="button button-small"
                           title="<?php echo esc_attr__( 'Fuerza el análisis ahora mismo, por si el proceso en segundo plano (WP-Cron) no se ha disparado solo. Puede tardar si el KML es muy grande.', 'kml-map' ); ?>">
                            <?php esc_html_e( 'Analizar ahora', 'kml-map' ); ?>
                        </a>
                    <?php endif; ?>
                    <a href="<?php echo esc_url( wp_nonce_url(
                        admin_url( 'admin-post.php?action=kml_map_delete&id=' . $map->ID ),
                        'kml_map_delete_' . $map->ID
                    ) ); ?>"
                       class="button button-small"
                       style="color:#b32d2e;border-color:#b32d2e"
                       onclick="return confirm('<?php echo esc_js( sprintf(
                           /* translators: %s: nombre del mapa */
                           __( '¿Eliminar el mapa «%s» y todas sus capas?', 'kml-map' ),
                           $map->post_title
                       ) ); ?>')">
                        <?php esc_html_e( 'Eliminar mapa', 'kml-map' ); ?>
                    </a>
                </div>
            </div>

            <!-- Capas KML del mapa -->
            <div style="padding:14px 20px">
                <p style="margin:0 0 10px;font-weight:600;color:#444">
                    <?php
                    printf(
                        /* translators: %d: número de capas del mapa */
                        esc_html__( 'Capas KML (%d):', 'kml-map' ),
                        (int) count( $layers )
                    );
                    ?>
                </p>

                <?php if ( empty( $layers ) ) : ?>
                    <p style="color:#999;font-style:italic"><?php esc_html_e( 'Sin capas. Añade archivos KML abajo.', 'kml-map' ); ?></p>
                <?php else : ?>
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="border-bottom:1px solid #e0e0e0;text-align:left;font-size:12px;color:#777">
                                <th style="padding:4px 8px;width:24px">#</th>
                                <th style="padding:4px 8px;width:18px"><?php esc_html_e( 'Color', 'kml-map' ); ?></th>
                                <th style="padding:4px 8px"><?php esc_html_e( 'Nombre de capa', 'kml-map' ); ?></th>
                                <th style="padding:4px 8px"><?php esc_html_e( 'Archivo', 'kml-map' ); ?></th>
                                <th style="padding:4px 8px;width:150px"><?php esc_html_e( 'Relleno', 'kml-map' ); ?></th>
                                <th style="padding:4px 8px;width:90px"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $layers as $idx => $layer ) :
                            $color = ! empty( $layer['color'] )
                                ? $layer['color']
                                : $palette[ $idx % count( $palette ) ];
                            $fill  = isset( $layer['fill'] ) ? (bool) $layer['fill'] : true;
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
                                            ⏳ <?php esc_html_e( 'procesando', 'kml-map' ); ?>
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
                                            <?php esc_html_e( 'Solo borde', 'kml-map' ); ?>
                                        </label>
                                        <button type="submit" class="button button-small"><?php esc_html_e( 'OK', 'kml-map' ); ?></button>
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
                                           /* translators: %s: nombre de la capa */
                                           __( '¿Eliminar la capa «%s»?', 'kml-map' ),
                                           $layer['name']
                                       ) ); ?>')">
                                        <?php esc_html_e( 'Eliminar capa', 'kml-map' ); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- Formulario para añadir más capas (premium) -->
                <?php if ( $kml_map_is_premium ) : ?>
                <details style="margin-top:14px">
                    <summary style="cursor:pointer;color:#2271b1;font-size:13px;font-weight:500;
                                    list-style:none;display:inline-flex;align-items:center;gap:5px">
                        <span style="font-size:18px;line-height:1">+</span> <?php esc_html_e( 'Añadir más capas KML', 'kml-map' ); ?>
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
                                <?php esc_html_e( 'Puedes seleccionar varios archivos. Elige el color de cada capa antes de subir; marca "Solo borde" para que se vea sin relleno.', 'kml-map' ); ?><br>
                                <?php
                                printf(
                                    /* translators: %s: tamaño máximo de subida, ya formateado (p.ej. "64 MB") */
                                    esc_html__( 'Tamaño máximo de subida: %s por archivo.', 'kml-map' ),
                                    '<strong>' . esc_html( $max_upload_size ) . '</strong>'
                                );
                                ?>
                            </p>
                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Añadir capas', 'kml-map' ); ?></button>
                        </form>
                    </div>
                </details>
                <?php else : ?>
                    <?php $kml_map_premium_notice(
                        __( 'Añadir más capas KML', 'kml-map' ),
                        __( 'Con la versión gratuita puedes subir varias capas a la vez al crear el mapa. Para añadir más capas a un mapa ya creado, activa la versión premium.', 'kml-map' )
                    ); ?>
                <?php endif; ?>

                <!-- Configuración de campos del popup (premium) -->
                <?php if ( ! empty( $fields_available ) ) : ?>
                    <?php if ( $kml_map_is_premium ) : ?>
                    <details style="margin-top:10px">
                        <summary style="cursor:pointer;color:#2271b1;font-size:13px;font-weight:500;
                                        list-style:none;display:inline-flex;align-items:center;gap:5px">
                            <span style="font-size:16px;line-height:1">⚙</span> <?php esc_html_e( 'Campos del popup', 'kml-map' ); ?>
                        </summary>
                        <div style="margin-top:10px;padding:14px;background:#f6f7f7;
                                    border-radius:4px;border:1px solid #ddd">
                            <p style="margin:0 0 10px;font-size:13px;color:#555">
                                <?php esc_html_e( 'Selecciona los campos que se mostrarán al hacer clic en una finca y el campo por el que se filtrará:', 'kml-map' ); ?>
                            </p>
                            <form method="post"
                                  action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                                <?php wp_nonce_field( 'kml_map_save_fields_' . $map->ID ); ?>
                                <input type="hidden" name="action" value="kml_map_save_fields">
                                <input type="hidden" name="map_id" value="<?php echo (int) $map->ID; ?>">

                            <div style="margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid #ddd">
                                <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px">
                                    <?php esc_html_e( 'Campo de filtrado:', 'kml-map' ); ?>
                                </label>
                                <select name="kml_filter_field"
                                        style="font-size:13px;padding:4px 6px;border:1px solid #b0b4bb;border-radius:4px;min-width:180px">
                                    <?php foreach ( $fields_available as $field ) : ?>
                                        <option value="<?php echo esc_attr( $field ); ?>"
                                                <?php selected( $filter_field, $field ); ?>>
                                            <?php echo esc_html( $field ); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description" style="margin-top:4px">
                                    <?php esc_html_e( 'El selector de la barra inferior del mapa usará este campo.', 'kml-map' ); ?>
                                </p>
                            </div>
                            <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px">
                                <?php esc_html_e( 'Campos visibles en el popup:', 'kml-map' ); ?>
                            </label>

                                <div style="display:flex;flex-wrap:wrap;gap:6px 20px;margin-bottom:12px">
                                    <?php foreach ( $fields_available as $field ) :
                                        // Si $fields_visible es null (sin configurar), todos visibles por defecto
                                        $checked = ( $fields_visible === null || in_array( $field, $fields_visible ) );
                                    ?>
                                    <label style="font-size:13px;display:flex;align-items:center;gap:5px;cursor:pointer">
                                        <input type="checkbox"
                                               name="kml_visible_fields[]"
                                               value="<?php echo esc_attr( $field ); ?>"
                                               <?php checked( $checked ); ?>>
                                        <?php echo esc_html( $field ); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                                <button type="submit" class="button button-primary button-small">
                                    <?php esc_html_e( 'Guardar selección', 'kml-map' ); ?>
                                </button>
                            </form>
                        </div>
                    </details>
                    <?php else : ?>
                        <?php $kml_map_premium_notice(
                            __( 'Campos del popup', 'kml-map' ),
                            __( 'Con la versión gratuita no hay filtro configurado y el popup muestra todos los campos. Para elegir qué campos se muestran y por cuál filtrar, activa la versión premium.', 'kml-map' )
                        ); ?>
                    <?php endif; ?>
                <?php endif; ?>

                <!-- Aspecto de la caja de filtro (premium) -->
                <?php if ( $kml_map_is_premium ) : ?>
                <details style="margin-top:10px">
                    <summary style="cursor:pointer;color:#2271b1;font-size:13px;font-weight:500;
                                    list-style:none;display:inline-flex;align-items:center;gap:5px">
                        <span style="font-size:16px;line-height:1">🎨</span> <?php esc_html_e( 'Aspecto de la caja de filtro', 'kml-map' ); ?>
                    </summary>
                    <div style="margin-top:10px;padding:14px;background:#f6f7f7;
                                border-radius:4px;border:1px solid #ddd">
                        <p style="margin:0 0 10px;font-size:13px;color:#555">
                            <?php esc_html_e( 'Colores de la barra de filtro y del botón "Limpiar filtro" que se ve bajo el mapa.', 'kml-map' ); ?>
                        </p>
                        <form method="post"
                              action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <?php wp_nonce_field( 'kml_map_set_bar_style_' . $map->ID ); ?>
                            <input type="hidden" name="action" value="kml_map_set_bar_style">
                            <input type="hidden" name="map_id" value="<?php echo (int) $map->ID; ?>">

                            <div style="display:flex;flex-wrap:wrap;gap:16px 28px;margin-bottom:14px">
                                <label style="font-size:13px;display:flex;flex-direction:column;gap:4px">
                                    <?php esc_html_e( 'Fondo de la barra', 'kml-map' ); ?>
                                    <input type="color" name="bar_bg"
                                           value="<?php echo esc_attr( $bar_style['bar_bg'] ?? '#f0f2f5' ); ?>"
                                           style="width:48px;height:30px;border:1px solid #ccc;border-radius:3px;cursor:pointer">
                                </label>
                                <label style="font-size:13px;display:flex;flex-direction:column;gap:4px">
                                    <?php esc_html_e( 'Texto de la barra', 'kml-map' ); ?>
                                    <input type="color" name="bar_text"
                                           value="<?php echo esc_attr( $bar_style['bar_text'] ?? '#333333' ); ?>"
                                           style="width:48px;height:30px;border:1px solid #ccc;border-radius:3px;cursor:pointer">
                                </label>
                                <label style="font-size:13px;display:flex;flex-direction:column;gap:4px">
                                    <?php esc_html_e( 'Fondo del botón', 'kml-map' ); ?>
                                    <input type="color" name="btn_bg"
                                           value="<?php echo esc_attr( $bar_style['btn_bg'] ?? '#c0392b' ); ?>"
                                           style="width:48px;height:30px;border:1px solid #ccc;border-radius:3px;cursor:pointer">
                                </label>
                                <label style="font-size:13px;display:flex;flex-direction:column;gap:4px">
                                    <?php esc_html_e( 'Texto del botón', 'kml-map' ); ?>
                                    <input type="color" name="btn_text"
                                           value="<?php echo esc_attr( $bar_style['btn_text'] ?? '#ffffff' ); ?>"
                                           style="width:48px;height:30px;border:1px solid #ccc;border-radius:3px;cursor:pointer">
                                </label>
                            </div>
                            <label style="font-size:13px;display:flex;align-items:center;gap:5px;cursor:pointer;margin-bottom:10px">
                                <input type="checkbox" name="reset" value="1">
                                <?php esc_html_e( 'Restablecer a los colores por defecto', 'kml-map' ); ?>
                            </label>
                            <button type="submit" class="button button-primary button-small">
                                <?php esc_html_e( 'Guardar aspecto', 'kml-map' ); ?>
                            </button>
                        </form>
                    </div>
                </details>
                <?php else : ?>
                    <?php $kml_map_premium_notice(
                        __( 'Aspecto de la caja de filtro', 'kml-map' ),
                        __( 'Con la versión gratuita la caja de filtro usa los colores por defecto. Para personalizarlos, activa la versión premium.', 'kml-map' )
                    ); ?>
                <?php endif; ?>

            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
(function () {
    var PALETTE = ['#4daf4a','#4393c3','#f1a340','#d73027','#998ec3','#bf812d','#35978f','#e9a3c9'];
    var I18N = {
        file:     '<?php echo esc_js( __( 'Archivo', 'kml-map' ) ); ?>',
        color:    '<?php echo esc_js( __( 'Color de capa', 'kml-map' ) ); ?>',
        fill:     '<?php echo esc_js( __( 'Relleno', 'kml-map' ) ); ?>',
        noFill:   '<?php echo esc_js( __( 'Solo borde', 'kml-map' ) ); ?>'
    };

    function buildPickers(input, container) {
        container.innerHTML = '';
        var files = input.files;
        if (!files.length) return;

        var table = document.createElement('table');
        table.style.cssText = 'margin-top:10px;border-collapse:collapse;font-size:13px';

        var header = document.createElement('tr');
        header.innerHTML = '<th style="padding:4px 12px 4px 0;color:#777;font-weight:normal;text-align:left">' + I18N.file + '</th>'
                         + '<th style="padding:4px 12px 4px 0;color:#777;font-weight:normal;text-align:left">' + I18N.color + '</th>'
                         + '<th style="padding:4px 0;color:#777;font-weight:normal;text-align:left">' + I18N.fill + '</th>';
        table.appendChild(header);

        for (var i = 0; i < files.length; i++) {
            var name  = files[i].name.replace(/\.kml$/i, '');
            var color = PALETTE[i % PALETTE.length];
            var tr    = document.createElement('tr');
            tr.innerHTML = '<td style="padding:5px 12px 5px 0">' + escHtml(name) + '</td>'
                         + '<td style="padding:5px 12px 5px 0">'
                         + '<input type="color" name="kml_colors[]" value="' + color + '" '
                         + 'style="width:48px;height:30px;border:1px solid #ccc;border-radius:3px;cursor:pointer;vertical-align:middle">'
                         + '</td>'
                         + '<td style="padding:5px 0;white-space:nowrap">'
                         + '<label style="display:flex;align-items:center;gap:4px;cursor:pointer">'
                         + '<input type="checkbox" name="kml_no_fill[' + i + ']" value="1"> ' + I18N.noFill
                         + '</label>'
                         + '</td>';
            table.appendChild(tr);
        }
        container.appendChild(table);
    }

    function escHtml(str) {
        return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }

    // Adjuntar a todos los file inputs de la página
    document.querySelectorAll('.kml-file-input').forEach(function (input) {
        var wrap      = input.closest('.kml-file-wrap');
        var container = wrap ? wrap.querySelector('.kml-color-pickers') : null;
        if (!container) return;
        input.addEventListener('change', function () {
            buildPickers(input, container);
        });
    });
})();
</script>
