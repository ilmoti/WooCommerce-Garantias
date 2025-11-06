<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Manejo de cupones de garantías
 */
class WC_Garantias_Cupones {
    
    public static function init() {
        // Hook para cuando se cancela un pedido
        add_action('woocommerce_order_status_cancelled', array(__CLASS__, 'reactivar_cupon_garantia'), 10, 1);
        add_action('woocommerce_order_status_refunded', array(__CLASS__, 'reactivar_cupon_garantia'), 10, 1);
        add_action('woocommerce_order_status_failed', array(__CLASS__, 'reactivar_cupon_garantia'), 10, 1);
        
        // NUEVO: Hook para cuando se elimina un pedido (trash o delete)
        add_action('wp_trash_post', array(__CLASS__, 'verificar_cupon_en_pedido_eliminado'), 10, 1);
        add_action('before_delete_post', array(__CLASS__, 'verificar_cupon_en_pedido_eliminado'), 10, 1);
        
        // Hook para limpiar el cupón cuando se usa
        add_action('woocommerce_applied_coupon', array(__CLASS__, 'limpiar_cupon_garantia_usado'), 10, 1);
    }
    
    public static function reactivar_cupon_garantia($order_id) {
        error_log('=== REACTIVAR CUPÓN DEBUG ===');
        error_log('Order ID: ' . $order_id);
        
        $order = wc_get_order($order_id);
        if (!$order) {
            error_log('No se pudo obtener la orden');
            return;
        }
        
        // Obtener cupones usados en el pedido
        $used_coupons = $order->get_coupon_codes();
        error_log('Cupones usados: ' . print_r($used_coupons, true));
        
        foreach ($used_coupons as $coupon_code) {
            // Verificar si es un cupón de garantía (comienza con GARANTIA-)
            if (strpos($coupon_code, 'GARANTIA-') === 0) {
                // Obtener el cupón
                $coupon = new WC_Coupon($coupon_code);
                if (!$coupon->get_id()) continue;
                
                // Reducir el contador de uso
                $usage_count = $coupon->get_usage_count();
                if ($usage_count > 0) {
                    $coupon->set_usage_count($usage_count - 1);
                    $coupon->save();
                }
                
                // Buscar la garantía asociada a este cupón
                $garantias = get_posts([
                    'post_type' => 'garantia',
                    'post_status' => 'publish',
                    'meta_query' => [
                        [
                            'key' => '_cupon_generado',
                            'value' => $coupon_code,
                            'compare' => '='
                        ]
                    ],
                    'posts_per_page' => 1
                ]);
                
                if (!empty($garantias)) {
                    $garantia = $garantias[0];
                    $cliente_id = get_post_meta($garantia->ID, '_cliente', true);
                    
                    // Reactivar el cupón para el cliente
                    update_user_meta($cliente_id, '_cupon_garantia_pendiente', $coupon_code);
                    
                    // Notificar al cliente
                    $user = get_userdata($cliente_id);
                    if ($user && $user->user_email) {
                        $codigo_garantia = get_post_meta($garantia->ID, '_codigo_unico', true);
                        $monto = $coupon->get_amount();
                        
                        // Enviar email
                        WC_Garantias_Emails::enviar_email('cupon_reactivado', $user->user_email, [
                            'cliente' => $user->display_name,
                            'codigo' => $codigo_garantia,
                            'cupon' => $coupon_code,
                            'importe' => number_format($monto, 2),
                            'motivo' => 'Tu pedido #' . $order_id . ' fue cancelado'
                        ]);
                    }
                }
            }
        }
    }
    
    public static function verificar_cupon_en_pedido_eliminado($post_id) {
        // Verificar si es un pedido
        if (get_post_type($post_id) !== 'shop_order') {
            return;
        }
        
        // Llamar a la función de reactivar cupón
        self::reactivar_cupon_garantia($post_id);
    }
    
