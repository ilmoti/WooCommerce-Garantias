<?php

/*
Plugin Name: WooCommerce Garantías
Description: Gestión avanzada de garantías para WooCommerce con generación de cupones, panel de cliente y administración.
Version: 5.51
Author: WiFix Development
*/

add_action('plugins_loaded', function() {

});

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WC_GARANTIAS_PATH', plugin_dir_path( __FILE__ ) );
define( 'WC_GARANTIAS_URL', plugin_dir_url( __FILE__ ) );

// ARCHIVOS A INCLUIR
$includes = [
    'includes/class-wc-garantias-init.php',
    'includes/class-wc-garantias-processor.php',
    'includes/class-wc-garantias-customer.php',
    'includes/class-wc-garantias-admin.php',
    'includes/class-wc-garantias-emails.php',
    'includes/class-wc-garantias-ajax.php',
    'includes/class-wc-garantias-timeline.php',
    'includes/class-wc-garantias-integrations.php',
    'includes/class-wc-garantias-dashboard.php',
    'includes/class-wc-garantias-motivos.php',
    'includes/class-wc-garantias-admin-badge.php',
    'includes/class-wc-garantias-historial.php',
    'includes/class-wc-garantias-admin-metabox.php' ,
    'includes/class-wc-garantias-notifications.php' ,
    'includes/class-wc-garantias-cupones.php',
    'includes/admin/class-wc-garantias-admin-cupones.php',
    'includes/class-wc-garantias-etiqueta.php',
    'includes/class-wc-garantias-whatsapp.php',
    'includes/class-wc-garantias-rma.php',
    'includes/class-wc-garantias-cron-rma.php',
    'includes/class-wc-garantias-ajax-rma.php',
    'includes/class-wc-garantias-rma-cart.php',
    'includes/class-wc-garantias-andreani.php',
    'includes/class-wc-garantias-recepcion-parcial.php',
    'includes/class-wc-garantias-recepcion-parcial-ui.php',
    'includes/class-wc-garantias-recepcion-parcial-cron.php',
    'diagnostic-garantias.php',
    'test-fix.php',
];

foreach ( $includes as $file ) {
    $filepath = WC_GARANTIAS_PATH . $file;
    if ( file_exists( $filepath ) ) {
        require_once $filepath;
    } else {
    }
}

// Iniciar el plugin principal
if ( class_exists( 'WC_Garantias_Init' ) ) {
    add_action( 'plugins_loaded', array( 'WC_Garantias_Init', 'init' ) );
} else {

}

// Asegura el panel de garantas SIEMPRE!
if ( class_exists( 'WC_Garantias_Admin' ) ) {
    WC_Garantias_Admin::init();
}

// ¡Asegura el panel de garantas en el dashboard del usuario!
if ( class_exists( 'WC_Garantias_Customer' ) ) {
    WC_Garantias_Customer::init();
}

// Inicializar manejo de cupones
if ( class_exists( 'WC_Garantias_Cupones' ) ) {
    WC_Garantias_Cupones::init();
}

// Inicializar admin de cupones
if ( class_exists( 'WC_Garantias_Admin_Cupones' ) ) {
    WC_Garantias_Admin_Cupones::init();
}

// Asegura las funcionalidades AJAX!
if ( class_exists( 'WC_Garantias_Ajax' ) ) {
    WC_Garantias_Ajax::init();
}

// Asegura el sistema de emails!
if ( class_exists( 'WC_Garantias_Emails' ) ) {
    WC_Garantias_Emails::init();
}

// Inicializar WhatsApp si existe la clase
if ( class_exists( 'WC_Garantias_WhatsApp' ) ) {
    WC_Garantias_WhatsApp::init();
}

// Inicializar integración con Andreani
if ( class_exists( 'WC_Garantias_Andreani' ) ) {
    WC_Garantias_Andreani::init();
}

// Inicializar AJAX RMA
if ( class_exists( 'WC_Garantias_Ajax_RMA' ) ) {
    WC_Garantias_Ajax_RMA::init();
} else {
}

// Inicializar CRON RMA
if ( class_exists( 'WC_Garantias_Cron_RMA' ) ) {
    WC_Garantias_Cron_RMA::init();
} else {
}

