<?php
/**
 * Frontend de Recepción Parcial para Clientes
 * Muestra contadores, botones y vistas para items esperando recepción
 * 
 * @package WC_Garantias
 * @since 1.0.0
 */

if (!defined('ABSPATH')) exit;

class WC_Garantias_Recepcion_Parcial_Frontend {
    
    /**
     * Renderizar contador de días y botón cancelar para items esperando
     */
    public static function render_contador_dias_cliente($item, $garantia_id) {
        // Solo mostrar para items esperando recepción
        if (!isset($item['estado']) || $item['estado'] !== 'esperando_recepcion') {
            return;
        }
        
        // Calcular días restantes
        $fecha_limite = strtotime($item['auto_rechazo']['fecha_limite'] ?? '+7 days');
        $ahora = current_time('timestamp');
        $dias_restantes = max(0, ceil(($fecha_limite - $ahora) / 86400));
        
        // Color según días restantes
        $color = '#28a745'; // Verde
        if ($dias_restantes <= 2) {
            $color = '#dc3545'; // Rojo
        } elseif ($dias_restantes <= 4) {
            $color = '#ff9800'; // Naranja
        }
        
        // Siempre puede cancelar mientras esté esperando
        $puede_cancelar = true;
        ?>
        
        <div style="display: flex; align-items: center; justify-content: center; gap: 10px; margin-top: 10px; flex-wrap: wrap;">
            <!-- Contador de días -->
            <div style="background: <?php echo $color; ?>; color: white; padding: 6px 12px; border-radius: 20px; font-size: 13px; font-weight: bold; display: inline-flex; align-items: center; justify-content: center; min-width: 120px; text-align: center;">
                <i class="fas fa-clock" style="margin-right: 5px;"></i>
                <?php echo $dias_restantes; ?> días restantes
            </div>
            
            <!-- Botón cancelar envío -->
            <?php if ($puede_cancelar): ?>
                <button type="button" 
                        class="btn-cancelar-envio-cliente"
                        data-garantia-id="<?php echo $garantia_id; ?>"
                        data-codigo-item="<?php echo esc_attr($item['codigo_item']); ?>"
                        style="background: #dc3545; color: white; border: none; padding: 6px 15px; border-radius: 5px; cursor: pointer; font-size: 13px;"
                        title="Cancelar envío de este item">
                    <i class="fas fa-times"></i> Cancelar envío
                </button>
            <?php else: ?>
                <span style="color: #dc3545; font-size: 12px;">
                    <i class="fas fa-exclamation-triangle"></i> Muy tarde para cancelar
                </span>
            <?php endif; ?>
        </div>
        
        <?php
    }
    
