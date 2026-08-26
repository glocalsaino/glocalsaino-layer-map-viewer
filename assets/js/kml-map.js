/* GlocalSaino Layer Map Viewer – frontend */
( function () {
    'use strict';

    // Campos KML internos que no se muestran en el popup
    var HIDDEN_FIELDS = [
        'stroke', 'stroke-opacity', 'fill-opacity',
        'tessellate', 'extrude', 'visibility'
    ];

    // Zoom máximo real de las capas base (OpenStreetMap y Esri no sirven
    // teselas por encima de este nivel). El mapa y el agrupador de clústeres
    // (ver pointCluster más abajo) tienen que respetar el mismo tope: si se
    // les deja zoomar más allá, el mapa base desaparece (queda en blanco) y,
    // con clústeres, el usuario puede verse obligado a llegar hasta ahí para
    // separar marcadores muy juntos y poder pulsar en uno.
    var MAX_TILE_ZOOM = 19;

    // Paleta de colores categóricos para las capas KML
    var COLORS = [
        { stroke: '#2d8a35', fill: '#4daf4a' },  // verde
        { stroke: '#1a6fa3', fill: '#4393c3' },  // azul
        { stroke: '#b35806', fill: '#f1a340' },  // naranja
        { stroke: '#990000', fill: '#d73027' },  // rojo
        { stroke: '#542788', fill: '#998ec3' },  // morado
        { stroke: '#7a5500', fill: '#bf812d' },  // marrón
        { stroke: '#01665e', fill: '#35978f' },  // verde azulado
        { stroke: '#9e1f7a', fill: '#e9a3c9' },  // rosa
    ];

    // Textos traducibles: los pone wp_localize_script en GlocalSainoMapConfig.i18n
    // (ver el shortcode en glocalsaino-layer-map-viewer.php); si por lo que sea no llegaran
    // (script cargado suelto, fuera de WordPress), se usa el texto en
    // español de toda la vida como último recurso.
    var i18n = ( typeof GlocalSainoMapConfig !== 'undefined' && GlocalSainoMapConfig.i18n ) ? GlocalSainoMapConfig.i18n : {};
    function t( key, fallback ) {
        return i18n[ key ] || fallback;
    }

    // Convierte un atributo data-* numérico opcional a Number, o null si no
    // se indicó (atributo vacío) o no es un número válido.
    function readNumericAttr( el, name ) {
        var raw = el.getAttribute( name );
        if ( raw === null || raw === '' ) return null;
        var n = parseFloat( raw );
        return isNaN( n ) ? null : n;
    }

    function initAll() {
        document.querySelectorAll( '.kml-map-canvas[data-kml-layers]' ).forEach( function ( el ) {
            try {
                var layers            = JSON.parse( el.getAttribute( 'data-kml-layers' ) );
                var visibleFields     = JSON.parse( el.getAttribute( 'data-kml-fields' ) || 'null' );
                var filterField       = el.getAttribute( 'data-kml-filter-field' ) || '';
                var filterValues      = JSON.parse( el.getAttribute( 'data-kml-filter-values' ) || '[]' );
                var filterValueBounds = JSON.parse( el.getAttribute( 'data-kml-filter-value-bounds' ) || '{}' );
                // Vista inicial explícita (atributos zoom/lat/lng del
                // shortcode); ausentes (null) si no se indicaron, en cuyo
                // caso el mapa se sigue encuadrando solo (ver fitAll()).
                var initialView = {
                    zoom: readNumericAttr( el, 'data-kml-zoom' ),
                    lat:  readNumericAttr( el, 'data-kml-lat' ),
                    lng:  readNumericAttr( el, 'data-kml-lng' )
                };
                // Array.isArray() basta (sin exigir layers.length): un mapa
                // sin capas todavía (p.ej. antes de añadir una capa externa
                // con un add-on) debe seguir mostrando el mapa base.
                if ( Array.isArray( layers ) ) {
                    initMap( el.id, layers, visibleFields, filterField, filterValues, filterValueBounds, initialView );
                }
            } catch ( e ) {
                console.error( 'kml-map: invalid JSON', e );
            }
        } );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', initAll );
    } else {
        initAll();
    }

    // -----------------------------------------------------------------------
    function initMap( uid, kmlLayers, visibleFields, filterField, filterValuesFromServer, filterValueBounds, initialView ) {

        // Canvas en vez de SVG (por defecto en Leaflet): con muchos objetos a
        // la vez el SVG crea un nodo del DOM por cada uno y se vuelve muy
        // pesado, sobre todo en móviles; Canvas dibuja todo en un único
        // elemento.
        var map = L.map( uid, { zoomControl: true, maxZoom: MAX_TILE_ZOOM, renderer: L.canvas() } );

        // --- Capas base ---
        // La lista viene del servidor (ver el shortcode en
        // glocalsaino-layer-map-viewer.php), que a su vez arranca con
        // OpenStreetMap y Satélite (Esri World Imagery — un servicio de
        // teselas documentado para este uso, a diferencia del endpoint no
        // oficial de Google que se usaba antes) y deja que otro código (p.ej.
        // un add-on) añada más vía el filtro 'glocalsaino_map_base_layers',
        // sin que este JS necesite saber nada de ellas de antemano.
        var baseLayersConfig = ( typeof GlocalSainoMapConfig !== 'undefined' && Array.isArray( GlocalSainoMapConfig.baseLayers ) )
            ? GlocalSainoMapConfig.baseLayers
            : [];
        var baseLayers  = {};
        var defaultBase = null;
        baseLayersConfig.forEach( function ( bl ) {
            if ( ! bl || ! bl.url ) return;

            var options = L.extend( { maxZoom: MAX_TILE_ZOOM }, bl.options || {} );
            var layer   = L.tileLayer( bl.url, options );

            baseLayers[ bl.label || bl.id ] = layer;
            if ( bl['default'] || ! defaultBase ) defaultBase = layer;
        } );

        if ( defaultBase ) defaultBase.addTo( map );

        // URL del endpoint que sirve los objetos de una capa por páginas (ver
        // kml_map_rest_get_features en PHP), inyectada por wp_localize_script.
        var restUrl = ( typeof GlocalSainoMapConfig !== 'undefined' && GlocalSainoMapConfig.restUrl ) ? GlocalSainoMapConfig.restUrl : null;

        // --- Definir cada capa KML ---
        // El bounding box de cada capa lo calcula el servidor al analizarla
        // en segundo plano, así que el mapa puede encuadrarse al instante. En
        // cuanto se abre el mapa se empieza a pedir cada capa entera al
        // servidor, página a página (ver fetchLayerPage): una vez cargado un
        // objeto se queda en el mapa, visible a cualquier nivel de zoom, sin
        // volver a pedirse ni desaparecer.
        var layerData     = [];   // [{name, url, bounds, displayLayer, requestSeq, loading}, ...]
        var overlays      = {};   // { 'nombre': displayLayer }
        var currentFilter = [];   // valores seleccionados en el filtro (vacío = sin filtro)

        // El desplegable del filtro se precarga con los valores calculados
        // en el servidor, así funciona desde el primer instante.
        var filterValues = Array.isArray( filterValuesFromServer )
            ? filterValuesFromServer.map( String )
            : [];
        var filterSelect = null;

        kmlLayers.forEach( function ( kml, i ) {
            var palette      = COLORS[ i % COLORS.length ];
            var fillColor    = ( kml.color && kml.color.length === 7 ) ? kml.color         : palette.fill;
            var strokeColor  = ( kml.color && kml.color.length === 7 ) ? darken(kml.color, 0.3) : palette.stroke;
            // Ausente (capas creadas antes de esta opción) se trata como
            // "con relleno", igual que se veían hasta ahora.
            var hasFill      = ( kml.fill === undefined ) || !!kml.fill;
            // Ausente (capas creadas antes de poder elegir transparencia):
            // mismo 0.6 que se usaba fijo hasta ahora.
            var fillOpacity  = ( typeof kml.opacity === 'number' ) ? kml.opacity : 0.6;

            function style() {
                return {
                    color:       strokeColor,
                    weight:      1.5,
                    fillColor:   fillColor,
                    // fillOpacity a 0 en vez de fill:false: el interior
                    // sigue siendo clicable para abrir el popup aunque no
                    // se pinte, solo se ve el borde.
                    fillOpacity: hasFill ? fillOpacity : 0
                };
            }

            function onEachFeature( feature, layer ) {
                if ( ! feature.properties ) return;
                var rows = '';
                for ( var k in feature.properties ) {
                    if ( HIDDEN_FIELDS.indexOf( k ) !== -1 ) continue;
                    if ( visibleFields !== null && visibleFields.indexOf( k ) === -1 ) continue;
                    var v = feature.properties[ k ];
                    if ( v !== null && v !== undefined && v !== '' ) {
                        rows += '<tr>'
                            + '<td style="padding:3px 8px;font-weight:bold;white-space:nowrap;border-bottom:1px solid #eee">'
                            + escHtml( k ) + '</td>'
                            + '<td style="padding:3px 8px;border-bottom:1px solid #eee">'
                            + formatPopupValue( v ) + '</td>'
                            + '</tr>';
                    }
                }

                layer.bindPopup(
                    '<table style="border-collapse:collapse;font-size:13px;font-family:sans-serif">'
                    + rows + '</table>',
                    { maxHeight: 320 }
                );
            }

            // Solo se usa para geometrías de tipo punto; en polígonos/líneas
            // Leaflet ni siquiera llama a esta función. Siempre un círculo,
            // con el mismo color/opacidad elegidos para la capa; lo único
            // configurable es su radio.
            function pointToLayer( feature, latlng ) {
                var iconSize = ( typeof kml.point_icon_size === 'number' && kml.point_icon_size > 0 ) ? kml.point_icon_size : 8;

                return L.circleMarker( latlng, {
                    radius:      iconSize,
                    color:       strokeColor,
                    weight:      1.5,
                    fillColor:   fillColor,
                    fillOpacity: hasFill ? fillOpacity : 0
                } );
            }

            // Los puntos se agrupan en clústeres cuando quedan muy juntos (a
            // poco zoom, o simplemente porque hay muchos en el mismo sitio),
            // en vez de quedar apilados unos encima de otros sin poder
            // distinguirlos ni hacer clic en el de abajo. Polígonos y líneas
            // se siguen dibujando aparte, sobre Canvas, como siempre.
            var shapesLayer  = L.geoJSON( null, { style: style, onEachFeature: onEachFeature } );
            // disableClusteringAtZoom: al llegar al zoom máximo de las
            // teselas, se muestran siempre todos los marcadores sueltos en
            // vez de seguir intentando separarlos por zoom (que llevaría a
            // querer zoomar más allá de donde hay mapa base).
            var pointCluster = L.markerClusterGroup( { chunkedLoading: true, disableClusteringAtZoom: MAX_TILE_ZOOM } );
            // "Molde" que solo sirve para construir los marcadores de punto
            // reutilizando pointToLayer/onEachFeature; nunca se añade al
            // mapa por sí mismo, sus marcadores se trasladan al clúster.
            var pointBuilder = L.geoJSON( null, { pointToLayer: pointToLayer, onEachFeature: onEachFeature } );

            var displayLayer = L.layerGroup( [ shapesLayer, pointCluster ] );

            // Métodos propios (no chocan con los de LayerGroup) para que el
            // resto del código trate displayLayer como una única capa de
            // datos, sin saber que por dentro reparte puntos y formas.
            displayLayer.addLayerData = function ( geojson ) {
                var features      = Array.isArray( geojson.features ) ? geojson.features : [ geojson ];
                var pointFeatures = [];
                var otherFeatures = [];
                features.forEach( function ( f ) {
                    ( f.geometry && f.geometry.type === 'Point' ? pointFeatures : otherFeatures ).push( f );
                } );
                if ( otherFeatures.length ) {
                    shapesLayer.addData( { type: 'FeatureCollection', features: otherFeatures } );
                }
                if ( pointFeatures.length ) {
                    pointBuilder.addData( { type: 'FeatureCollection', features: pointFeatures } );
                    pointCluster.addLayers( pointBuilder.getLayers() );
                    pointBuilder.clearLayers();
                }
            };
            displayLayer.clearLayerData = function () {
                shapesLayer.clearLayers();
                pointCluster.clearLayers();
            };
            displayLayer.countFeatures = function () {
                return shapesLayer.getLayers().length + pointCluster.getLayers().length;
            };

            var bounds = null;
            if ( Array.isArray( kml.bounds ) && kml.bounds.length === 4 ) {
                // PHP envía [south, west, north, east]
                bounds = L.latLngBounds(
                    [ kml.bounds[0], kml.bounds[1] ],
                    [ kml.bounds[2], kml.bounds[3] ]
                );
            }

            var ld = {
                name:          kml.name,
                url:           kml.url,
                bounds:        bounds,   // límites de la capa completa, calculados en el servidor
                displayLayer:  displayLayer,
                requestSeq:    0,        // para descartar respuestas de peticiones obsoletas (p.ej. tras cambiar el filtro)
                loading:       null,     // { shown, total } mientras se siguen pidiendo páginas de esta capa
                // Genérico: cualquier capa con un intervalo de actualización
                // configurado (típicamente una capa externa con datos que
                // cambian por su cuenta, ver el add-on de fuentes de datos
                // externas) se vuelve a pedir periódicamente sin recargar la
                // página, y se muestra un aviso con la última actualización.
                // El plugin base no sabe ni le importa de dónde vienen los
                // datos: solo mira si kml.refresh_minutes existe.
                liveRefreshMinutes: ( typeof kml.refresh_minutes === 'number' && kml.refresh_minutes > 0 ) ? kml.refresh_minutes : null,
                lastUpdated:        null  // Date de la última actualización con éxito, o null si aún no se ha completado ninguna
            };
            layerData.push( ld );

            overlays[ kml.name ] = displayLayer;
        } );

        // Vista inicial: si el shortcode indica un centro explícito (lat+lng),
        // ese manda siempre, con el zoom indicado o 8 por defecto. Si solo se
        // indica el zoom (sin centro), se mantiene el encuadre automático por
        // límites pero forzando ese nivel de zoom. Sin ninguno de los dos, se
        // encuadra solo a partir de los límites ya conocidos (sin pedir nada
        // al servidor), como siempre.
        if ( initialView && initialView.lat !== null && initialView.lng !== null ) {
            map.setView( [ initialView.lat, initialView.lng ], initialView.zoom !== null ? initialView.zoom : 8 );
        } else if ( initialView && initialView.zoom !== null ) {
            fitAll();
            map.setZoom( initialView.zoom );
        } else {
            fitAll();
        }

        // Control de capas (base + overlays): el usuario puede ocultar o
        // volver a mostrar una capa manualmente en cualquier momento;
        // Leaflet gestiona ese añadir/quitar por su cuenta, sin que dependa
        // del zoom.
        L.control.layers( baseLayers, overlays, {
            position:  'topright',
            collapsed: true
        } ).addTo( map );

        // Aviso mientras una capa muy densa sigue cargando por páginas (ver
        // fetchLayerPage): informa del progreso en vez de dejar que el
        // usuario piense que esos objetos no van a aparecer nunca.
        //
        // 'topleft' (debajo del control de zoom) en vez de 'bottomleft':
        // ahí abajo está la atribución del mapa base (OpenStreetMap/Esri),
        // que en pantallas estrechas (móvil) ocupa casi todo el ancho y se
        // superponía con este aviso, dejando ambos ilegibles. Leaflet apila
        // varios controles en la misma esquina sin solaparlos nunca, así que
        // aquí no choca con el zoom.
        var loadingIndicator = L.control( { position: 'topleft' } );
        loadingIndicator.onAdd = function () {
            var div = L.DomUtil.create( 'div' );
            div.style.cssText = 'background:#e7f1ff;color:#0a3766;padding:4px 10px;'
                + 'border-radius:4px;box-shadow:0 1px 4px rgba(0,0,0,0.3);font-size:12px;'
                + 'font-family:sans-serif;line-height:1.4;max-width:260px;display:none';
            loadingIndicator._div = div;
            return div;
        };
        loadingIndicator.addTo( map );

        function updateLoadingIndicator() {
            var div = loadingIndicator._div;
            if ( ! div ) return;

            var messages = [];
            layerData.forEach( function ( ld ) {
                if ( ld.loading ) {
                    var text = t( 'loadingTemplate', '%1$s: cargando objetos… (%2$s de %3$s)' )
                        .replace( '%1$s', escHtml( ld.name ) )
                        .replace( '%2$s', ld.loading.shown )
                        .replace( '%3$s', ld.loading.total );
                    messages.push( '⏳ ' + text );
                }
            } );

            if ( messages.length ) {
                div.innerHTML = messages.join( '<br>' );
                div.style.display = 'block';
            } else {
                div.style.display = 'none';
            }
        }

        // Aviso de "en directo" para las capas con liveRefreshMinutes (ver
        // más arriba): igual que loadingIndicator, un control de Leaflet más
        // en 'topleft' — se apila debajo sin superponerse a los demás, en
        // vez de un <div> con position:absolute a mano, que es lo que
        // causaba el choque con la atribución del mapa en móvil que ya
        // arreglamos una vez.
        var liveIndicator = L.control( { position: 'topleft' } );
        liveIndicator.onAdd = function () {
            var div = L.DomUtil.create( 'div' );
            div.style.cssText = 'background:#eafbea;color:#1a5c1a;padding:4px 10px;'
                + 'border-radius:4px;box-shadow:0 1px 4px rgba(0,0,0,0.3);font-size:12px;'
                + 'font-family:sans-serif;line-height:1.5;max-width:260px;display:none';
            liveIndicator._div = div;
            return div;
        };
        liveIndicator.addTo( map );

        function formatRelativeTime( date ) {
            var seconds = Math.max( 0, Math.round( ( Date.now() - date.getTime() ) / 1000 ) );
            if ( seconds < 60 ) return t( 'justNow', 'justo ahora' );
            var minutes = Math.round( seconds / 60 );
            var template = ( 1 === minutes )
                ? t( 'minuteAgo', 'hace %d minuto' )
                : t( 'minutesAgo', 'hace %d minutos' );
            return template.replace( '%d', minutes );
        }

        function updateLiveIndicator() {
            var div = liveIndicator._div;
            if ( ! div ) return;

            var liveLayers = layerData.filter( function ( ld ) { return ld.liveRefreshMinutes; } );
            if ( ! liveLayers.length ) {
                div.style.display = 'none';
                return;
            }

            var lines = liveLayers.map( function ( ld ) {
                var when = ld.lastUpdated ? formatRelativeTime( ld.lastUpdated ) : t( 'updating', 'actualizando…' );
                return '🟢 ' + escHtml( ld.name ) + ': ' + when;
            } );

            if ( liveLayers.length === 1 ) {
                // Una sola capa en directo: se muestra ella sola, sin
                // desplegable — no hay nada que ahorrar plegando una única
                // línea, y así se ve de un vistazo como siempre.
                liveIndicator._details = null;
                div.innerHTML = lines[ 0 ];
            } else {
                // Varias capas en directo: en un desplegable (<details>), para
                // no ocupar mucho espacio de golpe con una encima de otra. Se
                // reconstruye solo el contenido interno (resumen + lista), sin
                // recrear <details>, así no se pliega solo cada vez que se
                // refresca (cada 15s, o al llegar datos nuevos de alguna
                // capa) si el usuario lo había dejado abierto.
                if ( ! liveIndicator._details ) {
                    var details = document.createElement( 'details' );
                    var summary = document.createElement( 'summary' );
                    summary.style.cssText = 'cursor:pointer';
                    var list = document.createElement( 'div' );
                    list.style.marginTop = '4px';
                    details.appendChild( summary );
                    details.appendChild( list );
                    div.innerHTML = '';
                    div.appendChild( details );
                    liveIndicator._details = details;
                    liveIndicator._summary = summary;
                    liveIndicator._list    = list;
                }
                liveIndicator._summary.textContent = '🟢 ' + liveLayers.length + ' '
                    + t( 'liveLayersLabel', 'capas en directo' );
                liveIndicator._list.innerHTML = lines.join( '<br>' );
            }

            div.style.display = 'block';
        }

        // El texto ("hace X minutos") se refresca solo aunque no llegue
        // ningún dato nuevo, para que no se quede parado en "hace 1 minuto"
        // media hora después de la última actualización real.
        setInterval( updateLiveIndicator, 15000 );

        initFilterUI();

        // Todas las capas se añaden al mapa y empiezan a cargarse en cuanto
        // se abre el mapa; una vez cargado un objeto no se vuelve a tocar ni
        // se retira al hacer zoom o mover el mapa: se ve a cualquier nivel.
        layerData.forEach( function ( ld ) {
            map.addLayer( ld.displayLayer );
            fetchLayerFeatures( ld );

            // Capas "en directo": además de la carga inicial, se vuelven a
            // pedir por su cuenta cada liveRefreshMinutes, sin recargar la
            // página — recogen así lo último que haya escrito el refresco en
            // segundo plano del servidor (ver el add-on de fuentes externas).
            if ( ld.liveRefreshMinutes ) {
                setInterval( function () {
                    fetchLayerFeatures( ld );
                }, ld.liveRefreshMinutes * 60000 );
            }
        } );
        updateLiveIndicator(); // muestra "actualizando…" desde el primer momento, no solo tras la primera respuesta

        function fetchLayerFeatures( ld ) {
            if ( ! restUrl ) return;

            var token = ++ld.requestSeq;

            ld.displayLayer.clearLayerData();
            ld.loading = null;

            fetchLayerPage( ld, token, 0 );
        }

        // Pide una página de objetos de la capa; si el servidor avisa de que
        // hay más ('has_more'), sigue pidiendo automáticamente las
        // siguientes hasta tener la capa completa. Así una capa muy densa se
        // va rellenando en segundo plano en varias peticiones ligeras (de
        // KML_MAP_PAGE_SIZE objetos cada una) en vez de una única petición
        // gigante que podría colgar la página, sobre todo en móvil.
        function fetchLayerPage( ld, token, page ) {
            var url = restUrl
                + '?url='  + encodeURIComponent( ld.url )
                + '&page=' + page;

            // Las capas "en directo" no deben servirse desde ninguna caché
            // (navegador, CDN o caché del hosting): el endpoint cachea la
            // respuesta 60s para capas normales (ver kml_map_rest_get_features),
            // pero eso es justo lo que impedía ver la posición actualizada de
            // un objeto en movimiento. 'live=1' le dice al servidor que no
            // cachee esta respuesta, y el timestamp evita que una caché que
            // ignore esa cabecera sirva igualmente una copia antigua con la
            // misma URL.
            if ( ld.liveRefreshMinutes ) {
                url += '&live=1&_=' + Date.now();
            }

            if ( currentFilter.length ) {
                url += '&filter_field=' + encodeURIComponent( filterField )
                    + '&filter_values=' + encodeURIComponent( currentFilter.join( ',' ) );
            }

            fetch( url )
                .then( function ( r ) { return r.json(); } )
                .then( function ( geojson ) {
                    if ( token !== ld.requestSeq ) return; // ha llegado tarde: ya no es la petición actual (p.ej. cambió el filtro)

                    if ( geojson && geojson.features && geojson.features.length ) {
                        ld.displayLayer.addLayerData( geojson );
                    }

                    if ( geojson && geojson.has_more ) {
                        ld.loading = { shown: ld.displayLayer.countFeatures(), total: geojson.total };
                        updateLoadingIndicator();
                        fetchLayerPage( ld, token, page + 1 );
                    } else {
                        // Ya no quedan más páginas: la capa está completa.
                        ld.loading = null;
                        updateLoadingIndicator();

                        if ( ld.liveRefreshMinutes ) {
                            ld.lastUpdated = new Date();
                            updateLiveIndicator();
                        }
                    }
                } )
                .catch( function ( e ) {
                    console.error( 'kml-map [' + uid + ']: error al pedir objetos', e );
                    ld.loading = null;
                    updateLoadingIndicator();
                } );
        }

        // --- Ajustar mapa a todas las capas (usa los límites conocidos, sin
        // pedir nada al servidor) ---
        function fitAll() {
            var group = L.latLngBounds( [] );
            layerData.forEach( function ( d ) {
                if ( d.bounds && d.bounds.isValid() ) group.extend( d.bounds );
            } );
            if ( group.isValid() ) {
                map.fitBounds( group, { padding: [ 20, 20 ] } );
            } else {
                // Ningún límite conocido todavía (análisis en segundo plano
                // aún no terminado). Sin esto el mapa se queda sin vista y
                // map.getBounds()/getZoom() más adelante rompen toda la
                // inicialización. Vista de partida genérica de España.
                map.setView( [ 40.4168, -3.7038 ], 6 );
            }
        }

        // --- Filtro por campo configurable ---
        function initFilterUI() {
            filterSelect = document.getElementById( uid + '-sel' );
            if ( ! filterSelect ) return;

            filterValues.forEach( function ( v ) {
                var opt         = document.createElement( 'option' );
                opt.value       = v;
                opt.textContent = v;
                filterSelect.appendChild( opt );
            } );
            filterSelect.size = Math.min( filterValues.length, 6 ) || 1;

            filterSelect.addEventListener( 'change', applyFilter );

            var clearBtn = document.getElementById( uid + '-clear' );
            if ( clearBtn ) {
                clearBtn.addEventListener( 'click', function () {
                    for ( var i = 0; i < filterSelect.options.length; i++ ) filterSelect.options[ i ].selected = false;
                    applyFilter();
                } );
            }
        }

        function applyFilter() {
            currentFilter = [];
            for ( var i = 0; i < filterSelect.options.length; i++ ) {
                if ( filterSelect.options[ i ].selected ) currentFilter.push( filterSelect.options[ i ].value );
            }

            // Encuadrar la vista a los objetos que cumplen el filtro usando
            // los límites por valor precalculados en el servidor (sin
            // descargar nada); sin filtro, se vuelve al encuadre general.
            if ( currentFilter.length && filterValueBounds ) {
                var group = L.latLngBounds( [] );
                currentFilter.forEach( function ( v ) {
                    var b = filterValueBounds[ v ];
                    if ( b ) group.extend( L.latLngBounds( [ b[0], b[1] ], [ b[2], b[3] ] ) );
                } );
                if ( group.isValid() ) map.fitBounds( group, { padding: [ 20, 20 ] } );
            } else {
                fitAll();
            }

            // El filtro cambia lo que hay que pedir al servidor: se vuelve a
            // cargar cada capa desde cero con el nuevo filtro aplicado.
            layerData.forEach( function ( ld ) { fetchLayerFeatures( ld ); } );
        }
    }

    // -----------------------------------------------------------------------
    // Oscurece un color hex (#rrggbb) en el porcentaje indicado (0–1)
    function darken( hex, amount ) {
        var r = Math.max( 0, Math.round( parseInt( hex.slice(1,3), 16 ) * ( 1 - amount ) ) );
        var g = Math.max( 0, Math.round( parseInt( hex.slice(3,5), 16 ) * ( 1 - amount ) ) );
        var b = Math.max( 0, Math.round( parseInt( hex.slice(5,7), 16 ) * ( 1 - amount ) ) );
        return '#' + [ r, g, b ].map( function(v) {
            return v.toString(16).padStart(2, '0');
        } ).join('');
    }

    function escHtml( str ) {
        return str
            .replace( /&/g, '&amp;' )
            .replace( /</g, '&lt;' )
            .replace( />/g, '&gt;' )
            .replace( /"/g, '&quot;' );
    }

    // Convierte un valor de campo en un enlace clicable si (y solo si) el
    // valor entero es una URL http/https — nunca se detectan URLs sueltas
    // dentro de un texto más largo, y nunca se acepta otro esquema (evita
    // que algo como "javascript:..." en el archivo subido se cuele como
    // href). El propio texto del enlace pasa igualmente por escHtml(), así
    // que el resultado es tan seguro como el texto plano de siempre.
    function formatPopupValue( v ) {
        var raw     = String( v );
        var trimmed = raw.trim();
        if ( /^https?:\/\//i.test( trimmed ) ) {
            var escaped = escHtml( trimmed );
            return '<a href="' + escaped + '" target="_blank" rel="noopener noreferrer">' + escaped + '</a>';
        }
        return escHtml( raw );
    }

} )();