// Registrar cron para verificar timeouts
add_action('init', function() {
    if (!wp_next_scheduled('wc_garantias_check_timeouts')) {
        wp_schedule_event(time(), 'hourly', 'wc_garantias_check_timeouts');
    }
});

// Hook para ejecutar la verificación
add_action('wc_garantias_check_timeouts', function() {
    if (class_exists('WC_Garantias_Processor')) {
        WC_Garantias_Processor::check_info_timeouts();
    }
});

// Limpiar cron al desactivar
register_deactivation_hook(__FILE__, function() {
    wp_clear_scheduled_hook('wc_garantias_check_timeouts');
});

// Hook de activación para registrar endpoints
register_activation_hook(__FILE__, function() {
    // Registrar endpoints
    if (class_exists('WC_Garantias_Customer')) {
        WC_Garantias_Customer::add_garantias_endpoint();
        WC_Garantias_Customer::add_cupones_endpoint();
    }
    // Flush rewrite rules
    flush_rewrite_rules();
});

/**
 * Encola el CSS responsive de Garantas (solo en Mi Cuenta).
 */
function wcgarantias_enqueue_responsive_css() {
    if ( ! is_account_page() ) {
        return;
    }

    // Usa las constantes definidas al inicio del plugin
    $css_path = WC_GARANTIAS_PATH . 'assets/css/garantias-responsive.css';
    $css_url  = WC_GARANTIAS_URL  . 'assets/css/garantias-responsive.css';

    if ( file_exists( $css_path ) ) {
        wp_enqueue_style(
            'wc-garantias-responsive',    // handle único
            $css_url,                     // URL pblica
            array( 'woodmart-style' ),    // depende del CSS principal de Woodmart
            filemtime( $css_path )        // versin por timestamp
        );
    }
}
add_action( 'wp_enqueue_scripts', 'wcgarantias_enqueue_responsive_css', 100 );

