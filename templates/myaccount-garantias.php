<?php
// Activar reporte de errores para debug
ini_set('display_errors', 1);

if ( ! defined( 'ABSPATH' ) ) exit;

// Verificar que la clase de emails esté disponible
if (!class_exists('WC_Garantias_Emails')) {
}

// Cargar el frontend de recepción parcial
if (!class_exists('WC_Garantias_Recepcion_Parcial_Frontend')) {
    $archivo_frontend = dirname(__FILE__) . '/myaccount-garantias-recepcion-parcial-frontend.php';
    if (file_exists($archivo_frontend)) {
        require_once $archivo_frontend;
    }
}

// ========================================
// SISTEMA CENTRALIZADO DE MENSAJES
// ========================================
function garantias_set_mensaje($mensaje, $tipo = 'success') {
    if (!session_id()) {
        session_start();
    }
    if (!isset($_SESSION['garantia_mensajes'])) {
        $_SESSION['garantia_mensajes'] = [];
    }
    $_SESSION['garantia_mensajes'][] = [
        'mensaje' => $mensaje,
        'tipo' => $tipo // success, error, warning, info
    ];
}

function garantias_get_mensajes() {
    if (!session_id()) {
        session_start();
    }
    $mensajes = isset($_SESSION['garantia_mensajes']) ? $_SESSION['garantia_mensajes'] : [];
    unset($_SESSION['garantia_mensajes']); // Limpiar después de obtener
    return $mensajes;
}
// ========================================

// Recopilar productos comprados por el cliente
$customer_id = get_current_user_id();

// Obtener el rol del usuario actual
$user = wp_get_current_user();
$user_roles = $user->roles;
$is_distribuidor = false;
$is_cliente_final = false;

// Verificar si estamos en la sección de devoluciones
$is_devolucion_section = isset($_GET['devolucion']) && $_GET['devolucion'] === '1';

// Generar etiqueta si se solicita
if (isset($_GET['generar_etiqueta']) && isset($_GET['devolucion_id'])) {
    $devolucion_id = intval($_GET['devolucion_id']);
    $devolucion = get_post($devolucion_id);
    
    if ($devolucion && $devolucion->post_author == $customer_id) {
        require_once WC_GARANTIAS_PATH . 'includes/class-wc-garantias-etiqueta.php';
        $html = WC_Garantias_Etiqueta::generar_etiqueta_devolucion($devolucion_id);
        echo $html;
        exit;
    }
}
// Generar etiquetas de items A4
if (isset($_GET['generar_etiquetas_a4']) && isset($_GET['garantia_id'])) {
    // Limpiar cualquier salida previa
    ob_clean();
    
    $garantia_id = intval($_GET['garantia_id']);
    $garantia = get_post($garantia_id);
    
    if ($garantia && $garantia->post_author == $customer_id) {
        // Evitar cualquier salida de WordPress
        remove_all_actions('wp_head');
        remove_all_actions('wp_footer');
        
        require_once WC_GARANTIAS_PATH . 'includes/class-wc-garantias-etiqueta.php';
        WC_Garantias_Etiqueta::generar_etiquetas_items_a4($garantia_id);
        exit;
    }
}

// Generar etiqueta individual
if (isset($_GET['generar_etiqueta_individual']) && isset($_GET['garantia_id']) && isset($_GET['item_codigo'])) {
    // Limpiar cualquier salida previa
    ob_clean();
    
    $garantia_id = intval($_GET['garantia_id']);
    $item_codigo = sanitize_text_field($_GET['item_codigo']);
    $garantia = get_post($garantia_id);
    
    if ($garantia && $garantia->post_author == $customer_id) {
        // Evitar cualquier salida de WordPress
        remove_all_actions('wp_head');
        remove_all_actions('wp_footer');
        
        require_once WC_GARANTIAS_PATH . 'includes/class-wc-garantias-etiqueta.php';
        WC_Garantias_Etiqueta::generar_etiqueta_item_individual($garantia_id, $item_codigo);
        exit;
    }
}

// Generar TODAS las etiquetas individuales en un PDF
if (isset($_GET['generar_todas_individuales']) && isset($_GET['garantia_id'])) {
    // Limpiar cualquier salida previa
    ob_clean();
    
    $garantia_id = intval($_GET['garantia_id']);
    $garantia = get_post($garantia_id);
    
    if ($garantia && $garantia->post_author == $customer_id) {
        // Evitar cualquier salida de WordPress
        remove_all_actions('wp_head');
        remove_all_actions('wp_footer');
        
        require_once WC_GARANTIAS_PATH . 'includes/class-wc-garantias-etiqueta.php';
        WC_Garantias_Etiqueta::generar_todas_etiquetas_individuales($garantia_id);
        exit;
    }
}

// Mostrar mensaje de éxito si se acaba de crear una devolución Y estamos viendo ESA devolución
if (isset($_GET['devolucion_success']) && isset($_GET['devolucion_id']) && 
    isset($_POST['ver_detalle_garantia_id']) && $_POST['ver_detalle_garantia_id'] == $_GET['devolucion_id']) {
    $devolucion_id = intval($_GET['devolucion_id']);
    $codigo = get_post_meta($devolucion_id, '_codigo_unico', true);
    ?>
    <div class="woocommerce-message" style="margin-bottom: 20px;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <strong>Devolución registrada correctamente!</strong><br>
                Código: <?php echo esc_html($codigo); ?>
            </div>
            <div>
                <a href="<?php echo add_query_arg(['generar_etiqueta' => '1', 'devolucion_id' => $devolucion_id], wc_get_account_endpoint_url('garantias')); ?>" 
                   class="button" 
                   target="_blank"
                   style="background: #17a2b8; color: white; margin-left: 10px;">
                    <i class="fas fa-download"></i> Descargar Etiqueta
                </a>
            </div>
        </div>
    </div>
    <?php
}

// PROCESAR TRACKING DE DEVOLUCIN POR ERROR
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['actualizar_tracking_devolucion'])) {
    $garantia_id = intval($_POST['garantia_id']);
    $garantia = get_post($garantia_id);
    
    if ($garantia && $garantia->post_author == $customer_id) {
        $numero_seguimiento = sanitize_text_field($_POST['numero_seguimiento']);
        $empresa_transporte = sanitize_text_field($_POST['empresa_transporte']);
        
        // Si seleccionó "Otro", tomar el valor del campo adicional
        if ($empresa_transporte === 'Otro' && !empty($_POST['empresa_transporte_otro'])) {
            $empresa_transporte = sanitize_text_field($_POST['empresa_transporte_otro']);
        }
        
        // Actualizar meta datos
        update_post_meta($garantia_id, '_numero_seguimiento_devolucion', $numero_seguimiento);
        update_post_meta($garantia_id, '_empresa_transporte_devolucion', $empresa_transporte);
        update_post_meta($garantia_id, '_fecha_tracking_devolucion', current_time('mysql'));
        
        // Actualizar el estado del ITEM a "devolucion_en_transito" 
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        if (is_array($items)) {
            foreach ($items as &$item) {
                // Como es devolución por error, normalmente hay un solo item
                $item['estado'] = 'devolucion_en_transito';
            }
            update_post_meta($garantia_id, '_items_reclamados', $items);
        }
        
        // Actualizar estado GENERAL a "en_proceso"
        update_post_meta($garantia_id, '_estado', 'en_proceso');
        
        // Mostrar mensaje de éxito
        garantias_set_mensaje('Información de envio guardada correctamente. Estado actualizado a "En Proceso"', 'success');
        
        // Guardar el ID de la garantía en sesión para volver al detalle
        if (!session_id()) {
            session_start();
        }
        $_SESSION['garantia_volver_detalle'] = $garantia_id;
        
        // Redireccionar para evitar reenvio
        wp_redirect(wc_get_account_endpoint_url('garantias'));
        exit;
        
        // Mostrar mensaje de éxito
        garantias_set_mensaje('Información de envío guardada correctamente. Estado actualizado a "Enviado a WiFix"', 'success');
        
        // Redireccionar para evitar reenvío
        wp_redirect(wc_get_account_endpoint_url('garantias'));
        exit;
    }
}

// PROCESAR TODAS LAS ACCIONES POST DE FORMA CENTRALIZADA
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    WC_Garantias_Processor::process_frontend_actions();
}

// Verificar el tipo de usuario
foreach ($user_roles as $role) {
    if (in_array($role, ['distri10', 'distri20', 'distri30', 'superdistri30'])) {
        $is_distribuidor = true;
        break;
    }
}

// Si no es distribuidor, entonces es cliente final
if (!$is_distribuidor) {
    $is_cliente_final = true;
}

// SI VIENE UN TIMELINE INDIVIDUAL, SOLO MUESTRA ESO Y SALE
if (isset($_POST['ver_timeline_id'])) {
    $garantia_id = intval($_POST['ver_timeline_id']);
    $garantia_para_timeline = get_post($garantia_id);
    if ($garantia_para_timeline && $garantia_para_timeline->post_author == $customer_id) {
        include WC_GARANTIAS_PATH . 'templates/myaccount-garantias-timeline.php';
    } else {
        echo '<div class="woocommerce-error">No se encontró el reclamo o no tienes permiso para verlo.</div>';
    }
    return;
}

// Mensaje de éxito de carga masiva
if (isset($_GET['carga_masiva_exitosa']) && isset($_GET['codigo']) && isset($_GET['items'])) {
    ?>
    <div class="woocommerce-message" style="margin-bottom: 20px; background: #d4edda; border-color: #c3e6cb; color: #155724;">
        <div style="display: flex; align-items: center; justify-content: space-between;">
            <div>
                <strong><i class="fas fa-check-circle"></i> ¡Carga masiva exitosa!</strong><br>
                Se creó el reclamo <strong><?php echo esc_html($_GET['codigo']); ?></strong> con <?php echo intval($_GET['items']); ?> productos.
            </div>
            <form method="post" style="margin: 0;">
                <input type="hidden" name="ver_detalle_garantia_id" value="<?php echo get_posts(['post_type' => 'garantia', 'meta_key' => '_codigo_unico', 'meta_value' => $_GET['codigo'], 'posts_per_page' => 1])[0]->ID ?? 0; ?>">
                <button type="submit" class="button" style="background: #17a2b8; color: white;">
                    <i class="fas fa-eye"></i> Ver Detalle
                </button>
            </form>
        </div>
    </div>
    <?php
}

// NUEVO: MOSTRAR MENSAJES CENTRALIZADOS AQU
$mensajes = garantias_get_mensajes();
if (!empty($mensajes)): 
    foreach ($mensajes as $msg):
        $class = 'woocommerce-message'; // Por defecto success
        if ($msg['tipo'] === 'error') $class = 'woocommerce-error';
        elseif ($msg['tipo'] === 'warning') $class = 'woocommerce-info';
        elseif ($msg['tipo'] === 'info') $class = 'woocommerce-info';
        
        echo '<div class="' . $class . '" style="margin-bottom: 20px;">';
        echo esc_html($msg['mensaje']);
        echo '</div>';
    endforeach;
endif;

// Verificar si debemos volver a un detalle específico
if (!session_id()) {
    session_start();
}
if (isset($_SESSION['garantia_volver_detalle'])) {
    $garantia_id_volver = $_SESSION['garantia_volver_detalle'];
    unset($_SESSION['garantia_volver_detalle']);
    ?>
    <form id="auto-ver-detalle" method="post" style="display:none;">
        <input type="hidden" name="ver_detalle_garantia_id" value="<?php echo $garantia_id_volver; ?>">
    </form>
    <script>
        document.getElementById('auto-ver-detalle').submit();
    </script>
    <?php
    return;
}

// COMPATIBILIDAD: Mantener el sistema antiguo por si acaso
if (!session_id()) {
    session_start();
}
if (isset($_SESSION['garantia_mensaje'])) {
    echo '<div class="woocommerce-message">' . esc_html($_SESSION['garantia_mensaje']) . '</div>';
    unset($_SESSION['garantia_mensaje']);
}

// Mostrar seccin de devoluciones si corresponde
if ($is_devolucion_section) {
    include WC_GARANTIAS_PATH . 'templates/myaccount-devoluciones.php';
    return; // Importante: salir aqu para no mostrar el resto
}

// Mostrar seccin de carga masiva si corresponde
$is_carga_masiva_section = isset($_GET['carga_masiva']) && $_GET['carga_masiva'] === '1';
if ($is_carga_masiva_section && $is_distribuidor) {
    include WC_GARANTIAS_PATH . 'templates/myaccount-garantias-carga-masiva.php';
    return; // Importante: salir aquí para no mostrar el resto
}

// Dashboard y elementos principales solo si NO estamos viendo detalles
if (!isset($_POST['ver_detalle_garantia_id'])) {
    include WC_GARANTIAS_PATH . 'templates/myaccount-garantias-dashboard.php';
}

