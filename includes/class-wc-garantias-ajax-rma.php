<?php
if (!defined('ABSPATH')) exit;

class WC_Garantias_Ajax_RMA {
    
    public static function init() {
        add_action('wp_ajax_generar_cupon_rma_manual', array(__CLASS__, 'handle_generar_cupon_manual'));
        add_action('wp_ajax_diagnosticar_cupon_rma', array(__CLASS__, 'handle_diagnosticar_cupon'));
        add_action('wp_ajax_buscar_id_cupon_rma', array(__CLASS__, 'handle_buscar_id_cupon'));
        add_action('wp_ajax_reparar_cupon_rma', array(__CLASS__, 'handle_reparar_cupon'));
    }
    
    public static function handle_generar_cupon_manual() {
        error_log('=== GENERAR CUPON RMA MANUAL - INICIO ===');
        error_log('POST data: ' . print_r($_POST, true));
        
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'generar_cupon_rma')) {
            error_log('Error: Nonce inválido');
            wp_send_json_error(['message' => 'Error de seguridad']);
        }
        
        // Verificar permisos
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => 'Sin permisos']);
        }
        
        $garantia_id = intval($_POST['garantia_id']);
        $codigo_item = sanitize_text_field($_POST['codigo_item']);
        $item_index = intval($_POST['item_index']);
        
        // Obtener items
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        
        if (!is_array($items) || !isset($items[$item_index])) {
            wp_send_json_error(['message' => 'Item no encontrado']);
        }
        
        $item = $items[$item_index];
        
        // Verificar estado
        if ($item['estado'] !== 'retorno_cliente') {
            wp_send_json_error(['message' => 'El item no está en estado retorno_cliente']);
        }
        
        // Verificar si ya tiene cupón y si existe
        if (isset($item['cupon_rma']) && !empty($item['cupon_rma'])) {
            // Verificar si el cupón todavía existe
            $cupon_existente = get_page_by_title($item['cupon_rma'], OBJECT, 'shop_coupon');
            if ($cupon_existente && $cupon_existente->post_status == 'publish') {
                wp_send_json_error(['message' => 'El item ya tiene un cupón RMA activo: ' . $item['cupon_rma']]);
            }
            // Si el cupón fue borrado, permitir regenerarlo
        }
        
        // Buscar producto por SKU
        $producto_id = self::buscar_producto_por_sku($codigo_item);
        
        if (!$producto_id) {
            wp_send_json_error(['message' => 'No se encontró el producto RMA con SKU: ' . $codigo_item]);
        }
        
        // Verificar que sea un producto RMA
        $producto = wc_get_product($producto_id);
        if (!$producto || strpos($producto->get_name(), 'RMA -') !== 0) {
            wp_send_json_error(['message' => 'El producto encontrado no es un RMA válido']);
        }
        
        // Obtener cliente
        $cliente_id = get_post_meta($garantia_id, '_cliente', true);
        
        // Generar cupón
        $codigo_cupon = WC_Garantias_RMA::crear_cupon_rma($producto_id, $cliente_id, $codigo_item);
        
        if ($codigo_cupon) {
            // NUEVO: Obtener la cantidad del item
            $cantidad_rma = intval($item['cantidad']);
            
            // NUEVO: Guardar la cantidad en el meta del cupón
            $cupon_obj = new WC_Coupon($codigo_cupon);
            if ($cupon_obj->get_id()) {
                update_post_meta($cupon_obj->get_id(), '_cantidad_rma', $cantidad_rma);
                update_post_meta($cupon_obj->get_id(), '_producto_rma_id', $producto_id);
            }
            
            // Actualizar item
            $items[$item_index]['producto_rma_id'] = $producto_id;
            $items[$item_index]['cupon_rma'] = $codigo_cupon;
            update_post_meta($garantia_id, '_items_reclamados', $items);
            
            // NUEVO: Actualizar el estado de la garantía para verificar si debe generar cupón principal
            WC_Garantias_Admin::actualizar_estado_garantia($garantia_id);
            
            // Enviar notificacion
            self::notificar_cupon_rma_creado($garantia_id, $cliente_id, $producto_id, $codigo_cupon);
            
            wp_send_json_success([
                'cupon' => $codigo_cupon,
                'message' => 'Cupón generado exitosamente'
            ]);
        } else {
            wp_send_json_error(['message' => 'Error al generar el cupón']);
        }
    }
    
    private static function buscar_producto_por_sku($sku) {
        global $wpdb;
        
        $producto_id = $wpdb->get_var($wpdb->prepare("
            SELECT post_id 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_alg_ean' 
            AND meta_value = %s 
            LIMIT 1
        ", $sku));
        
        return $producto_id ? intval($producto_id) : false;
    }
    
    private static function notificar_cupon_rma_creado($garantia_id, $cliente_id, $producto_id, $codigo_cupon) {
        $user = get_userdata($cliente_id);
        if (!$user) return;
        
        $producto = wc_get_product($producto_id);
        if (!$producto) return;
        
        $codigo_garantia = get_post_meta($garantia_id, '_codigo_unico', true);
        $dias_validez = get_option('dias_validez_cupon_rma', 120);
        $fecha_vencimiento = date('d/m/Y', strtotime("+{$dias_validez} days"));
        
        WC_Garantias_Emails::enviar_email('rma_confirmado', $user->user_email, [
            'cliente' => $user->display_name,
            'codigo' => $codigo_garantia,
            'producto' => $producto->get_name(),
            'cupon_rma' => $codigo_cupon,
            'dias_validez' => $dias_validez,
            'fecha_vencimiento' => $fecha_vencimiento
        ]);
    }
    
    public static function handle_diagnosticar_cupon() {
        try {
            $cupon_id = intval($_POST['cupon_id']);
            $codigo_cupon = sanitize_text_field($_POST['codigo_cupon']);
            
            $diagnostico = "DIAGNÓSTICO CUPÓN: " . $codigo_cupon . "\n\n";
            
            // Verificar meta del cupón
            $producto_id = get_post_meta($cupon_id, '_producto_rma_id', true);
            $diagnostico .= "Producto ID guardado: " . ($producto_id ?: 'NO TIENE') . "\n";
            
            // Verificar si el producto existe
            if ($producto_id) {
                $producto = wc_get_product($producto_id);
                if ($producto) {
                    $diagnostico .= "✓ El producto EXISTE\n";
                } else {
                    $diagnostico .= "✗ El producto ID " . $producto_id . " NO EXISTE\n";
                }
            }
            
            // Extraer SKU del cupón
            $diagnostico .= "\n--- BÚSQUEDA POR SKU ---\n";
            preg_match('/rma-devolucion-(.*?)-x\d+/i', $codigo_cupon, $matches);
            if (isset($matches[1])) {
                $sku_buscado = $matches[1];
                $sku_buscado_upper = strtoupper($sku_buscado);
                $diagnostico .= "SKU extraído: " . $sku_buscado . "\n";
                $diagnostico .= "SKU en mayúsculas: " . $sku_buscado_upper . "\n";
                
                // Buscar TODOS los productos con este SKU
                global $wpdb;
                $productos_con_sku = $wpdb->get_results($wpdb->prepare("
                    SELECT pm.post_id, p.post_title, pm.meta_value as sku
                    FROM {$wpdb->postmeta} pm
                    JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                    WHERE pm.meta_key = '_alg_ean' 
                    AND (LOWER(pm.meta_value) = LOWER(%s) OR pm.meta_value = %s)
                    AND p.post_status = 'publish'
                ", $sku_buscado, $sku_buscado_upper));
                
                if ($productos_con_sku) {
                    $diagnostico .= "\nProductos encontrados con este SKU:\n";
                    foreach ($productos_con_sku as $prod_sku) {
                        $diagnostico .= "- ID: " . $prod_sku->post_id . "\n";
                        $diagnostico .= "  Título: " . $prod_sku->post_title . "\n";
                        $diagnostico .= "  SKU guardado: " . $prod_sku->sku . "\n";
                        $diagnostico .= "  Es RMA: " . (strpos($prod_sku->post_title, 'RMA -') === 0 ? 'SÍ' : 'NO') . "\n\n";
                    }
                } else {
                    $diagnostico .= "✗ No hay productos con este SKU\n";
                }
            } else {
                $diagnostico .= "No se pudo extraer SKU del código\n";
            }
            
            // Buscar en garantías
            $diagnostico .= "\n--- GARANTÍAS ---\n";
            $garantias = $wpdb->get_results($wpdb->prepare("
                SELECT post_id FROM {$wpdb->postmeta} 
                WHERE meta_key = '_items_reclamados' 
                AND meta_value LIKE %s LIMIT 5
            ", '%' . $wpdb->esc_like($codigo_cupon) . '%'));
            
            $diagnostico .= "Garantas encontradas: " . count($garantias) . "\n";
            
            echo $diagnostico;
            wp_die();
            
        } catch (Exception $e) {
            echo "ERROR: " . $e->getMessage();
            wp_die();
        }
    }
    public static function handle_buscar_id_cupon() {
        $codigo_cupon = sanitize_text_field($_POST['codigo_cupon']);
        
        $cupon = get_page_by_title($codigo_cupon, OBJECT, 'shop_coupon');
        
        if ($cupon) {
            wp_send_json_success(['cupon_id' => $cupon->ID]);
        } else {
            wp_send_json_error(['message' => 'Cupn no encontrado']);
        }
    }
    public static function handle_reparar_cupon() {
        $cupon_id = intval($_POST['cupon_id']);
        $codigo_cupon = sanitize_text_field($_POST['codigo_cupon']);
        
        // Extraer SKU del cupón
        preg_match('/rma-devolucion-(.*?)-x\d+/i', $codigo_cupon, $matches);
        if (!isset($matches[1])) {
            wp_send_json_error(['message' => 'No se pudo extraer SKU del código']);
        }
        
        $sku = $matches[1];
        $sku_upper = strtoupper($sku);
        
        // Buscar producto RMA con el SKU
        global $wpdb;
        $producto_rma = $wpdb->get_row($wpdb->prepare("
            SELECT pm.post_id, p.post_title 
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE pm.meta_key = '_alg_ean' 
            AND (LOWER(pm.meta_value) = LOWER(%s) OR pm.meta_value = %s)
            AND p.post_title LIKE 'RMA -%'
            AND p.post_status = 'publish'
            LIMIT 1
        ", $sku, $sku_upper));
        
        if (!$producto_rma) {
            wp_send_json_error(['message' => 'No se encontró producto RMA con SKU: ' . $sku]);
        }
        
        $producto_id = $producto_rma->post_id;
        
        // Actualizar el meta del cupón
        update_post_meta($cupon_id, '_producto_rma_id', $producto_id);
        
        // También actualizar el producto vinculado al cupón
        $coupon = new WC_Coupon($cupon_id);
        $coupon->set_product_ids([$producto_id]);
        $coupon->save();
        
        wp_send_json_success([
            'message' => 'Cupón reparado exitosamente',
            'producto_id' => $producto_id,
            'producto_nombre' => $producto_rma->post_title
        ]);
    }
}

// Inicializar
WC_Garantias_Ajax_RMA::init();