add_action('add_meta_boxes', function() {
    add_meta_box(
        'garantia_items_reclamados',
        'tems Reclamados',
        function($post) {
            $items = get_post_meta($post->ID, '_items_reclamados', true);
            if ($items && is_array($items)) {
                echo '<table class="widefat striped"><thead>
                        <tr>
                            <th>Cdigo ítem</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Motivo</th>
                            <th>Foto</th>
                            <th>Video</th>
                            <th>N Orden</th>
                        </tr>
                    </thead><tbody>';
                foreach ($items as $item) {
                    $prod = wc_get_product($item['producto_id']);
                    echo '<tr>';
                    echo '<td>' . esc_html($item['codigo_item']) . '</td>';
                    echo '<td>' . ($prod ? esc_html($prod->get_name()) : 'Producto eliminado') . '</td>';
                    echo '<td>' . esc_html($item['cantidad']) . '</td>';
                    echo '<td>' . esc_html($item['motivo']) . '</td>';
                    echo '<td>';
                    if (!empty($item['foto_url'])) {
                        echo '<a href="' . esc_url($item['foto_url']) . '" target="_blank">Ver foto</a>';
                    }
                    echo '</td>';
                    echo '<td>';
                    if (!empty($item['video_url'])) {
                        echo '<a href="' . esc_url($item['video_url']) . '" target="_blank">Ver video</a>';
                    }
                    echo '</td>';
                    echo '<td>' . esc_html($item['order_id']) . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            } else {
                echo "<em>No hay tems reclamados en este reclamo.</em>";
            }
        },
        'garantia',
        'normal',
        'high'
    );
});

add_filter('manage_garantia_posts_columns', function($columns) {
    $columns['items_count'] = 'tems reclamados';
    return $columns;
});

add_action('manage_garantia_posts_custom_column', function($column, $post_id) {
    if ($column === 'items_count') {
        $items = get_post_meta($post_id, '_items_reclamados', true);
        echo is_array($items) ? count($items) : 0;
    }

// ========== PROCESAMIENTO DIRECTO DE FORMULARIO GARANTAS ==========
add_action('init', 'wc_garantias_process_form_direct');

function wc_garantias_process_form_direct() {
    WC_Garantias_Processor::process_new_warranty_form();
}

// Mostrar mensaje de éxito
add_action('woocommerce_account_content', 'wc_garantias_show_success_message');
function wc_garantias_show_success_message() {
    if (isset($_GET['garantia_success'])) {
        echo '<div class="woocommerce-message">¡Reclamo enviado correctamente!</div>';
    }
}

// ========== ENDPOINT SIMPLE ==========
add_filter('woocommerce_account_menu_items', 'wc_garantias_add_menu_simple', 40);
function wc_garantias_add_menu_simple($menu_items) {
    $new_items = array();
    foreach ($menu_items as $key => $item) {
        $new_items[$key] = $item;
        if ($key === 'orders') {
            $new_items['garantias'] = 'Garantas';
        }
    }
    return $new_items;
}

add_action('init', 'wc_garantias_add_endpoint_simple');
function wc_garantias_add_endpoint_simple() {
    add_rewrite_endpoint('garantias', EP_ROOT | EP_PAGES);
}

add_action('woocommerce_account_garantias_endpoint', 'wc_garantias_endpoint_simple');
function wc_garantias_endpoint_simple() {
    include WC_GARANTIAS_PATH . 'templates/myaccount-garantias.php';
}
}, 10, 2);

// Funcin para enviar solo email (sin WhatsApp)
function enviar_solo_email_admin($tipo, $destinatario, $variables = []) {
    $asunto = "Notificacin de garanta - " . ($variables['codigo'] ?? 'Sin cdigo');
    $mensaje = "Se ha descargado una etiqueta de envío para la garanta: " . ($variables['codigo'] ?? 'Sin cdigo');
    $mensaje .= "\nTipo de usuario: " . ($variables['tipo_usuario'] ?? 'No especificado');
    $mensaje .= "\nCantidad de items: " . ($variables['cantidad'] ?? '0');
    
    wp_mail($destinatario, $asunto, $mensaje);
}

// Handler AJAX mejorado para actualizar estado de todos los items del grupo
add_action('wp_ajax_actualizar_estado_transito_grupo', 'handle_actualizar_estado_transito_grupo');
function handle_actualizar_estado_transito_grupo() {
    // Verificar nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'actualizar_transito')) {
        wp_send_json_error('Seguridad fallida');
        return;
    }
    
    $garantia_id = intval($_POST['garantia_id']);
    $item_index = intval($_POST['item_index']);
    $user_id = get_current_user_id();
    
    // Verificar que el usuario es dueo de la garanta
    $garantia = get_post($garantia_id);
    if (!$garantia || $garantia->post_author != $user_id) {
        wp_send_json_error('No autorizado');
        return;
    }
    
    // Verificar si el usuario es distribuidor
    $user = wp_get_current_user();
    $es_distribuidor = false;
    foreach ($user->roles as $role) {
        if (in_array($role, ['distri10', 'distri20', 'distri30', 'superdistri30'])) {
            $es_distribuidor = true;
            break;
        }
    }
    

    // Obtener los items
    $items = get_post_meta($garantia_id, '_items_reclamados', true);
    $items_actualizados = 0;
    
    if (is_array($items)) {
    }
    
    if (is_array($items)) {
        if ($es_distribuidor) {
            // DISTRIBUIDOR: Actualizar TODOS los items pendientes
            foreach ($items as $idx => &$item) {
                // Si no tiene estado, asumimos que es Pendiente
                if (!isset($item['estado']) || $item['estado'] === 'Pendiente') {
                    $item['estado'] = 'devolucion_en_transito';
                    $item['fecha_descarga_etiqueta'] = current_time('mysql');
                    $items_actualizados++;
                }
            }
        } else {
            // CLIENTE FINAL: Mantener lgica actual con grupos
            if (isset($items[$item_index])) {
                $etiqueta_grupo_id = isset($items[$item_index]['etiqueta_grupo_id']) 
                    ? $items[$item_index]['etiqueta_grupo_id'] 
                    : null;
                
                if ($etiqueta_grupo_id) {
                    foreach ($items as $idx => &$item) {
                        if (isset($item['etiqueta_grupo_id']) && 
                            $item['etiqueta_grupo_id'] === $etiqueta_grupo_id &&
                            $item['estado'] === 'aprobado_devolver') {
                            
                            $item['estado'] = 'devolucion_en_transito';
                            $item['fecha_descarga_etiqueta'] = current_time('mysql');
                            $items_actualizados++;
                        }
                    }
                } else {
                    $items[$item_index]['estado'] = 'devolucion_en_transito';
                    $items[$item_index]['fecha_descarga_etiqueta'] = current_time('mysql');
                    $items_actualizados = 1;
                }
            }
        }
        
        // Guardar los cambios
        update_post_meta($garantia_id, '_items_reclamados', $items);
        
        if ($items_actualizados > 0) {
            update_post_meta($garantia_id, '_estado', 'en_proceso');
        }
        
        // Notificar al admin
        if ($items_actualizados > 0) {
            $admin_email = get_option('admin_email_garantias', get_option('admin_email'));
            $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
            $tipo_usuario = $es_distribuidor ? 'distribuidor' : 'cliente';
            
            // USAR FUNCIN QUE SOLO ENVÍA EMAIL (sin WhatsApp)
            enviar_solo_email_admin('admin_etiqueta_descargada', $admin_email, [
                'tipo_usuario' => $tipo_usuario,
                'cantidad' => $items_actualizados,
                'codigo' => $codigo_unico
            ]);
        }
        
        wp_send_json_success([
            'message' => 'Estado actualizado',
            'items_actualizados' => $items_actualizados
        ]);
    }
    
    wp_send_json_error('Error al actualizar');
}

