<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Renderizado HTML para Ver Garantía
 */
class WC_Garantias_Admin_View_Render {
    
    public static function render($garantia_id, $datos) {
        // Extraer datos
        extract($datos);
        
        // Arreglar códigos duplicados si existen
        if (method_exists('WC_Garantias_Admin', 'arreglar_codigos_duplicados')) {
            WC_Garantias_Admin::arreglar_codigos_duplicados($garantia_id);
        }
        ?>
        <div class="wrap garantia-detail-page">
            
            <?php self::render_mensajes(); ?>
            
            <!-- Header con información del cliente y estado -->
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 40px 30px 30px; margin: -20px -20px 30px -20px; border-radius: 0 0 20px 20px;">
                <!-- Botón volver en la esquina superior derecha -->
                <div style="position: absolute; top: 20px; right: 20px;">
                    <a href="<?php echo admin_url('admin.php?page=wc-garantias'); ?>" 
                       style="display: inline-flex; align-items: center; gap: 8px; background: rgba(255,255,255,0.2); color: white; padding: 10px 20px; border-radius: 50px; text-decoration: none; border: 1px solid rgba(255,255,255,0.3); backdrop-filter: blur(10px);">
                        <i class="fas fa-arrow-left"></i>
                        <span>Volver</span>
                    </a>
                </div>
                <div style="max-width: 1200px; margin: 0 auto;">
                    <div style="display: grid; grid-template-columns: 1fr auto; gap: 30px; align-items: center;">
                        <!-- Info del cliente -->
                        <div>
                            <h1 style="margin: 0 0 20px 0; font-size: 28px; font-weight: 300;">
                                Garantía <strong><?php echo esc_html($codigo_unico); ?></strong>
                            </h1>
                            <div style="display: flex; flex-wrap: wrap; gap: 30px; font-size: 15px; opacity: 0.95;">
                                <div>
                                    <i class="fas fa-user" style="margin-right: 8px; opacity: 0.7;"></i>
                                    <?php echo esc_html($nombre_cliente); ?>
                                </div>
                                <div>
                                    <i class="fas fa-envelope" style="margin-right: 8px; opacity: 0.7;"></i>
                                    <?php echo esc_html($email_cliente); ?>
                                </div>
                                <div>
                                    <i class="fas fa-phone" style="margin-right: 8px; opacity: 0.7;"></i>
                                    <?php echo esc_html($telefono); ?>
                                </div>
                                <div>
                                    <i class="fas fa-calendar" style="margin-right: 8px; opacity: 0.7;"></i>
                                    <?php echo esc_html($fecha); ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Estado visual -->
                        <div style="text-align: center;">
                            <div style="width: 120px; height: 120px; background: rgba(255,255,255,0.2); border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 0 auto 10px;">
                                <i class="fas fa-<?php echo WC_Garantias_Admin_View::$estados[$estado]['icon'] ?? 'clock'; ?>" style="font-size: 48px;"></i>
                            </div>
                                <div style="background: rgba(255,255,255,0.9); color: #333; padding: 8px 20px; border-radius: 20px; font-size: 14px; font-weight: 500;">
                                <?php 
                                // Mapeo completo de estados
                                $estados_labels = [
                                    'nueva' => 'Nueva',
                                    'en_proceso' => 'En Proceso',
                                    'parcialmente_recibido' => 'Parcialmente Recibido',
                                    'finalizada' => 'Finalizada',
                                    'finalizado_cupon' => 'Finalizada'
                                ];
                                
                                // Usar el mapeo o el valor del array de estados
                                if (isset($estados_labels[$estado])) {
                                    $label_estado = $estados_labels[$estado];
                                } else if (isset(WC_Garantias_Admin_View::$estados[$estado]['label'])) {
                                    $label_estado = WC_Garantias_Admin_View::$estados[$estado]['label'];
                                } else {
                                    // Si no está en ningún lado, formatear el texto
                                    $label_estado = ucwords(str_replace('_', ' ', $estado));
                                }
                                
                                echo esc_html($label_estado); 
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cards de estadísticas -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <!-- Total items -->
                <div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #667eea;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <p style="margin: 0; color: #999; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Items Totales</p>
                            <p style="margin: 8px 0 0 0; font-size: 32px; font-weight: 700; color: #333;"><?php echo count($items); ?></p>
                        </div>
                        <div style="background: #f0f4ff; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-box" style="color: #667eea; font-size: 20px;"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Items pendientes -->
                <div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #f59e0b;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <p style="margin: 0; color: #999; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Pendientes</p>
                            <p style="margin: 8px 0 0 0; font-size: 32px; font-weight: 700; color: #333;">
                                <?php 
                                $pendientes = 0;
                                foreach ($items as $item) {
                                    if (isset($item['estado']) && $item['estado'] === 'Pendiente') $pendientes++;
                                }
                                echo $pendientes;
                                ?>
                            </p>
                        </div>
                        <div style="background: #fef3c7; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-clock" style="color: #f59e0b; font-size: 20px;"></i>
                        </div>
                    </div>
                </div>
                
                <!-- Tasa de reclamo -->
                <div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #ef4444;">
                    <div style="display: flex; justify-content: space-between; align-items: start;">
                        <div>
                            <p style="margin: 0; color: #999; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;">Tasa de Reclamo</p>
                            <p style="margin: 8px 0 0 0; font-size: 32px; font-weight: 700; color: #333;"><?php echo number_format($tasa_reclamo, 1); ?>%</p>
                        </div>
                        <div style="background: #fee2e2; width: 48px; height: 48px; border-radius: 12px; display: flex; align-items: center; justify-content: center;">
                            <i class="fas fa-percentage" style="color: #ef4444; font-size: 20px;"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Tabla de items -->
            <?php self::render_tabla_items($garantia_id, $items); ?>
            
            <!-- Contenedor para tarjetas lado a lado -->
            <div style="display: flex; gap: 20px; margin-top: 20px; align-items: stretch;">
                
                <!-- Acciones según estado -->
                <?php self::render_acciones($garantia_id, $estado, $items); ?>
                
                <!-- Etiquetas -->
                <?php self::render_etiquetas($garantia_id, $items, $is_distribuidor); ?>
                
            </div>
            
            <!-- JavaScript -->
            <?php self::render_javascript($garantia_id); ?>
            
            <!-- Modal de Aprobación Parcial -->
            <?php self::render_modal_aprobacion_parcial($garantia_id); ?>
            
            <!-- Modal de Recepción Parcial -->
            <?php 
            // Incluir el modal de recepción parcial si existe la clase
            if (class_exists('WC_Garantias_Recepcion_Parcial_UI')) {
                WC_Garantias_Recepcion_Parcial_UI::render_modal_recepcion($garantia_id);
            }
            ?>
        </div>
        <?php
    }
    
