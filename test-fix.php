<?php
/**
 * Script de Prueba del Fix - Corre dentro de WordPress
 *
 * Uso: Agregar shortcode [test_fix_garantias] en cualquier página
 */

add_shortcode('test_fix_garantias', 'test_fix_garantias_shortcode');

function test_fix_garantias_shortcode($atts) {
    if (!current_user_can('manage_options')) {
        return '<p style="color: red;">Acceso denegado.</p>';
    }

    ob_start();

    echo "<h1>Test del Fix de Balance</h1>";
    echo "<hr>";

    // 1. Verificar que la clase existe
    echo "<h2>1. Verificación de Clase</h2>";
    if (class_exists('WC_Garantias_Ajax')) {
        echo "<p style='color: green;'>✅ Clase WC_Garantias_Ajax EXISTE</p>";

        // Verificar métodos
        $reflection = new ReflectionClass('WC_Garantias_Ajax');
        $methods = $reflection->getMethods(ReflectionMethod::IS_PRIVATE | ReflectionMethod::IS_PUBLIC);
        $method_names = array_map(function($m) { return $m->name; }, $methods);

        echo "<p>Total de métodos: " . count($method_names) . "</p>";

        if (in_array('get_claimed_quantity_by_order', $method_names)) {
            echo "<p style='color: green;'>✅ Método get_claimed_quantity_by_order() EXISTE</p>";
        } else {
            echo "<p style='color: red;'>❌ Método get_claimed_quantity_by_order() NO EXISTE</p>";
            echo "<p>Métodos disponibles:</p>";
            echo "<ul>";
            foreach ($method_names as $method) {
                if (strpos($method, 'claimed') !== false || strpos($method, 'quantity') !== false) {
                    echo "<li><strong>$method</strong></li>";
                }
            }
            echo "</ul>";
        }

        // Verificar archivo físico
        $file_path = WC_GARANTIAS_PATH . 'includes/class-wc-garantias-ajax.php';
        if (file_exists($file_path)) {
            $mtime = filemtime($file_path);
            $date = date('Y-m-d H:i:s', $mtime);
            echo "<p>📁 Archivo físico: $date</p>";

            // Buscar la función en el archivo
            $content = file_get_contents($file_path);
            if (strpos($content, 'get_claimed_quantity_by_order') !== false) {
                echo "<p style='color: green;'>✅ Función get_claimed_quantity_by_order() EXISTE en el archivo físico</p>";
            } else {
                echo "<p style='color: red;'>❌ Función get_claimed_quantity_by_order() NO EXISTE en el archivo físico</p>";
            }
        }
    } else {
        echo "<p style='color: red;'>❌ Clase WC_Garantias_Ajax NO EXISTE</p>";
    }

    echo "<hr>";

    // 2. Probar el cálculo real
    echo "<h2>2. Prueba Real de Cálculo</h2>";

    $customer_id = 12748;
    $product_id = 48612;
    $order_id = 74510;

    echo "<p>Cliente ID: $customer_id</p>";
    echo "<p>Producto ID: $product_id</p>";
    echo "<p>Orden ID: $order_id</p>";
    echo "<hr>";

    // Obtener orden
    $order = wc_get_order($order_id);
    if ($order) {
        echo "<p style='color: green;'>✅ Orden encontrada</p>";

        // Buscar item en la orden
        $cantidad_comprada = 0;
        foreach ($order->get_items() as $item) {
            if ($item->get_product_id() == $product_id) {
                $cantidad_comprada = $item->get_quantity();
                echo "<p>Cantidad comprada en esta orden: <strong>$cantidad_comprada</strong></p>";
                break;
            }
        }

        if ($cantidad_comprada > 0) {
            // Intentar llamar al método nuevo
            if (class_exists('WC_Garantias_Ajax') && method_exists('WC_Garantias_Ajax', 'get_claimed_quantity_by_order')) {
                // Usar reflexión para llamar método privado
                $reflection = new ReflectionClass('WC_Garantias_Ajax');
                $method = $reflection->getMethod('get_claimed_quantity_by_order');
                $method->setAccessible(true);

                $cantidad_reclamada = $method->invokeArgs(null, [$customer_id, $product_id, $order_id]);
                $cantidad_disponible = $cantidad_comprada - $cantidad_reclamada;

                echo "<p>Cantidad reclamada de esta orden: <strong>$cantidad_reclamada</strong></p>";
                echo "<p style='font-size: 20px; color: blue;'>Cantidad disponible: <strong>$cantidad_disponible</strong></p>";

                if ($cantidad_disponible == 2) {
                    echo "<p style='color: green; font-size: 18px;'>✅ ¡FIX FUNCIONANDO! Muestra 2 disponibles correctamente</p>";
                } else {
                    echo "<p style='color: orange; font-size: 18px;'>⚠️ Resultado inesperado: debería ser 2</p>";
                }
            } else {
                echo "<p style='color: red;'>❌ No se puede probar: método no disponible</p>";
            }
        } else {
            echo "<p style='color: red;'>❌ Producto no encontrado en la orden</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Orden no encontrada</p>";
    }

    return ob_get_clean();
}