// ---- DETALLE DE UN RECLAMO ----
if (isset($_POST['ver_detalle_garantia_id'])) {
    $garantia_id = intval($_POST['ver_detalle_garantia_id']);
    $garantia = get_post($garantia_id);
    
    if ($garantia && $garantia->post_author == $customer_id) {
        $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
        $fecha_raw = get_post_meta($garantia_id, '_fecha', true);
        $estado = get_post_meta($garantia_id, '_estado', true);
        
        $estados_nombres = [
            'nueva'                  => 'Nueva Garantia',
            'en_proceso'             => 'En proceso',
            'en_revision'            => 'En revision',
            'pendiente_envio'        => 'Pendiente de envio',
            'recibido'               => 'Recibido - En anlisis',
            'aprobado_cupon'         => 'Aprobado - Cupon Enviado',
            'aprobado_destruir'      => 'Aprobado - Destruir',
            'aprobado_devolver'      => 'Aprobado - Devolver',
            'destruccion_subida'     => 'Destruccion subida - En revision',
            'devolucion_en_transito' => 'Enviado a WiFix', 
            'rechazado'              => 'Rechazado',
            'finalizado_cupon'       => 'Finalizado - Cupón utilizado',
            'finalizado'             => 'Finalizado',
            'info_solicitada'        => 'Información Solicitada',
            'parcialmente_recibido'  => 'Parcialmente Recibido',
        ];
        
        $fecha = '';
        if ($fecha_raw) {
            $timestamp = strtotime($fecha_raw);
            $fecha = $timestamp ? date('d/m/Y', $timestamp) : '';
        }

        $items = get_post_meta($garantia_id, '_items_reclamados', true);

        echo '<h3>Detalle de reclamo: ' . esc_html($codigo_unico) . '</h3>';
        
        // Mostrar vista detallada de recepción parcial
        if (class_exists('WC_Garantias_Recepcion_Parcial_Frontend')) {
            WC_Garantias_Recepcion_Parcial_Frontend::render_detalle_recepcion_parcial($garantia_id, $items);
        }
        
        echo '<p><strong>Fecha:</strong> ' . esc_html($fecha) . ' &nbsp; <strong>Estado:</strong> ' . esc_html($estados_nombres[$estado] ?? $estado) . '</p>';
        
        // Detectar si hay items divididos
        $hay_divisiones = false;
        $total_aprobado_parcial = 0;
        $total_rechazado_parcial = 0;
        
        if ($items && is_array($items)) {
            foreach ($items as $item) {
                if (isset($item['nota_division']) || isset($item['es_division'])) {
                    $hay_divisiones = true;
                    if (strpos($item['codigo_item'], '-R') !== false) {
                        $total_rechazado_parcial += intval($item['cantidad']);
                    } else if (isset($item['nota_division'])) {
                        $total_aprobado_parcial += intval($item['cantidad']);
                    }
                }
            }
        }
        
        // Mostrar alerta si hay divisiones
        if ($hay_divisiones) {
            echo '<div style="background: #fff3cd; border: 1px solid #ffeaa7; border-radius: 8px; padding: 15px; margin: 15px 0;">';
            echo '<h4 style="margin: 0 0 10px 0; color: #856404;"><i class="fas fa-exclamation-triangle"></i> Aprobación Parcial</h4>';
            echo '<p style="margin: 0; color: #856404;">Esta garantía tiene items con aprobacion parcial:</p>';
            echo '<ul style="margin: 10px 0 0 20px; color: #856404;">';
            if ($total_aprobado_parcial > 0) {
                echo '<li><strong>' . $total_aprobado_parcial . ' unidades aprobadas</strong> - Se generará cupón por estas unidades</li>';
            }
            if ($total_rechazado_parcial > 0) {
                echo '<li><strong>' . $total_rechazado_parcial . ' unidades rechazadas</strong> - No se incluyen en el cupón</li>';
            }
            echo '</ul>';
            echo '</div>';
        }
        
        // Verificar si hay items para devolver
        $items_para_devolver = array();
        if ($items && is_array($items)) {
            foreach ($items as $index => $item) {
                $estado_item = isset($item['estado']) ? $item['estado'] : 'Pendiente';
                if ($estado_item === 'aprobado_devolver' || $estado_item === 'devolucion_en_transito') {
                    $items_para_devolver[$index] = $item;
                }
            }
        }
        
        // Verificar si hay etiqueta de envío (para distribuidores)
        $tiene_etiqueta_envio = false;
        if ($is_distribuidor) {
            $etiqueta_url = get_post_meta($garantia_id, '_etiqueta_envio_url', true);
            if (empty($etiqueta_url)) {
                $etiqueta_url = get_post_meta($garantia_id, '_etiqueta_devolucion_url', true);
            }
            $tiene_etiqueta_envio = !empty($etiqueta_url);
        }
        
        // Mostrar etiquetas según el tipo de usuario
        $mostrar_etiquetas = false;
        if (!$is_distribuidor && !empty($items_para_devolver)) {
            // Cliente final: mostrar si hay items para devolver
            $mostrar_etiquetas = true;
        } elseif ($is_distribuidor && $tiene_etiqueta_envio && !empty($items_para_devolver)) {
            // Distribuidor: mostrar SOLO si ya hay etiqueta de envio
            $mostrar_etiquetas = true;
        }
        
        if ($mostrar_etiquetas) {
            echo '<div style="background: #e3f2fd; border: 1px solid #1976d2; border-radius: 8px; padding: 15px; margin: 15px 0;">';
            echo '<h4 style="margin: 0 0 10px 0; color: #1976d2;"><i class="fas fa-barcode"></i> Identificar Productos para Envío</h4>';
            echo '<p style="margin: 0 0 15px 0; color: #555; font-size: 14px;">';
            if ($is_distribuidor) {
                echo 'Ya tienes la etiqueta de envio. Ahora imprime estas etiquetas para identificar cada producto.';
            } else {
                echo 'Imprime estas etiquetas y pégalas en cada producto antes de enviarlo. Nos ayuda a procesarlos más rápido.';
            }
            echo '</p>';
            echo '<div style="display: flex; gap: 15px; flex-wrap: wrap;">';
            // Si hay múltiples items, mostrar botón A4
            if (count($items_para_devolver) > 1) {
                echo '<a href="' . add_query_arg(['generar_etiquetas_a4' => '1', 'garantia_id' => $garantia_id, 'solo_devolver' => '1'], wc_get_account_endpoint_url('garantias')) . '" 
                        target="_blank"
                        class="button" 
                        style="background: #1976d2; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                        <i class="fas fa-file"></i> Todas en A4 (' . count($items_para_devolver) . ' etiquetas)
                      </a>';
                
                echo '<a href="' . add_query_arg(['generar_todas_individuales' => '1', 'garantia_id' => $garantia_id, 'solo_devolver' => '1'], wc_get_account_endpoint_url('garantias')) . '" 
                        target="_blank"
                        class="button" 
                        style="background: #f57c00; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;">
                        <i class="fas fa-tags"></i> Todas Individuales (PDF)
                      </a>';
            }
            
            // Men de etiquetas individuales
            echo '<div style="position: relative; display: inline-block;">';
            echo '<button type="button" 
                    onclick="document.getElementById(\'menu-etiquetas-' . $garantia_id . '\').style.display = document.getElementById(\'menu-etiquetas-' . $garantia_id . '\').style.display === \'none\' ? \'block\' : \'none\';"
                    class="button" 
                    style="background: #388e3c; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
                    <i class="fas fa-tag"></i> Etiquetas Individuales ▼
                  </button>';
            
            // Menú desplegable solo con items para devolver
            echo '<div id="menu-etiquetas-' . $garantia_id . '" 
                    style="display: none; position: absolute; top: 100%; left: 0; background: white; 
                           border: 1px solid #ddd; border-radius: 5px; box-shadow: 0 2px 5px rgba(0,0,0,0.2); 
                           min-width: 200px; z-index: 1000; margin-top: 5px;">';
            
            foreach ($items_para_devolver as $index => $item) {
                $producto = wc_get_product($item['producto_id']);
                if ($producto) {
                    $codigo_item = $item['codigo_item'] ?? 'ITEM-' . $index;
                    echo '<a href="' . add_query_arg([
                            'generar_etiqueta_individual' => '1', 
                            'garantia_id' => $garantia_id,
                            'item_codigo' => $codigo_item
                        ], wc_get_account_endpoint_url('garantias')) . '" 
                        target="_blank"
                        style="display: block; padding: 10px 15px; color: #333; text-decoration: none; 
                               border-bottom: 1px solid #eee; font-size: 13px;"
                        onmouseover="this.style.background=\'#f5f5f5\'"
                        onmouseout="this.style.background=\'white\'">
                        <strong>' . esc_html($codigo_item) . '</strong><br>
                        <small>' . esc_html(substr($producto->get_name(), 0, 50)) . '...</small>
                    </a>';
                }
            }
            
            echo '</div>';
            echo '</div>';
            
            echo '</div>';
            echo '</div>';
        }

        if ($items && is_array($items)) {
            echo '<table class="shop_table shop_table_responsive">';
            echo '<thead><tr>
                <th style="width: 15%;">Codigo tem</th>
                <th style="width: 25%;">Producto</th>
                <th style="width: 60px; text-align: center;">Cantidad</th>
                <th style="width: 15%;">Motivo</th>
                <th style="width: 15%;">Estado</th>
                <th style="width: 60px;">Foto</th>
                <th style="width: 60px;">Video</th>
                <th style="width: 80px;">N° Orden</th>
                <th style="width: 120px;">Acciones</th>
                </tr></thead><tbody>';
            
            foreach ($items as $index => $item) {
                $prod = wc_get_product($item['producto_id']);
                
                // Determinar estado del item
                $estado_item = isset($item['estado']) ? $item['estado'] : 'Pendiente';
                $estado_color = [
                    'Pendiente' => '#ffc107',
                    'solicitar_info' => '#17a2b8',
                    'recibido' => '#17a2b8',
                    'aprobado' => '#28a745',
                    'aprobado_destruir' => '#dc3545',
                    'aprobado_devolver' => '#007bff',
                    'destruccion_subida' => '#fd7e14', 
                    'rechazado' => '#dc3545',
                    'devolucion_en_transito' => '#17a2b8', 
                    'apelacion' => '#6f42c1',
                    'esperando_recepcion' => '#ff9800',
                    'rechazado_no_recibido' => '#dc3545'
                ];
                
                $estado_texto = [
                    'Pendiente' => 'Pendiente',
                    'solicitar_info' => 'Info Solicitada',
                    'recibido' => 'Recibido',
                    'aprobado' => 'Aprobado',
                    'aprobado_destruir' => 'Destruir',
                    'aprobado_devolver' => 'Devolver',
                    'destruccion_subida' => 'Destrucción subida',
                    'rechazado' => 'Rechazado',
                    'devolucion_en_transito' => 'Enviado a WiFix',
                    'apelacion' => 'En Apelación',
                    'esperando_recepcion' => 'Esperando Recepcion',
                    'rechazado_no_recibido' => 'Rechazado - No Recibido',
                    'retorno_cliente' => 'Devolucion al Cliente'
                ];
                
                echo '<tr>';
                // Detectar si es item dividido
                $es_division = isset($item['es_division']) && $item['es_division'];
                $nota_division = isset($item['nota_division']) ? $item['nota_division'] : '';
                $es_rechazado_parcial = strpos($item['codigo_item'], '-R') !== false;
                
                // Mostrar código con indicador si es divisin
                echo '<td>';
                echo esc_html($item['codigo_item']);
                if ($es_division || $nota_division) {
                    echo ' <span style="background: #ff9800; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px; margin-left: 5px;">PARCIAL</span>';
                }
                echo '</td>';
                
                // Producto
                echo '<td>' . ($prod ? esc_html($prod->get_name()) : 'Producto eliminado') . '</td>';
                
                // Cantidad con nota si es división
                echo '<td style="text-align: center;">';
                echo esc_html($item['cantidad']);
                if ($nota_division) {
                    echo '<br><small style="color: #666; font-size: 11px;">' . esc_html($nota_division) . '</small>';
                }
                echo '</td>';
                echo '<td>' . esc_html($item['motivo']) . '</td>';
                echo '<td style="white-space: nowrap;">';
                echo '<span style="background: ' . ($estado_color[$estado_item] ?? '#6c757d') . '; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; display: inline-block;">' . 
                     esc_html($estado_texto[$estado_item] ?? $estado_item) . '</span>';
             
                     // Mostrar información adicional para items divididos
                    if ($es_rechazado_parcial && isset($item['motivo_rechazo'])) {
                        echo '<div style="background: #ffebee; padding: 8px; border-radius: 4px; margin-top: 5px; font-size: 12px;">';
                        echo '<strong style="color: #c62828;">Motivo rechazo parcial:</strong><br>';
                        echo esc_html($item['motivo_rechazo']);
                        echo '</div>';
                    }
                    
                    // Si tiene nota de divisin (item aprobado parcialmente)
                    if (!$es_rechazado_parcial && $nota_division) {
                        echo '<div style="background: #e8f5e9; padding: 8px; border-radius: 4px; margin-top: 5px; font-size: 12px;">';
                        echo '<i class="fas fa-info-circle" style="color: #4caf50;"></i> ';
                        echo esc_html($nota_division);
                        echo '</div>';
                    }
                
                // NUEVO: Mostrar botn Ver historial si hay comunicacin
                $tiene_historial = false;
                
                // Verificar si hay rechazos o apelaciones
                if ((isset($item['motivo_rechazo']) && !empty($item['motivo_rechazo'])) ||
                    (isset($item['historial_rechazos']) && !empty($item['historial_rechazos'])) ||
                    (isset($item['apelacion']) && !empty($item['apelacion']['motivo'])) ||
                    (isset($item['historial_apelaciones']) && !empty($item['historial_apelaciones'])) ||
                    (isset($item['historial_solicitudes']) && !empty($item['historial_solicitudes']))) {
                    $tiene_historial = true;
                }
                
                if ($tiene_historial) {
                    echo ' <span style="background: #17a2b8; color: white; padding: 4px 8px; border-radius: 4px; font-size: 12px; display: inline-block; margin-left: 5px; cursor: pointer;" 
                            class="btn-ver-historial"
                            data-modal="modal-historial-' . $garantia_id . '-' . $index . '"
                            data-overlay="overlay-historial-' . $garantia_id . '-' . $index . '">HISTORIAL</span>';
                }
                
                echo '</td>';
                
                // COLUMNA FOTO
                echo '<td>';
                if (!empty($item['foto_url'])) {
                    echo '<a href="' . esc_url($item['foto_url']) . '" target="_blank">Ver foto</a>';
                }
                echo '</td>';
                
                // COLUMNA VIDEO
                echo '<td>';
                if (!empty($item['video_url'])) {
                    echo '<a href="' . esc_url($item['video_url']) . '" target="_blank">Ver video</a>';
                }
                echo '</td>';
                
                // COLUMNA N° Orden
                echo '<td>' . esc_html($item['order_id']) . '</td>';
                
                // NUEVA COLUMNA: ACCIONES
                echo '<td>';
                
                if ($estado_item === 'esperando_recepcion') {
                    // Mostrar contador y botón para items esperando recepción
                    if (class_exists('WC_Garantias_Recepcion_Parcial_Frontend')) {
                        WC_Garantias_Recepcion_Parcial_Frontend::render_contador_dias_cliente($item, $garantia_id);
                    }
                } elseif ($estado_item === 'solicitar_info') {
                    // Verificar si ya respondió
                    $respondido = false;
                    if (isset($item['historial_solicitudes'])) {
                        $ultima_solicitud = end($item['historial_solicitudes']);
                        $respondido = isset($ultima_solicitud['respondido']) && $ultima_solicitud['respondido'];
                    }
                    
                    if (!$respondido) {
                        echo '<button type="button" class="accion-btn" data-action="info" data-index="' . $index . '" 
                                style="background: #17a2b8; color: white; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer;" 
                                title="Responder solicitud de informacion">
                                <i class="fas fa-info-circle"></i> Responder
                              </button>';
                    } else {
                        echo '<span style="color: #28a745;"><i class="fas fa-check-circle"></i> Respondido</span>';
                    }
                } elseif (($estado_item === 'aprobado_devolver' || $estado_item === 'devolucion_en_transito') && !$is_distribuidor) {
                    // Debug para ver qu etiquetas hay
                    $etiqueta_url = get_post_meta($garantia_id, '_etiqueta_envio_url', true);
                    $etiqueta_devolucion = get_post_meta($garantia_id, '_etiqueta_devolucion_url', true);
                    
                    // Intentar ambas posibles ubicaciones y también Andreani
                    $url_final = $etiqueta_url ?: $etiqueta_devolucion;
                    
                    // Si no hay etiqueta manual, buscar etiqueta de Andreani
                    if (!$url_final) {
                        $url_final = get_post_meta($garantia_id, '_andreani_etiqueta_url', true);
                    }
                    
                    if ($url_final) {
                        // NUEVO: Verificar si este item tiene grupo de etiqueta
                        $grupo_etiqueta = isset($item['etiqueta_grupo_id']) ? $item['etiqueta_grupo_id'] : '';
                        
                        // NUEVO: Contar cuntos items comparten esta etiqueta
                        $items_grupo = 0;
                        if ($grupo_etiqueta && is_array($items)) {
                            foreach ($items as $check_item) {
                                if (isset($check_item['etiqueta_grupo_id']) && 
                                    $check_item['etiqueta_grupo_id'] === $grupo_etiqueta) {
                                    $items_grupo++;
                                }
                            }
                        }
                        
                        // Construir el botn/enlace
                        echo '<a href="' . esc_url($url_final) . '" 
                                class="accion-btn" 
                                data-garantia-id="' . $garantia_id . '"
                                data-item-index="' . $index . '"
                                onclick="marcarComoEnTransito(this); return true;"
                                style="background: #007bff; color: white; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; position: relative;" 
                                title="Descargar etiqueta de envio' . ($items_grupo > 1 ? ' (compartida con ' . $items_grupo . ' items)' : '') . '" 
                                download>
                                <i class="fas fa-shipping-fast"></i> Etiqueta';
                        
                        // NUEVO: Mostrar indicador si hay mltiples items
                        if ($items_grupo > 1) {
                            echo ' <span style="background: #0056b3; padding: 2px 6px; border-radius: 10px; font-size: 11px; margin-left: 4px; vertical-align: super;">' . $items_grupo . '</span>';
                        }
                        
                        echo '</a>';
                    } else {
                        echo '<span style="color: #6c757d;"><i class="fas fa-clock"></i> Esperando etiqueta</span>';
                    }
                    
                    // NUEVO: Agregar botn de seguimiento si hay tracking
                    $tracking_numero = get_post_meta($garantia_id, '_numero_tracking_devolucion', true);
                    if (!empty($tracking_numero)) {
                        echo '<a href="http://andreani.com/envio/' . urlencode($tracking_numero) . '" 
                                target="_blank"
                                style="background: #17a2b8; color: white; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer; text-decoration: none; display: inline-block; margin-left: 5px;" 
                                title="Seguir envio">
                                <i class="fas fa-truck"></i> <span class="btn-text">Seguimiento</span>
                              </a>';
                    }
                    } elseif ($estado_item === 'aprobado_destruir') {
                        echo '<button type="button" class="accion-btn" data-action="destruir" data-index="' . $index . '" 
                                style="background: #dc3545; color: white; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer;" 
                                title="Subir evidencia de destruccion">
                                <i class="fas fa-fire"></i> Destruir
                              </button>';
                    } elseif ($estado_item === 'rechazado') {
                        // Verificar si es rechazo definitivo
                        $es_rechazo_definitivo = isset($item['rechazo_definitivo']) && $item['rechazo_definitivo'];
                        
                        if (!$es_rechazo_definitivo) {
                            echo '<button type="button" class="accion-btn" data-action="apelar" data-index="' . $index . '" 
                                    style="background: #6f42c1; color: white; border: none; padding: 8px 12px; border-radius: 5px; cursor: pointer;" 
                                    title="Apelar decisión">
                                    <i class="fas fa-gavel"></i> Apelar
                                  </button>';
                        } else {
                            echo '<span style="color: #dc3545; font-weight: bold;" title="Esta decisión es definitiva y no puede ser apelada">
                                    <i class="fas fa-ban"></i> No apelable
                                  </span>';
                        }
                    } else {
                        echo '-';
                    }
                    
                    echo '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
            }
            
            // Verificar si el usuario es distribuidor
            $user = wp_get_current_user();
            $user_roles = $user->roles;
            $is_distribuidor = false;
            
            foreach ($user_roles as $role) {
                if (in_array($role, ['distri10', 'distri20', 'distri30', 'superdistri30'])) {
                    $is_distribuidor = true;
                    break;
                }
            }
            
            // Mostrar etiqueta de envio si existe (solo para distribuidores)
            $etiqueta_url = get_post_meta($garantia_id, '_etiqueta_envio_url', true);
            if (empty($etiqueta_url)) {
                $etiqueta_url = get_post_meta($garantia_id, '_etiqueta_devolucion_url', true);
            }
            // NUEVO: También buscar etiqueta de Andreani
            if (empty($etiqueta_url)) {
                $etiqueta_url = get_post_meta($garantia_id, '_andreani_etiqueta_url', true);
            }
            
            // === DEBUG MEJORADO ===
            echo '<!-- Etiqueta envío: ' . get_post_meta($garantia_id, '_etiqueta_envio_url', true) . ' -->';
            echo '<!-- Etiqueta devolución: ' . get_post_meta($garantia_id, '_etiqueta_devolucion_url', true) . ' -->';
            echo '<!-- Etiqueta final: ' . $etiqueta_url . ' -->';
            
            // NUEVO: Mostrar formulario de tracking para devoluciones por error
            $es_devolucion_error = get_post_meta($garantia_id, '_es_devolucion_error', true);
            if ($es_devolucion_error && $estado === 'nueva'):
            ?>
            <div style="background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <h4 style="color: #0c5460; margin-top: 0;">
                    <i class="fas fa-truck"></i> Informacion de Envío
                </h4>
                <p style="color: #0c5460;">
                    Una vez que hayas enviado el paquete, ingresa la información de seguimiento:
                </p>
                
                <form method="post" style="margin-top: 15px;">
                    <input type="hidden" name="garantia_id" value="<?php echo $garantia_id; ?>">
                    <input type="hidden" name="actualizar_tracking_devolucion" value="1">
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">
                                <i class="fas fa-barcode"></i> Nmero de seguimiento: <span style="color: red;">*</span>
                            </label>
                            <input type="text" 
                                   name="numero_seguimiento" 
                                   placeholder="Ej: OCA123456789" 
                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"
                                   required>
                        </div>
                        
                        <div>
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">
                                <i class="fas fa-building"></i> Empresa de transporte: <span style="color: red;">*</span>
                            </label>
                            <select name="empresa_transporte" 
                                    id="empresa_transporte_select"
                                    style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;"
                                    onchange="toggleOtroTransporte(this.value)"
                                    required>
                                <option value="">Seleccionar...</option>
                                <option value="Andreani">Andreani</option>
                                <option value="OCA">OCA</option>
                                <option value="Correo Argentino">Correo Argentino</option>
                                <option value="Via Cargo">Via Cargo</option>
                                <option value="Otro">Otro</option>
                            </select>
                        </div>
                    </div>
                    
                    <!-- Campo adicional para "Otro" -->
                    <div id="otro_transporte_container" style="display: none; margin-bottom: 15px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: bold;">
                            <i class="fas fa-pencil-alt"></i> Especifica la empresa:
                        </label>
                        <input type="text" 
                               name="empresa_transporte_otro" 
                               id="empresa_transporte_otro"
                               placeholder="Nombre de la empresa de transporte" 
                               style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
                    </div>
                    
                    <button type="submit" 
                            style="background: #17a2b8; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer;">
                        <i class="fas fa-save"></i> Guardar Información
                    </button>
                </form>
                
                <script>
                function toggleOtroTransporte(valor) {
                    var container = document.getElementById('otro_transporte_container');
                    var input = document.getElementById('empresa_transporte_otro');
                    
                    if (valor === 'Otro') {
                        container.style.display = 'block';
                        input.setAttribute('required', 'required');
                    } else {
                        container.style.display = 'none';
                        input.removeAttribute('required');
                        input.value = '';
                    }
                }
                </script>
            <?php 
            elseif ($es_devolucion_error && $estado === 'devolucion_en_transito'):
                $tracking = get_post_meta($garantia_id, '_numero_seguimiento_devolucion', true);
                $empresa = get_post_meta($garantia_id, '_empresa_transporte_devolucion', true);
            ?>
            <div style="background: #cce5ff; border: 1px solid #b8daff; border-radius: 8px; padding: 20px; margin: 20px 0;">
                <h4 style="color: #004085; margin-top: 0;">
                    <i class="fas fa-shipping-fast"></i> Producto en Trnsito
                </h4>
                <div style="background: white; padding: 15px; border-radius: 5px;">
                    <p style="margin: 0 0 10px 0;"><strong>Nmero de seguimiento:</strong> <?php echo esc_html($tracking); ?></p>
                    <p style="margin: 0;"><strong>Empresa:</strong> <?php echo esc_html($empresa); ?></p>
                </div>
                <p style="margin: 15px 0 0 0; color: #004085;">
                    Estamos esperando recibir tu paquete. Te notificaremos cuando llegue y procesemos tu devolución.
                </p>
            </div>
            <?php endif; ?>
            
            <?php
            // Verificar si hay items pendientes de envo
            $hay_items_pendientes_envio = false;
            if (is_array($items)) {
                foreach ($items as $item) {
                    $estado_item = isset($item['estado']) ? $item['estado'] : 'Pendiente';
                    echo '<!-- Item estado: ' . $estado_item . ' -->';
                    // Para distribuidores: mostrar si hay items Pendientes, aprobado_devolver o devolucion_en_transito
                    if ($estado_item === 'Pendiente' || !isset($item['estado']) || 
                        $estado_item === 'devolucion_en_transito' || 
                        $estado_item === 'aprobado_devolver') {
                        $hay_items_pendientes_envio = true;
                        break;
                    }
                }
            }
            echo '<!-- Hay items pendientes: ' . ($hay_items_pendientes_envio ? 'S' : 'NO') . ' -->';
            echo '<!-- Condicin final: ' . (($is_distribuidor && $etiqueta_url && $hay_items_pendientes_envio) ? 'VERDADERO' : 'FALSO') . ' -->';
            
            if ($is_distribuidor && $etiqueta_url && $hay_items_pendientes_envio): ?>
                <div style="background: #d4edda; border: 1px solid #c3e6cb; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h4 style="color: #155724; margin-top: 0;">
                        <i class="fas fa-shipping-fast"></i> Etiqueta de envio Disponible - Enviar por ANDREANI
                    </h4>
                    <p style="color: #155724;">Tu etiqueta de envio está lista. Descárgala e imprímela para enviar el producto.</p>
                    
                    <div style="margin-bottom: 15px;">
                        <a href="<?php echo esc_url($etiqueta_url); ?>" 
                               class="button btn-descargar-etiqueta-distri" 
                               style="background: #28a745; color: white; padding: 10px 20px; margin-right: 10px;"
                               data-garantia-id="<?php echo $garantia_id; ?>"
                               onclick="marcarComoEnTransitoDistribuidor(this); return true;"
                               download
                               target="_blank">
                                <i class="fas fa-download"></i> Descargar Etiqueta PDF
                            </a>
                        
                        <?php 
                        // Mostrar botn de tracking si existe el nmero
                        $tracking_numero = get_post_meta($garantia_id, '_numero_tracking_devolucion', true);
                        if (!empty($tracking_numero)): 
                        ?>
                        <a href="http://andreani.com/envio/<?php echo urlencode($tracking_numero); ?>" 
                           target="_blank" 
                           class="button" 
                           style="background: #17a2b8; color: white; padding: 10px 20px;"
                           title="Seguir envío">
                            <i class="fas fa-truck"></i> Seguimiento: <?php echo esc_html($tracking_numero); ?>
                        </a>
                        <?php endif; ?>
                    </div>
                    
                <p style="margin-top: 15px; font-size: 14px; color: #666;">
                    <strong>Instrucciones:</strong><br>
                    1. Descarga e imprime la etiqueta.<br>
                    2. Pgala en el paquete de forma visible.<br>
                    3. Enva el paquete lo antes posible.<br>
                    4. Guarda el comprobante de envo.<br>
                    5. Lleva el paquete a una sucursal de Andreani para enviarlo.
                </p>
            </div>
        <?php endif; ?>
        
        <?php 
        // Mostrar formulario de tracking si est pendiente de devolucin
            if ($estado === 'aprobado_devolver') {
                // Obtener direccin de devolucin
                $direccion_devolucion = get_option('direccion_devolucion_garantias', 'Direccin no configurada');
                
                echo '<div style="background: #d1ecf1; border: 1px solid #bee5eb; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h4 style="color: #0c5460; margin-top: 0;">
                        <i class="fas fa-shipping-fast"></i> Devolucin Aprobada - Accion Requerida
                    </h4>
                    <p style="color: #0c5460;">
                        Tu garantia ha sido aprobada. Por favor enva el producto a nuestra direccin y sube la informacion de tracking.
                    </p>
                    
                    <div style="background: white; padding: 15px; border-radius: 5px; margin: 15px 0;">
                        <strong><i class="fas fa-map-marker-alt"></i> Direccin de envio:</strong><br>
                        <div style="margin-top: 10px; padding: 10px; background: #f8f9fa; border-left: 4px solid #17a2b8;">
                            ' . nl2br(esc_html($direccion_devolucion)) . '
                        </div>
                    </div>
                    
                    <form method="post" enctype="multipart/form-data" style="margin-top: 20px;">
                        <input type="hidden" name="garantia_id" value="' . $garantia_id . '">
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">
                                <i class="fas fa-barcode"></i> Nmero de tracking/gua: <span style="color: red;">*</span>
                            </label>
                            <input type="text" 
                                   name="numero_tracking" 
                                   placeholder="Ej: OCA123456789" 
                                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 14px;"
                                   required>
                        </div>
                        
                        <div style="margin-bottom: 15px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: bold;">
                                <i class="fas fa-camera"></i> Foto de la gua de envio: <span style="color: red;">*</span>
                            </label>
                            <input type="file" 
                                   name="foto_tracking" 
                                   accept="image/*" 
                                   required
                                   style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                            <small style="color: #666;">Sube una foto clara de la gua o comprobante de envio</small>
                        </div>
                        
                        <button type="submit" 
                                name="subir_tracking" 
                                class="button" 
                                style="background: #17a2b8; color: white; padding: 10px 20px; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;">
                            <i class="fas fa-upload"></i> Subir Informacin de Envio
                        </button>
                    </form>
                </div>';
                
            } elseif ($estado === 'devolucion_en_transito') {
                $tracking = get_post_meta($garantia_id, '_tracking_devolucion', true);
                $fecha_tracking = get_post_meta($garantia_id, '_fecha_tracking', true);
                $foto_tracking_url = get_post_meta($garantia_id, '_foto_tracking_url', true);
                
                echo '<div style="background: #cce5ff; border: 1px solid #b8daff; border-radius: 8px; padding: 20px; margin: 20px 0;">
                    <h4 style="color: #004085; margin-top: 0;">
                        <i class="fas fa-truck"></i> Producto en Transito
                    </h4>
                    <p style="color: #004085;">
                        Hemos recibido tu informacion de envio. Estamos esperando recibir el producto.
                        Te notificaremos cuando lo recibamos y generemos tu cupon.
                    </p>';
                    
                if ($tracking) {
                    echo '<div style="background: white; padding: 15px; border-radius: 5px; margin-top: 15px;">
                        <p style="margin: 0 0 10px 0;"><strong>Número de tracking:</strong> <code style="background: #f8f9fa; padding: 2px 6px; border-radius: 3px;">' . esc_html($tracking) . '</code></p>';
                    
                    if ($fecha_tracking) {
                        echo '<p style="margin: 0;"><strong>Fecha de envío:</strong> ' . date('d/m/Y H:i', strtotime($fecha_tracking)) . '</p>';
                    }
                    
                    if ($foto_tracking_url) {
                        echo '<p style="margin: 10px 0 0 0;"><a href="' . esc_url($foto_tracking_url) . '" target="_blank" style="color: #17a2b8;"><i class="fas fa-image"></i> Ver comprobante de envio</a></p>';
                    }
                    
                    echo '</div>';
                }
                
                echo '</div>';
            }
        
        // Mostrar formulario de destruccion si est pendiente
if ($estado === 'aprobado_destruir') {
    echo '<div style="max-width: 600px; margin: 20px auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); overflow: hidden;">
        <div style="background: #dc3545; color: white; padding: 16px 20px;">
            <h5 style="margin: 0; font-size: 18px; display: flex; align-items: center;">
                <i class="fas fa-fire" style="margin-right: 10px;"></i> Destruccion Requerida
            </h5>
        </div>';
    
    // Mostrar si hubo rechazo previo
    $motivo_rechazo = get_post_meta($garantia_id, '_motivo_rechazo_destruccion', true);
    if (isset($_POST['rechazar_destruccion'])) {
    update_post_meta($garantia_id, '_motivo_rechazo_destruccion', $motivo);
    garantias_set_mensaje('La evidencia de destruccion ha sido rechazada: ' . $motivo, 'error');
    wp_redirect(...);
}
    
    echo '<div style="padding: 20px;">
    <p style="margin: 0 0 16px 0; color: #666; font-size: 14px; line-height: 1.5;">
        Debes destruir el producto y subir evidencia fotogrfica
    </p>
    
    <div style="background: #f8f9fa; border-radius: 8px; padding: 16px; margin-bottom: 20px;">
        <h6 style="margin: 0 0 8px 0; font-size: 14px; color: #333;">Instrucciones:</h6>
        <div style="font-size: 13px; color: #666; line-height: 1.8;">' . 
        nl2br(esc_html(get_option('instrucciones_destruccion', "1. Destruye completamente el producto defectuoso\n2. Asegrate de que no pueda ser reutilizado\n3. Toma fotos/video claros de la destruccion\n4. Conserva la evidencia por 30 das"))) . 
        '</div>
    </div>

        
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="garantia_id" value="' . $garantia_id . '">
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 16px;">
                <div>
                    <label style="display: block; font-size: 13px; color: #333; margin-bottom: 6px; font-weight: 500;">
                        <i class="fas fa-camera"></i> Foto <span style="color: red;">*</span>
                    </label>
                    <input type="file" 
                           name="foto_destruccion" 
                           accept="image/*" 
                           required
                           style="width: 100%; font-size: 13px;">
                </div>
                
                <div>
                    <label style="display: block; font-size: 13px; color: #333; margin-bottom: 6px; font-weight: 500;">
                        <i class="fas fa-video"></i> Video <small style="color: #999;">(opcional)</small>
                    </label>
                    <input type="file" 
                           name="video_destruccion" 
                           accept="video/*"
                           style="width: 100%; font-size: 13px;">
                </div>
            </div>
            
            <div style="background: #ffeaa7; border-radius: 6px; padding: 12px; margin-bottom: 16px;">
                <label style="display: flex; align-items: center; margin: 0; cursor: pointer; font-size: 13px;">
                    <input type="checkbox" name="confirmo_destruccion" required style="margin-right: 8px;">
                    <span style="color: #333;">Confirmo que he destruido completamente el producto</span>
                </label>
            </div>
            
            <button type="submit" 
                    name="subir_destruccion" 
                    style="width: 100%; padding: 12px; background: #28a745; color: white; border: none; border-radius: 6px; font-size: 15px; font-weight: 500; cursor: pointer; transition: all 0.3s;">
                <i class="fas fa-upload"></i> Subir Evidencia de Destrucción
            </button>
        </form>
    </div>
    </div>';
}
        ?>
        <?php
        // Agregar scripts de recepcin parcial AQU
        if (class_exists('WC_Garantias_Recepcion_Parcial_Frontend')) {
            WC_Garantias_Recepcion_Parcial_Frontend::agregar_scripts_frontend();
        }
        ?>
        
        <!-- MODALES PARA ACCIONES -->
<div id="modal-overlay" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9998;"></div>

<!-- Modal informacion Adicional -->
<div id="modal-info" class="modal-accion" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 10px; box-shadow: 0 5px 30px rgba(0,0,0,0.3); z-index: 9999; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
    <div style="background: #17a2b8; color: white; padding: 20px; border-radius: 10px 10px 0 0;">
        <h3 style="margin: 0;">Informacion Adicional Requerida</h3>
        <button type="button" class="close-modal" style="position: absolute; top: 20px; right: 20px; background: none; border: none; color: white; font-size: 24px; cursor: pointer;"></button>
    </div>
    <div style="padding: 20px;">
        <div id="mensaje-admin" style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px;"></div>
        <form method="post" enctype="multipart/form-data" id="form-info-adicional">
            <input type="hidden" name="garantia_id" value="<?php echo $garantia_id; ?>">
            <input type="hidden" name="responder_info_item" value="1">
            <input type="hidden" name="item_index" id="info-item-index" value="">
            
            <div id="campos-archivos"></div>
            
            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold;">Comentarios adicionales:</label>
                <textarea name="comentario_respuesta" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; height: 100px;"></textarea>
            </div>
            
            <button type="submit" style="background: #17a2b8; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
                <i class="fas fa-paper-plane"></i> Enviar Informacion
            </button>
        </form>
    </div>
</div>

<!-- Modal Destruccion -->
<div id="modal-destruir" class="modal-accion" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 10px; box-shadow: 0 5px 30px rgba(0,0,0,0.3); z-index: 9999; max-width: 500px; width: 90%;">
    <div style="background: #dc3545; color: white; padding: 20px; border-radius: 10px 10px 0 0;">
        <h3 style="margin: 0;">Evidencia de Destrucción</h3>
        <button type="button" class="close-modal" style="position: absolute; top: 20px; right: 20px; background: none; border: none; color: white; font-size: 24px; cursor: pointer;">×</button>
    </div>
    <div style="padding: 20px;">
        <p>Debes destruir completamente el producto y subir evidencia fotogrfica/video.</p>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="garantia_id" value="<?php echo $garantia_id; ?>">
            <input type="hidden" name="destruir_item" value="1">
            <input type="hidden" name="item_index" id="destruir-item-index" value="">
            
            <div style="margin-bottom: 15px;">
                <label><i class="fas fa-camera"></i> Foto de destruccion: <span style="color: red;">*</span></label>
                <input type="file" name="foto_destruccion" accept="image/*" required>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label><i class="fas fa-video"></i> Video (opcional):</label>
                <input type="file" name="video_destruccion" accept="video/*">
            </div>
            
            <label style="background: #ffeaa7; padding: 10px; border-radius: 5px; display: block; margin-bottom: 15px;">
                <input type="checkbox" name="confirmo_destruccion" required>
                Confirmo que he destruido completamente el producto
            </label>
            
            <button type="submit" style="background: #dc3545; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
                <i class="fas fa-upload"></i> Subir Evidencia
            </button>
        </form>
    </div>
</div>

<!-- Modal Apelacin -->
<div id="modal-apelar" class="modal-accion" style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); background: white; border-radius: 10px; box-shadow: 0 5px 30px rgba(0,0,0,0.3); z-index: 9999; max-width: 500px; width: 90%;">
    <div style="background: #6f42c1; color: white; padding: 20px; border-radius: 10px 10px 0 0;">
        <h3 style="margin: 0;">Apelar Decisin</h3>
        <button type="button" class="close-modal" style="position: absolute; top: 20px; right: 20px; background: none; border: none; color: white; font-size: 24px; cursor: pointer;"></button>
    </div>
    <div style="padding: 20px;">
        <div id="motivo-rechazo" style="background: #f8d7da; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <strong>Motivo del rechazo:</strong>
            <p id="texto-motivo-rechazo"></p>
        </div>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="garantia_id" value="<?php echo $garantia_id; ?>">
            <input type="hidden" name="apelar_item" value="1">
            <input type="hidden" name="item_index" id="apelar-item-index" value="">
            
            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold;">Explica por qu debe ser garantia:</label>
                <textarea name="razon_apelacion" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; height: 120px;" placeholder="Proporciona informacion adicional que respalde tu reclamo..."></textarea>
            </div>
            
            <div style="margin-bottom: 15px;">
                <label><i class="fas fa-camera"></i> Nueva foto (opcional):</label>
                <input type="file" name="foto_apelacion" accept="image/*">
            </div>
            
            <div style="margin-bottom: 15px;">
                <label><i class="fas fa-video"></i> Nuevo video (opcional):</label>
                <input type="file" name="video_apelacion" accept="video/*">
            </div>
            
            <button type="submit" style="background: #6f42c1; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
                <i class="fas fa-paper-plane"></i> Enviar Apelacion
            </button>
        </form>
    </div>
</div>
    <!-- MODALES DE HISTORIAL PARA CLIENTE -->
<?php if (isset($garantia_id) && isset($items) && is_array($items)): ?>
    <?php foreach ($items as $index => $item): ?>
        <?php 
        $tiene_historial = (
            (isset($item['motivo_rechazo']) && !empty($item['motivo_rechazo'])) ||
            (isset($item['historial_rechazos']) && !empty($item['historial_rechazos'])) ||
            (isset($item['apelacion']) && !empty($item['apelacion']['motivo'])) ||
            (isset($item['historial_apelaciones']) && !empty($item['historial_apelaciones'])) ||
            (isset($item['historial_solicitudes']) && !empty($item['historial_solicitudes']))
        );
        
        if ($tiene_historial): ?>
            <!-- Modal Historial -->
            <div id="modal-historial-<?php echo $garantia_id; ?>-<?php echo $index; ?>" 
                 style="display: none; position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%); 
                        background: white; border-radius: 10px; box-shadow: 0 5px 30px rgba(0,0,0,0.3); 
                        z-index: 9999; max-width: 800px; width: 90%; max-height: 85vh; overflow: hidden;">
                
                <!-- Header -->
                <div style="background: #17a2b8; color: white; padding: 20px; border-radius: 10px 10px 0 0;">
                    <h3 style="margin: 0; color: white;">
                        <i class="fas fa-history"></i> Historial - <?php echo esc_html($item['codigo_item']); ?>
                    </h3>
                    <button type="button" class="close-modal-historial" 
                            style="position: absolute; top: 20px; right: 20px; background: none; border: none; color: white; font-size: 24px; cursor: pointer;">
                        
                    </button>
                </div>
                
                <!-- Body -->
                <div style="padding: 20px; max-height: calc(85vh - 140px); overflow-y: auto; background: #f8f9fa;">
                    <?php
                    // Crear array de eventos
                    $eventos = [];
                    
                    // Reclamo original
                    $eventos[] = [
                        'tipo' => 'reclamo_original',
                        'fecha' => get_post_meta($garantia_id, '_fecha', true),
                        'datos' => [
                            'motivo' => $item['motivo'] ?? '',
                            'foto_url' => $item['foto_url'] ?? '',
                            'video_url' => $item['video_url'] ?? ''
                        ]
                    ];
                    
                    // Historial de rechazos
                    if (isset($item['historial_rechazos']) && is_array($item['historial_rechazos'])) {
                        foreach ($item['historial_rechazos'] as $rechazo) {
                            $eventos[] = [
                                'tipo' => 'rechazo',
                                'fecha' => $rechazo['fecha'],
                                'datos' => $rechazo
                            ];
                        }
                    } elseif (isset($item['motivo_rechazo']) && !empty($item['motivo_rechazo'])) {
                        // Compatibilidad con formato antiguo
                        $eventos[] = [
                            'tipo' => 'rechazo',
                            'fecha' => $item['fecha_rechazo'] ?? current_time('mysql'),
                            'datos' => ['motivo' => $item['motivo_rechazo']]
                        ];
                    }
                    
                    // Historial de apelaciones
                    if (isset($item['historial_apelaciones']) && is_array($item['historial_apelaciones'])) {
                        foreach ($item['historial_apelaciones'] as $apelacion) {
                            $eventos[] = [
                                'tipo' => 'apelacion',
                                'fecha' => $apelacion['fecha'],
                                'datos' => $apelacion
                            ];
                        }
                    } elseif (isset($item['apelacion']) && !empty($item['apelacion']['motivo'])) {
                        // Compatibilidad con formato antiguo
                        $eventos[] = [
                            'tipo' => 'apelacion',
                            'fecha' => $item['apelacion']['fecha'],
                            'datos' => $item['apelacion']
                        ];
                    }
                    
                    // Solicitudes de informacion
                    if (isset($item['historial_solicitudes']) && is_array($item['historial_solicitudes'])) {
                        foreach ($item['historial_solicitudes'] as $solicitud) {
                            $eventos[] = [
                                'tipo' => 'solicitud_info',
                                'fecha' => $solicitud['fecha'],
                                'datos' => $solicitud
                            ];
                            
                            // Si hay respuesta, agregarla tambin
                            if (isset($solicitud['respondido']) && $solicitud['respondido'] && isset($solicitud['fecha_respuesta'])) {
                                $eventos[] = [
                                    'tipo' => 'respuesta_info',
                                    'fecha' => $solicitud['fecha_respuesta'],
                                    'datos' => $solicitud
                                ];
                            }
                        }
                    }
                    
                    // Ordenar por fecha
                    usort($eventos, function($a, $b) {
                        return strtotime($a['fecha']) - strtotime($b['fecha']);
                    });
                    
                    // Mostrar timeline
                    foreach ($eventos as $evento): ?>
                        <?php if ($evento['tipo'] === 'reclamo_original'): ?>
                            <div style="margin-bottom: 20px;">
                                <div style="display: flex; gap: 15px;">
                                    <div style="background: #007bff; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e0e0e0;">
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                                <strong style="color: #007bff;">Tu reclamo original</strong>
                                                <small style="color: #666;"><?php echo date('d/m/Y H:i', strtotime($evento['fecha'])); ?></small>
                                            </div>
                                            <p style="margin-bottom: 10px;"><?php echo esc_html($evento['datos']['motivo']); ?></p>
                                            <?php if (!empty($evento['datos']['foto_url']) || !empty($evento['datos']['video_url'])): ?>
                                                <div style="margin-top: 10px;">
                                                    <?php if (!empty($evento['datos']['foto_url'])): ?>
                                                        <a href="<?php echo esc_url($evento['datos']['foto_url']); ?>" target="_blank" 
                                                           style="padding: 5px 10px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 12px;">
                                                            <i class="fas fa-image"></i> Ver foto
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (!empty($evento['datos']['video_url'])): ?>
                                                        <a href="<?php echo esc_url($evento['datos']['video_url']); ?>" target="_blank" 
                                                           style="padding: 5px 10px; background: #dc3545; color: white; text-decoration: none; border-radius: 4px; font-size: 12px; margin-left: 5px;">
                                                            <i class="fas fa-video"></i> Ver video
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        <?php elseif ($evento['tipo'] === 'rechazo'): ?>
                            <!-- Rechazo -->
                            <div style="margin-bottom: 20px;">
                                <div style="display: flex; gap: 15px;">
                                    <div style="background: #dc3545; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-store"></i>
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e0e0e0; border-left: 3px solid #dc3545;">
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                                <strong style="color: #dc3545;">
                                                    WiFix - Rechazo
                                                    <?php if (isset($evento['datos']['definitivo']) && $evento['datos']['definitivo']): ?>
                                                        <span style="background: #dc3545; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-left: 10px;">
                                                            DEFINITIVO
                                                        </span>
                                                    <?php endif; ?>
                                                </strong>
                                                <small style="color: #666;"><?php echo date('d/m/Y H:i', strtotime($evento['fecha'])); ?></small>
                                            </div>
                                            <p style="margin: 0;"><?php echo nl2br(esc_html($evento['datos']['motivo'])); ?></p>
                                            
                                            <?php 
                                            // Mostrar evidencia si existe
                                            if (isset($evento['datos']['evidencia']) && 
                                                (!empty($evento['datos']['evidencia']['fotos']) || !empty($evento['datos']['evidencia']['video']))): 
                                            ?>
                                                <div style="margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0;">
                                                    <p style="margin: 0 0 10px 0; font-weight: 500; color: #dc3545;">
                                                        <i class="fas fa-paperclip"></i> Evidencia adjunta:
                                                    </p>
                                                    
                                                    <?php if (!empty($evento['datos']['evidencia']['fotos'])): ?>
                                                        <div style="margin-bottom: 10px;">
                                                            <strong>Fotos:</strong>
                                                            <?php foreach ($evento['datos']['evidencia']['fotos'] as $i => $foto): ?>
                                                                <a href="<?php echo esc_url($foto); ?>" target="_blank" 
                                                                   style="display: inline-block; margin: 5px; padding: 5px 10px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 12px;">
                                                                    <i class="fas fa-image"></i> Foto <?php echo $i + 1; ?>
                                                                </a>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (!empty($evento['datos']['evidencia']['video'])): ?>
                                                        <div>
                                                            <strong>Video:</strong>
                                                            <a href="<?php echo esc_url($evento['datos']['evidencia']['video']); ?>" target="_blank" 
                                                               style="display: inline-block; margin: 5px; padding: 5px 10px; background: #dc3545; color: white; text-decoration: none; border-radius: 4px; font-size: 12px;">
                                                                <i class="fas fa-video"></i> Ver video
                                                            </a>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        <?php elseif ($evento['tipo'] === 'apelacion'): ?>
                            <div style="margin-bottom: 20px;">
                                <div style="display: flex; gap: 15px;">
                                    <div style="background: #17a2b8; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e0e0e0; border-left: 3px solid #17a2b8;">
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                                <strong style="color: #17a2b8;">Tu apelación</strong>
                                                <small style="color: #666;"><?php echo date('d/m/Y H:i', strtotime($evento['fecha'])); ?></small>
                                            </div>
                                            <p style="margin-bottom: 10px;"><?php echo nl2br(esc_html($evento['datos']['motivo'])); ?></p>
                                            <?php if (!empty($evento['datos']['foto_url']) || !empty($evento['datos']['video_url'])): ?>
                                                <div style="margin-top: 10px;">
                                                    <?php if (!empty($evento['datos']['foto_url'])): ?>
                                                        <a href="<?php echo esc_url($evento['datos']['foto_url']); ?>" target="_blank" 
                                                           style="padding: 5px 10px; background: #007bff; color: white; text-decoration: none; border-radius: 4px; font-size: 12px;">
                                                            <i class="fas fa-image"></i> Ver foto
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (!empty($evento['datos']['video_url'])): ?>
                                                        <a href="<?php echo esc_url($evento['datos']['video_url']); ?>" target="_blank" 
                                                           style="padding: 5px 10px; background: #dc3545; color: white; text-decoration: none; border-radius: 4px; font-size: 12px; margin-left: 5px;">
                                                            <i class="fas fa-video"></i> Ver video
                                                        </a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        <?php elseif ($evento['tipo'] === 'solicitud_info'): ?>
                            <div style="margin-bottom: 20px;">
                                <div style="display: flex; gap: 15px;">
                                    <div style="background: #ffc107; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-store"></i>
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e0e0e0; border-left: 3px solid #ffc107;">
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                                <strong style="color: #ffc107;">WiFix - Solicitud de informacion</strong>
                                                <small style="color: #666;"><?php echo date('d/m/Y H:i', strtotime($evento['fecha'])); ?></small>
                                            </div>
                                            <p style="margin: 0;"><?php echo nl2br(esc_html($evento['datos']['mensaje'])); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                        <?php elseif ($evento['tipo'] === 'respuesta_info'): ?>
                            <div style="margin-bottom: 20px;">
                                <div style="display: flex; gap: 15px;">
                                    <div style="background: #28a745; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                                        <i class="fas fa-user"></i>
                                    </div>
                                    <div style="flex: 1;">
                                        <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e0e0e0; border-left: 3px solid #28a745;">
                                            <div style="display: flex; justify-content: space-between; margin-bottom: 10px;">
                                                <strong style="color: #28a745;">Tu respuesta</strong>
                                                <small style="color: #666;"><?php echo date('d/m/Y H:i', strtotime($evento['fecha'])); ?></small>
                                            </div>
                                            <p style="margin: 0;"><?php echo nl2br(esc_html($evento['datos']['comentario'] ?? '')); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
                
                <!-- Footer -->
                <div style="padding: 15px 20px; border-top: 1px solid #dee2e6; text-align: right; background: white;">
                    <button type="button" class="close-modal-historial"
                            style="padding: 8px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">
                        Cerrar
                    </button>
                </div>
            </div>
            
            <!-- Overlay -->
            <div id="overlay-historial-<?php echo $garantia_id; ?>-<?php echo $index; ?>"
                 style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; 
                        background: rgba(0,0,0,0.5); z-index: 9998;">
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
<?php endif; ?>
<script>
// Función GLOBAL para marcar como en tránsito
function marcarComoEnTransito(elemento) {
    var garantiaId = jQuery(elemento).data('garantia-id') || jQuery(elemento).attr('data-garantia-id');
    var itemIndex = jQuery(elemento).data('item-index') || jQuery(elemento).attr('data-item-index');

    if (!garantiaId || itemIndex === undefined) {
        alert('Error: Faltan datos necesarios');
        return false;
    }
    
    jQuery(elemento).html('<i class="fas fa-spinner fa-spin"></i> Procesando...');
    jQuery(elemento).prop('disabled', true);
    
    jQuery.ajax({
        url: '<?php echo admin_url("admin-ajax.php"); ?>',
        type: 'POST',
        data: {
            action: 'actualizar_estado_transito_grupo',
            garantia_id: garantiaId,
            item_index: itemIndex,
            nonce: '<?php echo wp_create_nonce("actualizar_transito"); ?>'
        },
        success: function(response) {
            if (response.success) {
                var mensaje = 'Estado actualizado correctamente';
                if (response.data && response.data.items_actualizados) {
                    mensaje = response.data.items_actualizados + ' item(s) marcados como "Enviado a WiFix"';
                }
                alert(mensaje);
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                alert('Error: ' + (response.data || 'Error desconocido'));
                jQuery(elemento).html('<i class="fas fa-shipping-fast"></i> Etiqueta');
                jQuery(elemento).prop('disabled', false);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', status, error);
            alert('Error al procesar la solicitud');
            jQuery(elemento).html('<i class="fas fa-shipping-fast"></i> Etiqueta');
            jQuery(elemento).prop('disabled', false);
        }
    });
    
    return false;
}
// Función específica para distribuidores
function marcarComoEnTransitoDistribuidor(elemento) {
    var garantiaId = jQuery(elemento).attr('data-garantia-id');
    
    if (!garantiaId) {
        console.error('No se encontró ID de garantía');
        return false;
    }
    
    console.log('Actualizando garantía ID:', garantiaId);
    console.log('Actualizando items Pendientes y aprobado_devolver a devolucion_en_transito');
    
    // Actualizar items pendientes O aprobado_devolver a "devolucion_en_transito"
    jQuery.ajax({
        url: '<?php echo admin_url("admin-ajax.php"); ?>',
        type: 'POST',
        data: {
            action: 'actualizar_items_devolver_transito',
            garantia_id: garantiaId,
            nonce: '<?php echo wp_create_nonce("actualizar_transito"); ?>'
        },
        success: function(response) {
            console.log('Respuesta completa:', response);
            if (response.success) {
                var mensaje = 'Items actualizados a "Enviado a WiFix"';
                if (response.data && response.data.items_actualizados) {
                    mensaje = response.data.items_actualizados + ' item(s) actualizados a "Enviado a WiFix"';
                    console.log('Items actualizados:', response.data.items_actualizados);
                }
                alert(mensaje);
                setTimeout(function() {
                    location.reload();
                }, 1500);
            } else {
                console.error('Error:', response.data);
                var mensajeError = 'No hay items pendientes para actualizar';
                if (response.data && response.data.message) {
                    mensajeError = response.data.message;
                }
                alert('Error: ' + mensajeError);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error AJAX:', status, error);
            console.error('Respuesta completa:', xhr.responseText);
            alert('Error al procesar la solicitud: ' + error);
        }
    });
    
    return true;
}

// Para distribuidores - manejar clic en etiqueta general  
jQuery(document).on('click', '.btn-descargar-etiqueta-distri', function(e) {
    // La función ya se llama desde onclick del HTML
});

jQuery(document).ready(function($) {
    var itemsData = <?php echo json_encode($items); ?>;
    
    // Funcin para abrir modal
    function openModal(modalId) {
        $('#modal-overlay').fadeIn(300);
        $('#' + modalId).fadeIn(300);
    }
    
    // Función para cerrar modal
    function closeModal() {
        $('.modal-accion').fadeOut(300);
        $('#modal-overlay').fadeOut(300);
    }
    
    // Cerrar modal con X o overlay
    $('.close-modal, #modal-overlay').on('click', closeModal);
    
    // Botn Apelar
    $('[data-action="apelar"]').on('click', function() {
        var index = $(this).data('index');
        var item = itemsData[index];
        
        if (item.motivo_rechazo) {
            $('#texto-motivo-rechazo').text(item.motivo_rechazo);
        } else {
            $('#texto-motivo-rechazo').text('No especificado');
        }
        
        $('#apelar-item-index').val(index);
        openModal('modal-apelar');
    });
    
    // Botn Info Adicional
    $('[data-action="info"]').on('click', function() {
        var index = $(this).data('index');
        var item = itemsData[index];
        
        if (item.historial_solicitudes && item.historial_solicitudes.length > 0) {
            var solicitud = item.historial_solicitudes[item.historial_solicitudes.length - 1];
            $('#mensaje-admin').html('<strong>Mensaje del administrador:</strong><br>' + solicitud.mensaje);
            
            var camposHtml = '';
            if (solicitud.solicitar_fotos) {
                camposHtml += '<div style="margin-bottom: 15px;"><label><i class="fas fa-camera"></i> Fotos adicionales:</label><input type="file" name="fotos_adicionales[]" accept="image/*" multiple required></div>';
            }
            if (solicitud.solicitar_videos) {
                camposHtml += '<div style="margin-bottom: 15px;"><label><i class="fas fa-video"></i> Videos adicionales:</label><input type="file" name="videos_adicionales[]" accept="video/*" multiple required></div>';
            }
            $('#campos-archivos').html(camposHtml);
        }
        
        $('#info-item-index').val(index);
        openModal('modal-info');
    });
    
    // Botn Destruir
    $('[data-action="destruir"]').on('click', function() {
        var index = $(this).data('index');
        $('#destruir-item-index').val(index);
        openModal('modal-destruir');
    });
    
    // Eventos para historial
    $(document).on('click', '.btn-ver-historial', function(e) {
        e.preventDefault();
        var modalId = $(this).attr('data-modal');
        var overlayId = $(this).attr('data-overlay');
        
        var modalElement = document.getElementById(modalId);
        var overlayElement = document.getElementById(overlayId);
        
        if (modalElement && overlayElement) {
            modalElement.style.display = 'block';
            overlayElement.style.display = 'block';
        }
    });
    
    // Cerrar modales de historial
    $(document).on('click', '.close-modal-historial', function() {
        $(this).closest('[id^="modal-historial-"]').fadeOut(300);
        $('[id^="overlay-historial-"]').fadeOut(300);
    });
    
    $(document).on('click', '[id^="overlay-historial-"]', function() {
        $(this).fadeOut(300);
        $('[id^="modal-historial-"]').fadeOut(300);
    });
});
</script>

<style>
.accion-btn {
    transition: all 0.3s ease;
}
.accion-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
}
.modal-accion {
    animation: modalIn 0.3s ease;
}
@keyframes modalIn {
    from {
        opacity: 0;
        transform: translate(-50%, -60%);
    }
    to {
        opacity: 1;
        transform: translate(-50%, -50%);
    }
}
</style>

        <p><a href="<?php echo esc_url(wc_get_account_endpoint_url('garantias')); ?>" class="button">&larr; Volver al listado</a></p>
    <?php } else {
        echo '<div class="woocommerce-error">No se encontr el reclamo o no tienes permiso para verlo.</div>';
    }
    return;
}

// ============ LGICA PARA FORMULARIO Y ENVIO DE RECLAMOS ============

// Recopilar productos comprados por el cliente, filtrando por duracin de garantía
$duracion_garantia = get_option('duracion_garantia', 180);
$fecha_limite = strtotime("-{$duracion_garantia} days");

// Obtener todos los estados que indican entrega
$estados_base = ['completed', 'delivered', 'shipped', 'entregado', 'despachado', 'finalizado'];

// Crear array con y sin prefijo wc-
$estados_validos = [];
foreach ($estados_base as $estado) {
    $estados_validos[] = $estado;
    $estados_validos[] = 'wc-' . $estado;  // Agregar versión con prefijo
}

// Obtener pedidos con cualquiera de estos estados
$orders = wc_get_orders([
    'customer_id' => $customer_id,
    'status'      => $estados_validos,
    'limit'       => -1,
]);

$productos = [];
$productos_nombres = array(); // NUEVO: Array para guardar nombres de productos eliminados

foreach ( $orders as $order ) {
    $order_time = strtotime($order->get_date_completed() ? $order->get_date_completed()->date('Y-m-d H:i:s') : $order->get_date_created()->date('Y-m-d H:i:s'));
    if ($order_time < $fecha_limite) continue;
    
    foreach ( $order->get_items() as $item ) {
        $pid = $item->get_product_id();
        $qty = $item->get_quantity();
        $nombre_item = $item->get_name();
        
        // Si el producto tiene ID 0, crear un ID ficticio
        if ($pid == 0) {

            // Intentar buscar el ID por el nombre en posts eliminados (trash)
            global $wpdb;
            $possible_id = $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} 
                 WHERE post_title = %s 
                 AND post_type IN ('product', 'product_variation')
                 AND post_status = 'trash'
                 LIMIT 1",
                $nombre_item
            ));
            
            if ($possible_id) {
                $pid = $possible_id;
            } else {
                // Si no encontramos el ID, crear un ID numrico ficticio
                $pid = 900000 + abs(crc32($nombre_item) % 100000);
                // Guardar el nombre para usar despus
                $productos_nombres[$pid] = $nombre_item;
            }
        }
        
        // NUEVO: Verificar si es un BULTO
        $producto = wc_get_product($pid);
        
        // Si no existe el producto pero tenemos el nombre guardado, usarlo
        if (!$producto && isset($productos_nombres[$pid])) {
            $nombre_producto = $productos_nombres[$pid];
        } else if ($producto) {
            $nombre_producto = $producto->get_name();
        } else {
            // Si no podemos obtener el nombre, usar el del item
            $nombre_producto = $nombre_item;
        }
        
        // EXCLUIR productos RMA del formulario de garantías
        if (stripos($nombre_producto, 'RMA') === 0) {
            continue; // Saltar este producto
        }
        
        // Si es un BULTO, extraer la cantidad real
        if (stripos($nombre_producto, 'BULTO') === 0) {
            // Buscar el patrón X seguido de números al final
            if (preg_match('/X(\d+)$/i', $nombre_producto, $matches)) {
                $cantidad_por_bulto = intval($matches[1]);
                $qty = $qty * $cantidad_por_bulto; // Multiplicar por la cantidad del bulto
            }
        }
        
        $productos[ $pid ] = ( $productos[ $pid ] ?? 0 ) + $qty;
    }
}


