<?php
/**
 * Script de Limpieza de Caché
 *
 * Uso: https://wifixargentina.com.ar/wp-content/plugins/WooCommerce-Garantias/clear-cache.php?token=CACHE_CLEAR_2025
 *
 * Limpia todos los cachés posibles para forzar recarga del código PHP
 */

// Token de seguridad
define('CACHE_TOKEN', 'CACHE_CLEAR_2025');

// Verificar token
$token = $_GET['token'] ?? '';
if ($token !== CACHE_TOKEN) {
    http_response_code(403);
    die('Forbidden');
}

$results = [];

// 1. Limpiar OPcache
if (function_exists('opcache_reset')) {
    opcache_reset();
    $results[] = '✅ OPcache cleared';
} else {
    $results[] = '⚠️ OPcache not available';
}

// 2. Limpiar Realpath Cache
clearstatcache(true);
$results[] = '✅ Realpath cache cleared';

// 3. Verificar archivos modificados recientemente
$files_to_check = [
    __DIR__ . '/includes/class-wc-garantias-ajax.php',
    __DIR__ . '/diagnostic-garantias.php',
];

$results[] = '';
$results[] = '📁 Archivos verificados:';

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $mtime = filemtime($file);
        $date = date('Y-m-d H:i:s', $mtime);
        $results[] = "  - " . basename($file) . ": $date";
    } else {
        $results[] = "  - " . basename($file) . ": NO EXISTE";
    }
}

// 4. Verificar si la función nueva existe
$results[] = '';
if (class_exists('WC_Garantias_Ajax')) {
    $results[] = '✅ Clase WC_Garantias_Ajax existe';

    // Verificar métodos
    $reflection = new ReflectionClass('WC_Garantias_Ajax');
    $methods = $reflection->getMethods();
    $method_names = array_map(function($m) { return $m->name; }, $methods);

    if (in_array('get_claimed_quantity_by_order', $method_names)) {
        $results[] = '✅ Método get_claimed_quantity_by_order() existe';
    } else {
        $results[] = '❌ Método get_claimed_quantity_by_order() NO EXISTE';
    }
} else {
    $results[] = '❌ Clase WC_Garantias_Ajax NO EXISTE';
}

// 5. Mostrar info de PHP
$results[] = '';
$results[] = '🔧 Info del servidor:';
$results[] = '  - PHP: ' . PHP_VERSION;
$results[] = '  - OPcache enabled: ' . (ini_get('opcache.enable') ? 'YES' : 'NO');
$results[] = '  - OPcache validate: ' . ini_get('opcache.revalidate_freq') . 's';
$results[] = '  - Timestamp: ' . date('Y-m-d H:i:s');

// Respuesta
http_response_code(200);
header('Content-Type: text/plain; charset=utf-8');
echo implode("\n", $results);