    private static function render_mensajes() {
        if (isset($_GET['items_aprobados']) || isset($_GET['items_recibidos']) || isset($_GET['items_rechazados']) || 
            isset($_GET['etiqueta_subida']) || isset($_GET['info_solicitada']) || isset($_GET['items_procesados']) ||
            isset($_GET['etiqueta_eliminada'])) {
            
            $mensaje = '';
            $tipo = 'success';
            
            if (isset($_GET['items_aprobados'])) {
                $items_aprobados = intval($_GET['items_aprobados']);
                $mensaje = "✓ Se aprobaron {$items_aprobados} items.";
                
                if (isset($_GET['items_pendientes'])) {
                    $items_pendientes = intval($_GET['items_pendientes']);
                    $mensaje .= " Aún quedan {$items_pendientes} items sin procesar.";
                }
                
                if (isset($_GET['cupon_generado'])) {
                    $codigo_cupon = sanitize_text_field($_GET['cupon_generado']);
                    $mensaje = "✓ Items procesados y cupón generado: {$codigo_cupon}";
                }
            }
            
            if (isset($_GET['items_recibidos'])) {
                $cantidad = intval($_GET['items_recibidos']);
                $mensaje = "✓ Se marcaron {$cantidad} items como recibidos.";
            }
            
            if (isset($_GET['items_rechazados'])) {
                $cantidad = intval($_GET['items_rechazados']);
                $mensaje = "✗ Se rechazaron {$cantidad} item(s) con el motivo especificado.";
                if (isset($_GET['rechazo_definitivo'])) {
                    $mensaje .= " RECHAZO DEFINITIVO - El cliente no podr apelar.";
                }
                $tipo = 'warning';
            }
            
            if (isset($_GET['etiqueta_subida'])) {
                $mensaje = "✓ Etiqueta subida correctamente. Se ha notificado al cliente.";
            }
            
            if (isset($_GET['etiqueta_eliminada'])) {
                $mensaje = "✓ Etiqueta eliminada correctamente.";
            }
            
            if (isset($_GET['info_solicitada'])) {
                $mensaje = "✓ Informacin solicitada correctamente. El cliente recibirá un email con las instrucciones.";
                $tipo = 'info';
            }
            
            if (isset($_GET['items_procesados'])) {
                $cantidad = intval($_GET['items_procesados']);
                $mensaje = "✓ Se procesaron {$cantidad} items.";
                if (isset($_GET['items_destruir'])) {
                    $mensaje = "✓ Se aprobaron {$cantidad} items para destrucción.";
                } elseif (isset($_GET['items_devolver'])) {
                    $mensaje = " Se aprobaron {$cantidad} items para devolución.";
                }
            }
            
            if ($mensaje) {
                $bg_color = $tipo === 'success' ? '#d4edda' : ($tipo === 'warning' ? '#fff3cd' : '#cce5ff');
                $text_color = $tipo === 'success' ? '#155724' : ($tipo === 'warning' ? '#856404' : '#004085');
                $border_color = $tipo === 'success' ? '#c3e6cb' : ($tipo === 'warning' ? '#ffeeba' : '#b8daff');
                ?>
                <div id="mensaje-flotante" style="position: fixed; top: 50px; right: 20px; max-width: 500px; padding: 15px 20px; background: <?php echo $bg_color; ?>; color: <?php echo $text_color; ?>; border: 1px solid <?php echo $border_color; ?>; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 99999; display: flex; align-items: center; gap: 10px;">
                    <div style="flex: 1;"><?php echo $mensaje; ?></div>
                    <button onclick="document.getElementById('mensaje-flotante').style.display='none'" style="background: none; border: none; color: <?php echo $text_color; ?>; font-size: 20px; cursor: pointer; padding: 0; line-height: 1;">&times;</button>
                </div>
                <script>
                setTimeout(function() {
                    var mensaje = document.getElementById('mensaje-flotante');
                    if (mensaje) {
                        mensaje.style.transition = 'opacity 0.5s';
                        mensaje.style.opacity = '0';
                        setTimeout(function() {
                            mensaje.style.display = 'none';
                        }, 500);
                    }
                }, 5000);
                
                if (window.history.replaceState) {
                    var url = new URL(window.location);
                    url.searchParams.delete('items_aprobados');
                    url.searchParams.delete('items_pendientes');
                    url.searchParams.delete('cupon_generado');
                    url.searchParams.delete('items_recibidos');
                    url.searchParams.delete('items_rechazados');
                    url.searchParams.delete('rechazo_definitivo');
                    url.searchParams.delete('etiqueta_subida');
                    url.searchParams.delete('info_solicitada');
                    url.searchParams.delete('items_procesados');
                    url.searchParams.delete('etiqueta_eliminada');
                    url.searchParams.delete('items_destruir');
                    url.searchParams.delete('items_devolver');
                    window.history.replaceState({}, document.title, url);
                }
                </script>
                <?php
            }
        }
    }
    private static function render_tabla_items($garantia_id, $items) {
        ?>
        <div class="card mt-4" style="max-width: 100%;">
            <div class="card-header bg-light d-flex align-items-center" style="padding: 15px 20px;">
                <h5 class="mb-0" style="display: flex; align-items: center; gap: 10px; margin-right: auto;">
                    <i class="fas fa-list"></i> Items Reclamados
                </h5>
                <div style="display: flex; gap: 10px; margin-left: 20px;">
                    <button type="button" onclick="jQuery('.item_checkbox:not(:disabled)').prop('checked', true);" 
                            style="padding: 5px 15px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 5px;">
                        <i class="fas fa-check-double"></i> Seleccionar Todos
                    </button>
                    
                    <button type="button" onclick="jQuery('.item_checkbox').prop('checked', false);" 
                            style="padding: 5px 15px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; display: flex; align-items: center; gap: 5px;">
                        <i class="fas fa-times"></i> Limpiar Selección
                    </button>
                </div>
            </div>
            
            <!-- Filtros de estado -->
            <div style="padding: 15px 20px; background: #f8f9fa; border-bottom: 1px solid #dee2e6;">
                <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                    <span style="font-weight: 600;">Filtrar por estado:</span>
                    <label style="margin: 0; cursor: pointer;">
                        <input type="checkbox" class="filtro-estado-item" value="Pendiente" checked> Pendiente
                    </label>
                    <label style="margin: 0; cursor: pointer;">
                        <input type="checkbox" class="filtro-estado-item" value="solicitar_info" checked> Info Solicitada
                    </label>
                    <label style="margin: 0; cursor: pointer;">
                        <input type="checkbox" class="filtro-estado-item" value="recibido" checked> Recibido
                    </label>
                    <label style="margin: 0; cursor: pointer;">
                        <input type="checkbox" class="filtro-estado-item" value="aprobado" checked> Aprobado
                    </label>
                    <label style="margin: 0; cursor: pointer;">
                        <input type="checkbox" class="filtro-estado-item" value="aprobado_destruir" checked> Destruir
                    </label>
                    <label style="margin: 0; cursor: pointer;">
                        <input type="checkbox" class="filtro-estado-item" value="aprobado_devolver" checked> Devolver
                    </label>
                    <label style="margin: 0; cursor: pointer;">
                        <input type="checkbox" class="filtro-estado-item" value="devolucion_en_transito" checked> Enviado a WiFix
                    </label>
                    <label style="margin: 0; cursor: pointer;">
                        <input type="checkbox" class="filtro-estado-item" value="rechazado" checked> Rechazado
                    </label>
                    <label style="margin: 0; cursor: pointer;">
                        <input type="checkbox" class="filtro-estado-item" value="destruccion_subida" checked> Destrucción Subida
                    </label>
                    <label style="margin: 0; cursor: pointer;">
                        <input type="checkbox" class="filtro-estado-item" value="apelacion" checked> Apelación
                    </label>
                    <label style="margin: 0; cursor: pointer;">
                        <input type="checkbox" class="filtro-estado-item" value="retorno_cliente" checked> Retorno Cliente
                    </label>
                    <label style="margin: 0; cursor: pointer;">
                        <input type="checkbox" class="filtro-estado-item" value="esperando_recepcion" checked> Esperando Recepción
                    </label>
                    <label style="margin: 0; cursor: pointer;">
                        <input type="checkbox" class="filtro-estado-item" value="rechazado_no_recibido" checked> Rechazado - No Recibido
                    </label>
                </div>
            </div>
            
            <div class="card-body">
                <form method="post" id="items_form">
                    <input type="hidden" name="garantia_id" value="<?php echo esc_attr($garantia_id); ?>">
                    <div style="overflow-x: auto;">
                        <table class="table table-hover" style="min-width: 1200px;">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="select_all_items" class="form-check-input">
                                    </th>
                                    <th style="width: 15%;">Código</th>
                                    <th style="width: 25%;">Producto</th>
                                    <th style="width: 60px;">Cant.</th>
                                    <th style="width: 200px;">Motivo</th>
                                    <th style="width: 100px;">Info</th>
                                    <th style="width: 100px;">Fecha compra</th>
                                    <th style="width: 80px;">N° Orden</th>
                                    <th style="width: 100px;">Estado</th>
                                    <th style="width: 200px;">Acciones</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php if (is_array($items) && count($items)): ?>
                                <?php foreach ($items as $index => $item):
                                    $codigo_item = $item['codigo_item'] ?? '';
                                    $producto_id = $item['producto_id'] ?? '';
                                    $cantidad = $item['cantidad'] ?? 1;
                                    $motivo = $item['motivo'] ?? '';
                                    $foto_url = $item['foto_url'] ?? '';
                                    $video_url = $item['video_url'] ?? '';
                                    $order_id = $item['order_id'] ?? '';
                                    $estado_item = $item['estado'] ?? 'Pendiente';
                                    
                                    // Manejo de productos
                                    $producto_nombre = '-';
                                    $es_producto_eliminado = false;
                                    
                                    // Verificar si es un ID ficticio (producto eliminado con ID >= 900000)
                                    if ($producto_id >= 900000) {
                                        $es_producto_eliminado = true;
                                        
                                        // Primero intentar usar el nombre guardado
                                        if (!empty($item['nombre_producto'])) {
                                            $producto_nombre = $item['nombre_producto'];
                                        } else {
                                            // Si no hay nombre guardado, buscar en las órdenes del cliente
                                            $cliente_id = get_post_meta($garantia_id, '_cliente', true);
                                            $orders = wc_get_orders([
                                                'customer_id' => $cliente_id,
                                                'status' => ['completed', 'processing'],
                                                'limit' => -1
                                            ]);
                                            
                                            foreach ($orders as $order) {
                                                foreach ($order->get_items() as $order_item) {
                                                    $item_name = $order_item->get_name();
                                                    $item_hash = 900000 + abs(crc32($item_name) % 100000);
                                                    
                                                    if ($item_hash == $producto_id) {
                                                        $producto_nombre = $item_name;
                                                        // Si no tenemos order_id, guardarlo
                                                        if (empty($order_id)) {
                                                            $order_id = $order->get_id();
                                                        }
                                                        break 2;
                                                    }
                                                }
                                            }
                                            
                                            if ($producto_nombre == '-') {
                                                $producto_nombre = 'Producto eliminado (ID: ' . $producto_id . ')';
                                            }
                                        }
                                    } else if ($producto_id) {
                                        // Producto con ID normal
                                        $producto = wc_get_product($producto_id);
                                        if ($producto) {
                                            // Producto existe
                                            $producto_nombre = $producto->get_name();
                                        } else {
                                            // Producto no existe (fue eliminado pero tenía ID real)
                                            $es_producto_eliminado = true;
                                            
                                            // Usar el nombre guardado si existe
                                            if (!empty($item['nombre_producto'])) {
                                                $producto_nombre = $item['nombre_producto'];
                                            } else {
                                                // Buscar en la orden original
                                                if (!empty($item['order_id'])) {
                                                    $order = wc_get_order($item['order_id']);
                                                    if ($order) {
                                                        foreach ($order->get_items() as $order_item) {
                                                            if ($order_item->get_product_id() == $producto_id) {
                                                                $producto_nombre = $order_item->get_name();
                                                                break;
                                                            }
                                                        }
                                                    }
                                                }
                                                
                                                // Si aún no tenemos nombre
                                                if ($producto_nombre == '-') {
                                                    $producto_nombre = 'Producto #' . $producto_id . ' (eliminado)';
                                                }
                                            }
                                        }
                                    }
                                    
                                    // Si aún no tenemos order_id y es producto eliminado, buscar la orden
                                    if (!$order_id && $es_producto_eliminado && $producto_id >= 900000) {
                                        $cliente_id = get_post_meta($garantia_id, '_cliente', true);
                                        $orders = wc_get_orders([
                                            'customer_id' => $cliente_id,
                                            'status' => ['completed', 'processing'],
                                            'limit' => -1
                                        ]);
                                        
                                        foreach ($orders as $order) {
                                            foreach ($order->get_items() as $order_item) {
                                                $item_name = $order_item->get_name();
                                                $item_hash = 900000 + abs(crc32($item_name) % 100000);
                                                
                                                if ($item_hash == $producto_id) {
                                                    $order_id = $order->get_id();
                                                    break 2;
                                                }
                                            }
                                        }
                                    }
                                    
                                    // Obtener fecha de la orden
                                    $order_fecha = '';
                                    if ($order_id) {
                                        $order = wc_get_order($order_id);
                                        if ($order) {
                                            $order_fecha = $order->get_date_created() ? $order->get_date_created()->date_i18n('d/m/Y') : '';
                                        }
                                    }
                                ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" class="form-check-input item_checkbox" 
                                                name="bulk_items[]" value="<?php echo esc_attr($codigo_item); ?>"
                                                   <?php echo in_array($estado_item, ['Pendiente', 'devolucion_en_transito', 'recibido', 'destruccion_subida', 'retorno_cliente', 'apelacion', 'aprobado_devolver', 'aprobado_destruir', 'esperando_recepcion']) ? '' : 'disabled'; ?>>
                                        </td>
                                        <td><code><?php echo esc_html($codigo_item); ?></code></td>
                                        <td>
                                            <?php echo esc_html($producto_nombre); ?>
                                            <?php if ($es_producto_eliminado): ?>
                                                <span class="badge bg-warning text-dark" style="font-size: 10px; margin-left: 5px;">Eliminado</span>
                                            <?php elseif ($producto_id && wc_get_product($producto_id)): ?>
                                                <a href="<?php echo get_edit_post_link($producto_id); ?>" 
                                                   class="text-decoration-none" target="_blank">
                                                    <i class="fas fa-external-link-alt fa-sm"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center"><?php echo esc_html($cantidad); ?></td>
                                        <td><?php echo esc_html($motivo); ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm" role="group">
                                                <?php if (!empty($foto_url)): ?>
                                                    <a href="<?php echo esc_url($foto_url); ?>" target="_blank" 
                                                       class="btn btn-outline-primary btn-sm" title="Foto del reclamo">
                                                        <i class="fas fa-image"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (!empty($video_url)): ?>
                                                    <a href="<?php echo esc_url($video_url); ?>" target="_blank" 
                                                       class="btn btn-outline-danger btn-sm" title="Video del reclamo">
                                                        <i class="fas fa-video"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <?php 
                                                // VERIFICAR SI HAY RESPUESTAS DEL CLIENTE A SOLICITUD DE INFO
                                                    if (isset($item['historial_solicitudes']) && !empty($item['historial_solicitudes'])) {
                                                        $tiene_respuesta = false;
                                                        $ultima_respuesta = null;
                                                        
                                                        foreach ($item['historial_solicitudes'] as $solicitud) {
                                                            if (isset($solicitud['respondido']) && $solicitud['respondido']) {
                                                                $tiene_respuesta = true;
                                                                $ultima_respuesta = $solicitud;
                                                            }
                                                        }
                                                        
                                                        // Mostrar badge de estado
                                                        if ($tiene_respuesta) {
                                                            ?>
                                                            <span class="badge bg-success" style="font-size: 10px;">
                                                                <i class="fas fa-check"></i> Respondido
                                                            </span>
                                                            <?php
                                                            
                                                            // Mostrar botones de archivos si hay respuesta
                                                            if ($ultima_respuesta && !empty($ultima_respuesta['archivos_respuesta']['fotos'])) {
                                                                foreach ($ultima_respuesta['archivos_respuesta']['fotos'] as $idx => $foto_resp) {
                                                                    ?>
                                                                    <a href="<?php echo esc_url($foto_resp); ?>" target="_blank" 
                                                                       class="btn btn-outline-success btn-sm" 
                                                                       title="Foto respuesta <?php echo ($idx + 1); ?>">
                                                                        <i class="fas fa-camera"></i>
                                                                    </a>
                                                                    <?php
                                                                }
                                                            }
                                                            
                                                            if ($ultima_respuesta && !empty($ultima_respuesta['archivos_respuesta']['videos'])) {
                                                                foreach ($ultima_respuesta['archivos_respuesta']['videos'] as $idx => $video_resp) {
                                                                    ?>
                                                                    <a href="<?php echo esc_url($video_resp); ?>" target="_blank" 
                                                                       class="btn btn-outline-danger btn-sm" 
                                                                       title="Video respuesta <?php echo ($idx + 1); ?>">
                                                                        <i class="fas fa-film"></i>
                                                                    </a>
                                                                    <?php
                                                                }
                                                            }
                                                        } else {
                                                            ?>
                                                            <span class="badge bg-warning text-dark" style="font-size: 10px;">
                                                                <i class="fas fa-clock"></i> Esperando
                                                            </span>
                                                            <?php
                                                        }
                                                        
                                                        // SIEMPRE mostrar el botón de historial
                                                        $unique_modal_id = 'hist-' . $garantia_id . '-' . $index;
                                                        ?>
                                                        <button type="button" 
                                                                class="btn btn-outline-info btn-sm"
                                                                onclick="mostrarHistorialCompleto('<?php echo esc_attr($unique_modal_id); ?>')"
                                                                title="Ver historial completo">
                                                            <i class="fas fa-history"></i> Historial
                                                        </button>
                                                        
                                                        <!-- Modal oculto con el historial completo -->
                                                        <div id="<?php echo esc_attr($unique_modal_id); ?>" style="display:none;">
                                                            <div class="historial-content">
                                                                <h4 style="margin-bottom: 20px;">
                                                                    <i class="fas fa-comments"></i> Historial de Comunicación
                                                                </h4>
                                                                
                                                                <?php foreach ($item['historial_solicitudes'] as $idx_sol => $solicitud): ?>
                                                                    <div style="margin-bottom: 25px; padding-bottom: 25px; border-bottom: 1px solid #dee2e6;">
                                                                        
                                                                        <!-- SOLICITUD DEL ADMIN -->
                                                                        <div style="background: #e3f2fd; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
                                                                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                                                                <strong style="color: #1976d2;">
                                                                                    <i class="fas fa-store"></i> WiFix - Solicitud #<?php echo ($idx_sol + 1); ?>
                                                                                </strong>
                                                                                <small style="color: #666;">
                                                                                    <?php echo date('d/m/Y H:i', strtotime($solicitud['fecha'])); ?>
                                                                                </small>
                                                                            </div>
                                                                            <p style="margin: 10px 0;"><?php echo nl2br(esc_html($solicitud['mensaje'])); ?></p>
                                                                            
                                                                            <?php if ($solicitud['solicitar_fotos'] || $solicitud['solicitar_videos']): ?>
                                                                                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #bbdefb;">
                                                                                    <small style="color: #1565c0;">
                                                                                        <strong>Solicitado:</strong>
                                                                                        <?php 
                                                                                        $solicitado = [];
                                                                                        if ($solicitud['solicitar_fotos']) $solicitado[] = 'Fotos';
                                                                                        if ($solicitud['solicitar_videos']) $solicitado[] = 'Videos';
                                                                                        echo implode(' y ', $solicitado);
                                                                                        ?>
                                                                                    </small>
                                                                                </div>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                        
                                                                        <!-- RESPUESTA DEL CLIENTE -->
                                                                        <?php if (isset($solicitud['respondido']) && $solicitud['respondido']): ?>
                                                                            <div style="background: #e8f5e9; padding: 15px; border-radius: 8px;">
                                                                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">
                                                                                    <strong style="color: #2e7d32;">
                                                                                        <i class="fas fa-user"></i> Cliente - Respuesta
                                                                                    </strong>
                                                                                    <small style="color: #666;">
                                                                                        <?php 
                                                                                        $fecha_resp = $solicitud['fecha_respuesta'] ?? '';
                                                                                        if ($fecha_resp) {
                                                                                            echo date('d/m/Y H:i', strtotime($fecha_resp));
                                                                                        }
                                                                                        ?>
                                                                                    </small>
                                                                                </div>
                                                                                
                                                                                <?php if (!empty($solicitud['comentario'])): ?>
                                                                                    <p style="margin: 10px 0;"><?php echo nl2br(esc_html($solicitud['comentario'])); ?></p>
                                                                                <?php endif; ?>
                                                                                
                                                                                <!-- Archivos adjuntos -->
                                                                                <?php if (!empty($solicitud['archivos_respuesta'])): ?>
                                                                                    <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #c8e6c9;">
                                                                                        <?php if (!empty($solicitud['archivos_respuesta']['fotos'])): ?>
                                                                                            <div style="margin-bottom: 8px;">
                                                                                                <strong>Fotos adjuntas:</strong>
                                                                                                <?php foreach ($solicitud['archivos_respuesta']['fotos'] as $idx_foto => $foto): ?>
                                                                                                    <a href="<?php echo esc_url($foto); ?>" target="_blank" 
                                                                                                       style="margin-left: 5px; color: #2e7d32;">
                                                                                                        <i class="fas fa-image"></i> Foto <?php echo ($idx_foto + 1); ?>
                                                                                                    </a>
                                                                                                <?php endforeach; ?>
                                                                                            </div>
                                                                                        <?php endif; ?>
                                                                                        
                                                                                        <?php if (!empty($solicitud['archivos_respuesta']['videos'])): ?>
                                                                                            <div>
                                                                                                <strong>Videos adjuntos:</strong>
                                                                                                <?php foreach ($solicitud['archivos_respuesta']['videos'] as $idx_video => $video): ?>
                                                                                                    <a href="<?php echo esc_url($video); ?>" target="_blank" 
                                                                                                       style="margin-left: 5px; color: #2e7d32;">
                                                                                                        <i class="fas fa-video"></i> Video <?php echo ($idx_video + 1); ?>
                                                                                                    </a>
                                                                                                <?php endforeach; ?>
                                                                                            </div>
                                                                                        <?php endif; ?>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        <?php else: ?>
                                                                            <div style="background: #fff3cd; padding: 15px; border-radius: 8px;">
                                                                                <strong style="color: #f57c00;">
                                                                                    <i class="fas fa-clock"></i> Esperando respuesta del cliente...
                                                                                </strong>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endforeach; ?>
                                                            </div>
                                                        </div>
                                                        <?php
                                                    }
                                                    ?>
                                                    
                                                    <?php 
                                                    // MOSTRAR BOTÓN DE APELACIÓN SI EXISTE
                                                    if (isset($item['apelacion']) && !empty($item['apelacion'])) {
                                                    $unique_apelacion_id = 'apelacion-' . $garantia_id . '-' . $index;
                                                    ?>
                                                    <button type="button" 
                                                            class="btn btn-outline-warning btn-sm"
                                                            onclick="mostrarApelacion('<?php echo esc_attr($unique_apelacion_id); ?>')"
                                                            title="Ver apelación del cliente"
                                                            style="margin-left: 5px;">
                                                        <i class="fas fa-exclamation-triangle"></i> Apelación
                                                    </button>
                                                    
                                                    <!-- Modal oculto con la apelacin -->
                                                    <div id="<?php echo esc_attr($unique_apelacion_id); ?>" style="display:none;">
                                                        <div class="apelacion-content">
                                                            <h4 style="color: #f57c00; margin-bottom: 20px;">
                                                                <i class="fas fa-exclamation-triangle"></i> Apelación del Cliente
                                                            </h4>
                                                            
                                                            <!-- Motivo de rechazo original -->
                                                            <?php if (!empty($item['motivo_rechazo'])): ?>
                                                                <div style="background: #ffebee; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                                                                    <h6 style="color: #c62828; margin-top: 0;">
                                                                        <i class="fas fa-times-circle"></i> Motivo del Rechazo Original:
                                                                    </h6>
                                                                    <p style="margin: 0; color: #d32f2f;">
                                                                        <?php echo nl2br(esc_html($item['motivo_rechazo'])); ?>
                                                                    </p>
                                                                    <?php if (!empty($item['fecha_rechazo'])): ?>
                                                                        <small style="color: #666; display: block; margin-top: 10px;">
                                                                            Rechazado: <?php echo date('d/m/Y H:i', strtotime($item['fecha_rechazo'])); ?>
                                                                        </small>
                                                                    <?php endif; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                            
                                                            <!-- Apelacin del cliente -->
                                                            <div style="background: #fff3e0; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                                                                <h6 style="color: #e65100; margin-top: 0;">
                                                                    <i class="fas fa-user"></i> Respuesta del Cliente:
                                                                </h6>
                                                                <p style="margin: 10px 0; color: #333;">
                                                                    <?php echo nl2br(esc_html($item['apelacion']['motivo'] ?? '')); ?>
                                                                </p>
                                                                
                                                                <!-- Fotos de la apelación -->
                                                                <?php if (!empty($item['apelacion']['foto_url'])): ?>
                                                                    <div style="margin-top: 15px;">
                                                                        <strong>Foto adjunta:</strong><br>
                                                                        <a href="<?php echo esc_url($item['apelacion']['foto_url']); ?>" 
                                                                           target="_blank" 
                                                                           class="btn btn-sm btn-primary" 
                                                                           style="margin-top: 5px;">
                                                                            <i class="fas fa-image"></i> Ver Foto de Apelación
                                                                        </a>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <!-- Video de la apelación -->
                                                                <?php if (!empty($item['apelacion']['video_url'])): ?>
                                                                    <div style="margin-top: 10px;">
                                                                        <strong>Video adjunto:</strong><br>
                                                                        <a href="<?php echo esc_url($item['apelacion']['video_url']); ?>" 
                                                                           target="_blank" 
                                                                           class="btn btn-sm btn-danger" 
                                                                           style="margin-top: 5px;">
                                                                            <i class="fas fa-video"></i> Ver Video de Apelación
                                                                        </a>
                                                                    </div>
                                                                <?php endif; ?>
                                                                
                                                                <?php if (!empty($item['apelacion']['fecha'])): ?>
                                                                    <small style="color: #666; display: block; margin-top: 15px;">
                                                                        <i class="fas fa-clock"></i> Apelado: <?php echo date('d/m/Y H:i', strtotime($item['apelacion']['fecha'])); ?>
                                                                    </small>
                                                                <?php endif; ?>
                                                            </div>
                                                            
                                                            <!-- Historial de apelaciones si hay múltiples -->
                                                            <?php if (isset($item['historial_apelaciones']) && count($item['historial_apelaciones']) > 1): ?>
                                                                <div style="margin-top: 20px;">
                                                                    <h6 style="color: #666;">
                                                                        <i class="fas fa-history"></i> Historial de Apelaciones Anteriores:
                                                                    </h6>
                                                                    <?php foreach ($item['historial_apelaciones'] as $idx_apel => $apel_hist): 
                                                                        if ($idx_apel == count($item['historial_apelaciones']) - 1) continue; // Saltar la última que ya mostramos
                                                                    ?>
                                                                        <div style="background: #f5f5f5; padding: 10px; border-radius: 4px; margin-bottom: 10px;">
                                                                            <small style="color: #666;">
                                                                                <?php echo date('d/m/Y H:i', strtotime($apel_hist['fecha'])); ?>
                                                                            </small>
                                                                            <p style="margin: 5px 0; font-size: 13px;">
                                                                                <?php echo nl2br(esc_html($apel_hist['motivo'])); ?>
                                                                            </p>
                                                                        </div>
                                                                    <?php endforeach; ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                    <?php
                                                }
                                                ?>
                                                <?php 
                                                // Mostrar evidencia de destrucción si existe
                                                if (isset($item['destruccion']) && $item['destruccion']['confirmado']):
                                                    if (!empty($item['destruccion']['foto_url'])): ?>
                                                        <a href="<?php echo esc_url($item['destruccion']['foto_url']); ?>" target="_blank" 
                                                           class="btn btn-outline-warning btn-sm" title="Foto de destrucción">
                                                            <i class="fas fa-fire"></i> <i class="fas fa-camera"></i>
                                                        </a>
                                                    <?php endif;
                                                    if (!empty($item['destruccion']['video_url'])): ?>
                                                        <a href="<?php echo esc_url($item['destruccion']['video_url']); ?>" target="_blank" 
                                                           class="btn btn-outline-warning btn-sm" title="Video de destrucción">
                                                            <i class="fas fa-fire"></i> <i class="fas fa-film"></i>
                                                        </a>
                                                    <?php endif;
                                                endif; ?>
                                            </div>
                                            
                                            <?php 
                                            // Sección para cupón RMA (mantener el cdigo existente)
                                            $necesita_cupon_rma = false;
                                            if ($estado_item === 'retorno_cliente') {
                                                if (empty($item['cupon_rma'])) {
                                                    $necesita_cupon_rma = true;
                                                } else {
                                                    $cupon_existe = get_page_by_title($item['cupon_rma'], OBJECT, 'shop_coupon');
                                                    if (!$cupon_existe || $cupon_existe->post_status != 'publish') {
                                                        $necesita_cupon_rma = true;
                                                    }
                                                }
                                            }
                                            
                                            if ($necesita_cupon_rma):
                                            ?>
                                                <button type="button" 
                                                        class="btn btn-outline-success btn-sm generar-cupon-rma-btn"
                                                        data-garantia-id="<?php echo intval($garantia_id); ?>"
                                                        data-codigo-item="<?php echo esc_attr($codigo_item); ?>"
                                                        data-item-index="<?php echo intval($index); ?>"
                                                        title="Generar cupón RMA"
                                                        style="margin-top: 5px; display: inline-block;">
                                                    <i class="fas fa-ticket-alt"></i> RMA
                                                </button>
                                            <?php elseif (!empty($item['cupon_rma'])): ?>
                                                <span class="badge bg-success" 
                                                      style="margin-top: 5px; display: inline-block; cursor: pointer;" 
                                                      title="<?php echo esc_attr($item['cupon_rma']); ?>"
                                                      onclick="alert('Cupón RMA:\n<?php echo esc_js($item['cupon_rma']); ?>')">
                                                    <i class="fas fa-check"></i> RMA
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html($order_fecha); ?></td>
                                        <td>
                                            <?php if ($order_id): ?>
                                                <a href="<?php echo admin_url('post.php?post=' . $order_id . '&action=edit'); ?>" 
                                                   target="_blank">#<?php echo esc_html($order_id); ?></a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php
                                            $estado_color = [
                                                'Pendiente' => 'warning',
                                                'solicitar_info' => 'info',
                                                'recibido' => 'info',
                                                'aprobado' => 'success',
                                                'aprobado_destruir' => 'danger',
                                                'aprobado_devolver' => 'primary',
                                                'destruccion_subida' => 'warning',
                                                'rechazado' => 'danger',
                                                'devolucion_en_transito' => 'info',
                                                'retorno_cliente' => 'secondary',
                                                'apelacion' => 'dark',
                                                'esperando_recepcion' => 'warning',
                                                'rechazado_no_recibido' => 'danger'
                                            ];
                                            
                                            $badge_color = $estado_color[$estado_item] ?? 'secondary';
                                            
                                            $estado_texto = [
                                                'Pendiente' => 'Pendiente',
                                                'solicitar_info' => 'Info Solicitada',
                                                'recibido' => 'Recibido',
                                                'aprobado' => 'Aprobado',
                                                'aprobado_destruir' => 'Aprobado - Destruir',
                                                'aprobado_devolver' => 'Aprobado - Devolver',
                                                'destruccion_subida' => 'Destrucción Subida',
                                                'rechazado' => 'Rechazado',
                                                'devolucion_en_transito' => 'Enviado a WiFix',
                                                'retorno_cliente' => 'Retorno Cliente',
                                                'apelacion' => 'Apelación',
                                                'esperando_recepcion' => 'Esperando Recepción',
                                                'rechazado_no_recibido' => 'Rechazado - No Recibido'
                                            ];
                                            
                                            $texto_mostrar = $estado_texto[$estado_item] ?? $estado_item;
                                            ?>
                                            <div>
                                                <span class="badge bg-<?php echo $badge_color; ?>">
                                                    <?php echo esc_html($texto_mostrar); ?>
                                                    <?php if (!empty($item['rechazo_definitivo']) && $item['rechazo_definitivo']): ?>
                                                        <i class="fas fa-lock" style="margin-left: 5px; font-size: 10px;"></i>
                                                    <?php endif; ?>
                                                </span>
                                                
                                                <?php if (($estado_item === 'rechazado' || $estado_item === 'retorno_cliente') && !empty($item['motivo_rechazo'])): ?>
                                                    <?php 
                                                    $motivo_completo = $item['motivo_rechazo'];
                                                    $motivo_corto = strlen($motivo_completo) > 50 
                                                        ? substr($motivo_completo, 0, 50) . '...' 
                                                        : $motivo_completo;
                                                    $necesita_tooltip = strlen($motivo_completo) > 50;
                                                    ?>
                                                    <div style="margin-top: 5px; font-size: 11px; color: #6c757d; line-height: 1.3; max-width: 150px;"
                                                         <?php if ($necesita_tooltip): ?>
                                                         title="<?php echo esc_attr($motivo_completo); ?>"
                                                         style="margin-top: 5px; font-size: 11px; color: #6c757d; line-height: 1.3; max-width: 150px; cursor: help;"
                                                         <?php endif; ?>>
                                                        <?php echo esc_html($motivo_corto); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </td>

                                        <td>
                                            <?php if ($estado_item === 'Pendiente' || $estado_item === 'devolucion_en_transito' || $estado_item === 'esperando_recepcion'): ?>
                                                <button type="button" 
                                                        onclick="abrirModalRecepcion('<?php echo esc_attr($codigo_item); ?>', <?php echo $cantidad; ?>, '<?php echo esc_attr(addslashes($producto_nombre)); ?>')"
                                                        class="btn btn-primary btn-sm"
                                                        title="Marcar como recibido">
                                                    <i class="fas fa-inbox"></i> Recibir Parcial
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php 
                                            // Botones especiales para items esperando recepción
                                            if ($estado_item === 'esperando_recepcion' && class_exists('WC_Garantias_Recepcion_Parcial_UI')): 
                                                WC_Garantias_Recepcion_Parcial_UI::render_botones_esperando($garantia_id, $item);
                                            endif; 
                                            ?>
                                            
                                            <?php if ($estado_item === 'recibido' && $cantidad > 1): ?>
                                                <button type="button" 
                                                        onclick="mostrarModalAprobacionParcial('<?php echo esc_attr($codigo_item); ?>', <?php echo $cantidad; ?>)"
                                                        class="btn btn-warning btn-sm"
                                                        title="Aprobacion parcial">
                                                    <i class="fas fa-percentage"></i>
                                                </button>
                                            <?php endif; ?>
                                            
                                            <?php if ($estado_item === 'retorno_cliente'): ?>
                                                <button type="button" 
                                                        onclick="imprimirEtiquetaIndividual('<?php echo $garantia_id; ?>', '<?php echo esc_attr($codigo_item); ?>', <?php echo $cantidad; ?>)"
                                                        class="btn btn-info btn-sm"
                                                        title="Imprimir etiqueta QR individual"
                                                        style="margin-left: 5px;">
                                                    <i class="fas fa-print"></i>
                                                </button>
                                            <?php endif; ?>
                                            <?php if ($estado_item === 'destruccion_subida'): ?>
                                                <!-- Botones para aprobar/rechazar destrucción -->
                                                <div class="btn-group btn-group-sm" role="group">
                                                    <button type="button" 
                                                            onclick="procesarDestruccion('<?php echo $garantia_id; ?>', '<?php echo esc_attr($codigo_item); ?>', 'aprobar')"
                                                            class="btn btn-success btn-sm"
                                                            title="Aprobar destruccin">
                                                        <i class="fas fa-check"></i> Aprobar
                                                    </button>
                                                    <button type="button" 
                                                            onclick="procesarDestruccion('<?php echo $garantia_id; ?>', '<?php echo esc_attr($codigo_item); ?>', 'rechazar')"
                                                            class="btn btn-danger btn-sm"
                                                            title="Rechazar destrucción">
                                                        <i class="fas fa-times"></i> Rechazar
                                                    </button>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="10" class="text-center text-muted py-4">
                                        No hay items reclamados
                                    </td>
                                </tr>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Resumen de recepción parcial si existe -->
        <?php 
        if (class_exists('WC_Garantias_Recepcion_Parcial_UI')) {
            WC_Garantias_Recepcion_Parcial_UI::render_resumen_recepcion_parcial($garantia_id);
        }
        ?>
        
        <?php
    }
    