$productos_js = [];

foreach ( $productos as $pid => $qty_original ) {
    // Verificar si es un ID ficticio (mayor a 900000)
    $es_producto_eliminado = ($pid >= 900000);
    
    $prod = wc_get_product( $pid );
    
    // Variables iniciales
    $nombre_producto = '';
    $custom_sku = '';
    $producto_valido = false;
    
    // NUEVO: Si es producto eliminado, buscar en nuestro array de nombres
    if ( $es_producto_eliminado && isset($productos_nombres[$pid]) ) {
        $nombre_producto = $productos_nombres[$pid];
        $custom_sku = 'SKU-DEL-' . substr($pid, -5);
        $producto_valido = true;

    } else if ( ! $prod ) {

        // Buscar en TODAS las órdenes (sin filtro de fecha para este caso)
        $todas_ordenes = wc_get_orders([
            'customer_id' => $customer_id,
            'status'      => $estados_validos,
            'limit'       => -1,
        ]);
        
        foreach ( $todas_ordenes as $order ) {
            foreach ( $order->get_items() as $item ) {
                if ( $item->get_product_id() == $pid ) {
                    $nombre_producto = $item->get_name();
                    
                    // Obtener SKU del item
                    $product_variation_id = $item->get_variation_id();
                    if ($product_variation_id) {
                        $custom_sku = get_post_meta($product_variation_id, '_sku', true);
                    }
                    if (empty($custom_sku)) {
                        $custom_sku = get_post_meta($pid, '_sku', true);
                    }
                    if (empty($custom_sku)) {
                        $custom_sku = 'SKU-' . $pid; // SKU por defecto
                    }
                    
                    $producto_valido = true;
                    break 2;
                }
            }
        }
        
        if (!$producto_valido) {
            continue;
        }
    } else {
        // Producto existe
        $nombre_producto = $prod->get_name();
        $custom_sku = get_post_meta( $pid, '_alg_ean', true );
        if ( is_array( $custom_sku ) ) {
            $custom_sku = reset( $custom_sku );
        }
        if (empty($custom_sku)) {
            $custom_sku = $prod->get_sku();
        }
        $producto_valido = true;
    }
    
    // Solo continuar si tenemos un producto válido
    if (!$producto_valido) {
        continue;
    }
    
    // NUEVO: Ajustar cantidad si es BULTO
    $qty = $qty_original;
    
    // El resto del cdigo continúa igual...
    // NUEVO: Si es un BULTO, extraer la cantidad real
    if (stripos($nombre_producto, 'BULTO') === 0) {
        if (preg_match('/X(\d+)$/i', $nombre_producto, $matches)) {
            $cantidad_por_bulto = intval($matches[1]);
            // Ya fue calculado arriba
        }
    }
    
    // Calcular cantidad ya reclamada
    $cantidad_reclamada = 0;

    $args_gar = [
        'post_type'      => 'garantia',
        'post_status'    => 'publish',
        'meta_query'     => [
            ['key' => '_cliente', 'value' => $customer_id]
        ],
        'posts_per_page' => -1
    ];
    $garantias = get_posts($args_gar);

    foreach ($garantias as $gar) {
        // NUEVO: Verificar que la garantía esté dentro del período válido (180 días)
        $order_id = get_post_meta($gar->ID, '_order_id', true);

        if ($order_id) {
            $order = wc_get_order($order_id);
            if ($order) {
                $order_time = strtotime($order->get_date_completed() ?
                    $order->get_date_completed()->date('Y-m-d H:i:s') :
                    $order->get_date_created()->date('Y-m-d H:i:s'));

                // Solo contar garantías de órdenes dentro del período válido
                if ($order_time < $fecha_limite) {
                    continue; // Saltar esta garantía, está fuera del período
                }
            }
        }

        $items_reclamados = get_post_meta($gar->ID, '_items_reclamados', true);

        if (is_array($items_reclamados)) {
            foreach ($items_reclamados as $item) {
                if (isset($item['producto_id']) && $item['producto_id'] == $pid) {
                    // NO contar items que fueron cancelados por el cliente o rechazados sin recibir
                    $estado_item = isset($item['estado']) ? $item['estado'] : 'Pendiente';

                    if ($estado_item !== 'rechazado_no_recibido') {
                        $cantidad_reclamada += intval($item['cantidad'] ?? 1);
                    }
                }
            }
        } else {
            $producto_reclamado = get_post_meta($gar->ID, '_producto', true);
            if ($producto_reclamado == $pid) {
                $cantidad_reclamada += intval(get_post_meta($gar->ID, '_cantidad', true) ?: 1);
            }
        }
    }
    
    $qty_disponible = $qty - $cantidad_reclamada;
    
    if($qty_disponible < 1) continue;
    
    // Preparar label para mostrar
    $nombre_mostrar = $nombre_producto;
    if (stripos($nombre_mostrar, 'BULTO') === 0) {
        $nombre_mostrar = preg_replace('/\s*X\d+$/i', '', $nombre_mostrar);
        $label = sprintf( '%s %s (%s unidades disponibles)', $nombre_mostrar, $custom_sku, $qty_disponible );
    } else {
        $label = sprintf( '%s %s (%s disponibles)', $nombre_mostrar, $custom_sku, $qty_disponible );
    }
    
    // Agregar indicador si el producto fue eliminado
    if ( $es_producto_eliminado ) {
        $label .= ' [PRODUCTO ELIMINADO]';
    } else if ( ! $prod ) {
        $label .= ' [DESCATALOGADO]';
    }
    
    // Excluir productos RMA
    if (strpos($nombre_producto, 'RMA -') === false) {
        $productos_js[] = [
            'label' => $label,
            'value' => $pid,
            'maxqty' => $qty_disponible,
        ];
    }
}