    public static function limpiar_cupon_garantia_usado($coupon_code) {
        if (strpos($coupon_code, 'GARANTIA-') === 0) {
            $user_id = get_current_user_id();
            if ($user_id) {
                $cupon_pendiente = get_user_meta($user_id, '_cupon_garantia_pendiente', true);
                if ($cupon_pendiente === $coupon_code) {
                    delete_user_meta($user_id, '_cupon_garantia_pendiente');
                }
            }
        }
    }
    public static function generar_cupon_garantia($garantia_id) {
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        $cliente_id = get_post_meta($garantia_id, '_cliente', true);
        
        if (!$items || !is_array($items) || !$cliente_id) {
            return false;
        }
        
        // Verificar si ya existe un cupón para esta garantía
        $cupon_existente = get_post_meta($garantia_id, '_cupon_generado', true);
        $cupon_fue_usado = false;
        $cupon_existente_id = null;
        
        if ($cupon_existente) {
            // Buscar el cupón por su código
            $cupon_post = get_page_by_title($cupon_existente, OBJECT, 'shop_coupon');
            
            if ($cupon_post) {
                $cupon_existente_id = $cupon_post->ID;
                $usage_count = get_post_meta($cupon_existente_id, 'usage_count', true);
                $cupon_fue_usado = ($usage_count > 0);
            }
        }
        
        // Calcular el total de TODOS los items aprobados
        $total_cupon = 0;
        $items_aprobados = [];
        $items_ya_cuponeados = [];
        
        // Si el cupón fue usado, necesitamos saber qué items ya fueron cuponeados
        if ($cupon_fue_usado) {
            $items_ya_cuponeados = get_post_meta($garantia_id, '_cupon_items', true) ?: [];
        }
        
        foreach ($items as $item) {
            if (isset($item['estado']) && $item['estado'] === 'aprobado') {
                $producto = wc_get_product($item['producto_id']);
                
                // Inicializar variables
                $precio = 0;
                $nombre_producto = '';
                $cantidad = intval($item['cantidad'] ?? 1);
                
                if ($producto) {
                    // Producto existe: usar precio actual
                    $precio = floatval($producto->get_price());
                    $nombre_producto = $producto->get_name();
                } else {
                    // Producto NO existe: usar precio guardado
                    if (isset($item['precio_unitario']) && $item['precio_unitario'] > 0) {
                        $precio = floatval($item['precio_unitario']);
                        $nombre_producto = isset($item['nombre_producto']) ? $item['nombre_producto'] : 'Producto descatalogado #' . $item['producto_id'];
                        
                        // Log para debug
                        error_log("CUPÓN PRODUCTO ELIMINADO - ID: " . $item['producto_id']);
                        error_log("CUPÓN PRODUCTO ELIMINADO - Precio guardado: " . $precio);
                        error_log("CUPÓN PRODUCTO ELIMINADO - Nombre: " . $nombre_producto);
                    } else {
                        // Si no hay precio guardado, intentar buscarlo en la orden
                        if (isset($item['order_id']) && $item['order_id']) {
                            $order = wc_get_order($item['order_id']);
                            if ($order) {
                                foreach ($order->get_items() as $order_item) {
                                    if ($order_item->get_product_id() == $item['producto_id']) {
                                        $precio = $order_item->get_total() / $order_item->get_quantity();
                                        $nombre_producto = $order_item->get_name();
                                        break;
                                    }
                                }
                            }
                        }
                    }
                    
                    // Si aún no tenemos precio, saltar este item
                    if ($precio <= 0) {
                        error_log("ERROR: No se pudo obtener precio para producto ID: " . $item['producto_id']);
                        continue;
                    }
                }
                
                // LÓGICA DE BULTOS CORREGIDA
                if (stripos($nombre_producto, 'BULTO') === 0) {
                    // Buscar el patrón X seguido de números al final
                    if (preg_match('/X(\d+)$/i', $nombre_producto, $matches)) {
                        $cantidad_por_bulto = intval($matches[1]);
                        // El precio del producto es del bulto completo, dividir para obtener unitario
                        $precio = $precio / $cantidad_por_bulto;
                        
                        // Log para debug
                        error_log("CUPÓN BULTO - Producto: $nombre_producto");
                        error_log("CUPÓN BULTO - Cantidad por bulto: $cantidad_por_bulto");
                        error_log("CUPÓN BULTO - Precio unitario: $precio");
                        error_log("CUPÓN BULTO - Cantidad reclamada: $cantidad");
                    }
                }
                
                // Verificar si es distribuidor
                $user = get_userdata($cliente_id);
                $es_distribuidor = false;
                if ($user) {
                    $roles_distribuidor = ['distri10', 'distri20', 'distri30', 'superdistri30'];
                    $es_distribuidor = !empty(array_intersect($user->roles, $roles_distribuidor));
                }
                
                // Si es distribuidor, aplicar descuento del 20%
                if ($es_distribuidor) {
                    $precio = $precio * 0.8; // 80% del precio (20% descuento)
                }
                
                // Verificar forma de pago USDT
                $descuento_adicional = 1; // Sin descuento adicional por defecto
                if (isset($item['order_id']) && $item['order_id']) {
                    $order = wc_get_order($item['order_id']);
                    if ($order) {
                        $payment_method_title = $order->get_payment_method_title();
                        
                        if (strpos($payment_method_title, 'Cripto USDT') !== false) {
                            $descuento_adicional = 0.95; // 95% del precio (5% descuento adicional)
                        }
                    }
                }
                
                // Aplicar descuento adicional si corresponde
                $precio = $precio * $descuento_adicional;
                
                $subtotal = $precio * $cantidad;
                
                $item_data = [
                    'producto_id' => $item['producto_id'],
                    'codigo_item' => isset($item['codigo_item']) ? $item['codigo_item'] : 'ITEM-' . $item['producto_id'],
                    'nombre' => $nombre_producto,
                    'cantidad' => $cantidad,
                    'precio' => $precio,
                    'subtotal' => $subtotal
                ];
                
                // Si el cupón fue usado, solo incluir items nuevos
                if ($cupon_fue_usado) {
                    $ya_cuponeado = false;
                    foreach ($items_ya_cuponeados as $item_cuponeado) {
                        // Comparar por codigo_item si existe
                        if (isset($item_cuponeado['codigo_item']) && isset($item['codigo_item']) && 
                            $item_cuponeado['codigo_item'] == $item['codigo_item']) {
                            $ya_cuponeado = true;
                            break;
                        }
                        // Si no tiene codigo_item (cupones antiguos), comparar por producto_id y cantidad
                        else if (!isset($item_cuponeado['codigo_item']) && 
                                 $item_cuponeado['producto_id'] == $item['producto_id'] && 
                                 $item_cuponeado['cantidad'] == $cantidad) {
                            $ya_cuponeado = true;
                            break;
                        }
                    }
                    
                    if (!$ya_cuponeado) {
                        $total_cupon += $subtotal;
                        $items_aprobados[] = $item_data;
                    }
                } else {
                    // Si el cupón no fue usado, incluir todos los aprobados
                    $total_cupon += $subtotal;
                    $items_aprobados[] = $item_data;
                }
            }
        }
        
        if ($total_cupon <= 0) {
            return false;
        }
        
        // Si existe un cupón no usado, cancelarlo
        if ($cupon_existente && !$cupon_fue_usado && $cupon_existente_id) {
            wp_update_post(array(
                'ID' => $cupon_existente_id,
                'post_status' => 'trash'
            ));
            
            // Limpiar meta del cupón anterior
            delete_post_meta($garantia_id, '_cupon_generado');
            delete_post_meta($garantia_id, '_cupon_monto');
            delete_post_meta($garantia_id, '_cupon_items');
            delete_user_meta($cliente_id, '_cupon_garantia_pendiente');
        }
        
        // Generar código único para el cupón
        $codigo_cupon = 'GARANTIA-' . strtoupper(wp_generate_password(8, false, false));
        
        // Obtener datos del cliente para la descripción
        $user = get_userdata($cliente_id);
        $nombre_cliente = $user ? $user->display_name : 'Cliente eliminado';
        $codigo_garantia = get_post_meta($garantia_id, '_codigo_unico', true);
        
        // Crear descripción detallada
        $descripcion = "Cupón por garantía aprobada\n";
        $descripcion .= "Garantía: {$codigo_garantia}\n";
        $descripcion .= "Cliente: {$nombre_cliente}\n";
        $descripcion .= "Items aprobados: " . count($items_aprobados) . "\n";
        $descripcion .= "Fecha: " . date('d/m/Y H:i');
        
        // Agregar detalle de productos
        if (!empty($items_aprobados)) {
            $descripcion .= "\n\nProductos:\n";
            foreach ($items_aprobados as $item) {
                $descripcion .= "- {$item['nombre']} (x{$item['cantidad']}): $" . number_format($item['subtotal'], 2) . "\n";
            }
        }
        
        // Crear el cupón
        $cupon = array(
            'post_title'   => $codigo_cupon,
            'post_content' => $descripcion,
            'post_status'  => 'publish',
            'post_author'  => 1,
            'post_type'    => 'shop_coupon'
        );
        
        $cupon_id = wp_insert_post($cupon);
        
        if ($cupon_id) {
            // Configurar el cupón
            update_post_meta($cupon_id, 'discount_type', 'fixed_cart');
            update_post_meta($cupon_id, 'coupon_amount', $total_cupon);
            update_post_meta($cupon_id, 'individual_use', 'no');
            update_post_meta($cupon_id, 'usage_limit', 1);
            update_post_meta($cupon_id, 'usage_limit_per_user', 1);
            update_post_meta($cupon_id, 'exclude_sale_items', 'no');
            
            // Asociar con el cliente
            $user = get_userdata($cliente_id);
            if ($user && $user->user_email) {
                update_post_meta($cupon_id, 'customer_email', array($user->user_email));
            }
            
            // Guardar referencia en la garantía
            if ($cupon_fue_usado) {
                // Si ya había un cupón usado, agregar este como cupón adicional
                $cupones_adicionales = get_post_meta($garantia_id, '_cupones_adicionales', true) ?: [];
                $cupones_adicionales[] = [
                    'codigo' => $codigo_cupon,
                    'monto' => $total_cupon,
                    'items' => $items_aprobados,
                    'fecha' => current_time('mysql')
                ];
                update_post_meta($garantia_id, '_cupones_adicionales', $cupones_adicionales);
            } else {
                // Si es el primer cupón o se reemplazó uno no usado
                update_post_meta($garantia_id, '_cupon_generado', $codigo_cupon);
                update_post_meta($garantia_id, '_cupon_monto', $total_cupon);
                update_post_meta($garantia_id, '_cupon_items', $items_aprobados);
            }
            
            // Guardar en el usuario para aplicación automática
            update_user_meta($cliente_id, '_cupon_garantia_pendiente', $codigo_cupon);
            
            // Enviar email al cliente
            if ($user && $user->user_email) {
                $codigo_garantia = get_post_meta($garantia_id, '_codigo_unico', true);
                
                // Crear detalle de items
                $detalle_items = "";
                foreach ($items_aprobados as $item_aprobado) {
                    $detalle_items .= sprintf("- %s (x%d): $%s\n", 
                        $item_aprobado['nombre'],
                        $item_aprobado['cantidad'],
                        number_format($item_aprobado['subtotal'], 2)
                    );
                }
                
                $variables = [
                    'cliente' => $user->display_name,
                    'codigo' => $codigo_garantia,
                    'cupon' => $codigo_cupon,
                    'importe' => number_format($total_cupon, 2),
                    'detalle_items' => $detalle_items
                ];
                
                if ($cupon_fue_usado) {
                    $variables['nota_adicional'] = "\n\nNota: Este es un cupón adicional por los items aprobados en la apelación.";
                }
                
                WC_Garantias_Emails::enviar_email('aprobada', $user->user_email, $variables);
            }
            
            return $codigo_cupon;
        }
        
        return false;
    }
}