<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Módulo para Ver Garantía Individual
 */
class WC_Garantias_Admin_View {
    
    private static $estados = [
        'nueva' => ['label' => 'Nueva', 'icon' => 'clock'],
        'en_proceso' => ['label' => 'En proceso', 'icon' => 'spinner'],
        'parcialmente_recibido' => ['label' => 'Parcialmente Recibido', 'icon' => 'exclamation-triangle'],
        'finalizada' => ['label' => 'Finalizada', 'icon' => 'check-circle'],
        'finalizado_cupon' => ['label' => 'Finalizada', 'icon' => 'check-circle']
    ];
    
    // Estados finales para items individuales
    public static $estados_finales_items = [
        'aprobado',
        'rechazado',
        'retorno_cliente',
        'rechazado_no_recibido'
    ];
    
    public static function render_page() {
        if (!isset($_GET['garantia_id'])) {
            echo "<div class='notice notice-error'><p>No se encontró la garantía.</p></div>";
            return;
        }
        
        $garantia_id = intval($_GET['garantia_id']);
        $garantia = get_post($garantia_id);
        
        if (!$garantia) {
            echo "<div class='notice notice-error'><p>No se encontró la garantía.</p></div>";
            return;
        }
        
        // Procesar acciones POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::procesar_acciones_post($garantia_id);
        }
        
        // Cargar estilos necesarios
        self::enqueue_styles();
        
        // Obtener datos de la garanta
        $datos = self::obtener_datos_garantia($garantia_id);
        