// ========== FORZAR OCULTACIÓN DE GRT-ITEM DESPUÉS DE SINCRONIZACIÓN CRM ==========

// 1. Hook con prioridad MUY BAJA para ejecutarse DESPUÉS de todo
add_action('wp_loaded', 'wc_garantias_verificar_visibilidad_grt', 999);
add_action('init', 'wc_garantias_verificar_visibilidad_grt', 999);
add_action('wp', 'wc_garantias_verificar_visibilidad_grt', 999);

function wc_garantias_verificar_visibilidad_grt() {
    // Solo ejecutar cada 60 segundos para no sobrecargar
    $ultima_verificacion = get_transient('wc_garantias_ultima_verificacion_grt');
    if ($ultima_verificacion) {
        return;
    }
    
    // Marcar que se ejecutó
    set_transient('wc_garantias_ultima_verificacion_grt', true, 60);
    
    // Verificar y ocultar productos GRT-ITEM que no estn ocultos
    wc_garantias_forzar_ocultacion_grt();
}

// 2. Función que verifica y oculta
function wc_garantias_forzar_ocultacion_grt() {
    global $wpdb;
    
    // Buscar productos GRT-ITEM que NO estén ocultos
    $sql = "SELECT DISTINCT pm.post_id 
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_alg_ean' 
            AND pm.meta_value LIKE 'GRT-ITEM-%'
            AND p.post_type = 'product'
            AND p.post_status = 'publish'
            AND NOT EXISTS (
                SELECT 1 FROM {$wpdb->term_relationships} tr
                INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                INNER JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE tr.object_id = pm.post_id
                AND tt.taxonomy = 'product_visibility'
                AND t.slug IN ('exclude-from-catalog', 'exclude-from-search')
            )";
    
    $productos_visibles = $wpdb->get_col($sql);
    
    if (!empty($productos_visibles)) {
        foreach ($productos_visibles as $product_id) {
            // Forzar ocultación
            wp_set_object_terms($product_id, array('exclude-from-catalog', 'exclude-from-search'), 'product_visibility', false);
        }
        
        // Limpiar cach de WooCommerce
        if (function_exists('wc_delete_product_transients')) {
            wc_delete_product_transients();
        }
    }
}

// 3. Hook en TODOS los posibles puntos donde el CRM podría sincronizar
add_action('woocommerce_api_create_product', 'wc_garantias_despues_api', 999, 2);
add_action('woocommerce_api_edit_product', 'wc_garantias_despues_api', 999, 2);
add_action('woocommerce_update_product', 'wc_garantias_despues_api', 999, 2);
add_action('woocommerce_new_product', 'wc_garantias_despues_api', 999);
add_action('woocommerce_process_product_meta', 'wc_garantias_despues_api', 999);
add_action('updated_post_meta', 'wc_garantias_check_meta_update', 999, 4);
add_action('added_post_meta', 'wc_garantias_check_meta_update', 999, 4);