$motivos_txt = get_option( 'motivos_garantia', "Producto defectuoso\nFalla tcnica\nFaltan piezas\nOtro" );
$motivos = array_filter( array_map( 'trim', explode( "\n", $motivos_txt ) ) );

// Email del admin para notificaciones post-rechazo
$admin_email_garantias = get_option('admin_email_garantias', 'rosariotechsrl@gmail.com');

        // --- PROCESAR ENVIO DEL FORMULARIO ---
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['garantia_form_submit'])) {
            // Variable para errores
            $errores = array();
            
            // Verificar que no es otro formulario
            if (isset($_POST['ver_detalle_garantia_id']) || isset($_POST['ver_timeline_id'])) {
                return;
            }
            
            // NUEVA VALIDACIÓN: Verificar que hay al menos un producto
            $productos_post = isset($_POST['producto']) ? $_POST['producto'] : array();
            $tiene_producto_valido = false;
            
            foreach ($productos_post as $producto_id) {
                // CAMBIO: Agregar trim() y verificar que no sea solo espacios
                $producto_id = trim($producto_id);
                if (!empty($producto_id) && $producto_id !== '' && $producto_id !== '0') {
                    $tiene_producto_valido = true;
                    error_log('DEBUG GARANTÍAS - Producto válido encontrado: ' . $producto_id);
                    break;
                }
            }
            
            error_log('DEBUG GARANTAS - ¿Tiene producto válido?: ' . ($tiene_producto_valido ? 'SI' : 'NO'));
            
            if (!$tiene_producto_valido) {
                $errores[] = 'Debes seleccionar al menos un producto para crear un reclamo de garantia.';
                garantias_set_mensaje('Error: No has seleccionado ningún producto. Por favor selecciona al menos uno.', 'error');
                wp_redirect(wc_get_account_endpoint_url('garantias'));
                exit;
            }
            
            $user_id = get_current_user_id();
            $productos_post = isset($_POST['producto']) ? $_POST['producto'] : array();
            $cantidades_post = isset($_POST['cantidad']) ? $_POST['cantidad'] : array();
            $motivos_post = isset($_POST['motivo']) ? $_POST['motivo'] : array();
            $otros_post = isset($_POST['motivo_otro']) ? $_POST['motivo_otro'] : array();
            $fotos_files = isset($_FILES['foto']) ? $_FILES['foto'] : array('name' => array(), 'type' => array(), 'tmp_name' => array(), 'error' => array(), 'size' => array());
            $videos_files = isset($_FILES['video']) ? $_FILES['video'] : array('name' => array(), 'type' => array(), 'tmp_name' => array(), 'error' => array(), 'size' => array());
            
            // VALIDAR CANTIDADES
            foreach($productos_post as $i => $producto_id) {
                $cantidad_solicitada = intval($cantidades_post[$i] ?? 1);
                
                // Buscar cuntos compr el usuario de este producto (dentro del período válido)
                $total_comprado = 0;
                $orders = wc_get_orders([
                    'customer_id' => $user_id,
                    'status' => 'completed',
                    'limit' => -1
                ]);

                foreach ($orders as $order) {
                    // Verificar si la orden está dentro del período de garantía
                    $order_time = strtotime($order->get_date_completed() ?
                        $order->get_date_completed()->date('Y-m-d H:i:s') :
                        $order->get_date_created()->date('Y-m-d H:i:s'));

                    if ($order_time < $fecha_limite) {
                        continue; // Saltar órdenes fuera del período
                    }

                    foreach ($order->get_items() as $item) {
                        if ($item->get_product_id() == $producto_id) {
                            $total_comprado += $item->get_quantity();
                        }
                    }
                }

                // Calcular cuntos ya reclam (solo de órdenes dentro del período)
                $cantidad_reclamada = 0;
                $garantias_previas = get_posts([
                    'post_type'      => 'garantia',
                    'post_status'    => 'publish',
                    'meta_query'     => [
                        ['key' => '_cliente', 'value' => $user_id],
                    ],
                    'posts_per_page' => -1
                ]);

                foreach ($garantias_previas as $gar) {
                    // NUEVO: Verificar que la garantía esté dentro del período válido
                    $order_id = get_post_meta($gar->ID, '_order_id', true);

                    if ($order_id) {
                        $order = wc_get_order($order_id);
                        if ($order) {
                            $order_time = strtotime($order->get_date_completed() ?
                                $order->get_date_completed()->date('Y-m-d H:i:s') :
                                $order->get_date_created()->date('Y-m-d H:i:s'));

                            // Solo contar garantías de órdenes dentro del período válido
                            if ($order_time < $fecha_limite) {
                                continue; // Saltar esta garantía
                            }
                        }
                    }

                    $items_reclamados = get_post_meta($gar->ID, '_items_reclamados', true);
                    if (is_array($items_reclamados)) {
                        foreach ($items_reclamados as $item) {
                            if ($item['producto_id'] == $producto_id) {
                                $cantidad_reclamada += intval($item['cantidad'] ?? 0);
                            }
                        }
                    } else {
                        // Compatibilidad con formato antiguo
                        if (get_post_meta($gar->ID, '_producto', true) == $producto_id) {
                            $cantidad_reclamada += intval(get_post_meta($gar->ID, '_cantidad', true));
                        }
                    }
                }
                
                $max_disponible = $total_comprado - $cantidad_reclamada;
                
                if ($cantidad_solicitada > $max_disponible) {
                    $producto = wc_get_product($producto_id);
                    $nombre_producto = $producto ? $producto->get_name() : 'Producto ID: ' . $producto_id;
                    $errores[] = 'Error en ' . $nombre_producto . ': Solicitaste ' . $cantidad_solicitada . ' unidades pero solo tienes ' . $max_disponible . ' disponibles para reclamar.';
                }
                
                if ($max_disponible <= 0) {
                    $producto = wc_get_product($producto_id);
                    $nombre_producto = $producto ? $producto->get_name() : 'Producto ID: ' . $producto_id;
                    $errores[] = 'Error en ' . $nombre_producto . ': Ya no tienes unidades disponibles para reclamar.';
                }
                
                // Verificar video (obligatorio SOLO para cliente final)
                if ($is_cliente_final) {
                    if (!isset($videos_files['name'][$i]) || empty($videos_files['name'][$i]) || $videos_files['error'][$i] !== UPLOAD_ERR_OK) {
                        $producto = wc_get_product($producto_id);
                        $nombre_producto = $producto ? $producto->get_name() : 'Producto ID: ' . $producto_id;
                        $errores[] = 'Error en ' . $nombre_producto . ': El video es obligatorio para clientes finales.';
                    }
                }
            }
            
            // Si hay errores, mostrarlos y no procesar
            if (!empty($errores)) {
                foreach ($errores as $error) {
                    garantias_set_mensaje($error, 'error');
                }
                wp_redirect(wc_get_account_endpoint_url('garantias'));
                exit;
            }
            
    // --- PREPARAR ARRAY DE ITEMS ---
    $items_guardar = [];
    foreach($productos_post as $i => $producto_id) {
        $producto_id = sanitize_text_field($producto_id);
    
        // SALTAR completamente si no hay producto seleccionado
        if (empty($producto_id) || $producto_id === '' || $producto_id === '0') {
            continue; // Saltar esta iteracin completamente
        }
        
        $cantidad = max(1, intval($cantidades_post[$i] ?? 1));
        $motivo_sel = isset($motivos_post[$i]) ? $motivos_post[$i] : '';
        $motivo_otro = isset($otros_post[$i]) ? sanitize_text_field($otros_post[$i]) : '';
        
        if ($motivo_sel === 'Otro' && !empty($motivo_otro)) {
            $motivo_str = 'Otro: ' . $motivo_otro;
        } else {
            $motivo_str = $motivo_sel;
        }
        
        $foto_url = '';
        if (isset($fotos_files['name'][$i]) && !empty($fotos_files['name'][$i]) && $fotos_files['error'][$i] === UPLOAD_ERR_OK) {
            $file = [
                'name'     => $fotos_files['name'][$i],
                'type'     => $fotos_files['type'][$i],
                'tmp_name' => $fotos_files['tmp_name'][$i],
                'error'    => $fotos_files['error'][$i],
                'size'     => $fotos_files['size'][$i]
            ];
            
            // Validar tamao de archivo
            $max_size = 5 * 1024 * 1024; // 5MB para fotos
            if ($file['size'] > $max_size) {
                wp_die('El archivo de foto es demasiado grande. Mximo 5MB.');
            }
            
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $uploaded = wp_handle_upload($file, ['test_form' => false]);
            if (empty($uploaded['error'])) {
                $foto_url = $uploaded['url'];
            }
        }
        
             $video_url = '';
            if (isset($videos_files['name'][$i]) && !empty($videos_files['name'][$i]) && $videos_files['error'][$i] === UPLOAD_ERR_OK) {
            $file = [
                'name'     => $videos_files['name'][$i],
                'type'     => $videos_files['type'][$i],
                'tmp_name' => $videos_files['tmp_name'][$i],
                'error'    => $videos_files['error'][$i],
                'size'     => $videos_files['size'][$i]
            ];
            
            // Validar tamao de archivo
            $max_video_size = 50 * 1024 * 1024; // 50MB para videos
            if ($file['size'] > $max_video_size) {
                wp_die('El archivo de video es demasiado grande. Mximo 50MB.');
            }
            
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $uploaded = wp_handle_upload($file, ['test_form' => false]);
            if (empty($uploaded['error'])) {
                $video_url = $uploaded['url'];
            }
        }
        
        // Buscar el order_id ms reciente donde el usuario compr ese producto
        $order_id = null;
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                if ($item->get_product_id() == $producto_id) {
                    $order_id = $order->get_id();
                    break 2;
                }
            }
        }
        
        // Obtener el precio del producto
        $precio_unitario = 0;
        $nombre_producto_guardado = '';
        $producto_temp = wc_get_product($producto_id);
        
        if ($producto_temp) {
            // Si el producto existe, usar su precio actual
            $precio_unitario = $producto_temp->get_price();
            $nombre_producto_guardado = $producto_temp->get_name();
        } else {
            // Si el producto no existe, buscar el precio en la orden
            if ($order_id) {
                $order_temp = wc_get_order($order_id);
                if ($order_temp) {
                    foreach ($order_temp->get_items() as $item_temp) {
                        if ($item_temp->get_product_id() == $producto_id) {
                            // Obtener precio unitario de la orden
                            $precio_unitario = $item_temp->get_total() / $item_temp->get_quantity();
                            $nombre_producto_guardado = $item_temp->get_name();
                            break;
                        }
                    }
                }
            }
        }
        
        $items_guardar[] = [
            'codigo_item'  => 'GRT-ITEM-' . strtoupper(wp_generate_password(8, false, false)),
            'producto_id'  => $producto_id,
            'cantidad'     => $cantidad,
            'motivo'       => $motivo_str,
            'foto_url'     => $foto_url,
            'video_url'    => $video_url,
            'order_id'     => $order_id,
            'estado'       => 'Pendiente',
            'precio_unitario' => $precio_unitario, // NUEVO: Guardar el precio
            'nombre_producto' => $nombre_producto_guardado, // NUEVO: Guardar el nombre
        ];

    // --- CREAR UN SOLO POST DE GARANTIA ---
    $garantia_post = [
        'post_type'   => 'garantia',
        'post_status' => 'publish',
        'post_title'  => 'Garantia - ' . $user_id . ' - ' . date('Y-m-d H:i:s'),
        'post_author' => $user_id,
        ];
    }
    
    $post_id = wp_insert_post($garantia_post);
    
    if ($post_id && !is_wp_error($post_id)) {
    $codigo_unico = 'GRT-' . date('Ymd') . '-' . strtoupper(wp_generate_password(5, false, false));
    update_post_meta($post_id, '_codigo_unico', $codigo_unico);
    update_post_meta($post_id, '_cliente', $user_id);
    update_post_meta($post_id, '_fecha', current_time('mysql'));
    update_post_meta($post_id, '_estado', 'nueva');
    update_post_meta($post_id, '_items_reclamados', $items_guardar);
    
    // Guardar mensaje en sesin
    if (!session_id()) {
        session_start();
    }
    garantias_set_mensaje('Reclamo enviado correctamente! Codigo: ' . $codigo_unico, 'success');
    
    // Redireccionar para evitar reenvio
    wp_redirect(wc_get_account_endpoint_url('garantias'));
    exit;
}
}

