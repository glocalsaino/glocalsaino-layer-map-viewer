=== KML-Map ===
Contributors: TU_USUARIO_WORDPRESSORG
Tags: kml, map, leaflet, gis, kmz
Requires at least: 5.8
Tested up to: 6.7
Stable tag: 4.4.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Sube archivos KML y muéstralos como mapas interactivos con capas de colores y filtro por campo, sin bloquear el navegador aunque tengan miles de objetos.

== Description ==

KML-Map convierte cualquier archivo KML (el formato que exportan QGIS, Google Earth y la mayoría de programas GIS) en un mapa interactivo insertable en cualquier página o entrada mediante un shortcode.

Pensado desde el principio para archivos KML grandes (decenas de miles de objetos): en vez de hacer que el navegador descargue y procese el archivo entero, el propio plugin lo analiza en segundo plano al subirlo y construye un índice; el visor pide después los objetos al servidor por páginas, sin llegar a congelar la pestaña ni en móviles de gama baja.

= Funciones principales =

* Sube uno o varios archivos KML y créales un mapa con el shortcode `[kml_map id="X"]`.
* Cada capa se pinta en su propio color, elegible al subirla.
* Opción de mostrar cada capa solo con el borde (sin relleno).
* Filtro interactivo por un campo del KML (por ejemplo, un código de parcela o de municipio), con el desplegable ya calculado en el servidor.
* Popup con los datos del objeto al hacer clic.
* Capa base de OpenStreetMap y de satélite.
* Análisis y construcción del índice en segundo plano (WP-Cron): subir un KML de varios miles de objetos no bloquea el panel de administración.
* Carga por páginas en el navegador: los objetos aparecen progresivamente sin llegar a colgar la pestaña, y una vez cargados no desaparecen al cambiar el zoom.

= Versión Premium =

La versión gratuita permite crear hasta 3 mapas, cada uno con varias capas KML a la vez, y verlos con sus colores por defecto. La versión premium añade:

* Mapas ilimitados (la gratuita se limita a 3).
* Añadir más capas KML a un mapa que ya existe, sin tener que volver a crearlo.
* Elegir el campo por el que se filtra y qué campos del KML se muestran en el popup (en la versión gratuita no hay filtro configurado).
* Personalizar los colores de la caja de filtro y del botón "Limpiar filtro".

== Installation ==

1. Sube la carpeta del plugin a `/wp-content/plugins/` o instálalo desde el panel de administración (Plugins → Añadir nuevo).
2. Actívalo desde el menú "Plugins".
3. Ve al nuevo menú "Mapas KML" del panel de administración y crea tu primer mapa subiendo uno o varios archivos `.kml`.
4. Copia el shortcode que se muestra (por ejemplo `[kml_map id="1"]`) y pégalo en cualquier página o entrada.

== Frequently Asked Questions ==

= ¿Admite archivos KMZ? =

No, de momento solo `.kml` (KMZ es un KML comprimido en ZIP; habría que descomprimirlo primero).

= ¿Cuántos objetos aguanta un archivo KML? =

Se ha probado con capas de más de 60.000 objetos. El análisis se hace en segundo plano al subir el archivo y el visor los carga por páginas, así que el tamaño del archivo no bloquea ni el panel de administración ni el navegador de quien visita la página.

= ¿Por qué no aparecen los objetos nada más subir el archivo? =

El KML se analiza en segundo plano (WP-Cron) para no bloquear la subida. Mientras se completa, el mapa aparece con una vista general y el badge "Procesando capas en segundo plano"; si tarda más de lo esperado, el botón "Analizar ahora" fuerza el análisis inmediatamente.

= ¿Qué campo se usa para el filtro si no configuro ninguno? =

Ninguno: en la versión gratuita no hay filtro configurado y la caja de filtro no aparece. Elegir el campo por el que se filtra es una función premium (ver "Campos del popup" en el panel de administración).

= ¿Cuántos mapas puedo crear? =

Hasta 3 con la versión gratuita. La versión premium permite crear mapas ilimitados.

== Screenshots ==

1. Mapa interactivo en el front-end, con varias capas y filtro por campo.
2. Panel de administración: formulario de creación de un mapa y listado de un mapa ya creado.
3. Panel de administración: funciones premium desplegadas (añadir más capas, campos del popup y aspecto de la caja de filtro).

== Changelog ==

= 4.4.0 =
* Internacionalización: todos los textos del plugin (admin y front-end) son ahora traducibles.
* La capa de satélite pasa de un endpoint no documentado de Google a Esri World Imagery, con términos de uso claros para este caso.
* Plugin renombrado a "KML-Map".

= 4.3.0 =
* Límite de 3 mapas en la versión gratuita (ilimitados en premium).
* Nueva función premium: personalizar los colores de la caja de filtro y del botón "Limpiar filtro".
* El campo de filtro ya no tiene un valor por defecto ("NUM_SOCIO"): en la versión gratuita no hay filtro hasta activar la premium y elegirlo en "Campos del popup".

= 4.2.0 =
* Preparación para WordPress.org: Leaflet se sirve empaquetado con el plugin en vez de desde un CDN externo.
* Integración con Freemius para la versión premium (añadir capas a un mapa existente y configurar los campos del popup).
* Revisión de sanitización de entradas y escapado de salida.

= 4.1.0 =
* Nueva opción por capa para pintar solo el borde, sin relleno.

= 4.0.1 =
* Corrección de acentos y eñes corruptos en el desplegable de filtro.

= 4.0.0 =
* Simplificación de la carga: cada capa se carga entera por páginas en cuanto se abre el mapa y ya no desaparece al cambiar el zoom.

= 3.7.0 y anteriores =
* Índice espacial en el servidor y endpoint paginado para evitar bloqueos con capas muy densas.
* Análisis en segundo plano (WP-Cron) para no bloquear la subida de archivos grandes.

== Upgrade Notice ==

= 4.4.0 =
La capa de satélite pasa de Google a Esri World Imagery: mismo uso, la imagen puede variar ligeramente según la zona.

= 4.3.0 =
Si tenías el filtro funcionando por el campo "NUM_SOCIO" por defecto sin haberlo elegido tú, tras esta actualización deja de mostrarse; actívalo de nuevo en "Campos del popup" (función premium).

= 4.2.0 =
Cambia de dónde se sirve Leaflet (ahora local en vez de un CDN externo); no requiere ninguna acción manual.