function wc_garantias_despues_api($product_id, $data = null) {
    if (get_post_type($product_id) === 'product') {
        $sku = get_post_meta($product_id, '_alg_ean', true);
        if ($sku && strpos($sku, 'GRT-ITEM-') === 0) {
            // Forzar ocultación inmediata
            wp_set_object_terms($product_id, array('exclude-from-catalog', 'exclude-from-search'), 'product_visibility', false);
            
            // También actualizar el meta de visibilidad por si acaso
            update_post_meta($product_id, '_visibility', 'hidden');
        }
    }
}

function wc_garantias_check_meta_update($meta_id, $post_id, $meta_key, $meta_value) {
    // Si se actualiza cualquier meta de un producto
    if (get_post_type($post_id) === 'product') {
        $sku = get_post_meta($post_id, '_alg_ean', true);
        if ($sku && strpos($sku, 'GRT-ITEM-') === 0) {
            // Programar verificación en 1 segundo
            wp_schedule_single_event(time() + 1, 'wc_garantias_verificar_producto_individual', array($post_id));
        }
    }
}

// 4. Verificación individual programada
add_action('wc_garantias_verificar_producto_individual', 'wc_garantias_verificar_individual');
function wc_garantias_verificar_individual($product_id) {
    $sku = get_post_meta($product_id, '_alg_ean', true);
    if ($sku && strpos($sku, 'GRT-ITEM-') === 0) {
        wp_set_object_terms($product_id, array('exclude-from-catalog', 'exclude-from-search'), 'product_visibility', false);
        update_post_meta($product_id, '_visibility', 'hidden');
    }
}

// 5. CRON que se ejecuta cada 5 minutos para asegurar
add_action('init', 'wc_garantias_programar_cron_grt');
function wc_garantias_programar_cron_grt() {
    if (!wp_next_scheduled('wc_garantias_cron_ocultar_grt')) {
        wp_schedule_event(time(), 'cinco_minutos', 'wc_garantias_cron_ocultar_grt');
    }
}

// Agregar intervalo de 5 minutos
add_filter('cron_schedules', 'wc_garantias_agregar_cron_interval');
function wc_garantias_agregar_cron_interval($schedules) {
    $schedules['cinco_minutos'] = array(
        'interval' => 300,
        'display' => 'Cada 5 minutos'
    );
    return $schedules;
}

// Ejecutar la verificación con el CRON
add_action('wc_garantias_cron_ocultar_grt', 'wc_garantias_forzar_ocultacion_grt');

// 6. Hook en el REST API (por si el CRM usa REST)
add_filter('woocommerce_rest_insert_product_object', 'wc_garantias_rest_api_check', 999, 3);
function wc_garantias_rest_api_check($product, $request, $creating) {
    $sku = get_post_meta($product->get_id(), '_alg_ean', true);
    if ($sku && strpos($sku, 'GRT-ITEM-') === 0) {
        wp_set_object_terms($product->get_id(), array('exclude-from-catalog', 'exclude-from-search'), 'product_visibility', false);
    }
    return $product;
}

// 7. JavaScript que verifica constantemente (último recurso)
add_action('admin_footer', 'wc_garantias_js_verificacion_admin');
function wc_garantias_js_verificacion_admin() {
    if (isset($_GET['post_type']) && $_GET['post_type'] === 'product') {
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Verificar cada 30 segundos si hay productos GRT-ITEM visibles
            setInterval(function() {
                $.post(ajaxurl, {
                    action: 'wc_garantias_verificar_ajax',
                    nonce: '<?php echo wp_create_nonce('wc_garantias_nonce'); ?>'
                });
            }, 30000);
        });
        </script>
        <?php
    }
}

// Ajax handler
add_action('wp_ajax_wc_garantias_verificar_ajax', 'wc_garantias_handle_ajax_verificacion');
function wc_garantias_handle_ajax_verificacion() {
    if (!wp_verify_nonce($_POST['nonce'], 'wc_garantias_nonce')) {
        wp_die();
    }
    
    wc_garantias_forzar_ocultacion_grt();
    wp_die();
}

