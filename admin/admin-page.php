<?php
if ( ! defined( 'ABSPATH' ) ) exit;

$maps = get_posts( [
    'post_type'   => 'kml_map',
    'numberposts' => -1,
    'orderby'     => 'date',
    'order'       => 'DESC',
] );

$errors = [
    'notitle' => 'Debes introducir un nombre para el mapa.',
    'nofile'  => 'Debes seleccionar al menos un archivo KML.',
    'badext'  => 'Los archivos deben tener extensión .kml.',
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
    <h1>Mapas KML</h1>

    <?php if ( isset( $_GET['added'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ Mapa creado correctamente.</p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['added_layer'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ Capa(s) añadida(s) correctamente.</p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['deleted'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ Mapa eliminado.</p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['deleted_layer'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ Capa eliminada.</p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['saved_fields'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>✔ Configuración de campos guardada.</p></div>
    <?php endif; ?>
    <?php if ( isset( $_GET['error'] ) ) : ?>
        <div class="notice notice-error is-dismissible">
            <p>Error: <?php echo esc_html( $errors[ $_GET['error'] ] ?? $_GET['error'] ); ?></p>
        </div>
    <?php endif; ?>

    <!-- ================================================================
         Formulario: crear nuevo mapa
    ================================================================ -->
    <div style="background:#fff;border:1px solid #c3c4c7;border-radius:4px;padding:20px 24px;max-width:640px;margin:20px 0">
        <h2 style="margin-top:0">Crear nuevo mapa</h2>
        <form method="post"
              action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
              enctype="multipart/form-data">
            <?php wp_nonce_field( 'kml_map_add' ); ?>
            <input type="hidden" name="action" value="kml_map_add">

            <table class="form-table" role="presentation">
                <tr>
                    <th><label for="map_title">Nombre del mapa</label></th>
                    <td>
                        <input type="text" id="map_title" name="map_title"
                               class="regular-text"
                               placeholder="Ej: Parcelas Armental" required>
                    </td>
                </tr>
                <tr>
                    <th><label for="kml_files">Archivos KML</label></th>
                    <td>
                        <div class="kml-file-wrap">
                            <input type="file" class="kml-file-input" name="kml_files[]"
                                   accept=".kml" multiple required>
                            <div class="kml-color-pickers"></div>
                        </div>
                        <p class="description">
                            Puedes seleccionar varios archivos a la vez (Ctrl+clic o Cmd+clic).<br>
                            Elige el color de cada capa antes de subir.<br>
                            Todos deben incluir el campo <strong>NUM_SOCIO</strong>.
                        </p>
                    </td>
                </tr>
            </table>
            <?php submit_button( 'Crear mapa', 'primary', 'submit', false ); ?>
        </form>
    </div>

    <!-- ================================================================
         Listado de mapas existentes
    ================================================================ -->
    <h2 style="margin-top:30px">Mapas disponibles</h2>

    <?php if ( empty( $maps ) ) : ?>
        <p style="color:#666">Todavía no hay mapas. Usa el formulario de arriba para crear el primero.</p>
    <?php else : ?>
        <p style="color:#666;margin-bottom:12px">
            Pega el shortcode en cualquier página o entrada.
            Puedes personalizar la altura: <code>[kml_map id="1" height="700px"]</code>
        </p>

        <?php foreach ( $maps as $map ) :
            $layers           = json_decode( get_post_meta( $map->ID, '_kml_layers', true ), true ) ?: [];
            $fields_available = json_decode( get_post_meta( $map->ID, '_kml_fields_available', true ), true ) ?: [];

            // Si el mapa tiene capas pero aún no tiene campos extraídos, escanear ahora
            if ( ! empty( $layers ) && empty( $fields_available ) ) {
                kml_map_refresh_fields( $map->ID, $layers );
                $fields_available = json_decode( get_post_meta( $map->ID, '_kml_fields_available', true ), true ) ?: [];
            }

            $fields_visible = json_decode( get_post_meta( $map->ID, '_kml_fields_visible', true ), true );
            $filter_field   = get_post_meta( $map->ID, '_kml_filter_field', true ) ?: 'NUM_SOCIO';
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
                </div>
                <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap">
                    <code style="background:#f0f0f1;padding:4px 8px;border-radius:3px">
                        [kml_map id="<?php echo $map->ID; ?>"]
                    </code>
                    <button type="button" class="button button-small"
                            onclick="
                                navigator.clipboard.writeText('[kml_map id=&quot;<?php echo $map->ID; ?>&quot;]');
                                this.textContent='¡Copiado!';
                                var b=this; setTimeout(function(){b.textContent='Copiar shortcode';},2000);
                            ">Copiar shortcode</button>
                    <a href="<?php echo esc_url( wp_nonce_url(
                        admin_url( 'admin-post.php?action=kml_map_delete&id=' . $map->ID ),
                        'kml_map_delete_' . $map->ID
                    ) ); ?>"
                       class="button button-small"
                       style="color:#b32d2e;border-color:#b32d2e"
                       onclick="return confirm('¿Eliminar el mapa «<?php echo esc_js($map->post_title); ?>» y todas sus capas?')">
                        Eliminar mapa
                    </a>
                </div>
            </div>

            <!-- Capas KML del mapa -->
            <div style="padding:14px 20px">
                <p style="margin:0 0 10px;font-weight:600;color:#444">
                    Capas KML (<?php echo count( $layers ); ?>):
                </p>

                <?php if ( empty( $layers ) ) : ?>
                    <p style="color:#999;font-style:italic">Sin capas. Añade archivos KML abajo.</p>
                <?php else : ?>
                    <table style="width:100%;border-collapse:collapse">
                        <thead>
                            <tr style="border-bottom:1px solid #e0e0e0;text-align:left;font-size:12px;color:#777">
                                <th style="padding:4px 8px;width:24px">#</th>
                                <th style="padding:4px 8px;width:18px">Color</th>
                                <th style="padding:4px 8px">Nombre de capa</th>
                                <th style="padding:4px 8px">Archivo</th>
                                <th style="padding:4px 8px;width:90px"></th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ( $layers as $idx => $layer ) :
                            $color = ! empty( $layer['color'] )
                                ? $layer['color']
                                : $palette[ $idx % count( $palette ) ];
                        ?>
                            <tr style="border-bottom:1px solid #f0f0f0">
                                <td style="padding:6px 8px;color:#999;font-size:12px"><?php echo $idx + 1; ?></td>
                                <td style="padding:6px 8px">
                                    <span style="display:inline-block;width:16px;height:16px;
                                                 background:<?php echo esc_attr($color); ?>;
                                                 border-radius:3px;border:1px solid rgba(0,0,0,0.2)"></span>
                                </td>
                                <td style="padding:6px 8px;font-weight:500">
                                    <?php echo esc_html( $layer['name'] ); ?>
                                </td>
                                <td style="padding:6px 8px;font-size:12px;color:#666">
                                    <?php echo esc_html( basename( $layer['url'] ) ); ?>
                                </td>
                                <td style="padding:6px 8px">
                                    <a href="<?php echo esc_url( wp_nonce_url(
                                        admin_url( 'admin-post.php?action=kml_map_del_layer&map_id=' . $map->ID . '&layer_idx=' . $idx ),
                                        'kml_map_del_layer_' . $map->ID . '_' . $idx
                                    ) ); ?>"
                                       class="button button-small"
                                       style="color:#b32d2e;border-color:#b32d2e"
                                       onclick="return confirm('¿Eliminar la capa «<?php echo esc_js($layer['name']); ?>»?')">
                                        Eliminar capa
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <!-- Formulario para añadir más capas -->
                <details style="margin-top:14px">
                    <summary style="cursor:pointer;color:#2271b1;font-size:13px;font-weight:500;
                                    list-style:none;display:inline-flex;align-items:center;gap:5px">
                        <span style="font-size:18px;line-height:1">+</span> Añadir más capas KML
                    </summary>
                    <div style="margin-top:10px;padding:14px;background:#f6f7f7;
                                border-radius:4px;border:1px solid #ddd">
                        <form method="post"
                              action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
                              enctype="multipart/form-data">
                            <?php wp_nonce_field( 'kml_map_add_layers_' . $map->ID ); ?>
                            <input type="hidden" name="action" value="kml_map_add_layers">
                            <input type="hidden" name="map_id" value="<?php echo $map->ID; ?>">
                            <div class="kml-file-wrap">
                                <input type="file" class="kml-file-input" name="kml_files[]"
                                       accept=".kml" multiple required>
                                <div class="kml-color-pickers"></div>
                            </div>
                            <p class="description" style="margin:6px 0 10px">
                                Puedes seleccionar varios archivos. Elige el color de cada capa antes de subir.
                            </p>
                            <button type="submit" class="button button-primary">Añadir capas</button>
                        </form>
                    </div>
                </details>

                <!-- Configuración de campos del popup -->
                <?php if ( ! empty( $fields_available ) ) : ?>
                <details style="margin-top:10px">
                    <summary style="cursor:pointer;color:#2271b1;font-size:13px;font-weight:500;
                                    list-style:none;display:inline-flex;align-items:center;gap:5px">
                        <span style="font-size:16px;line-height:1">⚙</span> Campos del popup
                    </summary>
                    <div style="margin-top:10px;padding:14px;background:#f6f7f7;
                                border-radius:4px;border:1px solid #ddd">
                        <p style="margin:0 0 10px;font-size:13px;color:#555">
                            Selecciona los campos que se mostrarán al hacer clic en una finca y el campo por el que se filtrará:
                        </p>
                        <form method="post"
                              action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                            <?php wp_nonce_field( 'kml_map_save_fields_' . $map->ID ); ?>
                            <input type="hidden" name="action" value="kml_map_save_fields">
                            <input type="hidden" name="map_id" value="<?php echo $map->ID; ?>">

                        <div style="margin-bottom:14px;padding-bottom:12px;border-bottom:1px solid #ddd">
                            <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px">
                                Campo de filtrado:
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
                                El selector de la barra inferior del mapa usará este campo.
                            </p>
                        </div>
                        <label style="font-size:13px;font-weight:600;display:block;margin-bottom:6px">
                            Campos visibles en el popup:
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
                                Guardar selección
                            </button>
                        </form>
                    </div>
                </details>
                <?php endif; ?>

            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
(function () {
    var PALETTE = ['#4daf4a','#4393c3','#f1a340','#d73027','#998ec3','#bf812d','#35978f','#e9a3c9'];

    function buildPickers(input, container) {
        container.innerHTML = '';
        var files = input.files;
        if (!files.length) return;

        var table = document.createElement('table');
        table.style.cssText = 'margin-top:10px;border-collapse:collapse;font-size:13px';

        var header = document.createElement('tr');
        header.innerHTML = '<th style="padding:4px 12px 4px 0;color:#777;font-weight:normal;text-align:left">Archivo</th>'
                         + '<th style="padding:4px 0;color:#777;font-weight:normal;text-align:left">Color de capa</th>';
        table.appendChild(header);

        for (var i = 0; i < files.length; i++) {
            var name  = files[i].name.replace(/\.kml$/i, '');
            var color = PALETTE[i % PALETTE.length];
            var tr    = document.createElement('tr');
            tr.innerHTML = '<td style="padding:5px 12px 5px 0">' + escHtml(name) + '</td>'
                         + '<td style="padding:5px 0">'
                         + '<input type="color" name="kml_colors[]" value="' + color + '" '
                         + 'style="width:48px;height:30px;border:1px solid #ccc;border-radius:3px;cursor:pointer;vertical-align:middle">'
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