    private static function render_acciones($garantia_id, $estado, $items) {
        // Verificar si hay items que requieren intervencin
        $hay_items_pendientes = false;
        $estados_requieren_accion = ['Pendiente', 'recibido', 'destruccion_subida', 'apelacion', 'devolucion_en_transito', 'retorno_cliente', 'aprobado_devolver'];
        
        if (is_array($items)) {
            foreach ($items as $item) {
                $estado_item = isset($item['estado']) ? $item['estado'] : 'Pendiente';
                if (in_array($estado_item, $estados_requieren_accion)) {
                    $hay_items_pendientes = true;
                    break;
                }
            }
        }
        ?>
        <div class="card" style="flex: 1;">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-tools"></i> Acciones Disponibles</h5>
            </div>
            <div class="card-body">
                <?php if ($hay_items_pendientes): ?>
                    <div style="background: #e3f2fd; border: 1px solid #1976d2; border-radius: 8px; padding: 20px; margin-bottom: 20px;">
                        <h6 style="margin-top: 0;"><i class="fas fa-tasks"></i> Procesar Items Seleccionados</h6>
                        <p style="margin-bottom: 10px; font-size: 14px;">Selecciona items en la tabla superior y aplica una accin:</p>
                        
                        <form method="post" id="bulk-form" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                            <input type="hidden" name="garantia_id" value="<?php echo esc_attr($garantia_id); ?>">
                            <select name="bulk_action" style="padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <option value="">-- Seleccionar accin --</option>
                                <option value="solicitar_info">Solicitar más info</option>
                                <option value="recibido">Recibido</option>
                                <option value="aprobado">Aprobar</option>
                                <option value="aprobado_destruir">Aprobar - Destrucción</option>
                                <option value="aprobado_devolver">Aprobar - Devolucin</option>
                                <option value="rechazado">Rechazar</option>
                            </select>
                            <button type="submit" name="procesar_items" style="padding: 8px 16px; background: #1976d2; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                <i class="fas fa-check"></i> Aplicar a Seleccionados
                            </button>
                        </form>
                    </div>
                <?php else: ?>
                    <p style="color: #666; text-align: center;">
                        <i class="fas fa-check-circle"></i> No hay items pendientes de procesar
                    </p>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    private static function render_etiquetas($garantia_id, $items, $is_distribuidor) {
        // Obtener TODAS las posibles etiquetas
        $etiqueta_devolucion_url = get_post_meta($garantia_id, '_etiqueta_devolucion_url', true);
        $etiqueta_envio_url = get_post_meta($garantia_id, '_etiqueta_envio_url', true);
        $numero_tracking = get_post_meta($garantia_id, '_numero_tracking_devolucion', true);
        
        // También verificar etiquetas de Andreani
        $andreani_etiqueta_url = get_post_meta($garantia_id, '_andreani_etiqueta_url', true);
        $andreani_numero_envio = get_post_meta($garantia_id, '_andreani_numero_envio', true);
        
        // Verificar etiqueta antigua (por compatibilidad)
        $etiqueta_subida = get_post_meta($garantia_id, '_etiqueta_subida', true);
        
        // Verificar si hay items pendientes de devolución
        $hay_items_devolver = false;
        $hay_items_en_transito = false;
        if (is_array($items)) {
            foreach ($items as $item) {
                if (isset($item['estado'])) {
                    if ($item['estado'] === 'aprobado_devolver') {
                        $hay_items_devolver = true;
                    }
                    if ($item['estado'] === 'devolucion_en_transito') {
                        $hay_items_en_transito = true;
                    }
                }
            }
        }
        
        // Determinar si hay alguna etiqueta
        $hay_etiquetas = $etiqueta_devolucion_url || $etiqueta_envio_url || $andreani_etiqueta_url || $etiqueta_subida;
        ?>
        <div class="card" style="flex: 1;">
            <div class="card-header bg-info text-white">
                <h5 class="mb-0"><i class="fas fa-tag"></i> Etiquetas y Tracking</h5>
            </div>
            <div class="card-body">
                <?php if ($hay_etiquetas): ?>
                    <div style="background: #e8f5e9; border: 1px solid #4caf50; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                        <h6 style="margin-top: 0;"><i class="fas fa-file-pdf"></i> Etiquetas Disponibles</h6>
                        
                        <?php if ($etiqueta_devolucion_url): ?>
                            <div style="margin-bottom: 10px;">
                                <a href="<?php echo esc_url($etiqueta_devolucion_url); ?>" target="_blank" 
                                   style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; background: #4caf50; color: white; text-decoration: none; border-radius: 4px;">
                                    <i class="fas fa-download"></i> Descargar Etiqueta Devolución
                                </a>
                                <?php 
                                $dimensiones = get_post_meta($garantia_id, '_dimensiones_caja_devolucion', true);
                                if ($dimensiones && is_array($dimensiones)): ?>
                                    <span style="margin-left: 10px; color: #666;">
                                        Caja: <?php echo $dimensiones['largo'] . 'x' . $dimensiones['ancho'] . 'x' . $dimensiones['alto']; ?> cm
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($andreani_etiqueta_url): ?>
                            <div style="margin-bottom: 10px;">
                                <a href="<?php echo esc_url($andreani_etiqueta_url); ?>" target="_blank" 
                                   style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; background: #ff5722; color: white; text-decoration: none; border-radius: 4px;">
                                    <i class="fas fa-download"></i> Descargar Etiqueta Andreani
                                </a>
                                <?php if ($andreani_numero_envio): ?>
                                    <span style="margin-left: 10px; color: #666;">
                                        Envo: <?php echo esc_html($andreani_numero_envio); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($etiqueta_envio_url): ?>
                            <div style="margin-bottom: 10px;">
                                <a href="<?php echo esc_url($etiqueta_envio_url); ?>" target="_blank" 
                                   style="display: inline-flex; align-items: center; gap: 8px; padding: 8px 12px; background: #2196f3; color: white; text-decoration: none; border-radius: 4px;">
                                    <i class="fas fa-download"></i> Descargar Etiqueta Envío
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($etiqueta_subida && !$etiqueta_devolucion_url && !$etiqueta_envio_url): ?>
                            <div style="margin-bottom: 10px;">
                                <span style="color: #4caf50;">
                                    <i class="fas fa-check-circle"></i> Etiqueta subida (formato antiguo)
                                </span>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($numero_tracking || $andreani_numero_envio): ?>
                            <div style="margin-top: 10px; padding-top: 10px; border-top: 1px solid #c8e6c9;">
                                <strong>Tracking:</strong> 
                                <code style="background: #f5f5f5; padding: 2px 6px; border-radius: 3px;">
                                    <?php echo esc_html($numero_tracking ?: $andreani_numero_envio); ?>
                                </code>
                                <a href="http://andreani.com/envio/<?php echo urlencode($numero_tracking ?: $andreani_numero_envio); ?>" 
                                   target="_blank" style="margin-left: 10px;">
                                    <i class="fas fa-external-link-alt"></i> Seguir envío
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" style="margin-top: 15px;">
                            <input type="hidden" name="garantia_id" value="<?php echo esc_attr($garantia_id); ?>">
                            <button type="submit" name="eliminar_etiqueta_devolucion" 
                                    onclick="return confirm('¿Seguro que deseas eliminar TODAS las etiquetas?');"
                                    style="padding: 6px 12px; background: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">
                                <i class="fas fa-trash"></i> Eliminar Etiquetas
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
                
                <?php if ($hay_items_en_transito && !$hay_etiquetas): ?>
                    <!-- Mostrar info de items en tránsito -->
                    <div style="background: #e3f2fd; border: 1px solid #2196f3; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                        <h6 style="margin-top: 0;"><i class="fas fa-truck"></i> Items en Trnsito</h6>
                        <p style="font-size: 14px; color: #666;">Hay items que estn siendo enviados a WiFix.</p>
                        
                        <!-- Opcin para agregar tracking manual -->
                        <form method="post" style="margin-top: 10px;">
                            <input type="hidden" name="garantia_id" value="<?php echo esc_attr($garantia_id); ?>">
                            <div style="margin-bottom: 10px;">
                                <input type="text" name="numero_tracking" placeholder="Número de tracking" 
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            <button type="submit" name="subir_tracking" 
                                    style="padding: 6px 12px; background: #2196f3; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                <i class="fas fa-plus"></i> Agregar Tracking
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
                
                <?php if ($hay_items_devolver && !$hay_etiquetas && $is_distribuidor): ?>
                    <!-- SISTEMA DE ETIQUETAS PARA DISTRIBUIDORES -->
                    <?php
                    // Calcular peso y dimensiones
                    $peso_total = 0;
                    $volumen_total = 0;
                    $total_items = 0;
                    
                    if (is_array($items)) {
                        foreach ($items as $item) {
                            if (isset($item['estado']) && $item['estado'] === 'aprobado_devolver') {
                                if (isset($item['producto_id'])) {
                                    $producto = wc_get_product($item['producto_id']);
                                    if ($producto) {
                                        $cantidad = intval($item['cantidad'] ?? 1);
                                        $total_items += $cantidad;
                                        
                                        // Peso
                                        $peso = floatval($producto->get_weight());
                                        $peso_total += ($peso * $cantidad);
                                        
                                        // Volumen
                                        $largo = floatval($producto->get_length());
                                        $ancho = floatval($producto->get_width());
                                        $alto = floatval($producto->get_height());
                                        $volumen_producto = $largo * $ancho * $alto;
                                        $volumen_total += ($volumen_producto * $cantidad);
                                    }
                                }
                            }
                        }
                    }
                    
                    // Determinar caja recomendada
                    $caja_recomendada = 'Pequeña';
                    if ($total_items > 5 || $peso_total > 10) {
                        $caja_recomendada = 'Grande';
                    } elseif ($total_items > 2 || $peso_total > 5) {
                        $caja_recomendada = 'Mediana';
                    }
                    ?>
                    <div style="background: #fff3e0; border: 1px solid #ff9800; border-radius: 8px; padding: 15px; margin-bottom: 15px;">
                        <h6 style="margin-top: 0; color: #e65100;">
                            <i class="fas fa-shipping-fast"></i> Etiqueta para Distribuidor
                        </h6>
                        
                        <!-- Información del envo -->
                        <div style="background: #fff; padding: 10px; border-radius: 4px; margin-bottom: 15px;">
                            <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; text-align: center;">
                                <div>
                                    <small style="color: #666;">Items</small>
                                    <div style="font-size: 20px; font-weight: bold; color: #ff9800;">
                                        <?php echo $total_items; ?>
                                    </div>
                                </div>
                                <div>
                                    <small style="color: #666;">Peso Total</small>
                                    <div style="font-size: 20px; font-weight: bold; color: #ff9800;">
                                        <?php echo number_format($peso_total, 2); ?> kg
                                    </div>
                                </div>
                                <div>
                                    <small style="color: #666;">Caja Sugerida</small>
                                    <div style="font-size: 20px; font-weight: bold; color: #ff9800;">
                                        <?php echo $caja_recomendada; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Botones para generar/subir etiqueta -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                            <button type="button" 
                                    id="generar-andreani-dist" 
                                    data-garantia="<?php echo $garantia_id; ?>" 
                                    class="btn btn-info btn-sm"
                                    style="padding: 8px; background: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                <i class="fas fa-magic"></i> Generar Andreani
                            </button>
                            <button type="button" 
                                    onclick="jQuery('#form-manual-dist').toggle()" 
                                    class="btn btn-secondary btn-sm"
                                    style="padding: 8px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                <i class="fas fa-upload"></i> Subir Manual
                            </button>
                        </div>
                        
                        <!-- Formulario para subir etiqueta manual -->
                        <form method="post" enctype="multipart/form-data" id="form-manual-dist" style="display: none;">
                            <input type="hidden" name="garantia_id" value="<?php echo $garantia_id; ?>">
                            <input type="hidden" name="subir_etiqueta" value="1">
                            
                            <div style="margin-bottom: 12px;">
                                <label style="display: block; margin-bottom: 5px; font-size: 13px;">Archivo PDF:</label>
                                <input type="file" name="etiqueta_pdf" accept=".pdf" required 
                                       style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            
                            <div style="margin-bottom: 12px;">
                                <label style="display: block; margin-bottom: 5px; font-size: 13px;">Tracking (opcional):</label>
                                <input type="text" name="numero_tracking" placeholder="Nmero de tracking" 
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-block w-100"
                                    style="padding: 8px; background: #ff9800; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                <i class="fas fa-upload"></i> Subir Etiqueta
                            </button>
                        </form>
                    </div>
                    
                    <!-- Script para generar etiqueta Andreani -->
                    <script>
                    jQuery(document).ready(function($) {
                        $('#generar-andreani-dist').on('click', function() {
                            var $btn = $(this);
                            var garantiaId = $btn.data('garantia');
                            
                            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Generando...');
                            
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'andreani_generar_etiqueta',
                                    garantia_id: garantiaId,
                                    nonce: '<?php echo wp_create_nonce("andreani_nonce"); ?>'
                                },
                                success: function(response) {
                                    if (response.success) {
                                        alert('Etiqueta Andreani generada: ' + response.data.numero_envio);
                                        location.reload();
                                    } else {
                                        alert('Error: ' + response.data);
                                    }
                                },
                                error: function() {
                                    alert('Error de conexión');
                                },
                                complete: function() {
                                    $btn.prop('disabled', false).html('<i class="fas fa-magic"></i> Generar Andreani');
                                }
                            });
                        });
                    });
                    </script>
                    
                <?php elseif ($hay_items_devolver && !$hay_etiquetas && !$is_distribuidor): ?>
                    <!-- SISTEMA DE ETIQUETAS PARA CLIENTES FINALES -->
                    <div style="background: #fff3e0; border: 1px solid #ff9800; border-radius: 8px; padding: 15px;">
                        <h6 style="margin-top: 0;"><i class="fas fa-shipping-fast"></i> Etiqueta de Devolución</h6>
                        <p style="font-size: 14px; color: #666;">Hay items aprobados pendientes de devolución.</p>
                        
                        <!-- Botones para generar/subir etiqueta -->
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 12px;">
                            <button type="button" 
                                    id="generar-andreani-cliente" 
                                    data-garantia="<?php echo $garantia_id; ?>" 
                                    class="btn btn-info btn-sm"
                                    style="padding: 8px; background: #17a2b8; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                <i class="fas fa-magic"></i> Generar Andreani
                            </button>
                            <button type="button" 
                                    onclick="jQuery('#form-manual-cliente').toggle()" 
                                    class="btn btn-secondary btn-sm"
                                    style="padding: 8px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                <i class="fas fa-upload"></i> Subir Manual
                            </button>
                        </div>
                        
                        <!-- Formulario para subir etiqueta manual (oculto por defecto) -->
                        <form method="post" enctype="multipart/form-data" id="form-manual-cliente" style="display: none;">
                            <input type="hidden" name="garantia_id" value="<?php echo esc_attr($garantia_id); ?>">
                            <input type="hidden" name="subir_etiqueta_devolucion" value="1">
                            
                            <div style="margin-bottom: 10px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Archivo PDF:</label>
                                <input type="file" name="etiqueta_pdf" accept=".pdf" required 
                                       style="width: 100%; padding: 5px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            
                            <div style="margin-bottom: 10px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Tracking (opcional):</label>
                                <input type="text" name="numero_tracking" placeholder="Número de seguimiento"
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            </div>
                            
                            <div style="margin-bottom: 10px;">
                                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Dimensiones de la caja:</label>
                                <div style="display: flex; gap: 10px;">
                                    <input type="number" name="largo" placeholder="Largo" min="1" required 
                                           style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    <input type="number" name="ancho" placeholder="Ancho" min="1" required 
                                           style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                    <input type="number" name="alto" placeholder="Alto" min="1" required 
                                           style="flex: 1; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                </div>
                            </div>
                            
                            <button type="submit" name="subir_etiqueta_devolucion" 
                                    style="padding: 8px 16px; background: #ff9800; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                <i class="fas fa-upload"></i> Subir Etiqueta
                            </button>
                        </form>
                    </div>
                    
                    <!-- Script para generar etiqueta Andreani (clientes finales) -->
                    <script>
                    jQuery(document).ready(function($) {
                        $('#generar-andreani-cliente').on('click', function() {
                            var $btn = $(this);
                            var garantiaId = $btn.data('garantia');
                            
                            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Generando...');
                            
                            $.ajax({
                                url: ajaxurl,
                                type: 'POST',
                                data: {
                                    action: 'andreani_generar_etiqueta',
                                    garantia_id: garantiaId,
                                    nonce: '<?php echo wp_create_nonce("andreani_nonce"); ?>'
                                },
                                success: function(response) {
                                    if (response.success) {
                                        alert('✓ Etiqueta Andreani generada: ' + response.data.numero_envio);
                                        location.reload();
                                    } else {
                                        alert('Error: ' + response.data);
                                    }
                                },
                                error: function() {
                                    alert('Error de conexión');
                                },
                                complete: function() {
                                    $btn.prop('disabled', false).html('<i class="fas fa-magic"></i> Generar Andreani');
                                }
                            });
                        });
                    });
                    </script>
                    
                <?php else: ?>
                    <!-- NO HAY ETIQUETAS PENDIENTES -->
                    <?php if (!$hay_etiquetas): ?>
                        <p style="color: #666; text-align: center;">
                            <i class="fas fa-info-circle"></i> No hay etiquetas pendientes
                        </p>
                    <?php endif; ?>
                <?php endif; ?>
                
                <?php 
                // Verificar si hay items en retorno_cliente para imprimir etiquetas individuales
                $hay_items_retorno_qr = false;
                $items_para_etiquetas = [];
                if (is_array($items)) {
                    foreach ($items as $item) {
                        if (isset($item['estado']) && $item['estado'] === 'retorno_cliente') {
                            $hay_items_retorno_qr = true;
                            $items_para_etiquetas[] = $item;
                        }
                    }
                }
                ?>
                
                <?php if ($hay_items_retorno_qr): ?>
                    <div style="background: #fff3e0; border: 1px solid #ff9800; border-radius: 8px; padding: 15px; margin-top: 15px;">
                        <h6 style="margin-top: 0; color: #e65100;">
                            <i class="fas fa-qrcode"></i> Etiquetas QR Individuales (6x2cm)
                        </h6>
                        
                        <p style="font-size: 14px; color: #666;">
                            Se generarn etiquetas individuales para cada item:
                        </p>
                        
                        <div style="background: white; padding: 10px; border-radius: 4px; margin: 10px 0; max-height: 200px; overflow-y: auto;">
                            <?php 
                            $total_etiquetas = 0;
                            foreach ($items_para_etiquetas as $item_etq): 
                                $producto_nombre = 'Producto eliminado';
                                $sku = $item_etq['codigo_item'] ?? '';
                                
                                if ($item_etq['producto_id'] ?? 0) {
                                    $producto = wc_get_product($item_etq['producto_id']);
                                    if ($producto) {
                                        $producto_nombre = $producto->get_name();
                                        // Obtener SKU real del producto
                                        $sku_real = get_post_meta($item_etq['producto_id'], '_alg_ean', true);
                                        if ($sku_real) {
                                            $sku = $sku_real;
                                        }
                                    } else {
                                        $producto_nombre = $item_etq['nombre_producto'] ?? 'Producto eliminado';
                                    }
                                }
                                
                                $cantidad = intval($item_etq['cantidad'] ?? 1);
                                $total_etiquetas += $cantidad;
                            ?>
                                <div style="margin-bottom: 8px; padding: 5px; background: #f5f5f5; border-radius: 3px;">
                                    <strong><?php echo esc_html($sku); ?></strong><br>
                                    <?php echo esc_html($producto_nombre); ?><br>
                                    <span style="color: #ff9800;"> <?php echo $cantidad; ?> etiqueta(s)</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div style="background: #e8f5e9; padding: 10px; border-radius: 4px; margin: 10px 0;">
                            <strong>Total de etiquetas a imprimir:</strong> <?php echo $total_etiquetas; ?> etiquetas de 6x2cm
                        </div>
                        
                        <button type="button" 
                                onclick="abrirVentanaEtiquetasIndividuales('<?php echo $garantia_id; ?>')"
                                style="padding: 10px 20px; background: #ff9800; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">
                            <i class="fas fa-print"></i> Imprimir <?php echo $total_etiquetas; ?> Etiquetas Individuales
                        </button>
                        
                        <script>
                        function abrirVentanaEtiquetasIndividuales(garantiaId) {
                            var url = '<?php echo admin_url('admin-ajax.php'); ?>?action=imprimir_etiquetas_individuales&garantia_id=' + garantiaId;
                            var ventana = window.open(url, 'EtiquetasIndividuales', 'width=800,height=600,menubar=yes,toolbar=no,location=no,status=no');
                            ventana.focus();
                        }
                        function imprimirEtiquetaIndividual(garantiaId, codigoItem, cantidad) {
                            if (confirm('Imprimir ' + cantidad + ' etiqueta(s) para este item?\n\nCódigo: ' + codigoItem)) {
                                var url = '<?php echo admin_url('admin-ajax.php'); ?>?action=imprimir_etiqueta_individual_item&garantia_id=' + garantiaId + '&codigo_item=' + codigoItem;
                                var ventana = window.open(url, 'EtiquetaIndividual', 'width=800,height=400,menubar=yes,toolbar=no,location=no,status=no');
                                ventana.focus();
                            }
                        }
                        </script>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
    
    private static function render_javascript($garantia_id) {
        ?>
        <script>
        console.log('=== JAVASCRIPT CARGADO - GARANTÍA <?php echo $garantia_id; ?> ===');
        console.log('jQuery disponible:', typeof jQuery !== 'undefined');
        
        jQuery(document).ready(function($) {
            console.log('Document ready ejecutado');
            
            // Checkbox maestro
            $('#select_all_items').on('change', function() {
                $('.item_checkbox:not(:disabled)').prop('checked', $(this).prop('checked'));
            });
            
            // Filtros de estado
            $('.filtro-estado-item').on('change', function() {
                filtrarItemsPorEstado();
            });
            
            function filtrarItemsPorEstado() {
                var estadosSeleccionados = [];
                $('.filtro-estado-item:checked').each(function() {
                    estadosSeleccionados.push($(this).val());
                });
                
                $('.table tbody tr').each(function() {
                    var $fila = $(this);
                    var estadoFila = $fila.find('.badge').text().trim();
                    
                    var mapaEstados = {
                        'Pendiente': 'Pendiente',
                        'Info Solicitada': 'solicitar_info',
                        'Recibido': 'recibido',
                        'Aprobado': 'aprobado',
                        'Aprobado - Destruir': 'aprobado_destruir',
                        'Aprobado - Devolver': 'aprobado_devolver',
                        'Destruccin Subida': 'destruccion_subida',
                        'Rechazado': 'rechazado',
                        'Enviado a WiFix': 'devolucion_en_transito',
                        'Retorno Cliente': 'retorno_cliente',
                        'Apelación': 'apelacion',
                        'Esperando Recepcin': 'esperando_recepcion',
                        'Rechazado - No Recibido': 'rechazado_no_recibido'
                    };
                    
                    var estadoReal = '';
                    for (var texto in mapaEstados) {
                        if (estadoFila === texto) {
                            estadoReal = mapaEstados[texto];
                            break;
                        }
                    }
                    
                    if (estadosSeleccionados.includes(estadoReal)) {
                        $fila.show();
                    } else {
                        $fila.hide();
                    }
                });
            }
            
            // ========== ENVO DEL FORMULARIO BULK ==========
            console.log('Buscando formulario #bulk-form...');
            console.log('Formulario encontrado:', $('#bulk-form').length);
            
            $('#bulk-form').on('submit', function(e) {
                console.log('¡FORMULARIO BULK ENVIÁNDOSE!');
                
                var selectedItems = [];
                
                $('.item_checkbox:checked').each(function() {
                    var valor = $(this).val();
                    console.log('Item seleccionado:', valor);
                    selectedItems.push(valor);
                });
                
                console.log('Total items seleccionados:', selectedItems.length);
                
                if (selectedItems.length === 0) {
                    e.preventDefault();
                    alert('Por favor selecciona al menos un item');
                    return false;
                }
                
                // Limpiar inputs anteriores
                $(this).find('input[name="bulk_items"]').remove();
                $(this).find('input[name="bulk_items[]"]').remove();
                
                // Agregar items como array PHP
                for (var i = 0; i < selectedItems.length; i++) {
                    $('<input>').attr({
                        type: 'hidden',
                        name: 'bulk_items[]',
                        value: selectedItems[i]
                    }).appendTo(this);
                    console.log('Agregado input hidden:', selectedItems[i]);
                }
                
                console.log('Formulario listo, enviando con', selectedItems.length, 'items');
                return true;
            });
            
            // ========== BOTÓN RMA - DELEGACIN DE EVENTOS ==========
            console.log('Configurando evento para botones RMA...');
            
            $(document).on('click', '.generar-cupon-rma-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                
                console.log('Botón RMA clickeado (delegación)');
                
                var btn = $(this);
                var garantiaId = btn.data('garantia-id');
                var codigoItem = btn.data('codigo-item');
                var itemIndex = btn.data('item-index');
                
                console.log('Datos del botn:', {
                    garantiaId: garantiaId,
                    codigoItem: codigoItem,
                    itemIndex: itemIndex
                });
                
                // Verificar que tenemos los datos
                if (!garantiaId || !codigoItem) {
                    alert('Error: Faltan datos necesarios\n\nGarantía ID: ' + garantiaId + '\nCódigo Item: ' + codigoItem + '\nIndex: ' + itemIndex);
                    return false;
                }
                
                if (!confirm('¿Generar cupón RMA para este item?\n\nSe buscará un producto con SKU: ' + codigoItem)) {
                    return false;
                }
                
                btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Procesando...');
                
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    dataType: 'json',
                    data: {
                        action: 'generar_cupon_rma_manual',
                        garantia_id: garantiaId,
                        codigo_item: codigoItem,
                        item_index: itemIndex,
                        nonce: '<?php echo wp_create_nonce('generar_cupon_rma'); ?>'
                    },
                    success: function(response) {
                        console.log('Respuesta RMA:', response);
                        
                        if (response && response.success) {
                            alert(' Cupn RMA generado: ' + response.data.cupon + '\n\nEl cupón se aplicar automticamente en la próxima compra del cliente.');
                            window.location.reload();
                        } else {
                            var mensaje = response && response.data && response.data.message ? response.data.message : 'Error desconocido';
                            alert(' Error: ' + mensaje);
                            btn.prop('disabled', false).html('<i class="fas fa-ticket-alt"></i> RMA');
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('Error AJAX RMA:', {
                            status: status,
                            error: error,
                            responseText: xhr.responseText
                        });
                        
                        alert('Error de conexión: ' + error + '\n\nRevisa la consola para ms detalles.');
                        btn.prop('disabled', false).html('<i class="fas fa-ticket-alt"></i> RMA');
                    }
                });
                
                return false;
            });
            
            // ========== VERIFICACIN FINAL ==========
            setTimeout(function() {
                console.log('=== VERIFICACIÓN FINAL ===');
                console.log('Formulario #bulk-form:', $('#bulk-form').length);
                console.log('Checkboxes .item_checkbox:', $('.item_checkbox').length);
                console.log('Botn de envo:', $('#bulk-form button[type="submit"]').length);
                console.log('Botones RMA:', $('.generar-cupon-rma-btn').length);
            }, 1000);
            // Función para procesar destruccin
            window.procesarDestruccion = function(garantiaId, codigoItem, accion) {
                if (accion === 'aprobar') {
                    var mensaje = '¿Aprobar la destruccin de este item?\n\nEsto confirmará que el cliente destruyó correctamente el producto.';
                    
                    if (!confirm(mensaje)) {
                        return false;
                    }
                    
                    // Enviar aprobación directa
                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'procesar_destruccion_item',
                            garantia_id: garantiaId,
                            codigo_item: codigoItem,
                            decision: accion,
                            nonce: '<?php echo wp_create_nonce("procesar_destruccion"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('✓ Destrucción aprobada correctamente');
                                location.reload();
                            } else {
                                alert('Error: ' + (response.data || 'Error desconocido'));
                            }
                        },
                        error: function() {
                            alert('Error de conexión');
                        }
                    });
                } else {
                    // Para rechazo, pedir motivo
                    var motivo = prompt('¿Por qué rechazas la destrucción?\n\nEscribe el motivo que ver el cliente:');
                    
                    if (motivo === null) {
                        return false; // Canceló
                    }
                    
                    if (motivo.trim() === '') {
                        motivo = 'La evidencia de destrucción no cumple con los requisitos. Por favor, destruye completamente el producto y sube nueva evidencia.';
                    }
                    
                    // Enviar rechazo con motivo
                    jQuery.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'procesar_destruccion_item',
                            garantia_id: garantiaId,
                            codigo_item: codigoItem,
                            decision: accion,
                            motivo_rechazo: motivo,
                            nonce: '<?php echo wp_create_nonce("procesar_destruccion"); ?>'
                        },
                        success: function(response) {
                            if (response.success) {
                                alert('✓ Destrucción rechazada. Se notificó al cliente.');
                                location.reload();
                            } else {
                                alert('Error: ' + (response.data || 'Error desconocido'));
                            }
                        },
                        error: function() {
                            alert('Error de conexión');
                        }
                    });
                }
            };
        }); // Fin del document.ready
        
        // Función para mostrar historial completo en modal
        function mostrarHistorialCompleto(modalId) {
            var content = document.getElementById(modalId);
            if (!content) {
                alert('No se encontró el historial');
                return;
            }
            
            // Crear modal ms grande para el historial
            var modal = document.createElement('div');
            modal.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:white;padding:30px;border-radius:10px;box-shadow:0 10px 40px rgba(0,0,0,0.3);z-index:99999;width:90%;max-width:800px;max-height:85vh;overflow-y:auto;';
            modal.innerHTML = content.innerHTML;
            
            // Fondo oscuro
            var overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:99998;';
            overlay.onclick = function() {
                document.body.removeChild(modal);
                document.body.removeChild(overlay);
            };
            
            // Botón cerrar
            var closeBtn = document.createElement('button');
            closeBtn.innerHTML = '&times;';
            closeBtn.style.cssText = 'position:absolute;top:10px;right:10px;background:none;border:none;font-size:30px;cursor:pointer;color:#999;padding:0;width:40px;height:40px;';
            closeBtn.onclick = function() {
                document.body.removeChild(modal);
                document.body.removeChild(overlay);
            };
            modal.appendChild(closeBtn);
            
            document.body.appendChild(overlay);
            document.body.appendChild(modal);
        }
        // Función para mostrar apelacin en modal
        function mostrarApelacion(modalId) {
            var content = document.getElementById(modalId);
            if (!content) {
                alert('No se encontró la apelacin');
                return;
            }
            
            // Crear modal para apelación
            var modal = document.createElement('div');
            modal.style.cssText = 'position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:white;padding:30px;border-radius:10px;box-shadow:0 10px 40px rgba(0,0,0,0.3);z-index:99999;width:90%;max-width:700px;max-height:85vh;overflow-y:auto;';
            modal.innerHTML = content.innerHTML;
            
            // Fondo oscuro
            var overlay = document.createElement('div');
            overlay.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:99998;';
            overlay.onclick = function() {
                document.body.removeChild(modal);
                document.body.removeChild(overlay);
            };
            
            // Botn cerrar
            var closeBtn = document.createElement('button');
            closeBtn.innerHTML = '&times;';
            closeBtn.style.cssText = 'position:absolute;top:10px;right:10px;background:none;border:none;font-size:30px;cursor:pointer;color:#999;padding:0;width:40px;height:40px;';
            closeBtn.onclick = function() {
                document.body.removeChild(modal);
                document.body.removeChild(overlay);
            };
            modal.appendChild(closeBtn);
            
            document.body.appendChild(overlay);
            document.body.appendChild(modal);
        }
        
        </script>
        <?php
    }
    
    private static function render_modal_aprobacion_parcial($garantia_id) {
        ?>
        <!-- Modal de Aprobación Parcial -->
        <div id="modal-aprobacion-parcial" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999;">
            <div style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; padding: 30px; border-radius: 10px; max-width: 500px; width: 90%;">
                <h3 style="margin-top: 0;">Aprobacion Parcial</h3>
                <p>Divide el item en cantidades aprobadas y rechazadas:</p>
                
                <form method="post">
                    <input type="hidden" name="garantia_id" value="<?php echo $garantia_id; ?>">
                    <input type="hidden" name="accion_parcial" value="dividir_item">
                    <input type="hidden" name="codigo_item" id="modal-codigo-item">
                    
                    <div style="margin-bottom: 15px;">
                        <label>Cantidad total: <strong id="modal-cantidad-total">0</strong></label>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label style="display: block; margin-bottom: 5px;">Cantidad a aprobar:</label>
                            <input type="number" name="cantidad_aprobar" id="cantidad_aprobar" min="1" required 
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                        </div>
                        <div>
                            <label style="display: block; margin-bottom: 5px;">Cantidad a rechazar:</label>
                            <input type="number" name="cantidad_rechazar" id="cantidad_rechazar" readonly 
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; background: #f5f5f5;">
                        </div>
                    </div>
                    
                    <div style="margin-bottom: 20px;">
                        <label style="display: block; margin-bottom: 5px;">Motivo del rechazo parcial:</label>
                        <textarea name="motivo_rechazo_parcial" required 
                                  style="width: 100%; height: 80px; padding: 8px; border: 1px solid #ddd; border-radius: 4px;"
                                  placeholder="Explica por qu se rechazan algunas unidades"></textarea>
                    </div>
                    
                    <div style="display: flex; gap: 10px; justify-content: flex-end;">
                        <button type="button" onclick="cerrarModalParcial()" 
                                style="padding: 8px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            Cancelar
                        </button>
                        <button type="submit" 
                                style="padding: 8px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer;">
                            Dividir Item
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <script>
        function mostrarModalAprobacionParcial(codigoItem, cantidadTotal) {
            document.getElementById('modal-aprobacion-parcial').style.display = 'block';
            document.getElementById('modal-codigo-item').value = codigoItem;
            document.getElementById('modal-cantidad-total').textContent = cantidadTotal;
            document.getElementById('cantidad_aprobar').max = cantidadTotal - 1;
            
            // Auto-calcular cantidad rechazada
            document.getElementById('cantidad_aprobar').addEventListener('input', function() {
                var aprobadas = parseInt(this.value) || 0;
                document.getElementById('cantidad_rechazar').value = cantidadTotal - aprobadas;
            });
        }
        
        function cerrarModalParcial() {
            document.getElementById('modal-aprobacion-parcial').style.display = 'none';
        }
        </script>
        <?php
    }
}