// NUEVO: Procesar destruccion del cliente
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subir_destruccion']) && isset($_POST['confirmo_destruccion'])) {
    $garantia_id = intval($_POST['garantia_id']);
    $garantia = get_post($garantia_id);
    
    if ($garantia && $garantia->post_author == $customer_id) {
        // Procesar foto de destruccion
        $foto_destruccion_url = '';
        if (isset($_FILES['foto_destruccion']) && $_FILES['foto_destruccion']['error'] === UPLOAD_ERR_OK) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $upload = wp_handle_upload($_FILES['foto_destruccion'], array('test_form' => false));
            if (!isset($upload['error'])) {
                $foto_destruccion_url = $upload['url'];
            }
        }
        
        // Procesar video opcional
        $video_destruccion_url = '';
        if (isset($_FILES['video_destruccion']) && $_FILES['video_destruccion']['error'] === UPLOAD_ERR_OK) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $upload = wp_handle_upload($_FILES['video_destruccion'], array('test_form' => false));
            if (!isset($upload['error'])) {
                $video_destruccion_url = $upload['url'];
            }
        }
        
        // Actualizar estado a "esperando verificacin de destruccion"
        update_post_meta($garantia_id, '_estado', 'destruccion_subida');
        update_post_meta($garantia_id, '_destruccion_fecha_subida', current_time('mysql')); 
        update_post_meta($garantia_id, '_destruccion_confirmada', current_time('mysql'));
        update_post_meta($garantia_id, '_foto_destruccion', $foto_destruccion_url);
        if ($video_destruccion_url) {
            update_post_meta($garantia_id, '_video_destruccion', $video_destruccion_url);
        }
        
        // Marcar todos los items como aprobados
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        if (is_array($items)) {
            foreach ($items as &$item) {
                $item['estado'] = 'aprobado';
            }
            update_post_meta($garantia_id, '_items_reclamados', $items);
        }
        
        echo '<div class="woocommerce-message">Evidencia de destruccion subida correctamente! El administrador la revisar y te notificaremos cuando sea aprobada.</div>';

        // Notificar al admin
        $admin_email = get_option('admin_email_garantias', get_option('admin_email'));
        $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
        
        $archivos_destruccion = '';
        if ($foto_destruccion_url) {
            $archivos_destruccion .= "Foto: {$foto_destruccion_url}\n";
        }
        if ($video_destruccion_url) {
            $archivos_destruccion .= "Video: {$video_destruccion_url}\n";
        }
        
        // Enviar solo email al admin (sin WhatsApp al cliente)
        $asunto = "Evidencia de destruccion subida - " . $codigo_unico;
        $mensaje = "Se ha subido evidencia de destruccion para la garantia: " . $codigo_unico . "\n\n";
        $mensaje .= "Archivos:\n" . $archivos_destruccion . "\n\n";
        $mensaje .= "Ver en admin: " . admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id);
        
        wp_mail($admin_email, $asunto, $mensaje);
    }
}

