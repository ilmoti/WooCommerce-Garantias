<?php
/**
 * Sistema de Recepción Parcial de Garantías
 * Maneja la lógica principal de división de items y recepción parcial
 * 
 * @package WC_Garantias
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

class WC_Garantias_Recepcion_Parcial {
    
    /**
     * Inicializar el módulo
     */
    public static function init() {
        // AJAX handlers
        add_action('wp_ajax_procesar_recepcion_parcial', [__CLASS__, 'ajax_procesar_recepcion']);
        add_action('wp_ajax_extender_plazo_item', [__CLASS__, 'ajax_extender_plazo']);
        add_action('wp_ajax_rechazar_manual_item', [__CLASS__, 'ajax_rechazar_manual']);
        add_action('wp_ajax_cancelar_envio_item', [__CLASS__, 'ajax_cancelar_envio_cliente']);
    }
    
    /**
     * Procesar recepción parcial de un item
     * 
     * @param int $garantia_id
     * @param string $codigo_item
     * @param int $cantidad_recibida
     * @param int $cantidad_esperada
     * @return array Resultado de la operación
     */
    public static function procesar_recepcion_parcial($garantia_id, $codigo_item, $cantidad_recibida, $cantidad_esperada) {
        error_log('=== INICIO PROCESAR RECEPCIÓN PARCIAL ===');
        error_log('Garantía ID: ' . $garantia_id);
        error_log('Cantidad recibida: ' . $cantidad_recibida);
        error_log('Cantidad esperada: ' . $cantidad_esperada);
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        
        if (!is_array($items)) {
            return ['success' => false, 'message' => 'No se encontraron items'];
        }
        
        $resultado = [
            'success' => false,
            'message' => '',
            'items_creados' => [],
            'items_actualizados' => []
        ];
        
        // Buscar el item original
        $item_index = null;
        $item_original = null;
        
        foreach ($items as $index => $item) {
            if (isset($item['codigo_item']) && $item['codigo_item'] === $codigo_item) {
                $item_index = $index;
                $item_original = $item;
                break;
            }
        }
        
        if ($item_original === null) {
            return ['success' => false, 'message' => 'Item no encontrado'];
        }
        
        // Validar cantidades
        if ($cantidad_recibida > $cantidad_esperada) {
            // Caso: Llegaron MÁS unidades de las esperadas
            $exceso = $cantidad_recibida - $cantidad_esperada;
            
            // Actualizar item original con la cantidad esperada
            $items[$item_index]['cantidad'] = $cantidad_esperada;
            $items[$item_index]['estado'] = 'recibido';
            $items[$item_index]['fecha_recepcion'] = current_time('mysql');
            $items[$item_index]['nota_recepcion'] = "Recibido completo ($cantidad_esperada de $cantidad_esperada unidades)";
            
            $resultado['items_actualizados'][] = $items[$item_index];
            
            // Crear item de retorno para el exceso
            $item_exceso = self::crear_item_exceso($item_original, $exceso);
            $items[] = $item_exceso;
            
            $resultado['items_creados'][] = $item_exceso;
            $resultado['message'] = "Se recibieron $exceso unidades adicionales que serán devueltas al cliente";
            
        } elseif ($cantidad_recibida < $cantidad_esperada) {
            // Caso: Llegaron MENOS unidades (recepción parcial)
            $faltante = $cantidad_esperada - $cantidad_recibida;
            
            // Actualizar item original con lo recibido
            $items[$item_index]['cantidad'] = $cantidad_recibida;
            $items[$item_index]['cantidad_original'] = $cantidad_esperada;
            $items[$item_index]['estado'] = 'recibido';
            $items[$item_index]['fecha_recepcion'] = current_time('mysql');
            $items[$item_index]['nota_recepcion'] = "Recepción parcial: $cantidad_recibida de $cantidad_esperada unidades";
            
            // Agregar información de recepción parcial
            $items[$item_index]['recepcion_parcial'] = [
                'fecha_recepcion' => current_time('mysql'),
                'cantidad_recibida' => $cantidad_recibida,
                'cantidad_faltante' => $faltante,
                'item_hijo' => $codigo_item . '-P'
            ];
            
            $resultado['items_actualizados'][] = $items[$item_index];
            
            // Crear nuevo item para lo faltante
            $item_faltante = self::crear_item_faltante($item_original, $faltante, $codigo_item);
            $items[] = $item_faltante;
            
            $resultado['items_creados'][] = $item_faltante;
            $resultado['message'] = "Recepción parcial: $cantidad_recibida recibidas, $faltante pendientes (7 días límite)";
            
        } else {
            // Caso: Cantidad exacta
            $items[$item_index]['cantidad'] = $cantidad_recibida;
            $items[$item_index]['estado'] = 'recibido';
            $items[$item_index]['fecha_recepcion'] = current_time('mysql');
            $items[$item_index]['nota_recepcion'] = "Recibido completo ($cantidad_recibida unidades)";
            
            $resultado['items_actualizados'][] = $items[$item_index];
            $resultado['message'] = "Recepción completa: $cantidad_recibida unidades";
        }
        
        // Guardar items actualizados
        update_post_meta($garantia_id, '_items_reclamados', $items);
        
        // Actualizar estado general de la garantía
        self::actualizar_estado_garantia($garantia_id);
        
        // Registrar en el log
        self::registrar_evento($garantia_id, 'recepcion_parcial', [
            'codigo_item' => $codigo_item,
            'cantidad_esperada' => $cantidad_esperada,
            'cantidad_recibida' => $cantidad_recibida
        ]);
        
        $resultado['success'] = true;
        
        // Enviar notificación si hubo recepción parcial (UNA SOLA VEZ)
        if ($cantidad_recibida < $cantidad_esperada) {
            error_log('CONDICIÓN CUMPLIDA: cantidad_recibida (' . $cantidad_recibida . ') < cantidad_esperada (' . $cantidad_esperada . ')');
            $email_enviado = self::notificar_recepcion_parcial($garantia_id, $resultado);
            error_log('Email enviado: ' . ($email_enviado ? 'SÍ' : 'NO'));
        } else {
            error_log('NO se envía notificación: cantidad recibida (' . $cantidad_recibida . ') >= esperada (' . $cantidad_esperada . ')');
        }
        
        return $resultado;
    }
    
    /**
     * Crear item para unidades faltantes
     */
    private static function crear_item_faltante($item_original, $cantidad_faltante, $codigo_padre) {
        $fecha_limite = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        $item_faltante = $item_original;
        $item_faltante['codigo_item'] = $codigo_padre . '-P'; // P de Pendiente
        $item_faltante['cantidad'] = $cantidad_faltante;
        $item_faltante['estado'] = 'esperando_recepcion';
        $item_faltante['fecha_creacion'] = current_time('mysql');
        $item_faltante['item_padre'] = $codigo_padre;
        $item_faltante['es_division'] = true;
        $item_faltante['nota_recepcion'] = "Faltante: $cantidad_faltante unidades no recibidas";
        
        // Configuración de auto-rechazo
        $item_faltante['auto_rechazo'] = [
            'activo' => true,
            'fecha_limite' => $fecha_limite,
            'dias_plazo' => 7,
            'recordatorio_enviado' => false,
            'permite_apelacion' => false,
            'motivo_auto' => 'No recibido en el plazo establecido'
        ];
        
        // Información de tracking heredada
        if (isset($item_original['tracking_original'])) {
            $item_faltante['tracking_original'] = $item_original['tracking_original'];
        }
        
        return $item_faltante;
    }
    
    /**
     * Crear item de retorno para exceso
     */
    private static function crear_item_exceso($item_original, $cantidad_exceso) {
        $item_exceso = $item_original;
        $item_exceso['codigo_item'] = $item_original['codigo_item'] . '-EX'; // EX de Exceso
        $item_exceso['cantidad'] = $cantidad_exceso;
        $item_exceso['estado'] = 'retorno_cliente';
        $item_exceso['fecha_creacion'] = current_time('mysql');
        $item_exceso['es_exceso'] = true;
        $item_exceso['nota_recepcion'] = "Exceso: $cantidad_exceso unidades adicionales no solicitadas";
        $item_exceso['motivo_retorno'] = 'Exceso de unidades enviadas';
        
        return $item_exceso;
    }
    
    /**
     * Auto-rechazar items vencidos (para cron job)
     */
    public static function auto_rechazar_items_vencidos() {
        global $wpdb;
        
        // Buscar todas las garantas con items esperando recepcin
        $garantias = get_posts([
            'post_type' => 'garantia',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                ['key' => '_items_reclamados', 'compare' => 'EXISTS']
            ]
        ]);
        
        $items_rechazados = 0;
        
        foreach ($garantias as $garantia) {
            $items = get_post_meta($garantia->ID, '_items_reclamados', true);
            $items_modificados = false;
            
            if (is_array($items)) {
                foreach ($items as &$item) {
                    if ($item['estado'] === 'esperando_recepcion' && 
                        isset($item['auto_rechazo']['activo']) && 
                        $item['auto_rechazo']['activo']) {
                        
                        $fecha_limite = strtotime($item['auto_rechazo']['fecha_limite']);
                        $ahora = current_time('timestamp');
                        
                        // Verificar si venció
                        if ($ahora > $fecha_limite) {
                            // Cambiar estado a rechazado sin apelación
                            $item['estado'] = 'rechazado_no_recibido';
                            $item['fecha_rechazo_auto'] = current_time('mysql');
                            $item['motivo_rechazo'] = $item['auto_rechazo']['motivo_auto'] ?? 'No recibido en el plazo de 7 días';
                            $item['rechazo_definitivo'] = true;
                            $item['permite_apelacion'] = false;
                            
                            $items_modificados = true;
                            $items_rechazados++;
                            
                            // Notificar al cliente
                            self::notificar_rechazo_automatico($garantia->ID, $item);
                            
                            // Log
                            error_log("Item {$item['codigo_item']} de garantía {$garantia->ID} rechazado automáticamente");
                        }
                        
                        // Enviar recordatorio (día 5)
                        elseif (!$item['auto_rechazo']['recordatorio_enviado']) {
                            $dias_restantes = ceil(($fecha_limite - $ahora) / 86400);
                            
                            if ($dias_restantes <= 2) {
                                self::enviar_recordatorio($garantia->ID, $item, $dias_restantes);
                                $item['auto_rechazo']['recordatorio_enviado'] = true;
                                $items_modificados = true;
                            }
                        }
                    }
                }
                
                if ($items_modificados) {
                    update_post_meta($garantia->ID, '_items_reclamados', $items);
                    self::actualizar_estado_garantia($garantia->ID);
                }
            }
        }
        
        if ($items_rechazados > 0) {
            error_log("Cron recepción parcial: $items_rechazados items rechazados automáticamente");
        }
        
        return $items_rechazados;
    }
    
    /**
     * Extender plazo de un item
     */
    public static function extender_plazo($garantia_id, $codigo_item, $dias_adicionales = 7) {
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        
        if (!is_array($items)) {
            return false;
        }
        
        foreach ($items as &$item) {
            if ($item['codigo_item'] === $codigo_item && 
                $item['estado'] === 'esperando_recepcion') {
                
                // Verificar extensiones previas
                $extensiones = isset($item['auto_rechazo']['extensiones']) ? 
                               $item['auto_rechazo']['extensiones'] : 0;
                
                if ($extensiones >= 2) {
                    return ['success' => false, 'message' => 'Máximo de extensiones alcanzado'];
                }
                
                // Extender fecha límite
                $fecha_actual = strtotime($item['auto_rechazo']['fecha_limite']);
                $nueva_fecha = date('Y-m-d H:i:s', strtotime("+$dias_adicionales days", $fecha_actual));
                
                $item['auto_rechazo']['fecha_limite'] = $nueva_fecha;
                $item['auto_rechazo']['extensiones'] = $extensiones + 1;
                $item['auto_rechazo']['ultima_extension'] = current_time('mysql');
                $item['auto_rechazo']['recordatorio_enviado'] = false; // Reset recordatorio
                
                update_post_meta($garantia_id, '_items_reclamados', $items);
                
                // Registrar evento
                self::registrar_evento($garantia_id, 'extension_plazo', [
                    'codigo_item' => $codigo_item,
                    'dias_adicionales' => $dias_adicionales,
                    'nueva_fecha_limite' => $nueva_fecha
                ]);
                
                return ['success' => true, 'message' => "Plazo extendido $dias_adicionales días"];
            }
        }
        
        return ['success' => false, 'message' => 'Item no encontrado'];
    }
    
    /**
     * Rechazar manualmente un item pendiente
     */
    public static function rechazar_manual($garantia_id, $codigo_item, $motivo = '') {
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        
        if (!is_array($items)) {
            return false;
        }
        
        foreach ($items as &$item) {
            if ($item['codigo_item'] === $codigo_item && 
                $item['estado'] === 'esperando_recepcion') {
                
                $item['estado'] = 'rechazado_no_recibido';
                $item['fecha_rechazo_manual'] = current_time('mysql');
                $item['motivo_rechazo'] = $motivo ?: 'Rechazado manualmente por el administrador';
                $item['rechazo_definitivo'] = true;
                $item['permite_apelacion'] = false;
                $item['rechazado_por'] = get_current_user_id();
                
                update_post_meta($garantia_id, '_items_reclamados', $items);
                self::actualizar_estado_garantia($garantia_id);
                
                // Notificar al cliente
                self::notificar_rechazo_manual($garantia_id, $item);
                
                return ['success' => true, 'message' => 'Item rechazado correctamente'];
            }
        }
        
        return ['success' => false, 'message' => 'Item no encontrado'];
    }
    
    /**
     * Cancelar envío por parte del cliente
     */
    public static function cancelar_envio_cliente($garantia_id, $codigo_item) {
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        
        if (!is_array($items)) {
            return false;
        }
        
        foreach ($items as &$item) {
            if ($item['codigo_item'] === $codigo_item && 
                $item['estado'] === 'esperando_recepcion') {
                
                // Verificar días restantes (no permitir si quedan menos de 2 días)
                $fecha_limite = strtotime($item['auto_rechazo']['fecha_limite']);
                $ahora = current_time('timestamp');
                $dias_restantes = ceil(($fecha_limite - $ahora) / 86400);
                
                if ($dias_restantes < 2) {
                    return [
                        'success' => false, 
                        'message' => 'No puedes cancelar con menos de 2 días restantes'
                    ];
                }
                
                // Procesar cancelación
                $item['estado'] = 'rechazado_no_recibido';
                $item['fecha_cancelacion_cliente'] = current_time('mysql');
                $item['motivo_rechazo'] = 'Cancelado por el cliente - No enviará los items';
                $item['rechazo_definitivo'] = true;
                $item['permite_apelacion'] = false;
                $item['cancelado_por_cliente'] = true;
                
                update_post_meta($garantia_id, '_items_reclamados', $items);
                self::actualizar_estado_garantia($garantia_id);
                
                // Registrar evento
                self::registrar_evento($garantia_id, 'cancelacion_cliente', [
                    'codigo_item' => $codigo_item,
                    'cliente_id' => get_current_user_id()
                ]);
                
                return [
                    'success' => true, 
                    'message' => 'Items cancelados. Puedes crear una nueva garantía para estos productos.'
                ];
            }
        }
        
        return ['success' => false, 'message' => 'Item no encontrado o no cancelable'];
    }
    
    /**
     * Actualizar estado general de la garantía
     */
    private static function actualizar_estado_garantia($garantia_id) {
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        
        if (!is_array($items)) return;
        
        $hay_pendientes = false;
        $hay_esperando = false;
        $todos_finalizados = true;
        
        foreach ($items as $item) {
            $estado = $item['estado'] ?? 'Pendiente';
            
            if ($estado === 'Pendiente') {
                $hay_pendientes = true;
                $todos_finalizados = false;
            } elseif ($estado === 'esperando_recepcion') {
                $hay_esperando = true;
                $todos_finalizados = false;
            } elseif (!in_array($estado, ['aprobado', 'rechazado', 'rechazado_no_recibido', 'retorno_cliente'])) {
                $todos_finalizados = false;
            }
        }
        
        // Determinar estado
        if ($todos_finalizados) {
            update_post_meta($garantia_id, '_estado', 'finalizada');
        } elseif ($hay_esperando) {
            update_post_meta($garantia_id, '_estado', 'parcialmente_recibido');
        } elseif (!$hay_pendientes) {
            update_post_meta($garantia_id, '_estado', 'en_proceso');
        }
    }
    
    /**
     * Notificar rechazo automático
     */
    private static function notificar_rechazo_automatico($garantia_id, $item) {
        $cliente_id = get_post_meta($garantia_id, '_cliente', true);
        $user = get_userdata($cliente_id);
        
        if (!$user || !$user->user_email) return;
        
        $codigo_garantia = get_post_meta($garantia_id, '_codigo_unico', true);
        $producto = wc_get_product($item['producto_id']);
        $nombre_producto = $producto ? $producto->get_name() : 'Producto';
        
        if (class_exists('WC_Garantias_Emails')) {
            WC_Garantias_Emails::enviar_email('rechazo_automatico', $user->user_email, [
                'cliente' => $user->display_name,
                'codigo' => $codigo_garantia,
                'producto' => $nombre_producto,
                'cantidad' => $item['cantidad'],
                'motivo' => 'No recibido en el plazo de 7 das',
                'link_cuenta' => wc_get_account_endpoint_url('garantias')
            ]);
        }
    }
    
    /**
     * Enviar recordatorio
     */
    private static function enviar_recordatorio($garantia_id, $item, $dias_restantes) {
        $cliente_id = get_post_meta($garantia_id, '_cliente', true);
        $user = get_userdata($cliente_id);
        
        if (!$user || !$user->user_email) return;
        
        $codigo_garantia = get_post_meta($garantia_id, '_codigo_unico', true);
        $producto = wc_get_product($item['producto_id']);
        $nombre_producto = $producto ? $producto->get_name() : 'Producto';
        
        if (class_exists('WC_Garantias_Emails')) {
            WC_Garantias_Emails::enviar_email('recordatorio_recepcion', $user->user_email, [
                'cliente' => $user->display_name,
                'codigo' => $codigo_garantia,
                'producto' => $nombre_producto,
                'cantidad' => $item['cantidad'],
                'dias_restantes' => $dias_restantes,
                'link_cuenta' => wc_get_account_endpoint_url('garantias')
            ]);
        }
    }
    
    /**
     * Notificar rechazo manual
     */
    private static function notificar_rechazo_manual($garantia_id, $item) {
        // Similar a notificar_rechazo_automatico pero con mensaje diferente
        $cliente_id = get_post_meta($garantia_id, '_cliente', true);
        $user = get_userdata($cliente_id);
        
        if (!$user || !$user->user_email) return;
        
        $codigo_garantia = get_post_meta($garantia_id, '_codigo_unico', true);
        
        if (class_exists('WC_Garantias_Emails')) {
            WC_Garantias_Emails::enviar_email('rechazo_manual_parcial', $user->user_email, [
                'cliente' => $user->display_name,
                'codigo' => $codigo_garantia,
                'motivo' => $item['motivo_rechazo'],
                'link_cuenta' => wc_get_account_endpoint_url('garantias')
            ]);
        }
    }
    
    /**
     * Registrar evento en el log
     */
    private static function registrar_evento($garantia_id, $tipo_evento, $datos) {
        $log = get_post_meta($garantia_id, '_log_eventos', true);
        if (!is_array($log)) {
            $log = [];
        }
        
        $log[] = [
            'fecha' => current_time('mysql'),
            'tipo' => $tipo_evento,
            'datos' => $datos,
            'usuario' => get_current_user_id()
        ];
        
        update_post_meta($garantia_id, '_log_eventos', $log);
    }
    
    /**
     * AJAX: Procesar recepción parcial
     */
    public static function ajax_procesar_recepcion() {
        check_ajax_referer('procesar_recepcion_parcial', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Sin permisos');
        }
        
        $garantia_id = intval($_POST['garantia_id']);
        $codigo_item = sanitize_text_field($_POST['codigo_item']);
        $cantidad_recibida = intval($_POST['cantidad_recibida']);
        $cantidad_esperada = intval($_POST['cantidad_esperada']);
        
        $resultado = self::procesar_recepcion_parcial(
            $garantia_id, 
            $codigo_item, 
            $cantidad_recibida, 
            $cantidad_esperada
        );
        
        if ($resultado['success']) {
            wp_send_json_success($resultado);
        } else {
            wp_send_json_error($resultado['message']);
        }
    }
    
    /**
     * AJAX: Extender plazo
     */
    public static function ajax_extender_plazo() {
        check_ajax_referer('extender_plazo', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Sin permisos');
        }
        
        $garantia_id = intval($_POST['garantia_id']);
        $codigo_item = sanitize_text_field($_POST['codigo_item']);
        $dias = intval($_POST['dias'] ?? 7);
        
        $resultado = self::extender_plazo($garantia_id, $codigo_item, $dias);
        
        if ($resultado['success']) {
            wp_send_json_success($resultado);
        } else {
            wp_send_json_error($resultado['message']);
        }
    }
    
    /**
     * AJAX: Rechazar manual
     */
    public static function ajax_rechazar_manual() {
        check_ajax_referer('rechazar_manual', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Sin permisos');
        }
        
        $garantia_id = intval($_POST['garantia_id']);
        $codigo_item = sanitize_text_field($_POST['codigo_item']);
        $motivo = sanitize_textarea_field($_POST['motivo'] ?? '');
        
        $resultado = self::rechazar_manual($garantia_id, $codigo_item, $motivo);
        
        if ($resultado['success']) {
            wp_send_json_success($resultado);
        } else {
            wp_send_json_error($resultado['message']);
        }
    }
    
    /**
     * AJAX: Cancelar envío (cliente)
     */
    public static function ajax_cancelar_envio_cliente() {
        check_ajax_referer('cancelar_envio', 'nonce');
        
        $garantia_id = intval($_POST['garantia_id']);
        $codigo_item = sanitize_text_field($_POST['codigo_item']);
        
        // Verificar que el cliente es el dueño de la garantía
        $cliente_id = get_post_meta($garantia_id, '_cliente', true);
        if ($cliente_id != get_current_user_id()) {
            wp_send_json_error('Sin permisos');
        }
        
        $resultado = self::cancelar_envio_cliente($garantia_id, $codigo_item);
        
        if ($resultado['success']) {
            wp_send_json_success($resultado);
        } else {
            wp_send_json_error($resultado['message']);
        }
    }
    /**
     * Notificar recepción parcial al cliente
     */
        private static function notificar_recepcion_parcial($garantia_id, $resultado) {
            error_log('=== FUNCIÓN notificar_recepcion_parcial ===');
            error_log('Garantía ID: ' . $garantia_id);
            error_log('Resultado recibido: ' . print_r($resultado, true));
            
            $cliente_id = get_post_meta($garantia_id, '_cliente', true);
            $user = get_userdata($cliente_id);
            
            if (!$user || !$user->user_email) {
                return false;
            }
            
            $codigo_garantia = get_post_meta($garantia_id, '_codigo_unico', true);
            
            // Obtener información del primer item procesado
            $item_recibido = isset($resultado['items_actualizados'][0]) ? $resultado['items_actualizados'][0] : null;
            $item_pendiente = isset($resultado['items_creados'][0]) ? $resultado['items_creados'][0] : null;
            
            // DEBUG: Ver qué contiene el item
            error_log('Item recibido: ' . print_r($item_recibido, true));
            error_log('Item pendiente: ' . print_r($item_pendiente, true));
            
            // Obtener nombre del producto - MEJORADO
            $producto_nombre = 'Producto';
            
            // Primero intentar del item recibido
            if ($item_recibido && isset($item_recibido['producto_id'])) {
                error_log('Producto ID: ' . $item_recibido['producto_id']);
                
                $producto = wc_get_product($item_recibido['producto_id']);
                if ($producto) {
                    $producto_nombre = $producto->get_name();
                    error_log('Producto encontrado: ' . $producto_nombre);
                } else {
                    error_log('Producto NO encontrado con ID: ' . $item_recibido['producto_id']);
                    
                    // Intentar obtener el nombre guardado
                    if (isset($item_recibido['nombre_producto'])) {
                        $producto_nombre = $item_recibido['nombre_producto'];
                        error_log('Usando nombre guardado: ' . $producto_nombre);
                    }
                }
            }
            
            // Si aún no tenemos nombre, buscar en los items de la garantía
            if ($producto_nombre === 'Producto') {
                error_log('Buscando en items de la garantía...');
                $items = get_post_meta($garantia_id, '_items_reclamados', true);
                if (is_array($items) && !empty($items)) {
                    $primer_item = reset($items);
                    if (isset($primer_item['producto_id'])) {
                        $producto = wc_get_product($primer_item['producto_id']);
                        if ($producto) {
                            $producto_nombre = $producto->get_name();
                            error_log('Producto encontrado en items: ' . $producto_nombre);
                        }
                    }
                }
            }
            
            $codigo_item = 'SIN-ITEM';
            if ($item_recibido && isset($item_recibido['codigo_item'])) {
                $codigo_item = $item_recibido['codigo_item'];
            }
            
            $variables = [
                'cliente' => $user->display_name,
                'codigo' => $codigo_garantia,
                'producto' => $producto_nombre,
                'cantidad_recibida' => $item_recibido ? $item_recibido['cantidad'] : 0,
                'cantidad_pendiente' => $item_pendiente ? $item_pendiente['cantidad'] : 0,
                'fecha_limite' => $item_pendiente ? date('d/m/Y', strtotime($item_pendiente['auto_rechazo']['fecha_limite'])) : '',
                'link_cuenta' => wc_get_account_endpoint_url('garantias'),
                'codigo_item' => $codigo_item,
                'item_codigo_procesado' => $codigo_item
            ];
            
            error_log('Variables finales para email: ' . print_r($variables, true));
            
            if (class_exists('WC_Garantias_Emails')) {
                $resultado_email = WC_Garantias_Emails::enviar_email('recepcion_parcial', $user->user_email, $variables);
                error_log('Resultado del envío: ' . ($resultado_email ? 'ÉXITO' : 'FALLO'));
                return $resultado_email;
            }
            
            return false;
        }
}

// Inicializar
add_action('init', ['WC_Garantias_Recepcion_Parcial', 'init']);