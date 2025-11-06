<?php
/**
 * Script de Diagnóstico de Garantías
 *
 * Uso: Agregar shortcode [diagnostico_garantias customer_id=12748 product_id=48612]
 * en cualquier página o post de WordPress
 */

// Registrar shortcode
add_shortcode('diagnostico_garantias', 'wc_garantias_diagnostic_shortcode');

function wc_garantias_diagnostic_shortcode($atts) {
    // Verificar que sea admin
    if (!current_user_can('manage_options')) {
        return '<p style="color: red;">Acceso denegado. Solo administradores pueden ver este diagnóstico.</p>';
    }

    $atts = shortcode_atts([
        'customer_id' => 12748,
        'product_id' => 48612
    ], $atts);

    $customer_id = intval($atts['customer_id']);
    $product_id = intval($atts['product_id']);

    ob_start();

echo "<h1>Diagnóstico de Garantías</h1>";
echo "<p>Cliente ID: $customer_id | Producto ID: $product_id</p>";
echo "<hr>";

// 1. Obtener garantías del cliente
$args = [
    'post_type' => 'garantia',
    'post_status' => 'publish',
    'meta_query' => [
        ['key' => '_cliente', 'value' => $customer_id],
    ],
    'posts_per_page' => -1,
];

$garantias = get_posts($args);

echo "<h2>Garantías encontradas: " . count($garantias) . "</h2>";

$duracion_garantia = get_option('duracion_garantia', 180);
$fecha_limite = strtotime("-{$duracion_garantia} days");

echo "<p>Duración de garantía configurada: $duracion_garantia días</p>";
echo "<p>Fecha límite: " . date('Y-m-d', $fecha_limite) . "</p>";
echo "<hr>";

foreach ($garantias as $garantia) {
    $codigo_unico = get_post_meta($garantia->ID, '_codigo_unico', true);
    echo "<h3>Garantía #{$garantia->ID}" . ($codigo_unico ? " - $codigo_unico" : '') . "</h3>";

    // Obtener items
    $items = get_post_meta($garantia->ID, '_items_reclamados', true) ?: [];
    echo "<p>Items en esta garantía: " . count($items) . "</p>";

    // Verificar si hay order_id a nivel de post (incorrecto)
    $order_id_post = get_post_meta($garantia->ID, '_order_id', true);
    echo "<p>_order_id a nivel de post: " . ($order_id_post ? $order_id_post : '<strong>NO EXISTE</strong>') . "</p>";

    echo "<table border='1' cellpadding='5' style='margin-bottom: 20px;'>";
    echo "<tr><th>Producto</th><th>Nombre</th><th>Cantidad</th><th>order_id en item</th><th>Fecha Orden</th><th>¿Válido?</th></tr>";

    foreach ($items as $index => $item) {
        $item_product_id = $item['producto_id'] ?? 'N/A';
        $item_cantidad = $item['cantidad'] ?? 1;
        $item_order_id = $item['order_id'] ?? null;

        $product = wc_get_product($item_product_id);
        $product_name = $product ? $product->get_name() : 'Producto no encontrado';

        $order_info = 'N/A';
        $es_valido = 'N/A';

        if ($item_order_id) {
            $order = wc_get_order($item_order_id);
            if ($order) {
                $order_time = strtotime($order->get_date_completed() ?
                    $order->get_date_completed()->date('Y-m-d H:i:s') :
                    $order->get_date_created()->date('Y-m-d H:i:s')
                );
                $order_info = date('Y-m-d', $order_time);
                $es_valido = ($order_time >= $fecha_limite) ? '✓ SÍ' : '✗ NO (antigua)';
            } else {
                $order_info = 'Orden no existe';
                $es_valido = '✗ NO';
            }
        } else {
            $order_info = '<strong>NO TIENE order_id</strong>';
            $es_valido = '<strong>✗ NO (sin order_id)</strong>';
        }

        $highlight = ($item_product_id == $product_id) ? ' style="background-color: yellow;"' : '';

        echo "<tr$highlight>";
        echo "<td>$item_product_id</td>";
        echo "<td>$product_name</td>";
        echo "<td>$item_cantidad</td>";
        echo "<td>" . ($item_order_id ?: '<strong>NULL</strong>') . "</td>";
        echo "<td>$order_info</td>";
        echo "<td>$es_valido</td>";
        echo "</tr>";
    }

    echo "</table>";
}

echo "<hr>";
echo "<h2>Órdenes del cliente</h2>";

$orders = wc_get_orders([
    'customer_id' => $customer_id,
    'status' => 'completed',
    'limit' => -1,
]);

echo "<p>Total de órdenes completadas: " . count($orders) . "</p>";

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>Orden ID</th><th>Fecha</th><th>¿Válida?</th><th>Items</th></tr>";

foreach ($orders as $order) {
    $order_time = strtotime($order->get_date_completed() ?
        $order->get_date_completed()->date('Y-m-d H:i:s') :
        $order->get_date_created()->date('Y-m-d H:i:s')
    );

    $es_valida = ($order_time >= $fecha_limite) ? '✓ SÍ' : '✗ NO (antigua)';

    $tiene_producto = false;
    $items_info = [];
    foreach ($order->get_items() as $item) {
        $item_product_id = $item->get_product_id();
        $item_cantidad = $item->get_quantity();
        $product = wc_get_product($item_product_id);
        $product_name = $product ? $product->get_name() : 'Producto no encontrado';

        if ($item_product_id == $product_id) {
            $tiene_producto = true;
            $items_info[] = "<strong>$product_name (ID: $item_product_id) x $item_cantidad</strong>";
        } else {
            $items_info[] = "$product_name (ID: $item_product_id) x $item_cantidad";
        }
    }

    $highlight = $tiene_producto ? ' style="background-color: yellow;"' : '';

    echo "<tr$highlight>";
    echo "<td>{$order->get_id()}</td>";
    echo "<td>" . date('Y-m-d', $order_time) . "</td>";
    echo "<td>$es_valida</td>";
    echo "<td>" . implode('<br>', $items_info) . "</td>";
    echo "</tr>";
}

echo "</table>";

    return ob_get_clean();
}