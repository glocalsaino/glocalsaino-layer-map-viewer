<?php
/**
 * Autoloader mínimo para las dependencias de Composer de este plugin.
 *
 * No se generó con "composer install" (esta máquina no tiene un PHP con la
 * extensión phar disponible para ejecutar Composer), pero el resultado es
 * funcionalmente idéntico: el composer.json del propio SDK de Freemius
 * declara "autoload": { "files": ["start.php"] }, es decir, un simple
 * require de ese archivo, sin autoload de clases por namespace. Si en algún
 * momento se ejecuta "composer install" en un entorno con phar, este archivo
 * se sobrescribirá con el generado por Composer sin cambiar el resultado.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once __DIR__ . '/freemius/wordpress-sdk/start.php';