// --- ACCIONES post-rechazo ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['garantia_accion_rechazo'], $_POST['garantia_id'])) {
    $garantia_id = intval($_POST['garantia_id']);
    $accion = sanitize_text_field($_POST['garantia_accion_rechazo']);
    $garantia = get_post($garantia_id);

    if (
        $garantia &&
        $garantia->post_type === 'garantia' &&
        $garantia->post_author == $customer_id &&
        get_post_meta($garantia_id, '_estado', true) === 'rechazado'
        && !get_post_meta($garantia_id, '_accion_post_rechazo', true)
        && in_array($accion, ['destruccion', 'reenvio'])
    ) {
        update_post_meta($garantia_id, '_accion_post_rechazo', $accion);

        // Notificar al admin
        $motivo_rechazo = get_post_meta($garantia_id, '_motivo_rechazo', true);
        $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
        $producto_id = get_post_meta($garantia_id, '_producto', true);
        $prod = wc_get_product($producto_id);
        $nombre_producto = $prod ? $prod->get_name() : 'Producto eliminado';

        $mensaje = "El cliente #" . $customer_id . " ha seleccionado la opcin post-rechazo para la garantia $codigo_unico\n\n";
        $mensaje .= "Producto: $nombre_producto\n";
        $mensaje .= "Motivo de rechazo: $motivo_rechazo\n";
        $mensaje .= "Accion solicitada: " . ($accion === 'destruccion' ? 'Destruccion de la pieza' : 'Reenvio a depsito');

        wp_mail(
            $admin_email_garantias,
            'Accion post-rechazo de garantia',
            $mensaje
        );

        echo '<div class="woocommerce-message">Tu solicitud post-rechazo fue registrada correctamente!</div>';
    }
}

