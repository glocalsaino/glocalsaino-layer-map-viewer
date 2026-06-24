/* KML Map Viewer v2 – frontend */
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
                var layers        = JSON.parse( el.getAttribute( 'data-kml-layers' ) );
                var visibleFields = JSON.parse( el.getAttribute( 'data-kml-fields' ) || 'null' );
                var filterField   = el.getAttribute( 'data-kml-filter-field' ) || 'NUM_SOCIO';
                if ( Array.isArray( layers ) && layers.length ) {
                    initMap( el.id, layers, visibleFields, filterField );
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
    function initMap( uid, kmlLayers, visibleFields, filterField ) {

        var map = L.map( uid, { zoomControl: true, maxZoom: 22 } );

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
            'Sat\u00e9lite (Google)': satellite,
            'OpenStreetMap': osm
        };

        // --- Cargar cada KML como capa de overlay independiente ---
        var layerData   = [];   // [{features, displayLayer}, ...]
        var overlays    = {};   // { 'nombre': displayLayer }
        var loadedCount = 0;

        kmlLayers.forEach( function ( kml, i ) {
            var palette      = COLORS[ i % COLORS.length ];
            var fillColor    = ( kml.color && kml.color.length === 7 ) ? kml.color         : palette.fill;
            var strokeColor  = ( kml.color && kml.color.length === 7 ) ? darken(kml.color, 0.3) : palette.stroke;

            var displayLayer = L.geoJSON( null, {
                style: function () {
                    return {
                        color:       strokeColor,
                        weight:      1.5,
                        fillColor:   fillColor,
                        fillOpacity: 0.6
                    };
                },
                onEachFeature: function ( feature, layer ) {
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
            } ).addTo( map );

            var features = [];
            layerData.push( { features: features, displayLayer: displayLayer } );
            overlays[ kml.name ] = displayLayer;

            // Cargar KML con omnivore usando displayLayer directamente
            omnivore.kml( kml.url, null, displayLayer )
                .on( 'ready', function () {
                    displayLayer.eachLayer( function ( l ) {
                        if ( l.feature ) features.push( l.feature );
                    } );

                    loadedCount++;
                    if ( loadedCount === kmlLayers.length ) {
                        onAllLoaded();
                    }
                } )
                .on( 'error', function () {
                    console.error( 'kml-map [' + uid + ']: error al cargar', kml.url );
                    loadedCount++;
                    if ( loadedCount === kmlLayers.length ) onAllLoaded();
                } );
        } );

        // --- Cuando todas las capas han cargado ---
        function onAllLoaded() {
            // Control de capas (base + overlays)
            L.control.layers( baseLayers, overlays, {
                position:  'topright',
                collapsed: true
            } ).addTo( map );

            // Ajustar vista a todas las capas
            fitAll();

            // Construir filtro con todos los valores únicos del campo de filtrado
            buildFilter();

            setTimeout( function () { map.invalidateSize(); }, 150 );
        }

        // --- Ajustar mapa a todos los layers visibles ---
        function fitAll() {
            var validLayers = layerData
                .map( function ( d ) { return d.displayLayer; } )
                .filter( function ( l ) {
                    try { return l.getBounds().isValid(); } catch(e) { return false; }
                } );

            if ( validLayers.length ) {
                var group = L.featureGroup( validLayers );
                if ( group.getBounds().isValid() ) {
                    map.fitBounds( group.getBounds(), { padding: [ 20, 20 ] } );
                }
            }
        }

        // --- Filtro por campo configurable ---
        function buildFilter() {
            var values = [];
            layerData.forEach( function ( d ) {
                d.features.forEach( function ( f ) {
                    var v = f.properties && f.properties[ filterField ];
                    if ( v != null && v !== '' && values.indexOf( String( v ) ) === -1 ) {
                        values.push( String( v ) );
                    }
                } );
            } );
            values.sort();

            var sel = document.getElementById( uid + '-sel' );
            if ( ! sel ) return;

            sel.size = Math.min( values.length, 6 ) || 1;
            values.forEach( function ( v ) {
                var opt       = document.createElement( 'option' );
                opt.value     = v;
                opt.textContent = v;
                sel.appendChild( opt );
            } );

            sel.addEventListener( 'change', applyFilter );

            var clearBtn = document.getElementById( uid + '-clear' );
            if ( clearBtn ) {
                clearBtn.addEventListener( 'click', function () {
                    for ( var i = 0; i < sel.options.length; i++ ) sel.options[ i ].selected = false;
                    applyFilter();
                } );
            }
        }

        function applyFilter() {
            var sel      = document.getElementById( uid + '-sel' );
            var selected = [];
            for ( var i = 0; i < sel.options.length; i++ ) {
                if ( sel.options[ i ].selected ) selected.push( sel.options[ i ].value );
            }

            layerData.forEach( function ( d ) {
                var filtered = selected.length === 0
                    ? d.features
                    : d.features.filter( function ( f ) {
                        return selected.indexOf( String( f.properties[ filterField ] ) ) >= 0;
                    } );

                d.displayLayer.clearLayers();
                if ( filtered.length ) {
                    d.displayLayer.addData( { type: 'FeatureCollection', features: filtered } );
                }
            } );

            // Ajustar vista solo a las capas actualmente visibles en el mapa
            var visibleLayers = layerData
                .filter( function ( d ) { return map.hasLayer( d.displayLayer ); } )
                .map( function ( d ) { return d.displayLayer; } )
                .filter( function ( l ) {
                    try { return l.getBounds().isValid(); } catch(e) { return false; }
                } );

            if ( visibleLayers.length ) {
                var group = L.featureGroup( visibleLayers );
                if ( group.getBounds().isValid() ) {
                    map.fitBounds( group.getBounds(), { padding: [ 20, 20 ] } );
                }
            }
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
