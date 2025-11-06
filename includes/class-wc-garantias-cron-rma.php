<?php
if (!defined('ABSPATH')) exit;

class WC_Garantias_Cron_RMA {
    
    public static function init() {
        // Programar evento diario
        add_action('init', array(__CLASS__, 'schedule_events'));
        
        // Hook para verificar vencimientos
        add_action('wc_garantias_check_rma_expiry', array(__CLASS__, 'check_rma_expiry'));
        
        // NUEVO: Hook para verificar productos RMA pendientes
        add_action('wc_garantias_check_pending_rma', array(__CLASS__, 'check_pending_rma_coupons'));
    }
    
    public static function schedule_events() {
        if (!wp_next_scheduled('wc_garantias_check_rma_expiry')) {
            wp_schedule_event(time(), 'daily', 'wc_garantias_check_rma_expiry');
        }
        
        // NUEVO: Verificar cada hora productos RMA pendientes
        if (!wp_next_scheduled('wc_garantias_check_pending_rma')) {
            wp_schedule_event(time(), 'hourly', 'wc_garantias_check_pending_rma');
        }
    }
    
    public static function check_rma_expiry() {
        global $wpdb;
        
        // Buscar cupones RMA
        $cupones = $wpdb->get_results("
            SELECT p.ID, p.post_title 
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
            WHERE p.post_type = 'shop_coupon'
            AND p.post_status = 'publish'
            AND pm.meta_key = '_es_cupon_rma'
            AND pm.meta_value = 'yes'
        ");
        
        foreach ($cupones as $cupon) {
            $coupon_obj = new WC_Coupon($cupon->ID);
            $expiry_date = $coupon_obj->get_date_expires();
            
            if ($expiry_date) {
                $days_until_expiry = floor(($expiry_date->getTimestamp() - time()) / DAY_IN_SECONDS);
                
                // Notificar 30 días antes
                if ($days_until_expiry == 30) {
                    self::notify_expiry_warning($cupon->ID, 30);
                }
                // Notificar cuando expira
                elseif ($days_until_expiry <= 0 && get_post_meta($cupon->ID, '_notified_expired', true) != 'yes') {
                    self::notify_expired($cupon->ID);
                    update_post_meta($cupon->ID, '_notified_expired', 'yes');
                }
            }
        }
    }
    
    private static function notify_expiry_warning($cupon_id, $days_remaining) {
        $coupon = new WC_Coupon($cupon_id);
        $producto_id = get_post_meta($cupon_id, '_producto_rma_id', true);
        $producto = wc_get_product($producto_id);
        
        if (!$producto) return;
        
        // Obtener cliente
        $customer_emails = get_post_meta($cupon_id, 'customer_email', true);
        if (empty($customer_emails)) return;
        
        $customer_email = is_array($customer_emails) ? $customer_emails[0] : $customer_emails;
        $user = get_user_by('email', $customer_email);
        
        if (!$user) return;
        
        // Enviar usando el sistema de emails configurables
        WC_Garantias_Emails::enviar_email('rma_vencimiento_30', $customer_email, [
            'cliente' => $user->display_name,
            'producto' => $producto->get_name(),
            'cupon_rma' => $coupon->get_code(),
            'fecha_vencimiento' => $coupon->get_date_expires()->date_i18n('d/m/Y'),
            'link_cuenta' => wc_get_account_endpoint_url('shop')
        ]);
        
        // Marcar como notificado
        update_post_meta($cupon_id, '_notified_30_days', 'yes');
    }
    
    private static function notify_expired($cupon_id) {
        $coupon = new WC_Coupon($cupon_id);
        $producto_id = get_post_meta($cupon_id, '_producto_rma_id', true);
        $producto = wc_get_product($producto_id);
        
        if (!$producto) return;
        
        // Obtener SKU
        $sku = get_post_meta($producto_id, '_alg_ean', true);
        
        // Obtener cliente
        $customer_emails = get_post_meta($cupon_id, 'customer_email', true);
        $customer_email = is_array($customer_emails) ? $customer_emails[0] : $customer_emails;
        $user = get_user_by('email', $customer_email);
        $cliente_nombre = $user ? $user->display_name : 'Cliente desconocido';
        
        // Notificar al admin
        $admin_email = get_option('admin_email_garantias', get_option('admin_email'));
        
        WC_Garantias_Emails::enviar_email('admin_rma_expirado', $admin_email, [
            'cupon_rma' => $coupon->get_code(),
            'producto' => $producto->get_name(),
            'sku' => $sku,
            'cliente' => $cliente_nombre,
            'link_producto' => admin_url('post.php?post=' . $producto_id . '&action=edit')
        ]);
    }
    /**
     * Verificar productos RMA pendientes de cupón
     */
    public static function check_pending_rma_coupons() {
        global $wpdb;
        
        error_log('=== VERIFICANDO PRODUCTOS RMA PENDIENTES DE CUPÓN ===');
        
        // Buscar todas las garantías
        $garantias = get_posts(array(
            'post_type' => 'garantia',
            'posts_per_page' => -1,
            'post_status' => 'publish'
        ));
        
        foreach ($garantias as $garantia) {
            $items = get_post_meta($garantia->ID, '_items_reclamados', true);
            
            if (!is_array($items)) continue;
            
            foreach ($items as $index => $item) {
                // Buscar items en estado retorno_cliente sin cupón
                if (isset($item['estado']) && 
                    $item['estado'] === 'retorno_cliente' && 
                    (!isset($item['cupon_rma']) || empty($item['cupon_rma']))) {
                    
                    $codigo_item = $item['codigo_item'] ?? '';
                    
                    if (empty($codigo_item)) continue;
                    
                    error_log('Buscando producto RMA con SKU: ' . $codigo_item);
                    
                    // Buscar producto por SKU en meta _alg_ean
                    $producto_id = self::buscar_producto_por_sku($codigo_item);
                    
                    if ($producto_id) {
                        error_log('Producto RMA encontrado! ID: ' . $producto_id);
                        
                        // Verificar que sea un producto RMA
                        $producto = wc_get_product($producto_id);
                        if ($producto && strpos($producto->get_name(), 'RMA -') === 0) {
                            // Generar cupn
                            $cliente_id = get_post_meta($garantia->ID, '_cliente', true);
                            $codigo_cupon = WC_Garantias_RMA::crear_cupon_rma($producto_id, $cliente_id, $codigo_item);
                            
                            if ($codigo_cupon) {
                                // Actualizar item
                                $items[$index]['producto_rma_id'] = $producto_id;
                                $items[$index]['cupon_rma'] = $codigo_cupon;
                                update_post_meta($garantia->ID, '_items_reclamados', $items);
                                
                                // NUEVO: Guardar cupón para auto-aplicación
                                update_user_meta($cliente_id, '_cupon_rma_pendiente_' . $codigo_item, $codigo_cupon);
                                
                                error_log('Cupón RMA creado: ' . $codigo_cupon);
                                
                                // Enviar notificación
                                self::notificar_cupon_rma_creado($garantia->ID, $cliente_id, $producto_id, $codigo_cupon);
                            }
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Buscar producto por SKU en meta _alg_ean
     */
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
    
    /**
     * Notificar que se creó el cupón RMA
     */
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
}

// Inicializar
WC_Garantias_Cron_RMA::init();