        /**
     * Renderizar vista detallada de recepción parcial
     */
    public static function render_detalle_recepcion_parcial($garantia_id, $items) {
        // Verificar si hay items divididos
        $hay_recepcion_parcial = false;
        $items_recibidos = [];
        $items_esperando = [];
        $total_recibido = 0;
        $total_esperando = 0;
        
        foreach ($items as $item) {
            if (isset($item['recepcion_parcial'])) {
                $hay_recepcion_parcial = true;
                $items_recibidos[] = $item;
                $total_recibido += $item['cantidad'];
            }
            if (isset($item['estado']) && $item['estado'] === 'esperando_recepcion') {
                $hay_recepcion_parcial = true;
                $items_esperando[] = $item;
                $total_esperando += $item['cantidad'];
            }
        }
        
        if (!$hay_recepcion_parcial) {
            return;
        }
        
        // Obtener tracking original si existe
        $tracking_original = '';
        foreach ($items as $item) {
            if (isset($item['tracking_original'])) {
                $tracking_original = $item['tracking_original'];
                break;
            }
        }
        ?>
        
        <div style="background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%); border-radius: 8px; padding: 15px; margin: 15px 0; border: 1px solid #ffeaa7;">
            <h4 style="margin: 0 0 10px 0; color: #856404; font-size: 16px;">
                <i class="fas fa-exclamation-triangle"></i> Recepción Parcial - Garantía <?php echo get_post_meta($garantia_id, '_codigo_unico', true); ?>
            </h4>
            
            <div style="background: white; padding: 12px; border-radius: 6px; margin-bottom: 10px;">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 10px;">
                    <div>
                        <div style="color: #6c757d; font-size: 11px;">Enviaste:</div>
                        <div style="font-size: 18px; font-weight: bold; color: #333;">
                            <?php echo ($total_recibido + $total_esperando); ?> unidades
                        </div>
                    </div>
                    
                    <div>
                        <div style="color: #28a745; font-size: 11px;">✅ Recibimos:</div>
                        <div style="font-size: 18px; font-weight: bold; color: #28a745;">
                            <?php echo $total_recibido; ?> unidades
                        </div>
                    </div>
                    
                    <div>
                        <div style="color: #ff9800; font-size: 11px;">⏳ Esperando:</div>
                        <div style="font-size: 18px; font-weight: bold; color: #ff9800;">
                            <?php echo $total_esperando; ?> unidades
                        </div>
                    </div>
                </div>
                
                <?php if ($tracking_original): ?>
                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #dee2e6; font-size: 12px;">
                        <strong>Tracking original:</strong> <?php echo esc_html($tracking_original); ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($total_esperando > 0): ?>
                <!-- Mostrar items esperando con detalles -->
                <div style="background: #fff3cd; padding: 10px; border-radius: 6px; margin-bottom: 10px; font-size: 13px;">
                    <strong>Items pendientes de recepción:</strong>
                    <?php foreach ($items_esperando as $item_esp): ?>
                        <?php 
                        $producto = wc_get_product($item_esp['producto_id']);
                        $nombre_producto = $producto ? $producto->get_name() : 'Producto eliminado';
                        ?>
                        <div style="margin-top: 5px; padding: 5px 0; border-bottom: 1px dashed #ffeaa7;">
                            <strong><?php echo esc_html($item_esp['codigo_item']); ?></strong> - 
                            <?php echo esc_html($nombre_producto); ?> 
                            (<?php echo $item_esp['cantidad']; ?> unidades)
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="background: #fff3cd; padding: 10px; border-radius: 6px; border: 1px solid #ffeaa7;">
                    <p style="margin: 0; color: #856404; font-size: 13px;">
                  <?php 
                    // Calcular días restantes del primer item esperando
                    $dias_mostrar = 7;
                    foreach ($items_esperando as $item_esp) {
                        if (isset($item_esp['auto_rechazo']['fecha_limite'])) {
                            $fecha_limite = strtotime($item_esp['auto_rechazo']['fecha_limite']);
                            $ahora = current_time('timestamp');
                            $dias_mostrar = max(0, ceil(($fecha_limite - $ahora) / 86400));
                            break;
                        }
                    }
                    ?>
                    <p style="margin: 0; color: #856404; font-size: 13px;">
                        <strong>Importante:</strong> Tienes <?php echo $dias_mostrar; ?> días para enviar las unidades faltantes o serán rechazadas automáticamente.
                        Si no vas a enviarlas, puedes cancelar el envío para crear una nueva garantía.
                    </p>
                </div>
            <?php endif; ?>
        </div>
        
        <?php
    }
    
    /**
     * Agregar scripts para manejo de cancelación
     */
        public static function agregar_scripts_frontend() {
        ?>
        <script>
        jQuery(document).ready(function($) {
            console.log('Script de recepción parcial cargado');
            
            // Manejar clic en botón cancelar envío
            $(document).on('click', '.btn-cancelar-envio-cliente', function(e) {
                e.preventDefault();
                console.log('Botn cancelar clickeado');
                
                var $boton = $(this);
                var garantiaId = $boton.data('garantia-id');
                var codigoItem = $boton.data('codigo-item');
                
                console.log('Garantia ID:', garantiaId);
                console.log('Codigo Item:', codigoItem);
                
                // Confirmación
                var mensaje = '¿Confirmas que NO enviars estos items?\n\n';
                mensaje += 'Se rechazarán definitivamente sin posibilidad de apelación.\n';
                mensaje += 'Podrás crear una nueva garantía después.';
                
                if (!confirm(mensaje)) {
                    console.log('Cancelado por el usuario');
                    return;
                }
                
                // Deshabilitar botón
                $boton.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Cancelando...');
                
                console.log('Enviando AJAX...');
                
                // Llamada AJAX
                $.ajax({
                    url: '<?php echo admin_url("admin-ajax.php"); ?>',
                    type: 'POST',
                    data: {
                        action: 'cancelar_envio_item',
                        garantia_id: garantiaId,
                        codigo_item: codigoItem,
                        nonce: '<?php echo wp_create_nonce("cancelar_envio"); ?>'
                    },
                    success: function(response) {
                        console.log('Respuesta:', response);
                        if (response.success) {
                            alert('✅ ' + response.data.message);
                            location.reload();
                        } else {
                            alert('❌ Error: ' + (response.data || 'Error desconocido'));
                            $boton.prop('disabled', false).html('<i class="fas fa-times"></i> Cancelar envío');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error AJAX:', status, error);
                        console.error('Respuesta completa:', xhr.responseText);
                        alert(' Error de conexión: ' + error);
                        $boton.prop('disabled', false).html('<i class="fas fa-times"></i> Cancelar envío');
                    }
                });
            });
        });
        </script>
        <?php
    }
}

// Inicializar funciones AJAX
add_action('wp_ajax_cancelar_envio_item', ['WC_Garantias_Recepcion_Parcial', 'ajax_cancelar_envio_cliente']);