        // Renderizar la página
        self::render_html($garantia_id, $datos);
    }
    
    private static function procesar_acciones_post($garantia_id) {
        // Procesar división de items (aprobacin parcial)
        if (isset($_POST['accion_parcial']) && $_POST['accion_parcial'] === 'dividir_item') {
            self::procesar_division_item($garantia_id);
        }
        
        // Procesar recepción parcial
        if (isset($_POST['accion']) && $_POST['accion'] === 'recepcion_parcial') {
            self::procesar_recepcion_parcial_post($garantia_id);
        }
        
        // Procesar subida de etiqueta
        if (isset($_POST['subir_etiqueta_devolucion']) || isset($_POST['subir_etiqueta'])) {
            self::procesar_subida_etiqueta($garantia_id);
        }
        
        // Procesar eliminación de etiqueta
        if (isset($_POST['eliminar_etiqueta_devolucion']) || isset($_POST['eliminar_etiqueta'])) {
            self::procesar_eliminacion_etiqueta($garantia_id);
        }
        
        // Procesar acciones masivas
        if (isset($_POST['bulk_action']) && (isset($_POST['bulk_items']) || isset($_POST['procesar_items']))) {
            self::procesar_acciones_masivas($garantia_id);
        }
        
        // Procesar acciones individuales de items
        if (isset($_POST['accion_item']) && isset($_POST['item_codigo'])) {
            self::procesar_accion_item($garantia_id);
        }
        
        // Procesar confirmacin de devolución recibida
        if (isset($_POST['confirmar_devolucion_recibida'])) {
            self::procesar_devolucion_recibida($garantia_id);
        }
        
        // Procesar tracking
        if (isset($_POST['subir_tracking'])) {
            self::procesar_tracking($garantia_id);
        }
    }
    
    private static function enqueue_styles() {
        wp_enqueue_style('bootstrap5', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css');
        wp_enqueue_style('fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css');
        wp_enqueue_script('bootstrap5', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js');
    }
    
    private static function obtener_datos_garantia($garantia_id) {
        $estado = get_post_meta($garantia_id, '_estado', true);
        $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
        $cliente_id = get_post_meta($garantia_id, '_cliente', true);
        
        // Datos del cliente
        $nombre_cliente = 'Usuario eliminado';
        $email_cliente = '';
        $telefono = '';
        $is_distribuidor = false;
        
        if ($cliente_id) {
            $user_info = get_userdata($cliente_id);
            if ($user_info) {
                $nombre_cliente = $user_info->display_name ?: $user_info->user_login;
                $email_cliente = $user_info->user_email;
                $telefono = get_user_meta($cliente_id, 'billing_phone', true);
                if (!$telefono) $telefono = get_user_meta($cliente_id, 'phone', true);
                if (!$telefono) $telefono = '-';
                
                // Verificar si es distribuidor
                $roles_distribuidor = ['distri10', 'distri20', 'distri30', 'superdistri30'];
                $is_distribuidor = !empty(array_intersect($user_info->roles, $roles_distribuidor));
            }
        }
        
        $fecha = get_the_date('d/m/Y H:i', $garantia_id);
        $motivo_rechazo = get_post_meta($garantia_id, '_motivo_rechazo', true);
        
        // Items
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        if (!is_array($items) || count($items) === 0) {
            // Compatibilidad con formato antiguo
            $producto_id = get_post_meta($garantia_id, '_producto', true);
            $cantidad = get_post_meta($garantia_id, '_cantidad', true);
            $motivo = get_post_meta($garantia_id, '_motivos', true);
            $foto_url = get_post_meta($garantia_id, '_foto_url', true);
            $video_url = get_post_meta($garantia_id, '_video_url', true);
            $order_id = get_post_meta($garantia_id, '_order_id', true);
            
            $items = [];
            if ($producto_id) {
                $items[] = [
                    'codigo_item' => 'LEGACY-ITEM',
                    'producto_id' => $producto_id,
                    'cantidad'    => $cantidad ? $cantidad : 1,
                    'motivo'      => $motivo,
                    'foto_url'    => $foto_url,
                    'video_url'   => $video_url,
                    'order_id'    => $order_id,
                    'estado'      => 'Pendiente'
                ];
            }
        }
        
        // Calcular estadsticas
        $cantidad_total_reclamada = 0;
        $garantias_cliente = get_posts([
            'post_type' => 'garantia',
            'post_status' => 'publish',
            'meta_query' => [
                ['key' => '_cliente', 'value' => $cliente_id]
            ],
            'posts_per_page' => -1
        ]);
        
        foreach ($garantias_cliente as $g) {
            $items_g = get_post_meta($g->ID, '_items_reclamados', true);
            if (is_array($items_g) && count($items_g)) {
                foreach ($items_g as $item) {
                    $cantidad_total_reclamada += isset($item['cantidad']) ? intval($item['cantidad']) : 1;
                }
            } else {
                $cantidad = get_post_meta($g->ID, '_cantidad', true);
                $cantidad_total_reclamada += intval($cantidad ? $cantidad : 1);
            }
        }
        
        // Calcular tasa de reclamo
        $orders = wc_get_orders([
            'customer_id' => $cliente_id,
            'status'      => 'completed',
            'limit'       => -1,
        ]);
        
        $total_items_comprados = 0;
        foreach ($orders as $order) {
            foreach ($order->get_items() as $item) {
                $total_items_comprados += $item->get_quantity();
            }
        }
        
        $tasa_reclamo = $total_items_comprados > 0 ? ($cantidad_total_reclamada / $total_items_comprados) * 100 : 0;
        
        // Motivos de rechazo
        $motivos_rechazo = explode("\n", get_option('motivos_rechazo_garantia', "Fuera de plazo\nProducto daado\nNo corresponde a la compra\nOtro"));
        $motivos_rechazo = array_filter(array_map('trim', $motivos_rechazo));
        
        return [
            'estado' => $estado,
            'codigo_unico' => $codigo_unico,
            'cliente_id' => $cliente_id,
            'nombre_cliente' => $nombre_cliente,
            'email_cliente' => $email_cliente,
            'telefono' => $telefono,
            'fecha' => $fecha,
            'motivo_rechazo' => $motivo_rechazo,
            'items' => $items,
            'tasa_reclamo' => $tasa_reclamo,
            'motivos_rechazo' => $motivos_rechazo,
            'is_distribuidor' => $is_distribuidor
        ];
    }
    
    private static function procesar_recepcion_parcial_post($garantia_id) {
        // Verificar que se cargue la clase
        $recepcion_parcial_path = plugin_dir_path(__FILE__) . 'class-wc-garantias-recepcion-parcial.php';
        if (file_exists($recepcion_parcial_path)) {
            require_once $recepcion_parcial_path;
            
            $codigo_item = sanitize_text_field($_POST['codigo_item']);
            $cantidad_recibida = intval($_POST['cantidad_recibida']);
            $cantidad_esperada = intval($_POST['cantidad_esperada']);
            
            $resultado = WC_Garantias_Recepcion_Parcial::procesar_recepcion_parcial(
                $garantia_id,
                $codigo_item,
                $cantidad_recibida,
                $cantidad_esperada
            );
            
            if ($resultado['success']) {
                echo '<div class="notice notice-success"><p>✓ ' . esc_html($resultado['message']) . '</p></div>';
            } else {
                echo '<div class="notice notice-error"><p> Error: ' . esc_html($resultado['message']) . '</p></div>';
            }
        } else {
            echo '<div class="notice notice-error"><p>✗ Error: No se pudo cargar el módulo de recepción parcial.</p></div>';
        }
    }
    
    private static function procesar_division_item($garantia_id) {
        require_once(plugin_dir_path(__FILE__) . '../class-wc-garantias-partial-approval.php');
        
        $codigo_item = sanitize_text_field($_POST['codigo_item']);
        $cantidad_aprobar = intval($_POST['cantidad_aprobar']);
        $cantidad_rechazar = intval($_POST['cantidad_rechazar']);
        $motivo_rechazo = sanitize_textarea_field($_POST['motivo_rechazo_parcial']);
        
        $resultado = WC_Garantias_Partial_Approval::split_item(
            $garantia_id,
            $codigo_item,
            $cantidad_aprobar,
            $cantidad_rechazar,
            $motivo_rechazo
        );
        
        if ($resultado) {
            WC_Garantias_Admin::actualizar_estado_garantia($garantia_id);
            echo '<div class="notice notice-success"><p>✓ Item dividido correctamente: ' . $cantidad_aprobar . ' aprobados, ' . $cantidad_rechazar . ' rechazados.</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>Error al dividir el item.</p></div>';
        }
    }
    
    private static function procesar_subida_etiqueta($garantia_id) {
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        $file = $_FILES['etiqueta_pdf'];
        
        // Validar que sea PDF
        $file_type = wp_check_filetype($file['name']);
        if ($file_type['ext'] !== 'pdf') {
            ?>
            <div style="padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 20px;">
                <h3 style="color: #721c24;">✗ Error</h3>
                <p>El archivo debe ser un PDF.</p>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id); ?>" 
                       style="display: inline-block; padding: 10px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 4px;">
                        Volver a intentar
                    </a>
                </p>
            </div>
            <?php
            die();
        }
        
        $upload = wp_handle_upload($file, array('test_form' => false));
        
        if (!isset($upload['error'])) {
            // Generar un ID nico para este grupo de etiqueta
            $etiqueta_grupo_id = 'ETQ-' . date('YmdHis') . '-' . wp_generate_password(4, false, false);
            
            // Guardar URL de la etiqueta
            if (isset($_POST['subir_etiqueta_devolucion'])) {
                update_post_meta($garantia_id, '_etiqueta_devolucion_url', $upload['url']);
                update_post_meta($garantia_id, '_etiqueta_grupo_id', $etiqueta_grupo_id);
                
                // Guardar dimensiones
                update_post_meta($garantia_id, '_dimensiones_caja_devolucion', [
                    'largo' => intval($_POST['largo']),
                    'ancho' => intval($_POST['ancho']),
                    'alto' => intval($_POST['alto'])
                ]);
            } else {
                update_post_meta($garantia_id, '_etiqueta_envio_url', $upload['url']);
            }
            
            // Guardar tracking si se proporcionó
            if (!empty($_POST['numero_tracking'])) {
                update_post_meta($garantia_id, '_numero_tracking_devolucion', sanitize_text_field($_POST['numero_tracking']));
            }
            
            // Notificar al cliente
            self::notificar_etiqueta_subida($garantia_id);
            
            // Redirigir
            wp_redirect(admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id . '&etiqueta_subida=1'));
            exit;
        } else {
            ?>
            <div style="padding: 20px; background: #f8d7da; border: 1px solid #f5c6cb; border-radius: 4px; margin: 20px;">
                <h3 style="color: #721c24;"> Error al subir</h3>
                <p><?php echo $upload['error']; ?></p>
                <p>
                    <a href="<?php echo admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id); ?>" 
                       style="display: inline-block; padding: 10px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 4px;">
                        Volver a intentar
                    </a>
                </p>
            </div>
            <?php
        }
        die();
    }
    
    private static function procesar_eliminacion_etiqueta($garantia_id) {
        // Eliminar todas las etiquetas
        delete_post_meta($garantia_id, '_etiqueta_devolucion_url');
        delete_post_meta($garantia_id, '_dimensiones_caja_devolucion');
        delete_post_meta($garantia_id, '_andreani_etiqueta_url');
        delete_post_meta($garantia_id, '_andreani_numero_envio');
        delete_post_meta($garantia_id, '_andreani_fecha_generacion');
        delete_post_meta($garantia_id, '_numero_tracking_devolucion');
        delete_post_meta($garantia_id, '_etiqueta_envio_url');
        delete_post_meta($garantia_id, '_etiqueta_subida');
        
        // Limpiar tracking de items
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        if (is_array($items)) {
            foreach ($items as &$item) {
                if (isset($item['tracking_devolucion'])) {
                    unset($item['tracking_devolucion']);
                }
            }
            update_post_meta($garantia_id, '_items_reclamados', $items);
        }
        
        // Limpiar cach
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($garantia_id, 'post_meta');
        }
        clean_post_cache($garantia_id);
        
        // Redirigir
        wp_redirect(admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id . '&etiqueta_eliminada=1'));
        exit;
    }
    
    private static function notificar_etiqueta_subida($garantia_id) {
        $cliente_id = get_post_meta($garantia_id, '_cliente', true);
        $user = get_userdata($cliente_id);
        
        if ($user && $user->user_email && class_exists('WC_Garantias_Emails')) {
            $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
            $dimensiones = get_post_meta($garantia_id, '_dimensiones_caja_devolucion', true);
            $dimensiones_str = '';
            
            if ($dimensiones && is_array($dimensiones)) {
                $dimensiones_str = $dimensiones['largo'] . "x" . $dimensiones['ancho'] . "x" . $dimensiones['alto'] . " cm";
            }
            
            // Obtener el primer item aprobado para devolver
            $items = get_post_meta($garantia_id, '_items_reclamados', true);
            $primer_item_codigo = 'SIN-ITEM';
            
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (isset($item['estado']) && $item['estado'] === 'aprobado_devolver') {
                        $primer_item_codigo = $item['codigo_item'] ?? 'SIN-ITEM';
                        break;
                    }
                }
            }
            
            WC_Garantias_Emails::enviar_email('etiqueta_devolucion', $user->user_email, [
                'cliente' => $user->display_name,
                'codigo' => $codigo_unico,
                'dimensiones' => $dimensiones_str,
                'link_cuenta' => wc_get_account_endpoint_url('garantias'),
                'item_codigo_procesado' => $primer_item_codigo  // ESTA LÍNEA ES IMPORTANTE
            ]);
        }
    }
    private static function procesar_acciones_masivas($garantia_id) {
    $accion = sanitize_text_field($_POST['bulk_action']);
    
    // DEBUG: Ver qué llega
    error_log('=== PROCESAR ACCIONES MASIVAS ===');
    error_log('Acción: ' . $accion);
    error_log('POST completo: ' . print_r($_POST, true));
    
    // Obtener items seleccionados
    $items_seleccionados = [];
    
    // Verificar diferentes formas en que pueden venir los items
    if (isset($_POST['bulk_items'])) {
        if (is_string($_POST['bulk_items']) && strpos($_POST['bulk_items'], '[') === 0) {
            $items_seleccionados = json_decode(stripslashes($_POST['bulk_items']), true);
            error_log('Items decodificados de JSON');
        } else if (is_array($_POST['bulk_items'])) {
            $items_seleccionados = array_map('sanitize_text_field', $_POST['bulk_items']);
            error_log('Items como array directo');
        } else {
            $items_seleccionados = [sanitize_text_field($_POST['bulk_items'])];
            error_log('Items como string nico');
        }
    }
    
    error_log('Items seleccionados finales: ' . print_r($items_seleccionados, true));
    
    if (empty($items_seleccionados)) {
        error_log('ERROR: No hay items seleccionados!');
        wp_redirect(admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id . '&error=no_items'));
        exit;
    }
    
    // Delegar segn la acción
        switch ($accion) {
            case 'rechazado':
                self::mostrar_formulario_rechazo($garantia_id, $items_seleccionados);
                break;
            case 'rechazado_enviar':
                self::procesar_rechazo_items($garantia_id, $items_seleccionados);
                break;
            case 'solicitar_info':
                self::mostrar_formulario_info($garantia_id, $items_seleccionados);
                break;
            case 'solicitar_info_enviar':
                self::procesar_solicitud_info($garantia_id, $items_seleccionados);
                break;
            case 'recibido':
                self::marcar_items_recibidos($garantia_id, $items_seleccionados);
                break;
            case 'aprobado':
                self::aprobar_items($garantia_id, $items_seleccionados);
                break;
            case 'aprobado_destruir':
            case 'aprobado_devolver':
                self::cambiar_estado_items($garantia_id, $items_seleccionados, $accion);
                break;
        }
    }
    
    private static function mostrar_formulario_rechazo($garantia_id, $items_seleccionados) {
        ?>
        <div class="wrap" style="max-width: 100%; margin-top: 20px;">
            <div style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; margin: -20px -20px 0 -20px;">
                <h1 style="color: white; margin: 0; font-size: 28px;">
                    <i class="fas fa-times-circle"></i> Rechazar Items
                </h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">
                    Rechazando <?php echo count($items_seleccionados); ?> item(s)
                </p>
            </div>
            
            <div style="background: white; padding: 40px; border-radius: 0 0 10px 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="garantia_id" value="<?php echo $garantia_id; ?>">
                    <input type="hidden" name="bulk_action" value="rechazado_enviar">
                    <input type="hidden" name="bulk_items" value="<?php echo htmlspecialchars(json_encode($items_seleccionados)); ?>">
                    
                    <div style="margin-bottom: 30px;">
                        <label style="display: block; margin-bottom: 10px; font-weight: 600; font-size: 16px; color: #333;">
                            <i class="fas fa-exclamation-triangle"></i> Motivo del rechazo:
                        </label>
                        <textarea name="motivo_rechazo" required 
                                  placeholder="Explica claramente por qu se rechaza la garantía. El cliente recibirá este mensaje." 
                                  style="width: 100%; height: 150px; padding: 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 15px; transition: border-color 0.3s; resize: vertical;"
                                  onfocus="this.style.borderColor='#dc3545'"
                                  onblur="this.style.borderColor='#e9ecef'"
                        ></textarea>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px;">
                        <h4 style="margin-top: 0; color: #333;">
                            <i class="fas fa-camera"></i> Evidencia del funcionamiento (opcional)
                        </h4>
                        <p style="color: #666; margin-bottom: 15px;">
                            Sube fotos o videos que demuestren que el producto funciona correctamente.
                        </p>
                        
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                            <div>
                                <label style="display: block; margin-bottom: 8px; font-weight: 500;">
                                    <i class="fas fa-image"></i> Foto(s)
                                </label>
                                <input type="file" 
                                       name="fotos_funcionamiento[]" 
                                       accept="image/*" 
                                       multiple
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <small style="color: #666;">Acepta mltiples imágenes</small>
                            </div>
                            
                            <div>
                                <label style="display: block; margin-bottom: 8px; font-weight: 500;">
                                    <i class="fas fa-video"></i> Video
                                </label>
                                <input type="file" 
                                       name="video_funcionamiento" 
                                       accept="video/*"
                                       style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px;">
                                <small style="color: #666;">Máx. 100MB</small>
                            </div>
                        </div>
                    </div>
                    
                    <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin-bottom: 30px; border: 1px solid #ffeaa7;">
                        <label style="display: flex; align-items: center; cursor: pointer; margin: 0;">
                            <input type="checkbox" 
                                   name="rechazo_definitivo" 
                                   value="1"
                                   style="margin-right: 10px; width: 18px; height: 18px;">
                            <div>
                                <strong style="color: #856404;">
                                    <i class="fas fa-ban"></i> Rechazo Definitivo
                                </strong>
                                <p style="margin: 5px 0 0 0; color: #856404; font-size: 14px;">
                                    Marcar esta opción impedir que el cliente pueda apelar la decisión. 
                                    <?php 
                                    // Verificar si hay items ya recibidos entre los seleccionados
                                    $hay_items_recibidos = false;
                                    foreach ($items_seleccionados as $codigo_sel) {
                                        foreach ($items as $item_check) {
                                            if ($item_check['codigo_item'] == $codigo_sel && 
                                                in_array($item_check['estado'], ['recibido', 'aprobado_destruir', 'aprobado_devolver'])) {
                                                $hay_items_recibidos = true;
                                                break 2;
                                            }
                                        }
                                    }
                                    
                                    if ($hay_items_recibidos) {
                                        echo "Los items recibidos sern marcados para devolución al cliente.";
                                    } else {
                                        echo "Como estos items no fueron recibidos, quedarán rechazados sin requerir devolución.";
                                    }
                                    ?>
                                </p>
                            </div>
                        </label>
                    </div>
                    
                    <div style="display: flex; gap: 15px; justify-content: center;">
                        <button type="submit" style="padding: 12px 40px; background: #dc3545; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-times-circle"></i> Rechazar Items
                        </button>
                        
                        <a href="<?php echo admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id); ?>" 
                           style="padding: 12px 40px; background: #6c757d; color: white; text-decoration: none; border-radius: 8px; font-size: 16px; transition: all 0.3s; display: inline-flex; align-items: center; gap: 10px;">
                            <i class="fas fa-arrow-left"></i> Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        die();
    }
    
    private static function mostrar_formulario_info($garantia_id, $items_seleccionados) {
        ?>
        <div class="wrap" style="max-width: 100%; margin-top: 20px;">
            <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px 10px 0 0; margin: -20px -20px 0 -20px;">
                <h1 style="color: white; margin: 0; font-size: 28px;">
                    <i class="fas fa-info-circle"></i> Solicitar Información Adicional
                </h1>
                <p style="margin: 10px 0 0 0; opacity: 0.9;">
                    Solicitando informacin para <?php echo count($items_seleccionados); ?> item(s)
                </p>
            </div>
            
            <div style="background: white; padding: 40px; border-radius: 0 0 10px 10px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
                <form method="post">
                    <input type="hidden" name="garantia_id" value="<?php echo $garantia_id; ?>">
                    <input type="hidden" name="bulk_action" value="solicitar_info_enviar">
                    <input type="hidden" name="bulk_items" value="<?php echo htmlspecialchars(json_encode($items_seleccionados)); ?>">
                    
                    <div style="margin-bottom: 30px;">
                        <label style="display: block; margin-bottom: 10px; font-weight: 600; font-size: 16px; color: #333;">
                            <i class="fas fa-comment-alt"></i> Mensaje para el cliente:
                        </label>
                        <textarea name="mensaje_info" required 
                                  placeholder="Explica detalladamente qué informacin adicional necesitas." 
                                  style="width: 100%; height: 180px; padding: 15px; border: 2px solid #e9ecef; border-radius: 8px; font-size: 15px; transition: border-color 0.3s; resize: vertical;"
                                  onfocus="this.style.borderColor='#667eea'"
                                  onblur="this.style.borderColor='#e9ecef'"
                        ></textarea>
                    </div>
                    
                    <div style="background: #f8f9fa; padding: 25px; border-radius: 8px; margin-bottom: 30px;">
                        <label style="font-weight: 600; margin-bottom: 15px; display: block; font-size: 16px; color: #333;">
                            <i class="fas fa-camera"></i> Tipo de información requerida:
                        </label>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px;">
                            <label style="display: flex; align-items: center; background: white; padding: 15px; border-radius: 6px; border: 2px solid #e9ecef; cursor: pointer; transition: all 0.3s;">
                                <input type="checkbox" name="solicitar_fotos" value="1" checked style="margin-right: 10px; width: 18px; height: 18px;"> 
                                <span style="font-size: 15px;">
                                    <i class="fas fa-image" style="color: #667eea; margin-right: 8px;"></i>
                                    Solicitar fotos adicionales
                                </span>
                            </label>
                            
                            <label style="display: flex; align-items: center; background: white; padding: 15px; border-radius: 6px; border: 2px solid #e9ecef; cursor: pointer; transition: all 0.3s;">
                                <input type="checkbox" name="solicitar_videos" value="1" style="margin-right: 10px; width: 18px; height: 18px;"> 
                                <span style="font-size: 15px;">
                                    <i class="fas fa-video" style="color: #667eea; margin-right: 8px;"></i>
                                    Solicitar videos
                                </span>
                            </label>
                        </div>
                    </div>
                    
                    <div style="display: flex; gap: 15px; justify-content: center;">
                        <button type="submit" style="padding: 12px 40px; background: #667eea; color: white; border: none; border-radius: 8px; font-size: 16px; cursor: pointer; transition: all 0.3s; display: flex; align-items: center; gap: 10px;">
                            <i class="fas fa-paper-plane"></i> Enviar Solicitud
                        </button>
                        
                        <a href="<?php echo admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id); ?>" 
                           style="padding: 12px 40px; background: #6c757d; color: white; text-decoration: none; border-radius: 8px; font-size: 16px; transition: all 0.3s; display: inline-flex; align-items: center; gap: 10px;">
                            <i class="fas fa-times"></i> Cancelar
                        </a>
                    </div>
                </form>
            </div>
        </div>
        <?php
        die();
    }
    
    private static function procesar_rechazo_items($garantia_id, $items_seleccionados) {
        $motivo = sanitize_textarea_field($_POST['motivo_rechazo']);
        $rechazo_definitivo = isset($_POST['rechazo_definitivo']) && $_POST['rechazo_definitivo'] == '1';
        
        // Procesar archivos de evidencia
        $evidencia_urls = self::procesar_archivos_evidencia();
        
        // Actualizar items
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        $items_procesados = 0;
        
        if (is_array($items)) {
            foreach ($items as &$item) {
                if (isset($item['codigo_item']) && in_array($item['codigo_item'], $items_seleccionados)) {
                    // Obtener el estado actual del item
                    $estado_actual = isset($item['estado']) ? $item['estado'] : 'Pendiente';
                    
                    // Lógica mejorada para rechazo definitivo
                    if ($rechazo_definitivo) {
                        // Verificar si REALMENTE recibimos el producto
                        $producto_en_wifix = false;
                        
                        // Verificar estados que confirman que WiFix tiene el producto
                        if (in_array($estado_actual, ['recibido', 'aprobado', 'destruccion_subida'])) {
                            $producto_en_wifix = true;
                        }
                        
                        // Si tiene tracking pero está pendiente, NO lo tenemos aún
                        if ($estado_actual === 'devolucion_en_transito' || $estado_actual === 'aprobado_devolver') {
                            $producto_en_wifix = false;
                        }
                        
                        // Si tiene fecha de recibido, sí lo tenemos
                        if (isset($item['fecha_recibido']) && !empty($item['fecha_recibido'])) {
                            $producto_en_wifix = true;
                        }
                        
                        if ($producto_en_wifix) {
                            $item['estado'] = 'retorno_cliente';
                        } else {
                            // Si no lo tenemos físicamente, usar rechazado_no_recibido
                            $item['estado'] = 'rechazado_no_recibido';
                            // Limpiar datos de envío si existen
                            unset($item['pendiente_devolucion']);
                            unset($item['tracking_devolucion']);
                            unset($item['fecha_transito']);
                        }
                    } else {
                        $item['estado'] = 'rechazado';
                    }
                    $item['motivo_rechazo'] = $motivo;
                    $item['fecha_rechazo'] = current_time('mysql');
                    $item['rechazo_definitivo'] = $rechazo_definitivo;
                    $item['evidencia_rechazo'] = $evidencia_urls;
                    
                    if (!isset($item['historial_rechazos'])) {
                        $item['historial_rechazos'] = [];
                    }
                    $item['historial_rechazos'][] = [
                        'motivo' => $motivo,
                        'fecha' => current_time('mysql'),
                        'tipo' => 'rechazo_normal',
                        'definitivo' => $rechazo_definitivo,
                        'evidencia' => $evidencia_urls
                    ];
                    
                    $items_procesados++;
                }
            }
            
            update_post_meta($garantia_id, '_items_reclamados', $items);
            
            // Verificar si todos los items estn procesados
            $todos_procesados = true;
            foreach ($items as $item) {
                $estado = isset($item['estado']) ? $item['estado'] : 'Pendiente';
                // Estados FINALES son solo: aprobado, rechazado, retorno_cliente
                // Todo lo demás se considera "en proceso"
                if (!in_array($estado, ['aprobado', 'rechazado', 'retorno_cliente', 'rechazado_no_recibido'])) {
                    $todos_procesados = false;
                    break;
                }
            }
            
            // Si todos están procesados, marcar garanta como finalizada
            if ($todos_procesados) {
                update_post_meta($garantia_id, '_estado', 'finalizada');
            } else {
                WC_Garantias_Admin::actualizar_estado_garantia($garantia_id);
            }
            
            // Verificar si generar cupón (solo si hay items aprobados)
            self::verificar_generar_cupon($garantia_id, $items);
        }
        
        // Notificar al cliente
        $primer_item_codigo = !empty($items_seleccionados) ? $items_seleccionados[0] : 'SIN-ITEM';
        self::notificar_rechazo($garantia_id, $motivo, $rechazo_definitivo, $evidencia_urls, $primer_item_codigo);
        
        // Redirigir
        $mensaje_rechazo = '&items_rechazados=' . $items_procesados;
        if ($rechazo_definitivo) {
            $mensaje_rechazo .= '&rechazo_definitivo=1';
        }
        
        wp_redirect(admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id . $mensaje_rechazo));
        exit;
    }
    
    private static function procesar_archivos_evidencia() {
        $evidencia_urls = ['fotos' => [], 'video' => ''];
        
        if (!empty($_FILES['fotos_funcionamiento']['name'][0])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            
            for ($i = 0; $i < count($_FILES['fotos_funcionamiento']['name']); $i++) {
                if ($_FILES['fotos_funcionamiento']['error'][$i] === UPLOAD_ERR_OK) {
                    $file = [
                        'name' => $_FILES['fotos_funcionamiento']['name'][$i],
                        'type' => $_FILES['fotos_funcionamiento']['type'][$i],
                        'tmp_name' => $_FILES['fotos_funcionamiento']['tmp_name'][$i],
                        'error' => $_FILES['fotos_funcionamiento']['error'][$i],
                        'size' => $_FILES['fotos_funcionamiento']['size'][$i]
                    ];
                    
                    $upload = wp_handle_upload($file, ['test_form' => false]);
                    if (!isset($upload['error'])) {
                        $evidencia_urls['fotos'][] = $upload['url'];
                    }
                }
            }
        }
        
        if (!empty($_FILES['video_funcionamiento']['name'])) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
            $upload = wp_handle_upload($_FILES['video_funcionamiento'], ['test_form' => false]);
            if (!isset($upload['error'])) {
                $evidencia_urls['video'] = $upload['url'];
            }
        }
        
        return $evidencia_urls;
    }
    
    private static function verificar_generar_cupon($garantia_id, $items) {
        $todos_procesados = true;
        $hay_aprobados = false;
        
        foreach ($items as $item) {
            $estado_normalizado = strtolower(trim($item['estado']));
            
            if ($estado_normalizado === 'aprobado') {
                $hay_aprobados = true;
            }
            
            if (!in_array($estado_normalizado, ['aprobado', 'rechazado', 'retorno_cliente', 'rechazado_no_recibido'])) {
                $todos_procesados = false;
            }
        }
        
        if ($todos_procesados && $hay_aprobados && class_exists('WC_Garantias_Cupones')) {
            $codigo_cupon = WC_Garantias_Cupones::generar_cupon_garantia($garantia_id);
            if ($codigo_cupon) {
                update_post_meta($garantia_id, '_estado', 'finalizada');
            }
        }
    }
    
    private static function notificar_rechazo($garantia_id, $motivo, $rechazo_definitivo, $evidencia_urls, $item_codigo_procesado = '') {
        $cliente_id = get_post_meta($garantia_id, '_cliente', true);
        $user = get_userdata($cliente_id);
        
        if ($user && $user->user_email && class_exists('WC_Garantias_Emails')) {
            $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
            
            WC_Garantias_Emails::enviar_email('rechazada', $user->user_email, [
                'cliente' => $user->display_name,
                'codigo' => $codigo_unico,
                'motivo' => $motivo,
                'link_cuenta' => wc_get_account_endpoint_url('garantias'),
                'rechazo_definitivo' => $rechazo_definitivo,
                'evidencia' => $evidencia_urls,
                'item_codigo_procesado' => $item_codigo_procesado // NUEVA LÍNEA AGREGADA
            ]);
        }
    }
    
    private static function procesar_solicitud_info($garantia_id, $items_seleccionados) {
        $mensaje = sanitize_textarea_field($_POST['mensaje_info']);
        $solicitar_fotos = isset($_POST['solicitar_fotos']);
        $solicitar_videos = isset($_POST['solicitar_videos']);
        
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        $items_procesados = 0;
        
        if (is_array($items)) {
            foreach ($items as &$item) {
                if (isset($item['codigo_item']) && in_array($item['codigo_item'], $items_seleccionados)) {
                    $item['estado'] = 'solicitar_info';
                    
                    if (!isset($item['historial_solicitudes'])) {
                        $item['historial_solicitudes'] = [];
                    }
                    
                    $item['historial_solicitudes'][] = [
                        'fecha' => current_time('mysql'),
                        'mensaje' => $mensaje,
                        'solicitar_fotos' => $solicitar_fotos,
                        'solicitar_videos' => $solicitar_videos,
                        'respondido' => false
                    ];
                    
                    $items_procesados++;
                }
            }
            
            update_post_meta($garantia_id, '_items_reclamados', $items);
            WC_Garantias_Admin::actualizar_estado_garantia($garantia_id);
        }
        
        // Notificar al cliente
        self::notificar_solicitud_info($garantia_id, $mensaje, $solicitar_fotos, $solicitar_videos);
        
        wp_redirect(admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id . '&info_solicitada=1'));
        exit;
    }
    
    private static function notificar_solicitud_info($garantia_id, $mensaje, $solicitar_fotos, $solicitar_videos) {
        $cliente_id = get_post_meta($garantia_id, '_cliente', true);
        $user = get_userdata($cliente_id);
        
        if ($user && $user->user_email && class_exists('WC_Garantias_Emails')) {
            $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
            
            $tipo_informacion = [];
            if ($solicitar_fotos) $tipo_informacion[] = "Fotos adicionales";
            if ($solicitar_videos) $tipo_informacion[] = "Videos";
            
            WC_Garantias_Emails::enviar_email('info_solicitada', $user->user_email, [
                'cliente' => $user->display_name,
                'codigo' => $codigo_unico,
                'mensaje_admin' => $mensaje,
                'tipo_informacion' => implode(' y ', $tipo_informacion),
                'link_cuenta' => wc_get_account_endpoint_url('garantias'),
                'garantia_id' => $garantia_id
            ]);
        }
    }
    
    private static function marcar_items_recibidos($garantia_id, $items_seleccionados) {

    
    $items = get_post_meta($garantia_id, '_items_reclamados', true);

    $items_procesados = 0;
    
    if (is_array($items)) {
        foreach ($items as $key => &$item) {
            error_log("Procesando item índice $key");
            error_log("Código item: " . ($item['codigo_item'] ?? 'NO TIENE'));
            error_log("Estado actual: " . ($item['estado'] ?? 'NO TIENE'));
            
            if (isset($item['codigo_item']) && in_array($item['codigo_item'], $items_seleccionados)) {
                error_log("Item {$item['codigo_item']} COINCIDE con seleccionados");
                
                $estado_actual = isset($item['estado']) ? $item['estado'] : 'Pendiente';
                $estado_normalizado = strtolower($estado_actual);
                
                error_log("Estado normalizado: $estado_normalizado");
                
                $estados_permitidos = [
                    'pendiente',
                    'devolucion_en_transito',
                    'aprobado_devolver',
                    'aprobado_destruir',
                    'solicitar_info',
                    'esperando_recepcion'
                ];
                
                if (in_array($estado_normalizado, $estados_permitidos)) {
                    error_log("Estado PERMITIDO - Cambiando a recibido");
                    $item['estado'] = 'recibido';
                    $item['fecha_recibido'] = current_time('mysql');
                    $items_procesados++;
                } else {
                    error_log("Estado NO PERMITIDO: $estado_normalizado");
                }
            } else {
                error_log("Item NO coincide o no tiene codigo_item");
            }
        }
        
        error_log("Total items procesados: $items_procesados");
        
        if ($items_procesados > 0) {
            update_post_meta($garantia_id, '_items_reclamados', $items);
            WC_Garantias_Admin::actualizar_estado_garantia($garantia_id);
            error_log("Metadata actualizada");
        }
    } else {
        error_log("ERROR: Items no es un array!");
    }
    
    wp_redirect(admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id . '&items_recibidos=' . $items_procesados));
    exit;
}
    
    private static function aprobar_items($garantia_id, $items_seleccionados) {
    $items = get_post_meta($garantia_id, '_items_reclamados', true);
    $items_procesados = 0;
    
    if (is_array($items)) {
        foreach ($items as &$item) {
            if (isset($item['codigo_item']) && in_array($item['codigo_item'], $items_seleccionados)) {
                $estado_actual = isset($item['estado']) ? $item['estado'] : 'Pendiente';
                
                // Normalizar estado para comparación
                $estado_normalizado = strtolower($estado_actual);
                
                // Estados desde los cuales se puede aprobar
                $estados_permitidos = [
                    'pendiente',
                    'recibido',
                    'destruccion_subida',
                    'apelacion',
                    'aprobado_devolver',  // Permitir aprobar desde este estado
                    'aprobado_destruir'   // Y desde este también
                ];
                
                if (in_array($estado_normalizado, $estados_permitidos)) {
                    $item['estado'] = 'aprobado';
                    $item['fecha_aprobacion'] = current_time('mysql');
                    
                    // Si hay destruccin confirmada, marcarla como aprobada
                    if (isset($item['destruccion']) && $item['destruccion']['confirmado']) {
                        $item['destruccion']['aprobada'] = true;
                        $item['destruccion']['fecha_aprobacion'] = current_time('mysql');
                    }
                    
                    $items_procesados++;
                    
                    error_log("Item {$item['codigo_item']} cambiado de {$estado_actual} a aprobado");
                }
            }
        }
        
        if ($items_procesados > 0) {
            update_post_meta($garantia_id, '_items_reclamados', $items);
            WC_Garantias_Admin::actualizar_estado_garantia($garantia_id);
            
            // Verificar si generar cupón
            self::verificar_generar_cupon($garantia_id, $items);
        }
    }
    
    $redirect_params = [
        'page' => 'wc-garantias-ver',
        'garantia_id' => $garantia_id,
        'items_aprobados' => $items_procesados
    ];
    
    wp_redirect(add_query_arg($redirect_params, admin_url('admin.php')));
    exit;
}
    
    private static function cambiar_estado_items($garantia_id, $items_seleccionados, $nuevo_estado) {
    $items = get_post_meta($garantia_id, '_items_reclamados', true);
    $items_procesados = 0;
    
    if (is_array($items)) {
        foreach ($items as &$item) {
            if (isset($item['codigo_item']) && in_array($item['codigo_item'], $items_seleccionados)) {
                $item['estado'] = $nuevo_estado;
                
                if ($nuevo_estado === 'aprobado_destruir') {
                    $item['pendiente_destruccion'] = true;
                } elseif ($nuevo_estado === 'aprobado_devolver') {
                    $item['pendiente_devolucion'] = true;
                }
                
                $items_procesados++;
            }
        }
        
        update_post_meta($garantia_id, '_items_reclamados', $items);
        WC_Garantias_Admin::actualizar_estado_garantia($garantia_id);
    }
    
    // NUEVO: Notificar al cliente según el estado
    $cliente_id = get_post_meta($garantia_id, '_cliente', true);
    $user = get_userdata($cliente_id);
    
    if ($user && $user->user_email && class_exists('WC_Garantias_Emails')) {
        $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
        $primer_item_codigo = !empty($items_seleccionados) ? $items_seleccionados[0] : 'SIN-ITEM';
        
        if ($nuevo_estado === 'aprobado_destruir') {
            // Enviar notificacin para destrucción
            WC_Garantias_Emails::enviar_email('destruccion_aprobada', $user->user_email, [
                'cliente' => $user->display_name,
                'codigo' => $codigo_unico,
                'link_cuenta' => wc_get_account_endpoint_url('garantias'),
                'garantia_id' => $garantia_id,
                'item_codigo_procesado' => $primer_item_codigo
            ]);
        } elseif ($nuevo_estado === 'aprobado_devolver') {
            // NO enviar notificación aquí - se enviará cuando se genere la etiqueta
            // O si quieres enviar un mensaje simple sin mencionar etiqueta:
            /*
            WC_Garantias_Emails::enviar_email('devolucion_aprobada_simple', $user->user_email, [
                'cliente' => $user->display_name,
                'codigo' => $codigo_unico,
                'link_cuenta' => wc_get_account_endpoint_url('garantias'),
                'item_codigo_procesado' => $primer_item_codigo
            ]);
            */
        }
    }
    // FIN DE LA NOTIFICACIN
    
    $mensaje_param = '';
    if ($nuevo_estado === 'aprobado_destruir') {
        $mensaje_param = '&items_destruir=1';
    } elseif ($nuevo_estado === 'aprobado_devolver') {
        $mensaje_param = '&items_devolver=1';
    }
    
    wp_redirect(admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id . '&items_procesados=' . $items_procesados . $mensaje_param));
    exit;
}
    
    private static function procesar_accion_item($garantia_id) {
        $accion = sanitize_text_field($_POST['accion_item']);
        $codigo_item = sanitize_text_field($_POST['item_codigo']);
        
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        
        if (is_array($items)) {
            foreach ($items as &$item) {
                if (isset($item['codigo_item']) && $item['codigo_item'] === $codigo_item) {
                    switch ($accion) {
                        case 'marcar_recibido':
                            $item['estado'] = 'recibido';
                            $item['fecha_recibido'] = current_time('mysql');
                            break;
                            
                        case 'aprobar_individual':
                            $item['estado'] = 'aprobado';
                            $item['fecha_aprobado'] = current_time('mysql');
                            break;
                            
                        case 'rechazar_individual':
                            $item['estado'] = 'rechazado';
                            $item['fecha_rechazo'] = current_time('mysql');
                            if (isset($_POST['motivo_rechazo'])) {
                                $item['motivo_rechazo'] = sanitize_textarea_field($_POST['motivo_rechazo']);
                            }
                            break;
                    }
                    break;
                }
            }
            
            update_post_meta($garantia_id, '_items_reclamados', $items);
            WC_Garantias_Admin::actualizar_estado_garantia($garantia_id);
        }
        
        wp_redirect(admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id . '&accion_realizada=1'));
        exit;
    }
    
    private static function procesar_devolucion_recibida($garantia_id) {
        $items_recibidos = isset($_POST['items_recibidos']) ? array_map('sanitize_text_field', $_POST['items_recibidos']) : [];
        
        if (empty($items_recibidos)) {
            wp_redirect(admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id . '&error=no_items'));
            exit;
        }
        
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        $items_procesados = 0;
        
        if (is_array($items)) {
            foreach ($items as &$item) {
                if (isset($item['codigo_item']) && in_array($item['codigo_item'], $items_recibidos)) {
                    if ($item['estado'] === 'devolucion_en_transito') {
                        $item['estado'] = 'recibido';
                        $item['fecha_devolucion_recibida'] = current_time('mysql');
                        $items_procesados++;
                    }
                }
            }
            
            update_post_meta($garantia_id, '_items_reclamados', $items);
            
            // Actualizar meta de devolucin recibida
            update_post_meta($garantia_id, '_devolucion_recibida', true);
            update_post_meta($garantia_id, '_fecha_devolucion_recibida', current_time('mysql'));
            
            WC_Garantias_Admin::actualizar_estado_garantia($garantia_id);
        }
        
        // Notificar al cliente si está configurado
        $cliente_id = get_post_meta($garantia_id, '_cliente', true);
        $user = get_userdata($cliente_id);
        
        if ($user && $user->user_email && class_exists('WC_Garantias_Emails')) {
            $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
            
            WC_Garantias_Emails::enviar_email('devolucion_recibida', $user->user_email, [
                'cliente' => $user->display_name,
                'codigo' => $codigo_unico,
                'items_recibidos' => $items_procesados,
                'link_cuenta' => wc_get_account_endpoint_url('garantias')
            ]);
        }
        
        wp_redirect(admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id . '&devolucion_recibida=' . $items_procesados));
        exit;
    }
    
    private static function procesar_tracking($garantia_id) {
        $numero_tracking = sanitize_text_field($_POST['numero_tracking']);
        $empresa_envio = isset($_POST['empresa_envio']) ? sanitize_text_field($_POST['empresa_envio']) : 'andreani';
        
        if (empty($numero_tracking)) {
            wp_redirect(admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id . '&error=tracking_vacio'));
            exit;
        }
        
        // Guardar tracking
        update_post_meta($garantia_id, '_numero_tracking_devolucion', $numero_tracking);
        update_post_meta($garantia_id, '_empresa_envio', $empresa_envio);
        update_post_meta($garantia_id, '_fecha_tracking_agregado', current_time('mysql'));
        
        // Si hay items específicos, actualizar su estado
        if (isset($_POST['items_tracking'])) {
            $items_tracking = array_map('sanitize_text_field', $_POST['items_tracking']);
            $items = get_post_meta($garantia_id, '_items_reclamados', true);
            
            if (is_array($items)) {
                foreach ($items as &$item) {
                    if (isset($item['codigo_item']) && in_array($item['codigo_item'], $items_tracking)) {
                        $item['tracking_devolucion'] = $numero_tracking;
                        $item['empresa_envio'] = $empresa_envio;
                        
                        // Si el item est aprobado para devolver, cambiar a en tránsito
                        if ($item['estado'] === 'aprobado_devolver') {
                            $item['estado'] = 'devolucion_en_transito';
                        }
                    }
                }
                
                update_post_meta($garantia_id, '_items_reclamados', $items);
                WC_Garantias_Admin::actualizar_estado_garantia($garantia_id);
            }
        }
        
        // Notificar al cliente del tracking
        $cliente_id = get_post_meta($garantia_id, '_cliente', true);
        $user = get_userdata($cliente_id);
        
        if ($user && $user->user_email && class_exists('WC_Garantias_Emails')) {
            $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
            
            WC_Garantias_Emails::enviar_email('tracking_agregado', $user->user_email, [
                'cliente' => $user->display_name,
                'codigo' => $codigo_unico,
                'numero_tracking' => $numero_tracking,
                'empresa_envio' => $empresa_envio,
                'link_seguimiento' => "http://andreani.com/envio/{$numero_tracking}",
                'link_cuenta' => wc_get_account_endpoint_url('garantias')
            ]);
        }
        
        wp_redirect(admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id . '&tracking_agregado=1'));
        exit;
    }
    
    private static function render_html($garantia_id, $datos) {
     // Cargar el renderizador si existe
        $render_path = plugin_dir_path(__FILE__) . 'class-wc-garantias-admin-view-render.php';
        if (file_exists($render_path)) {
            require_once $render_path;
            if (class_exists('WC_Garantias_Admin_View_Render')) {
                WC_Garantias_Admin_View_Render::render($garantia_id, $datos);
                return;
            }
        }
            
        // Fallback si no existe el archivo
        ?>
        <div class="wrap">
            <h1>Ver Garantía - <?php echo esc_html($datos['codigo_unico']); ?></h1>
            <p>Error: No se pudo cargar el mdulo de renderizado.</p>
            <a href="<?php echo admin_url('admin.php?page=wc-garantias'); ?>" class="button">Volver al listado</a>
        </div>
        <?php
    }
}