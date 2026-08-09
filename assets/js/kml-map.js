/* KML Map Viewer v3 – frontend */
( function () {
    'use strict';

    // Campos KML internos que no se muestran en el popup
    var HIDDEN_FIELDS = [
        'stroke', 'stroke-opacity', 'fill-opacity',
        'tessellate', 'extrude', 'visibility'
    ];

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

    function initAll() {
        document.querySelectorAll( '.kml-map-canvas[data-kml-layers]' ).forEach( function ( el ) {
            try {
                var layers            = JSON.parse( el.getAttribute( 'data-kml-layers' ) );
                var visibleFields     = JSON.parse( el.getAttribute( 'data-kml-fields' ) || 'null' );
                var filterField       = el.getAttribute( 'data-kml-filter-field' ) || 'NUM_SOCIO';
                var filterValues      = JSON.parse( el.getAttribute( 'data-kml-filter-values' ) || '[]' );
                var filterValueBounds = JSON.parse( el.getAttribute( 'data-kml-filter-value-bounds' ) || '{}' );
                if ( Array.isArray( layers ) && layers.length ) {
                    initMap( el.id, layers, visibleFields, filterField, filterValues, filterValueBounds );
                }
            } catch ( e ) {
                console.error( 'kml-map: JSON inválido', e );
            }
        } );
    }

    if ( document.readyState === 'loading' ) {
        document.addEventListener( 'DOMContentLoaded', initAll );
    } else {
        initAll();
    }

    // -----------------------------------------------------------------------
    function initMap( uid, kmlLayers, visibleFields, filterField, filterValuesFromServer, filterValueBounds ) {

        // Canvas en vez de SVG (por defecto en Leaflet): con muchos objetos a
        // la vez el SVG crea un nodo del DOM por cada uno y se vuelve muy
        // pesado, sobre todo en móviles; Canvas dibuja todo en un único
        // elemento.
        var map = L.map( uid, { zoomControl: true, maxZoom: 22, renderer: L.canvas() } );

        // --- Capas base ---
        var osm = L.tileLayer(
            'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            { attribution: '© OpenStreetMap', maxZoom: 19 }
        );
        var satellite = L.tileLayer(
            'https://mt1.google.com/vt/lyrs=y&x={x}&y={y}&z={z}',
            { attribution: '© Google', maxZoom: 22, maxNativeZoom: 18 }
        );
        satellite.addTo( map );

        var baseLayers = {
            'Satélite (Google)': satellite,
            'OpenStreetMap': osm
        };

        // URL del endpoint que sirve los objetos de una capa por páginas (ver
        // kml_map_rest_get_features en PHP), inyectada por wp_localize_script.
        var restUrl = ( typeof KmlMapConfig !== 'undefined' && KmlMapConfig.restUrl ) ? KmlMapConfig.restUrl : null;

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

            function style() {
                return {
                    color:       strokeColor,
                    weight:      1.5,
                    fillColor:   fillColor,
                    // fillOpacity a 0 en vez de fill:false: el interior
                    // sigue siendo clicable para abrir el popup aunque no
                    // se pinte, solo se ve el borde.
                    fillOpacity: hasFill ? 0.6 : 0
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
                            + escHtml( String( v ) ) + '</td>'
                            + '</tr>';
                    }
                }
                // Indicar de qué capa viene
                rows += '<tr><td colspan="2" style="padding:3px 8px;font-size:11px;color:#888;font-style:italic">'
                    + 'Capa: ' + escHtml( kml.name ) + '</td></tr>';

                layer.bindPopup(
                    '<table style="border-collapse:collapse;font-size:13px;font-family:sans-serif">'
                    + rows + '</table>',
                    { maxHeight: 320 }
                );
            }

            var displayLayer = L.geoJSON( null, { style: style, onEachFeature: onEachFeature } );

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
                loading:       null      // { shown, total } mientras se siguen pidiendo páginas de esta capa
            };
            layerData.push( ld );

            overlays[ kml.name ] = displayLayer;
        } );

        // Vista inicial a partir de los límites ya conocidos (sin pedir nada
        // al servidor): el mapa aparece de inmediato.
        fitAll();

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
        var loadingIndicator = L.control( { position: 'bottomleft' } );
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
                    messages.push(
                        '⏳ ' + escHtml( ld.name ) + ': cargando objetos… (' + ld.loading.shown
                        + ' de ' + ld.loading.total + ')'
                    );
                }
            } );

            if ( messages.length ) {
                div.innerHTML = messages.join( '<br>' );
                div.style.display = 'block';
            } else {
                div.style.display = 'none';
            }
        }

        initFilterUI();

        // Todas las capas se añaden al mapa y empiezan a cargarse en cuanto
        // se abre el mapa; una vez cargado un objeto no se vuelve a tocar ni
        // se retira al hacer zoom o mover el mapa: se ve a cualquier nivel.
        layerData.forEach( function ( ld ) {
            map.addLayer( ld.displayLayer );
            fetchLayerFeatures( ld );
        } );

        function fetchLayerFeatures( ld ) {
            if ( ! restUrl ) return;

            var token = ++ld.requestSeq;

            ld.displayLayer.clearLayers();
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

            if ( currentFilter.length ) {
                url += '&filter_field=' + encodeURIComponent( filterField )
                    + '&filter_values=' + encodeURIComponent( currentFilter.join( ',' ) );
            }

            fetch( url )
                .then( function ( r ) { return r.json(); } )
                .then( function ( geojson ) {
                    if ( token !== ld.requestSeq ) return; // ha llegado tarde: ya no es la petición actual (p.ej. cambió el filtro)

                    if ( geojson && geojson.features && geojson.features.length ) {
                        ld.displayLayer.addData( geojson );
                    }

                    if ( geojson && geojson.has_more ) {
                        ld.loading = { shown: ld.displayLayer.getLayers().length, total: geojson.total };
                        updateLoadingIndicator();
                        fetchLayerPage( ld, token, page + 1 );
                    } else {
                        // Ya no quedan más páginas: la capa está completa.
                        ld.loading = null;
                        updateLoadingIndicator();
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

} )();