// ========== FIN FORZAR OCULTACIÓN ==========

// Auto-aplicar cupones RMA
add_action('woocommerce_before_cart', 'wc_garantias_aplicar_cupones_rma');
add_action('woocommerce_before_checkout_form', 'wc_garantias_aplicar_cupones_rma', 5);
function wc_garantias_aplicar_cupones_rma() {
    // Solo ejecutar si estamos en las pginas correctas
    if (!is_cart() && !is_checkout()) {
        return;
    }
    
    // Verificar que WooCommerce está listo
    if (!function_exists('WC') || !isset(WC()->cart) || is_null(WC()->cart)) {
        return;
    }
    
    // Solo para usuarios logueados
    if (!is_user_logged_in()) {
        return;
    }
    
    $user_id = get_current_user_id();
    
    // Buscar cupones RMA pendientes
    global $wpdb;
    $cupones_rma = $wpdb->get_results($wpdb->prepare(
        "SELECT meta_key, meta_value 
         FROM {$wpdb->usermeta} 
         WHERE user_id = %d 
         AND meta_key LIKE %s",
        $user_id,
        '_cupon_rma_pendiente_%'
    ));
    
    if (empty($cupones_rma)) {
        return;
    }
    
    foreach ($cupones_rma as $cupon_data) {
        $codigo_cupon = $cupon_data->meta_value;
        
        if (empty($codigo_cupon)) {
            continue;
        }
        
        // Obtener el objeto cupn
        $coupon = new WC_Coupon($codigo_cupon);
        
        // Verificar que el cupón existe y es vlido
        if (!$coupon->get_id() || $coupon->get_usage_count() >= $coupon->get_usage_limit()) {
            continue;
        }
        
        // Obtener el producto RMA asociado
        $producto_rma_id = get_post_meta($coupon->get_id(), '_producto_rma_id', true);
        
        if ($producto_rma_id) {
            // Verificar si el producto ya est en el carrito
            $producto_en_carrito = false;
            foreach (WC()->cart->get_cart() as $cart_item_key => $cart_item) {
                if ($cart_item['product_id'] == $producto_rma_id) {
                    $producto_en_carrito = true;
                    break;
                }
            }
            
            // Si no est en el carrito, agregarlo
            if (!$producto_en_carrito) {
                $producto = wc_get_product($producto_rma_id);
                if ($producto && $producto->is_in_stock() && $producto->is_purchasable()) {
                    WC()->cart->add_to_cart($producto_rma_id, 1);
                }
            }
        }
        
        // Aplicar el cupn si no est aplicado
        if (!WC()->cart->has_discount($codigo_cupon)) {
            WC()->cart->apply_coupon($codigo_cupon);
        }
    }
// ========== RESTAURAR CUPONES RMA CUANDO SE CANCELA UN PEDIDO ==========

// Hook cuando se cancela un pedido
add_action('woocommerce_order_status_cancelled', 'wc_garantias_restaurar_cupones_rma', 10, 1);
add_action('woocommerce_order_status_refunded', 'wc_garantias_restaurar_cupones_rma', 10, 1);
add_action('woocommerce_order_status_failed', 'wc_garantias_restaurar_cupones_rma', 10, 1);

function wc_garantias_restaurar_cupones_rma($order_id) {
    $order = wc_get_order($order_id);
    if (!$order) return;
    
    // Obtener cupones usados en este pedido
    $coupons = $order->get_coupon_codes();
    
    foreach ($coupons as $coupon_code) {
        // Verificar si es un cupón RMA (empieza con RMA-)
        if (strpos($coupon_code, 'RMA-') === 0) {
            $coupon = new WC_Coupon($coupon_code);
            
            if ($coupon->get_id()) {
                // Verificar que es un cupn RMA
                $es_rma = get_post_meta($coupon->get_id(), '_es_cupon_rma', true);
                
                if ($es_rma === 'yes') {
                    // Reducir el contador de uso
                    $usage_count = $coupon->get_usage_count();
                    if ($usage_count > 0) {
                        update_post_meta($coupon->get_id(), 'usage_count', $usage_count - 1);
                    }
                    
                    // Restaurar el cupón pendiente en el usuario
                    $customer_email = $order->get_billing_email();
                    $user = get_user_by('email', $customer_email);
                    
                    if ($user) {
                        // Buscar el código del item original
                        $producto_rma_id = get_post_meta($coupon->get_id(), '_producto_rma_id', true);
                        if ($producto_rma_id) {
                            $sku = get_post_meta($producto_rma_id, '_alg_ean', true);
                            if ($sku) {
                                update_user_meta($user->ID, '_cupon_rma_pendiente_' . $sku, $coupon_code);
                            }
                        }
                    }
                    
                    // Opcional: Notificar al cliente que su cupón est disponible nuevamente
                    if ($user && $user->user_email) {
                        $subject = 'Tu cupón RMA está disponible nuevamente';
                        $message = "Hola " . $user->display_name . ",\n\n";
                        $message .= "Tu pedido #" . $order_id . " fue cancelado.\n";
                        $message .= "Tu cupón " . $coupon_code . " est disponible nuevamente para usar en tu prxima compra.\n\n";
                        $message .= "Saludos";
                        
                        wp_mail($user->user_email, $subject, $message);
                    }
                }
            }
        }
    }
}

// Tambin restaurar si se elimina el pedido completamente
add_action('before_delete_post', 'wc_garantias_restaurar_cupones_antes_eliminar', 10, 1);
function wc_garantias_restaurar_cupones_antes_eliminar($post_id) {
    // Verificar si es un pedido
    if (get_post_type($post_id) === 'shop_order') {
        wc_garantias_restaurar_cupones_rma($post_id);
    }
}
// ========== REGISTRAR ORDEN CUANDO SE USA CUPN RMA ==========

    // Hook cuando se crea una orden (captura ms temprano)
    add_action('woocommerce_checkout_order_processed', 'wc_garantias_registrar_uso_cupon_rma', 10, 3);
    function wc_garantias_registrar_uso_cupon_rma($order_id, $posted_data, $order) {
        if (!$order) return;
        
        // Obtener cupones usados en este pedido
        $coupons = $order->get_coupon_codes();
        
        foreach ($coupons as $coupon_code) {
            // Verificar si es un cupón RMA
            if (strpos($coupon_code, 'RMA-') === 0) {
                $coupon = new WC_Coupon($coupon_code);
                
                if ($coupon->get_id()) {
                    $es_rma = get_post_meta($coupon->get_id(), '_es_cupon_rma', true);
                    
                    if ($es_rma === 'yes') {
                        // Guardar el número de orden donde se usó
                        update_post_meta($coupon->get_id(), '_orden_canjeado', $order_id);
                        update_post_meta($coupon->get_id(), '_fecha_canjeado', current_time('mysql'));
                    }
                }
            }
        }
    }
    
    // Hook adicional por si el anterior no funciona
    add_action('woocommerce_new_order', 'wc_garantias_registrar_uso_cupon_rma_backup', 10, 1);
    function wc_garantias_registrar_uso_cupon_rma_backup($order_id) {
        $order = wc_get_order($order_id);
        if (!$order) return;
        
        $coupons = $order->get_coupon_codes();
        
        foreach ($coupons as $coupon_code) {
            if (strpos($coupon_code, 'RMA-') === 0) {
                $coupon = new WC_Coupon($coupon_code);
                
                if ($coupon->get_id()) {
                    $es_rma = get_post_meta($coupon->get_id(), '_es_cupon_rma', true);
                    
                    if ($es_rma === 'yes') {
                        // Solo actualizar si no se haba registrado antes
                        $orden_previa = get_post_meta($coupon->get_id(), '_orden_canjeado', true);
                        if (!$orden_previa) {
                            update_post_meta($coupon->get_id(), '_orden_canjeado', $order_id);
                            update_post_meta($coupon->get_id(), '_fecha_canjeado', current_time('mysql'));
                        }
                    }
                }
            }
        }
    }
    register_deactivation_hook(__FILE__, function() {
        wp_clear_scheduled_hook('wc_garantias_cron_ocultar_grt');
    });
}