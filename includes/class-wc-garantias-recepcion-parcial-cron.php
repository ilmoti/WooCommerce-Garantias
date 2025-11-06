<?php
/**
 * Sistema de Recepción Parcial - Tareas Programadas (Cron)
 * Maneja el auto-rechazo y recordatorios automáticos
 * 
 * @package WC_Garantias
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

class WC_Garantias_Recepcion_Parcial_Cron {
    
    /**
     * Hook para el cron
     */
    const CRON_HOOK = 'wc_garantias_check_recepcion_parcial';
    const CRON_HOOK_RECORDATORIO = 'wc_garantias_enviar_recordatorios';
    
    /**
     * Inicializar cron jobs
     */
    public static function init() {
        // Registrar hooks
        add_action(self::CRON_HOOK, [__CLASS__, 'ejecutar_auto_rechazo']);
        add_action(self::CRON_HOOK_RECORDATORIO, [__CLASS__, 'ejecutar_recordatorios']);
        
        // Programar eventos si no existen
        add_action('wp', [__CLASS__, 'programar_cron_jobs']);
        
        // Limpiar al desactivar
        register_deactivation_hook(WC_GARANTIAS_FILE, [__CLASS__, 'limpiar_cron_jobs']);
        
        // Admin: Ver estado del cron
        add_action('admin_menu', [__CLASS__, 'agregar_pagina_cron_status']);
        
        // AJAX para ejecutar manualmente
        add_action('wp_ajax_garantias_ejecutar_cron_manual', [__CLASS__, 'ajax_ejecutar_cron_manual']);
    }
    
    /**
     * Programar cron jobs
     */
    public static function programar_cron_jobs() {
        // Auto-rechazo diario a las 2 AM
        if (!wp_next_scheduled(self::CRON_HOOK)) {
            $timestamp = strtotime('today 2:00am');
            if ($timestamp < time()) {
                $timestamp = strtotime('tomorrow 2:00am');
            }
            wp_schedule_event($timestamp, 'daily', self::CRON_HOOK);
            error_log('Cron de auto-rechazo programado para: ' . date('Y-m-d H:i:s', $timestamp));
        }
        
        // Recordatorios cada 6 horas
        if (!wp_next_scheduled(self::CRON_HOOK_RECORDATORIO)) {
            wp_schedule_event(time(), 'sixhours', self::CRON_HOOK_RECORDATORIO);
            error_log('Cron de recordatorios programado cada 6 horas');
        }
    }
    
    /**
     * Limpiar cron jobs
     */
    public static function limpiar_cron_jobs() {
        wp_clear_scheduled_hook(self::CRON_HOOK);
        wp_clear_scheduled_hook(self::CRON_HOOK_RECORDATORIO);
        error_log('Cron jobs de recepcin parcial eliminados');
    }
    
    /**
     * Ejecutar auto-rechazo de items vencidos
     */
    public static function ejecutar_auto_rechazo() {
        $inicio = microtime(true);
        error_log('=== INICIANDO CRON AUTO-RECHAZO RECEPCIÓN PARCIAL ===');
        
        // Obtener todas las garantías activas
        $garantias = get_posts([
            'post_type' => 'garantia',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_estado',
                    'value' => ['nueva', 'en_proceso', 'parcialmente_recibido'],
                    'compare' => 'IN'
                ]
            ]
        ]);
        
        $estadisticas = [
            'garantias_procesadas' => 0,
            'items_rechazados' => 0,
            'items_con_error' => 0,
            'emails_enviados' => 0
        ];
        
        foreach ($garantias as $garantia) {
            $items = get_post_meta($garantia->ID, '_items_reclamados', true);
            
            if (!is_array($items)) continue;
            
            $items_modificados = false;
            $estadisticas['garantias_procesadas']++;
            
            foreach ($items as &$item) {
                // Solo procesar items en estado "esperando_recepcion"
                if ($item['estado'] !== 'esperando_recepcion') continue;
                
                // Verificar si tiene auto-rechazo activo
                if (!isset($item['auto_rechazo']['activo']) || !$item['auto_rechazo']['activo']) continue;
                
                $fecha_limite = strtotime($item['auto_rechazo']['fecha_limite']);
                $ahora = current_time('timestamp');
                
                // Verificar si venció
                if ($ahora > $fecha_limite) {
                    try {
                        // Cambiar estado
                        $item['estado'] = 'rechazado_no_recibido';
                        $item['fecha_rechazo_auto'] = current_time('mysql');
                        $item['motivo_rechazo'] = 'No recibido en el plazo de 7 días';
                        $item['rechazo_definitivo'] = true;
                        $item['permite_apelacion'] = false;
                        $item['rechazado_por_cron'] = true;
                        
                        // Registrar en log del item
                        if (!isset($item['log_eventos'])) {
                            $item['log_eventos'] = [];
                        }
                        $item['log_eventos'][] = [
                            'fecha' => current_time('mysql'),
                            'evento' => 'auto_rechazo',
                            'descripcion' => 'Rechazado automáticamente por vencimiento del plazo'
                        ];
                        
                        $items_modificados = true;
                        $estadisticas['items_rechazados']++;
                        
                        // Notificar al cliente
                        if (self::notificar_rechazo_automatico($garantia->ID, $item)) {
                            $estadisticas['emails_enviados']++;
                        }
                        
                        error_log("✓ Item {$item['codigo_item']} rechazado automáticamente");
                        
                    } catch (Exception $e) {
                        error_log("ERROR al rechazar item {$item['codigo_item']}: " . $e->getMessage());
                        $estadisticas['items_con_error']++;
                    }
                }
            }
            
            // Guardar cambios si hubo modificaciones
            if ($items_modificados) {
                update_post_meta($garantia->ID, '_items_reclamados', $items);
                
                // Actualizar estado general de la garantía
                self::actualizar_estado_garantia_cron($garantia->ID);
                
                // Registrar en log de la garantía
                self::registrar_evento_cron($garantia->ID, 'auto_rechazo_ejecutado', [
                    'items_rechazados' => $estadisticas['items_rechazados']
                ]);
            }
        }
        
        $tiempo_ejecucion = round(microtime(true) - $inicio, 2);
        
        // Guardar estadísticas
        self::guardar_estadisticas_cron($estadisticas, $tiempo_ejecucion);
        
        error_log("=== CRON AUTO-RECHAZO COMPLETADO ===");
        error_log("Tiempo: {$tiempo_ejecucion}s");
        error_log("Garantías procesadas: {$estadisticas['garantias_procesadas']}");
        error_log("Items rechazados: {$estadisticas['items_rechazados']}");
        error_log("Emails enviados: {$estadisticas['emails_enviados']}");
        
        return $estadisticas;
    }
    
    /**
     * Ejecutar envío de recordatorios
     */
    public static function ejecutar_recordatorios() {
        $inicio = microtime(true);
        error_log('=== INICIANDO CRON RECORDATORIOS ===');
        
        $garantias = get_posts([
            'post_type' => 'garantia',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_estado',
                    'value' => ['nueva', 'en_proceso', 'parcialmente_recibido'],
                    'compare' => 'IN'
                ]
            ]
        ]);
        
        $recordatorios_enviados = 0;
        
        foreach ($garantias as $garantia) {
            $items = get_post_meta($garantia->ID, '_items_reclamados', true);
            
            if (!is_array($items)) continue;
            
            $items_modificados = false;
            
            foreach ($items as &$item) {
                // Solo items esperando recepción
                if ($item['estado'] !== 'esperando_recepcion') continue;
                
                // Verificar si ya se envió recordatorio
                if (isset($item['auto_rechazo']['recordatorio_enviado']) && 
                    $item['auto_rechazo']['recordatorio_enviado']) continue;
                
                $fecha_limite = strtotime($item['auto_rechazo']['fecha_limite']);
                $ahora = current_time('timestamp');
                $dias_restantes = ceil(($fecha_limite - $ahora) / 86400);
                
                // Enviar recordatorio cuando quedan 2 días o menos
                if ($dias_restantes <= 2 && $dias_restantes > 0) {
                    if (self::enviar_recordatorio($garantia->ID, $item, $dias_restantes)) {
                        $item['auto_rechazo']['recordatorio_enviado'] = true;
                        $item['auto_rechazo']['fecha_recordatorio'] = current_time('mysql');
                        $items_modificados = true;
                        $recordatorios_enviados++;
                        
                        error_log("✓ Recordatorio enviado para item {$item['codigo_item']} - {$dias_restantes} días restantes");
                    }
                }
            }
            
            if ($items_modificados) {
                update_post_meta($garantia->ID, '_items_reclamados', $items);
            }
        }
        
        $tiempo_ejecucion = round(microtime(true) - $inicio, 2);
        
        error_log("=== CRON RECORDATORIOS COMPLETADO ===");
        error_log("Tiempo: {$tiempo_ejecucion}s");
        error_log("Recordatorios enviados: {$recordatorios_enviados}");
        
        return $recordatorios_enviados;
    }
    
    /**
     * Notificar rechazo automático al cliente
     */
    private static function notificar_rechazo_automatico($garantia_id, $item) {
        $cliente_id = get_post_meta($garantia_id, '_cliente', true);
        $user = get_userdata($cliente_id);
        
        if (!$user || !$user->user_email) {
            error_log("No se pudo obtener email del cliente para garantía {$garantia_id}");
            return false;
        }
        
        $codigo_garantia = get_post_meta($garantia_id, '_codigo_unico', true);
        $producto = wc_get_product($item['producto_id']);
        $nombre_producto = $producto ? $producto->get_name() : 'Producto';
        
        // Preparar variables para el email
        $variables = [
            'cliente' => $user->display_name,
            'codigo' => $codigo_garantia,
            'producto' => $nombre_producto,
            'cantidad' => $item['cantidad'],
            'codigo_item' => $item['codigo_item'],
            'fecha_limite' => date('d/m/Y', strtotime($item['auto_rechazo']['fecha_limite'])),
            'link_cuenta' => wc_get_account_endpoint_url('garantias'),
            'puede_crear_nueva' => true
        ];
        
        // Enviar email
        if (class_exists('WC_Garantias_Emails')) {
            return WC_Garantias_Emails::enviar_email('rechazo_no_recibido', $user->user_email, $variables);
        }
        
        // Fallback: email simple
        $asunto = "Items rechazados - Garantía {$codigo_garantia}";
        $mensaje = "Hola {$user->display_name},\n\n";
        $mensaje .= "Los siguientes items fueron rechazados por no ser recibidos en el plazo establecido:\n\n";
        $mensaje .= "• {$item['cantidad']} unidades de {$nombre_producto}\n";
        $mensaje .= "• Cdigo: {$item['codigo_item']}\n";
        $mensaje .= "• Fecha límite: " . date('d/m/Y', strtotime($item['auto_rechazo']['fecha_limite'])) . "\n\n";
        $mensaje .= "Puedes crear una nueva garantía para estos items desde tu cuenta.\n\n";
        $mensaje .= "Saludos,\nEquipo de " . get_bloginfo('name');
        
        return wp_mail($user->user_email, $asunto, $mensaje);
    }
    
    /**
     * Enviar recordatorio al cliente
     */
    private static function enviar_recordatorio($garantia_id, $item, $dias_restantes) {
        $cliente_id = get_post_meta($garantia_id, '_cliente', true);
        $user = get_userdata($cliente_id);
        
        if (!$user || !$user->user_email) {
            return false;
        }
        
        $codigo_garantia = get_post_meta($garantia_id, '_codigo_unico', true);
        $producto = wc_get_product($item['producto_id']);
        $nombre_producto = $producto ? $producto->get_name() : 'Producto';
        
        $variables = [
            'cliente' => $user->display_name,
            'codigo' => $codigo_garantia,
            'producto' => $nombre_producto,
            'cantidad' => $item['cantidad'],
            'dias_restantes' => $dias_restantes,
            'fecha_limite' => date('d/m/Y H:i', strtotime($item['auto_rechazo']['fecha_limite'])),
            'link_cuenta' => wc_get_account_endpoint_url('garantias')
        ];
        
        if (class_exists('WC_Garantias_Emails')) {
            return WC_Garantias_Emails::enviar_email('recordatorio_recepcion', $user->user_email, $variables);
        }
        
        // Fallback
        $asunto = "⏰ Recordatorio: {$dias_restantes} días para enviar items - Garantía {$codigo_garantia}";
        $mensaje = "Hola {$user->display_name},\n\n";
        $mensaje .= "Te recordamos que tienes {$dias_restantes} días para enviar:\n\n";
        $mensaje .= "• {$item['cantidad']} unidades de {$nombre_producto}\n\n";
        $mensaje .= "Fecha límite: " . date('d/m/Y', strtotime($item['auto_rechazo']['fecha_limite'])) . "\n\n";
        $mensaje .= "Si no envías los items antes de esta fecha, serán rechazados automáticamente.\n\n";
        $mensaje .= "Ingresa a tu cuenta para más detalles o cancelar el envío.\n\n";
        $mensaje .= "Saludos,\nEquipo de " . get_bloginfo('name');
        
        return wp_mail($user->user_email, $asunto, $mensaje);
    }
    
    /**
     * Actualizar estado de garantía desde cron
     */
    private static function actualizar_estado_garantia_cron($garantia_id) {
        if (class_exists('WC_Garantias_Recepcion_Parcial')) {
            // Usar el método de la clase principal
            $items = get_post_meta($garantia_id, '_items_reclamados', true);
            
            if (!is_array($items)) return;
            
            $todos_finalizados = true;
            $hay_esperando = false;
            
            foreach ($items as $item) {
                $estado = $item['estado'] ?? 'Pendiente';
                
                if ($estado === 'esperando_recepcion') {
                    $hay_esperando = true;
                    $todos_finalizados = false;
                } elseif (!in_array($estado, ['aprobado', 'rechazado', 'rechazado_no_recibido', 'retorno_cliente'])) {
                    $todos_finalizados = false;
                }
            }
            
            if ($todos_finalizados) {
                update_post_meta($garantia_id, '_estado', 'finalizada');
            } elseif ($hay_esperando) {
                update_post_meta($garantia_id, '_estado', 'parcialmente_recibido');
            }
        }
    }
    
    /**
     * Registrar evento en log
     */
    private static function registrar_evento_cron($garantia_id, $evento, $datos = []) {
        $log = get_post_meta($garantia_id, '_log_eventos', true);
        if (!is_array($log)) {
            $log = [];
        }
        
        $log[] = [
            'fecha' => current_time('mysql'),
            'tipo' => $evento,
            'origen' => 'cron',
            'datos' => $datos
        ];
        
        update_post_meta($garantia_id, '_log_eventos', $log);
    }
    
    /**
     * Guardar estadísticas del cron
     */
    private static function guardar_estadisticas_cron($estadisticas, $tiempo_ejecucion) {
        $historico = get_option('wc_garantias_cron_stats', []);
        
        if (!is_array($historico)) {
            $historico = [];
        }
        
        // Agregar nueva entrada
        $historico[] = [
            'fecha' => current_time('mysql'),
            'estadisticas' => $estadisticas,
            'tiempo_ejecucion' => $tiempo_ejecucion
        ];
        
        // Mantener solo los últimos 30 registros
        if (count($historico) > 30) {
            $historico = array_slice($historico, -30);
        }
        
        update_option('wc_garantias_cron_stats', $historico);
    }
    
    /**
     * Página de estado del cron en admin
     */
    public static function agregar_pagina_cron_status() {
        add_submenu_page(
            'wc-garantias-dashboard',
            'Estado Cron',
            'Estado Cron',
            'manage_woocommerce',
            'wc-garantias-cron-status',
            [__CLASS__, 'render_pagina_cron_status']
        );
    }
    
    /**
     * Renderizar página de estado del cron
     */
    public static function render_pagina_cron_status() {
        // Obtener próximas ejecuciones
        $proximo_autorechazo = wp_next_scheduled(self::CRON_HOOK);
        $proximo_recordatorio = wp_next_scheduled(self::CRON_HOOK_RECORDATORIO);
        
        // Obtener estadísticas
        $stats = get_option('wc_garantias_cron_stats', []);
        $ultima_ejecucion = !empty($stats) ? end($stats) : null;
        
        // Contar items pendientes
        $items_esperando = 0;
        $garantias = get_posts([
            'post_type' => 'garantia',
            'post_status' => 'publish',
            'posts_per_page' => -1
        ]);
        
        foreach ($garantias as $garantia) {
            $items = get_post_meta($garantia->ID, '_items_reclamados', true);
            if (is_array($items)) {
                foreach ($items as $item) {
                    if ($item['estado'] === 'esperando_recepcion') {
                        $items_esperando++;
                    }
                }
            }
        }
        ?>
        <div class="wrap">
            <h1>Estado del Cron - Recepción Parcial</h1>
            
            <!-- Estado actual -->
            <div class="card" style="max-width: 100%; margin: 20px 0;">
                <h2>Estado Actual</h2>
                <table class="wp-list-table widefat fixed striped">
                    <tr>
                        <th>Tarea</th>
                        <th>Próxima Ejecución</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                    <tr>
                        <td><strong>Auto-rechazo diario</strong></td>
                        <td>
                            <?php 
                            if ($proximo_autorechazo) {
                                echo date('d/m/Y H:i:s', $proximo_autorechazo);
                                echo ' (' . human_time_diff(time(), $proximo_autorechazo) . ')';
                            } else {
                                echo '<span style="color: red;">No programado</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php echo $proximo_autorechazo ? 
                                '<span style="color: green;">✓ Activo</span>' : 
                                '<span style="color: red;">✗ Inactivo</span>'; ?>
                        </td>
                        <td>
                            <button class="button" onclick="ejecutarCronManual('auto_rechazo')">
                                Ejecutar Ahora
                            </button>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Recordatorios</strong></td>
                        <td>
                            <?php 
                            if ($proximo_recordatorio) {
                                echo date('d/m/Y H:i:s', $proximo_recordatorio);
                                echo ' (' . human_time_diff(time(), $proximo_recordatorio) . ')';
                            } else {
                                echo '<span style="color: red;">No programado</span>';
                            }
                            ?>
                        </td>
                        <td>
                            <?php echo $proximo_recordatorio ? 
                                '<span style="color: green;">✓ Activo</span>' : 
                                '<span style="color: red;">✗ Inactivo</span>'; ?>
                        </td>
                        <td>
                            <button class="button" onclick="ejecutarCronManual('recordatorios')">
                                Ejecutar Ahora
                            </button>
                        </td>
                    </tr>
                </table>
            </div>
            
            <!-- Items pendientes -->
            <div class="card" style="max-width: 100%; margin: 20px 0;">
                <h2>Items Pendientes</h2>
                <p>
                    <strong><?php echo $items_esperando; ?></strong> items esperando recepción
                </p>
                <?php if ($items_esperando > 0): ?>
                    <button class="button button-primary" onclick="ejecutarCronManual('verificar_todos')">
                        Verificar Todos Ahora
                    </button>
                <?php endif; ?>
            </div>
            
            <!-- Última ejecución -->
            <?php if ($ultima_ejecucion): ?>
            <div class="card" style="max-width: 100%; margin: 20px 0;">
                <h2>Última Ejecución</h2>
                <table class="wp-list-table widefat fixed striped">
                    <tr>
                        <th>Fecha</th>
                        <td><?php echo date('d/m/Y H:i:s', strtotime($ultima_ejecucion['fecha'])); ?></td>
                    </tr>
                    <tr>
                        <th>Tiempo de Ejecución</th>
                        <td><?php echo $ultima_ejecucion['tiempo_ejecucion']; ?> segundos</td>
                    </tr>
                    <?php if (isset($ultima_ejecucion['estadisticas'])): ?>
                    <tr>
                        <th>Garantías Procesadas</th>
                        <td><?php echo $ultima_ejecucion['estadisticas']['garantias_procesadas'] ?? 0; ?></td>
                    </tr>
                    <tr>
                        <th>Items Rechazados</th>
                        <td><?php echo $ultima_ejecucion['estadisticas']['items_rechazados'] ?? 0; ?></td>
                    </tr>
                    <tr>
                        <th>Emails Enviados</th>
                        <td><?php echo $ultima_ejecucion['estadisticas']['emails_enviados'] ?? 0; ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            <?php endif; ?>
            
            <!-- Botones de acción -->
            <div class="card" style="max-width: 100%; margin: 20px 0;">
                <h2>Acciones</h2>
                <p>
                    <button class="button" onclick="reprogramarCron()">
                        Reprogramar Cron Jobs
                    </button>
                    <button class="button" onclick="limpiarEstadisticas()">
                        Limpiar Estadísticas
                    </button>
                </p>
            </div>
            
            <script>
            function ejecutarCronManual(tipo) {
                if (!confirm('¿Ejecutar esta tarea ahora?')) return;
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'garantias_ejecutar_cron_manual',
                        tipo: tipo,
                        nonce: '<?php echo wp_create_nonce("ejecutar_cron_manual"); ?>'
                    },
                    beforeSend: function() {
                        jQuery('button').prop('disabled', true);
                    },
                    success: function(response) {
                        if (response.success) {
                            alert('✅ Tarea ejecutada:\n' + response.data.message);
                            location.reload();
                        } else {
                            alert('❌ Error: ' + response.data);
                        }
                    },
                    error: function() {
                        alert('❌ Error de conexión');
                    },
                    complete: function() {
                        jQuery('button').prop('disabled', false);
                    }
                });
            }
            
            function reprogramarCron() {
                if (!confirm('¿Reprogramar todos los cron jobs?')) return;
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'garantias_reprogramar_cron',
                        nonce: '<?php echo wp_create_nonce("reprogramar_cron"); ?>'
                    },
                    success: function(response) {
                        alert(' Cron jobs reprogramados');
                        location.reload();
                    }
                });
            }
            
            function limpiarEstadisticas() {
                if (!confirm('Limpiar todas las estadísticas?')) return;
                
                jQuery.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'garantias_limpiar_stats',
                        nonce: '<?php echo wp_create_nonce("limpiar_stats"); ?>'
                    },
                    success: function(response) {
                        alert('✅ Estadísticas limpiadas');
                        location.reload();
                    }
                });
            }
            </script>
        </div>
        <?php
    }
    
    /**
     * AJAX: Ejecutar cron manualmente
     */
    public static function ajax_ejecutar_cron_manual() {
        check_ajax_referer('ejecutar_cron_manual', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Sin permisos');
        }
        
        $tipo = sanitize_text_field($_POST['tipo']);
        
        switch ($tipo) {
            case 'auto_rechazo':
                $resultado = self::ejecutar_auto_rechazo();
                wp_send_json_success([
                    'message' => "Items rechazados: {$resultado['items_rechazados']}"
                ]);
                break;
                
            case 'recordatorios':
                $resultado = self::ejecutar_recordatorios();
                wp_send_json_success([
                    'message' => "Recordatorios enviados: {$resultado}"
                ]);
                break;
                
            case 'verificar_todos':
                $auto_rechazo = self::ejecutar_auto_rechazo();
                $recordatorios = self::ejecutar_recordatorios();
                wp_send_json_success([
                    'message' => "Rechazados: {$auto_rechazo['items_rechazados']}, Recordatorios: {$recordatorios}"
                ]);
                break;
                
            default:
                wp_send_json_error('Tipo no válido');
        }
    }
}