// --- MOSTRAR RECLAMOS ENVIADOS SOLO DESDE POSTS DE GARANTIA ---
$args = [
    'post_type'      => 'garantia',
    'post_status'    => 'publish',
    'meta_query'     => [
        ['key' => '_cliente', 'value' => $customer_id]
    ],
    'posts_per_page' => 100,
    'orderby'        => 'date',
    'order'          => 'DESC'
];
$garantias = get_posts($args);

$estados_nombres = [
    'nueva'                  => 'Nueva Garantia',
    'en_proceso'             => 'En proceso',
    'en_revision'            => 'En revision',
    'pendiente_envio'        => 'Pendiente de envio',
    'recibido'               => 'Recibido - En anlisis',
    'aprobado_cupon'         => 'Aprobado - Cupon Enviado',
    'aprobado_destruir'      => 'Aprobado - Destruir',
    'aprobado_devolver'      => 'Aprobado - Devolver',
    'destruccion_subida'     => 'Destruccion subida - En revision',
    'devolucion_en_transito' => 'Enviado a WiFix',
    'rechazado'              => 'Rechazado',
    'finalizado_cupon'       => 'Finalizado - Cupon utilizado',
    'finalizado'             => 'Finalizado',
    'info_solicitada'        => 'Informacion Solicitada',
    'parcialmente_recibido'  => 'Parcialmente Recibido',
];

if ($garantias) {
    // Solo mostrar la lista de reclamos si NO estamos viendo detalles
if (!isset($_POST['ver_detalle_garantia_id'])) {
    echo '<h3>Mis reclamos enviados</h3>';
    echo '<table class="shop_table shop_table_responsive" id="tabla-reclamos">';
    echo '<thead><tr>
        <th>Codigo</th>
        <th>Fecha</th>
        <th>Estado</th>
        <th>Acciones</th>
        </tr></thead><tbody>';
    foreach ($garantias as $garantia) {
        $codigo_unico = get_post_meta($garantia->ID, '_codigo_unico', true);
        $fecha_raw = get_post_meta($garantia->ID, '_fecha', true);
        $estado = get_post_meta($garantia->ID, '_estado', true);

        $fecha = '';
        if ($fecha_raw) {
            $timestamp = strtotime($fecha_raw);
            $fecha = $timestamp ? date('d/m/Y', $timestamp) : '';
        }

        echo '<tr>';
        echo '<td data-label="Código">' . esc_html($codigo_unico);
        $es_carga_masiva = get_post_meta($garantia->ID, '_es_carga_masiva', true);
        if ($es_carga_masiva) {
            $cantidad_items = get_post_meta($garantia->ID, '_cantidad_items', true);
            echo ' <span style="background: #17a2b8; color: white; padding: 2px 8px; border-radius: 4px; font-size: 11px; margin-left: 5px;" title="Carga masiva">
                    <i class="fas fa-file-excel"></i> ' . $cantidad_items . ' items
                  </span>';
        }
        echo '</td>';
        echo '<td data-label="Fecha">' . esc_html($fecha) . '</td>';
        echo '<td data-label="Estado">' . esc_html($estados_nombres[$estado] ?? $estado) . '</td>';
        echo '<td>';
        
        // Botón Ver detalles
        echo '<form method="post" style="margin:0;display:inline;">
                <input type="hidden" name="ver_detalle_garantia_id" value="' . esc_attr($garantia->ID) . '" />
                <button type="submit" class="button" name="ver_detalle_garantia_btn">Ver detalles</button>
            </form>';
        
        // Botón Etiqueta si es devolucin
        $es_devolucion = get_post_meta($garantia->ID, '_es_devolucion_error', true);
        if ($es_devolucion) {
            echo ' <a href="' . add_query_arg(['generar_etiqueta' => '1', 'devolucion_id' => $garantia->ID], wc_get_account_endpoint_url('garantias')) . '" 
                     class="button" 
                     target="_blank"
                     style="background: #17a2b8; color: white; margin-left: 5px;">
                     <i class="fas fa-tag"></i> Etiqueta
                  </a>';
        }
        
        echo '</td>';
        echo '</tr>';
        }
    echo '</tbody></table>';
    }
}
?>

<!-- Carga jQuery UI Autocomplete -->
<link rel="stylesheet" href="//code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<?php
// INICIO MODIFICACIÓN - Incluir formulario modularizado
// Pasar variables al formulario
$GLOBALS['productos_js'] = $productos_js;
$GLOBALS['motivos'] = $motivos;
$GLOBALS['is_cliente_final'] = $is_cliente_final;
$GLOBALS['is_distribuidor'] = $is_distribuidor;

// Incluir el formulario modularizado
require_once WC_GARANTIAS_PATH . 'templates/myaccount-garantias-formulario.php';

// Incluir el JavaScript del formulario
wp_enqueue_script(
    'garantias-formulario',
    WC_GARANTIAS_URL . 'assets/js/myaccount-garantias-formulario.js',
    array('jquery', 'jquery-ui-autocomplete'),
    '1.0.0',
    true
);
// FIN MODIFICACIÓN
?>

<?php
// ========== OPCIN: Panel de administracin para cambiar el email de notificaciones ==========
add_action('admin_menu', function() {
    add_options_page(
        'Email de Notificaciones Garantias',
        'Email Garantias',
        'manage_options',
        'garantias-email',
        function() {
            if (isset($_POST['guardar_email_garantias']) && check_admin_referer('guardar_email_garantias')) {
                $nuevo_email = sanitize_email($_POST['admin_email_garantias']);
                if (is_email($nuevo_email)) {
                    update_option('admin_email_garantias', $nuevo_email);
                    echo '<div class="updated notice"><p>Email guardado correctamente.</p></div>';
                } else {
                    echo '<div class="error notice"><p>El email ingresado no es vlido.</p></div>';
                }
            }
            $email_actual = get_option('admin_email_garantias', 'rosariotechsrl@gmail.com');
            ?>
            <div class="wrap">
                <h1>Email de notificaciones de garantias</h1>
                <form method="post">
                    <?php wp_nonce_field('guardar_email_garantias'); ?>
                    <label for="admin_email_garantias">Email actual:</label>
                    <input type="email" id="admin_email_garantias" name="admin_email_garantias" value="<?php echo esc_attr($email_actual); ?>" style="width:350px;max-width:100%;" required>
                    <p><button type="submit" class="button button-primary" name="guardar_email_garantias" value="1">Guardar Email</button></p>
                </form>
            </div>
            <?php
        }
    );
});