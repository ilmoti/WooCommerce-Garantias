<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Procesador centralizado para garantías
 * Elimina código duplicado y centraliza la lógica
 */
class WC_Garantias_Processor {
    
    /**
     * Validar que el usuario puede modificar la garantía
     */
    public static function validate_user_permission($garantia_id, $customer_id) {
        $garantia = get_post($garantia_id);
        return $garantia && $garantia->post_author == $customer_id;
    }
    
    /**
     * Procesar subida de archivos (fotos y videos)
     */
    public static function process_file_uploads($files_data, $type = 'mixed') {
        $uploaded_files = [];
        
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        // NUEVO: Manejar arrays de archivos múltiples correctamente
        if (isset($files_data['name']) && is_array($files_data['name'])) {
            // Formato múltiple: $_FILES['fotos_adicionales']
            $num_files = count($files_data['name']);
            for ($i = 0; $i < $num_files; $i++) {
                if (!empty($files_data['name'][$i]) && $files_data['error'][$i] === UPLOAD_ERR_OK) {
                    $file_info = [
                        'name'     => $files_data['name'][$i],
                        'type'     => $files_data['type'][$i], 
                        'tmp_name' => $files_data['tmp_name'][$i],
                        'error'    => $files_data['error'][$i],
                        'size'     => $files_data['size'][$i]
                    ];
                    
                    // Validar tamaño según tipo
                    $max_size = ($type === 'video') ? 50 * 1024 * 1024 : 5 * 1024 * 1024;
                    
                    if ($file_info['size'] > $max_size) {
                        $max_mb = ($type === 'video') ? '50MB' : '5MB';
                        garantias_set_mensaje("El archivo es demasiado grande. Máximo {$max_mb}.", 'error');
                        continue;
                    }
                    
                    $upload = wp_handle_upload($file_info, ['test_form' => false]);
                    if (!isset($upload['error'])) {
                        $uploaded_files[] = $upload['url'];
                    }
                }
            }
        } else {
            // Formato individual: archivo único
            foreach ($files_data as $key => $file_info) {
                if (!empty($file_info['name']) && $file_info['error'] === UPLOAD_ERR_OK) {
                    
                    // Validar tamaño según tipo
                    $max_size = ($type === 'video') ? 50 * 1024 * 1024 : 5 * 1024 * 1024;
                    
                    if ($file_info['size'] > $max_size) {
                        $max_mb = ($type === 'video') ? '50MB' : '5MB';
                        garantias_set_mensaje("El archivo es demasiado grande. Máximo {$max_mb}.", 'error');
                        continue;
                    }
                    
                    $upload = wp_handle_upload($file_info, ['test_form' => false]);
                    if (!isset($upload['error'])) {
                        $uploaded_files[] = $upload['url'];
                    }
                }
            }
        }
        
        return $uploaded_files;
    }
    
    /**
     * Procesar respuesta de información del cliente
     */
    public static function process_info_response($garantia_id, $customer_id, $item_index = null, $comentario = '') {
        if (!self::validate_user_permission($garantia_id, $customer_id)) {
            garantias_set_mensaje('No tienes permiso para modificar esta garantía', 'error');
            return false;
        }
        
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        if (!is_array($items)) {
            return false;
        }
        
        // Procesar archivos
        $archivos_subidos = [];
        if (isset($_FILES['fotos_adicionales'])) {
            $fotos = self::process_file_uploads($_FILES['fotos_adicionales'], 'foto');
            if (!empty($fotos)) {
                $archivos_subidos['fotos'] = $fotos;
            }
        }
        
        if (isset($_FILES['videos_adicionales'])) {
            $videos = self::process_file_uploads($_FILES['videos_adicionales'], 'video');
            if (!empty($videos)) {
                $archivos_subidos['videos'] = $videos;
            }
        }
        
        // Actualizar items
        $actualizado = false;
        
        if ($item_index !== null) {
            // Respuesta a item específico
            $actualizado = self::update_specific_item_response($items, $item_index, $archivos_subidos, $comentario);
        } else {
            // Respuesta general
            $actualizado = self::update_general_response($items, $archivos_subidos, $comentario);
        }
        
        if ($actualizado) {
            update_post_meta($garantia_id, '_items_reclamados', $items);
            update_post_meta($garantia_id, '_estado', 'nueva');
            
            garantias_set_mensaje('Información enviada correctamente. El administrador la revisará pronto.', 'success');
            
            // Notificar al admin
            self::notify_admin_response($garantia_id, $archivos_subidos, $comentario);
            // Guardar el ID de la garantía en sesión para volver al detalle
            if (!session_id()) {
                session_start();
            }
            $_SESSION['garantia_volver_detalle'] = $garantia_id;
        }
        
        return $actualizado;
    }
    
    /**
     * Actualizar respuesta de item específico
     */
    private static function update_specific_item_response(&$items, $item_index, $archivos_subidos, $comentario) {
        if (!isset($items[$item_index])) {
            return false;
        }
        
        $item = &$items[$item_index];
        if ($item['estado'] === 'solicitar_info' && isset($item['historial_solicitudes'])) {
            $ultima_solicitud = &$item['historial_solicitudes'][count($item['historial_solicitudes']) - 1];
            if (!$ultima_solicitud['respondido']) {
                $ultima_solicitud['respondido'] = true;
                $ultima_solicitud['fecha_respuesta'] = current_time('mysql');
                $ultima_solicitud['archivos_respuesta'] = $archivos_subidos;
                $ultima_solicitud['comentario'] = $comentario;
                $item['estado'] = 'Pendiente';
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Actualizar respuesta general
     */
    private static function update_general_response(&$items, $archivos_subidos, $comentario) {
        $actualizado = false;
        
        foreach ($items as &$item) {
            if (isset($item['estado']) && $item['estado'] === 'solicitar_info' && isset($item['historial_solicitudes'])) {
                $num_solicitudes = count($item['historial_solicitudes']);
                if ($num_solicitudes > 0) {
                    $ultima_solicitud = &$item['historial_solicitudes'][$num_solicitudes - 1];
                    if (isset($ultima_solicitud['respondido']) && !$ultima_solicitud['respondido']) {
                        $ultima_solicitud['respondido'] = true;
                        $ultima_solicitud['fecha_respuesta'] = current_time('mysql');
                        $ultima_solicitud['archivos_respuesta'] = $archivos_subidos;
                        $ultima_solicitud['comentario'] = $comentario;
                        $item['estado'] = 'Pendiente';
                        $actualizado = true;
                    }
                }
            }
        }
        
        return $actualizado;
    }
    
    /**
     * Notificar al administrador
     */
    private static function notify_admin_response($garantia_id, $archivos_subidos, $comentario) {
        $admin_email = get_option('admin_email_garantias', get_option('admin_email'));
        $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
        
        $archivos_info = '';
        if (!empty($archivos_subidos['fotos'])) {
            $archivos_info .= "📷 Fotos subidas: " . count($archivos_subidos['fotos']) . "\n";
        }
        if (!empty($archivos_subidos['videos'])) {
            $archivos_info .= "🎥 Videos subidos: " . count($archivos_subidos['videos']) . "\n";
        }
        
        $comentario_cliente = '';
        if (!empty($comentario)) {
            $comentario_cliente = "\n💬 Comentario del cliente:\n" . $comentario;
        }
        
        // Enviar solo email al admin (sin WhatsApp al cliente)
        $asunto = "Respuesta del cliente recibida - " . $codigo_unico;
        $mensaje = "El cliente ha respondido a la solicitud de informacin para la garantía: " . $codigo_unico . "\n\n";
        
        if ($archivos_info) {
            $mensaje .= $archivos_info . "\n";
        }
        
        if ($comentario_cliente) {
            $mensaje .= $comentario_cliente . "\n\n";
        }
        
        $mensaje .= "Ver en admin: " . admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id);
        
        wp_mail($admin_email, $asunto, $mensaje);
    }
    /**
     * Procesar destrucción de item individual
     */
    public static function process_item_destruction($garantia_id, $customer_id, $item_index) {
        if (!self::validate_user_permission($garantia_id, $customer_id)) {
            garantias_set_mensaje('No tienes permiso para modificar esta garantía', 'error');
            return false;
        }
        
        // Verificar checkbox de confirmación
        if (!isset($_POST['confirmo_destruccion'])) {
            garantias_set_mensaje('Debes confirmar la destrucción', 'error');
            return false;
        }
        
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        if (!is_array($items) || !isset($items[$item_index])) {
            return false;
        }
        
        // Procesar archivos de destrucción
        $foto_url = '';
        $video_url = '';
        
        if (isset($_FILES['foto_destruccion']) && $_FILES['foto_destruccion']['error'] === UPLOAD_ERR_OK) {
            $fotos = self::process_file_uploads([$_FILES['foto_destruccion']], 'foto');
            if (!empty($fotos)) {
                $foto_url = $fotos[0];
            }
        }
        
        if (isset($_FILES['video_destruccion']) && $_FILES['video_destruccion']['error'] === UPLOAD_ERR_OK) {
            $videos = self::process_file_uploads([$_FILES['video_destruccion']], 'video');
            if (!empty($videos)) {
                $video_url = $videos[0];
            }
        }
        
        // Actualizar item
        $items[$item_index]['estado'] = 'destruccion_subida';
        $items[$item_index]['destruccion'] = [
            'fecha' => current_time('mysql'),
            'foto_url' => $foto_url,
            'video_url' => $video_url,
            'confirmado' => true
        ];
        
        update_post_meta($garantia_id, '_items_reclamados', $items);
        garantias_set_mensaje('Evidencia de destruccion subida correctamente.', 'success');
        
        // Notificar admin
        self::notify_admin_destruction($garantia_id, $foto_url, $video_url);
        
        // Guardar el ID de la garantía en sesión para volver al detalle
        if (!session_id()) {
            session_start();
        }
        $_SESSION['garantia_volver_detalle'] = $garantia_id;
        
        return true;
    }
    
    /**
     * Procesar apelación de item
     */
    public static function process_item_appeal($garantia_id, $customer_id, $item_index, $razon) {
        if (!self::validate_user_permission($garantia_id, $customer_id)) {
            garantias_set_mensaje('No tienes permiso para modificar esta garantía', 'error');
            return false;
        }
        
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        if (!is_array($items) || !isset($items[$item_index])) {
            return false;
        }
        
        // Procesar archivos opcionales
        $foto_url = '';
        $video_url = '';
        
        if (isset($_FILES['foto_apelacion']) && $_FILES['foto_apelacion']['error'] === UPLOAD_ERR_OK) {
            $fotos = self::process_file_uploads([$_FILES['foto_apelacion']], 'foto');
            if (!empty($fotos)) {
                $foto_url = $fotos[0];
            }
        }
        
        if (isset($_FILES['video_apelacion']) && $_FILES['video_apelacion']['error'] === UPLOAD_ERR_OK) {
            $videos = self::process_file_uploads([$_FILES['video_apelacion']], 'video');
            if (!empty($videos)) {
                $video_url = $videos[0];
            }
        }
        
        // Actualizar item
        $items[$item_index]['estado'] = 'apelacion';
        if (!isset($items[$item_index]['historial_apelaciones'])) {
            $items[$item_index]['historial_apelaciones'] = [];
        }
        $items[$item_index]['historial_apelaciones'][] = [
            'fecha' => current_time('mysql'),
            'motivo' => $razon,
            'foto_url' => $foto_url,
            'video_url' => $video_url
        ];
        
        // Mantener también la última para compatibilidad
        $items[$item_index]['apelacion'] = [
            'fecha' => current_time('mysql'),
            'motivo' => $razon,
            'foto_url' => $foto_url,
            'video_url' => $video_url
        ];
        
        update_post_meta($garantia_id, '_items_reclamados', $items);
        garantias_set_mensaje('Apelacion enviada correctamente. La revisaremos pronto.', 'success');
        
        // Notificar admin
        self::notify_admin_appeal($garantia_id, $items[$item_index], $razon, $foto_url, $video_url);
        
        // Guardar el ID de la garantía en sesión para volver al detalle
        if (!session_id()) {
            session_start();
        }
        $_SESSION['garantia_volver_detalle'] = $garantia_id;
        
        return true;
    }
    
    /**
     * Notificar admin sobre destruccion
     */
    private static function notify_admin_destruction($garantia_id, $foto_url, $video_url) {
        $admin_email = get_option('admin_email_garantias', get_option('admin_email'));
        $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
        
        $archivos_destruccion = '';
        if ($foto_url) {
            $archivos_destruccion .= "Foto: {$foto_url}\n";
        }
        if ($video_url) {
            $archivos_destruccion .= "Video: {$video_url}\n";
        }
        
        // Enviar solo email al admin (sin WhatsApp al cliente)
        $asunto = "Evidencia de destrucción subida - " . $codigo_unico;
        $mensaje = "Se ha subido evidencia de destrucción para la garantía: " . $codigo_unico . "\n\n";
        $mensaje .= "Archivos:\n" . $archivos_destruccion . "\n\n";
        $mensaje .= "Ver en admin: " . admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id);
        
        wp_mail($admin_email, $asunto, $mensaje);
    }
    
    /**
     * Notificar admin sobre apelación
     */
    private static function notify_admin_appeal($garantia_id, $item, $razon, $foto_url, $video_url) {
        $admin_email = get_option('admin_email_garantias', get_option('admin_email'));
        $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
        $cliente_id = get_post_meta($garantia_id, '_cliente', true);
        
        $archivos_adjuntos = '';
        if ($foto_url) {
            $archivos_adjuntos .= "Nueva foto: {$foto_url}\n";
        }
        if ($video_url) {
            $archivos_adjuntos .= "Nuevo video: {$video_url}\n";
        }
        
        // Enviar solo email al admin (sin WhatsApp al cliente)
        $asunto = "Nueva apelacion recibida - " . $codigo_unico;
        $mensaje = "Se ha recibido una nueva apelación para la garantía: " . $codigo_unico . "\n\n";
        $mensaje .= "Cliente: " . get_userdata($cliente_id)->display_name . "\n";
        $mensaje .= "Item: " . $item['codigo_item'] . "\n";
        $mensaje .= "Razón de la apelación: " . $razon . "\n\n";
        if ($archivos_adjuntos) {
            $mensaje .= "Archivos adjuntos:\n" . $archivos_adjuntos . "\n";
        }
        $mensaje .= "Ver en admin: " . admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id);
        
        wp_mail($admin_email, $asunto, $mensaje);
    }
    /**
     * Procesar nuevo formulario de garanta
     */
    public static function process_new_warranty_form() {
        if (!($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['garantia_form_submit']) && is_account_page())) {
            return false;
        }
        
        $customer_id = get_current_user_id();
        if (!$customer_id) {
            return false;
        }
        
        $productos_post = isset($_POST['producto']) ? $_POST['producto'] : array();
        $cantidades_post = $_POST['cantidad'];
        $motivos_post = $_POST['motivo'];
        $otros_post = $_POST['motivo_otro'];
        $fotos_files = $_FILES['foto'];
        $videos_files = $_FILES['video'];

        // Obtener pedidos completados para buscar order_id
        $orders = wc_get_orders([
            'customer_id' => $customer_id,
            'status'      => 'completed',
            'limit'       => -1,
        ]);

        // Preparar array de items
        $items_guardar = [];
        foreach($productos_post as $i => $producto_id) {
            $producto_id = sanitize_text_field($producto_id);
            $cantidad = max(1, intval($cantidades_post[$i] ?? 1));
            $motivo_sel = isset($motivos_post[$i]) ? $motivos_post[$i] : '';
            $motivo_otro = isset($otros_post[$i]) ? sanitize_text_field($otros_post[$i]) : '';
            
            if ($motivo_sel === 'Otro' && !empty($motivo_otro)) {
                $motivo_str = 'Otro: ' . $motivo_otro;
            } else {
                $motivo_str = $motivo_sel;
            }

            // Procesar foto
            $foto_url = '';
            if (!empty($fotos_files['name'][$i]) && $fotos_files['error'][$i] === UPLOAD_ERR_OK) {
                $file = [
                    'name'     => $fotos_files['name'][$i],
                    'type'     => $fotos_files['type'][$i],
                    'tmp_name' => $fotos_files['tmp_name'][$i],
                    'error'    => $fotos_files['error'][$i],
                    'size'     => $fotos_files['size'][$i]
                ];
                
                $fotos = self::process_file_uploads([$file], 'foto');
                if (!empty($fotos)) {
                    $foto_url = $fotos[0];
                }
            }

            // Procesar video
            $video_url = '';
            if (!empty($videos_files['name'][$i]) && $videos_files['error'][$i] === UPLOAD_ERR_OK) {
                $file = [
                    'name'     => $videos_files['name'][$i],
                    'type'     => $videos_files['type'][$i],
                    'tmp_name' => $videos_files['tmp_name'][$i],
                    'error'    => $videos_files['error'][$i],
                    'size'     => $videos_files['size'][$i]
                ];
                
                $videos = self::process_file_uploads([$file], 'video');
                if (!empty($videos)) {
                    $video_url = $videos[0];
                }
            }

            // Buscar order_id más reciente
            $order_id = null;
            $precio_unitario = 0;
            $nombre_producto_guardado = '';
            
            // Primero intentar obtener el producto
            $producto_temp = wc_get_product($producto_id);
            
            if ($producto_temp) {
                // Si el producto existe, usar su precio actual
                $precio_unitario = $producto_temp->get_price();
                $nombre_producto_guardado = $producto_temp->get_name();
            }
            
            // Buscar en las órdenes
            foreach ($orders as $order) {
                foreach ($order->get_items() as $item) {
                    if ($item->get_product_id() == $producto_id) {
                        $order_id = $order->get_id();
                        
                        // Si el producto NO existe, obtener precio de la orden
                        if (!$producto_temp) {
                            $precio_unitario = $item->get_total() / $item->get_quantity();
                            $nombre_producto_guardado = $item->get_name();
                        }
                        break 2;
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
                'precio_unitario' => $precio_unitario,  // NUEVO
                'nombre_producto' => $nombre_producto_guardado,  // NUEVO
            ];
        }

        // Crear post de garantia
        $garantia_post = [
            'post_type'   => 'garantia',
            'post_status' => 'publish',
            'post_title'  => 'Garantía - ' . $customer_id . ' - ' . date('Y-m-d H:i:s'),
            'post_author' => $customer_id,
        ];

        $post_id = wp_insert_post($garantia_post);

        if ($post_id && !is_wp_error($post_id)) {
            $codigo_unico = 'GRT-' . date('Ymd') . '-' . strtoupper(wp_generate_password(5, false, false));
            update_post_meta($post_id, '_codigo_unico', $codigo_unico);
            update_post_meta($post_id, '_cliente', $customer_id);
            update_post_meta($post_id, '_fecha', current_time('mysql'));
            update_post_meta($post_id, '_estado', 'nueva');
            update_post_meta($post_id, '_items_reclamados', $items_guardar);
            
            // Enviar emails
            self::send_warranty_confirmation_emails($customer_id, $codigo_unico, $post_id);
            
            // Redirigir
            wp_redirect(add_query_arg('garantia_success', '1', wc_get_account_endpoint_url('garantias')));
            exit;
        }
        
        return false;
    }
    
    /**
     * Procesar todas las acciones POST del frontend
     */
    public static function process_frontend_actions() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !is_account_page()) {
            return;
        }
        
        $customer_id = get_current_user_id();
        if (!$customer_id) {
            return;
        }
        
        // Procesar según la accin
        if (isset($_POST['subir_tracking'])) {
            self::process_tracking_upload();
        } elseif (isset($_POST['responder_solicitud_info'])) {
            self::process_info_response($_POST['garantia_id'], $customer_id, null, $_POST['comentario_respuesta'] ?? '');
            wp_redirect(wc_get_account_endpoint_url('garantias'));
            exit; 
        } elseif (isset($_POST['responder_info_item'])) {
            self::process_info_response($_POST['garantia_id'], $customer_id, $_POST['item_index'], $_POST['comentario_respuesta'] ?? '');
            wp_redirect(wc_get_account_endpoint_url('garantias')); 
            exit; 
        } elseif (isset($_POST['destruir_item'])) {
            self::process_item_destruction($_POST['garantia_id'], $customer_id, $_POST['item_index']);
            wp_redirect(wc_get_account_endpoint_url('garantias')); 
            exit; 
        } elseif (isset($_POST['apelar_item'])) {
            self::process_item_appeal($_POST['garantia_id'], $customer_id, $_POST['item_index'], $_POST['razon_apelacion']);
            wp_redirect(wc_get_account_endpoint_url('garantias'));
            exit; 
        } elseif (isset($_POST['garantia_form_submit'])) {
            self::process_new_warranty_form();
        } elseif (isset($_POST['devolucion_form_submit'])) {
            self::process_devolucion_form();
        } elseif (isset($_POST['actualizar_tracking_devolucion'])) {
            self::process_tracking_devolucion();
        }
    }
    
    /**
     * Procesar subida de tracking
     */
    private static function process_tracking_upload() {
        $garantia_id = intval($_POST['garantia_id']);
        $customer_id = get_current_user_id();
        
        if (!self::validate_user_permission($garantia_id, $customer_id)) {
            garantias_set_mensaje('No tienes permiso para modificar esta garantía', 'error');
            wp_redirect(wc_get_account_endpoint_url('garantias'));
            exit;
        }
        
        $numero_tracking = sanitize_text_field($_POST['numero_tracking']);
        
        // Procesar foto de tracking
        $foto_url = '';
        if (!empty($_FILES['foto_tracking']['name'])) {
            $fotos = self::process_file_uploads([$_FILES['foto_tracking']], 'foto');
            if (!empty($fotos)) {
                $foto_url = $fotos[0];
            } else {
                garantias_set_mensaje('Error al subir la foto del tracking', 'error');
                wp_redirect(wc_get_account_endpoint_url('garantias'));
                exit;
            }
        }
        
        if ($foto_url && !empty($numero_tracking)) {
            // Actualizar datos
            update_post_meta($garantia_id, '_tracking_devolucion', $numero_tracking);
            update_post_meta($garantia_id, '_fecha_tracking', current_time('mysql'));
            update_post_meta($garantia_id, '_foto_tracking_url', $foto_url);
            update_post_meta($garantia_id, '_estado', 'devolucion_en_transito');
            
            garantias_set_mensaje('Información de envío recibida correctamente. Número de tracking: ' . $numero_tracking, 'success');
            
            // Notificar al admin
            self::notify_admin_tracking($garantia_id, $numero_tracking, $foto_url);
        } else {
            garantias_set_mensaje('Por favor completa todos los campos requeridos', 'error');
        }
        
        // Guardar el ID de la garantía en sesión para volver al detalle
        if (!session_id()) {
            session_start();
        }
        $_SESSION['garantia_volver_detalle'] = $garantia_id;
        
        wp_redirect(wc_get_account_endpoint_url('garantias'));
        exit;
    }
    
    
    
    /**
     * Enviar emails de confirmación
     */
    private static function send_warranty_confirmation_emails($customer_id, $codigo_unico, $post_id) {
        // Email al cliente
        $user = get_userdata($customer_id);
        if ($user && $user->user_email && class_exists('WC_Garantias_Emails')) {
            WC_Garantias_Emails::enviar_email('confirmacion', $user->user_email, [
                'cliente' => $user->display_name,
                'codigo' => $codigo_unico,
                'fecha' => date('d/m/Y H:i')
            ]);
        }
        
        // Email al administrador
        $admin_email = get_option('admin_email_garantias', get_option('admin_email'));
        if (class_exists('WC_Garantias_Emails')) {
            WC_Garantias_Emails::enviar_email('admin_nuevo_reclamo', $admin_email, [
                'codigo' => $codigo_unico,
                'cliente' => $user->display_name,
                'fecha' => date('d/m/Y H:i'),
                'link_admin' => admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $post_id)
            ]);
        }
    }
    /**
     * Verificar timeouts de solicitudes de informacin
     */
    public static function check_info_timeouts() {
        error_log('=== VERIFICANDO TIMEOUTS DE INFORMACIÓN ===');
        
        $horas_limite = get_option('garantia_tiempo_limite_info', 72);
        $motivo_rechazo = get_option('garantia_motivo_rechazo_timeout', 'Fuera de plazo para enviar la información solicitada');
        
        // Obtener garantas con posibles timeouts
        $garantias = get_posts([
            'post_type' => 'garantia',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'meta_query' => [
                [
                    'key' => '_estado',
                    'value' => ['nueva', 'en_proceso', 'en_revision'],
                    'compare' => 'IN'
                ]
            ]
        ]);
        
        foreach ($garantias as $garantia) {
            self::process_garantia_timeouts($garantia->ID, $horas_limite, $motivo_rechazo);
        }
    }
    
    /**
     * Procesar timeouts de una garantía específica
     */
    private static function process_garantia_timeouts($garantia_id, $horas_limite, $motivo_rechazo) {
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        if (!is_array($items)) return;
        
        $items_modificados = false;
        
        foreach ($items as &$item) {
            // Solo procesar items en estado solicitar_info
            if (!isset($item['estado']) || $item['estado'] !== 'solicitar_info') continue;
            
            // Verificar historial de solicitudes
            if (!isset($item['historial_solicitudes']) || empty($item['historial_solicitudes'])) continue;
            
            // Obtener última solicitud
            $ultima_solicitud = &$item['historial_solicitudes'][count($item['historial_solicitudes']) - 1];
            
            // Si ya fue respondida, ignorar
            if (isset($ultima_solicitud['respondido']) && $ultima_solicitud['respondido']) continue;
            
            // Calcular horas hábiles transcurridas
            $fecha_solicitud = $ultima_solicitud['fecha'];
            $horas_transcurridas = self::calculate_business_hours($fecha_solicitud);
            
            error_log("Item {$item['codigo_item']}: {$horas_transcurridas} horas hábiles desde solicitud");
            
            // Enviar recordatorio a las 48 horas
            if ($horas_transcurridas >= 48 && $horas_transcurridas < 72 && !isset($ultima_solicitud['recordatorio_enviado'])) {
                self::send_48h_reminder($garantia_id, $item, $ultima_solicitud);
                $ultima_solicitud['recordatorio_enviado'] = true;
                $items_modificados = true;
            }
            
            // Rechazar automáticamente después del lmite
            if ($horas_transcurridas >= $horas_limite) {
                error_log("Rechazando item {$item['codigo_item']} por timeout");
                
                // Cambiar estado a rechazado
                $item['estado'] = 'rechazado';
                $item['motivo_rechazo'] = $motivo_rechazo;
                $item['fecha_rechazo'] = current_time('mysql');
                $item['rechazo_automatico'] = true;
                
                // Agregar al historial
                if (!isset($item['historial_rechazos'])) {
                    $item['historial_rechazos'] = [];
                }
                $item['historial_rechazos'][] = [
                    'motivo' => $motivo_rechazo,
                    'fecha' => current_time('mysql'),
                    'tipo' => 'timeout_informacion'
                ];
                
                $items_modificados = true;
                
                // Notificar rechazo por timeout
                self::notify_timeout_rejection($garantia_id, $item);
            }
        }
        
        if ($items_modificados) {
            update_post_meta($garantia_id, '_items_reclamados', $items);
        }
    }
    
    /**
     * Calcular horas hbiles entre dos fechas (lunes a viernes)
     */
    private static function calculate_business_hours($fecha_inicio) {
        $inicio = new DateTime($fecha_inicio);
        $ahora = new DateTime();
        
        $horas = 0;
        $current = clone $inicio;
        
        while ($current < $ahora) {
            $dia_semana = $current->format('N');
            // Solo contar lunes (1) a viernes (5)
            if ($dia_semana >= 1 && $dia_semana <= 5) {
                $horas++;
            }
            $current->add(new DateInterval('PT1H'));
        }
        
        return $horas;
    }
    
    /**
     * Enviar recordatorio de 48 horas
     */
    private static function send_48h_reminder($garantia_id, $item, $solicitud) {
        $cliente_id = get_post_meta($garantia_id, '_cliente', true);
        $user = get_userdata($cliente_id);
        
        if ($user && $user->user_email && class_exists('WC_Garantias_Emails')) {
            $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
            $horas_restantes = get_option('garantia_tiempo_limite_info', 72) - 48;
            
            WC_Garantias_Emails::enviar_email('recordatorio_info_24h', $user->user_email, [
                'cliente' => $user->display_name,
                'codigo' => $codigo_unico,
                'item_codigo' => $item['codigo_item'],
                'mensaje_original' => $solicitud['mensaje'],
                'horas_restantes' => $horas_restantes,
                'link_cuenta' => wc_get_account_endpoint_url('garantias')
            ]);
        }
    }
    /**
     * Notificar rechazo por timeout
     */
    private static function notify_timeout_rejection($garantia_id, $item) {
        $cliente_id = get_post_meta($garantia_id, '_cliente', true);
        $user = get_userdata($cliente_id);
        
        if ($user && $user->user_email && class_exists('WC_Garantias_Emails')) {
            $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
            $motivo = get_option('garantia_motivo_rechazo_timeout', 'Fuera de plazo para enviar la información solicitada');
            
            WC_Garantias_Emails::enviar_email('rechazada', $user->user_email, [
                'cliente' => $user->display_name,
                'codigo' => $codigo_unico,
                'motivo' => $motivo,
                'link_cuenta' => wc_get_account_endpoint_url('garantias'),
                'item_codigo_procesado' => $item['codigo_item']
            ]);
        }
    }
    /**
     * Procesar formulario de devolución por error de compra
     */
    public static function process_devolucion_form() {
        $customer_id = get_current_user_id();
        if (!$customer_id) {
            return false;
        }
        
        $productos_post = isset($_POST['producto']) ? $_POST['producto'] : array();
        $cantidades_post = $_POST['cantidad'];
        
        // Preparar array de items
        $items_guardar = [];
        foreach($productos_post as $i => $producto_id) {
            // NUEVO: Solo procesar si el producto tiene un ID válido (fue seleccionado)
            if (empty($producto_id)) {
                continue; // Saltar productos no seleccionados
            }
            
            $producto_id = sanitize_text_field($producto_id);
            $cantidad = max(1, intval($cantidades_post[$i] ?? 1));
            
            // NUEVO: Verificar que la cantidad sea mayor a 0
            if ($cantidad <= 0) {
                continue; // Saltar si no hay cantidad
            }
            
            // Buscar order_id más reciente
            $orders = wc_get_orders([
                'customer_id' => $customer_id,
                'status' => 'completed',
                'limit' => -1,
            ]);
            
            $order_id = null;
            foreach ($orders as $order) {
                foreach ($order->get_items() as $item) {
                    if ($item->get_product_id() == $producto_id) {
                        $order_id = $order->get_id();
                        break 2;
                    }
                }
            }

            $items_guardar[] = [
                'codigo_item'  => 'DEV-ITEM-' . strtoupper(wp_generate_password(8, false, false)),
                'producto_id'  => $producto_id,
                'cantidad'     => $cantidad,
                'motivo'       => 'Devolución por error de compra',
                'foto_url'     => '',
                'video_url'    => '',
                'order_id'     => $order_id,
                'estado'       => 'Pendiente',
                'es_devolucion_error' => true
            ];
        }

        // Crear post de garanta
        $garantia_post = [
            'post_type'   => 'garantia',
            'post_status' => 'publish',
            'post_title'  => 'Devolucin - ' . $customer_id . ' - ' . date('Y-m-d H:i:s'),
            'post_author' => $customer_id,
        ];

        $post_id = wp_insert_post($garantia_post);

        if ($post_id && !is_wp_error($post_id)) {
            $codigo_unico = 'DEV-' . date('Ymd') . '-' . strtoupper(wp_generate_password(5, false, false));
            update_post_meta($post_id, '_codigo_unico', $codigo_unico);
            update_post_meta($post_id, '_cliente', $customer_id);
            update_post_meta($post_id, '_fecha', current_time('mysql'));
            update_post_meta($post_id, '_estado', 'nueva');
            update_post_meta($post_id, '_items_reclamados', $items_guardar);
            update_post_meta($post_id, '_es_devolucion_error', true);
            
            // Enviar email al cliente
            $user = get_userdata($customer_id);
            if ($user && $user->user_email && class_exists('WC_Garantias_Emails')) {
                WC_Garantias_Emails::enviar_email('devolucion_confirmada', $user->user_email, [
                    'cliente' => $user->display_name,
                    'codigo' => $codigo_unico,
                    'fecha' => date('d/m/Y H:i'),
                    'link_etiqueta' => add_query_arg([
                        'generar_etiqueta' => '1',
                        'devolucion_id' => $post_id
                    ], wc_get_account_endpoint_url('garantias'))
                ]);
            }
            // Enviar WhatsApp si est configurado
            if (class_exists('WC_Garantias_WhatsApp')) {
                $telefono = get_user_meta($customer_id, 'billing_phone', true);
                
                if ($telefono) {
                    // Limpiar nmero de teléfono (quitar espacios, guiones, etc)
                    $telefono = preg_replace('/[^0-9]/', '', $telefono);
                    
                    // Si no tiene código de país, agregar 54 para Argentina
                    if (strlen($telefono) == 10) {
                        $telefono = '54' . $telefono;
                    }
                    
                    $parametros = array(
                        $user->display_name,
                        $codigo_unico
                    );
                    
                    WC_Garantias_WhatsApp::send_template('devolucion_error', $telefono, $parametros);
                }
            }
            
            // Guardar en sesión para mostrar mensaje
            garantias_set_mensaje('Devolución registrada correctamente. Código: ' . $codigo_unico . '. Descarga tu etiqueta a continuación.', 'success');
            
            // Redirigir con parámetro para mostrar etiqueta
            wp_redirect(add_query_arg([
                'devolucion_success' => '1',
                'devolucion_id' => $post_id
            ], wc_get_account_endpoint_url('garantias')));
            exit;
        }
        
        return false;
    }
    /**
     * Procesar tracking de devolución
     */
    private static function process_tracking_devolucion() {
        $garantia_id = intval($_POST['garantia_id']);
        $customer_id = get_current_user_id();
        
        if (!self::validate_user_permission($garantia_id, $customer_id)) {
            garantias_set_mensaje('No tienes permiso para modificar esta garantía', 'error');
            wp_redirect(wc_get_account_endpoint_url('garantias'));
            exit;
        }
        
        $numero_seguimiento = sanitize_text_field($_POST['numero_seguimiento']);
        $empresa_transporte = sanitize_text_field($_POST['empresa_transporte']);
        
        // Actualizar datos
        update_post_meta($garantia_id, '_numero_seguimiento_devolucion', $numero_seguimiento);
        update_post_meta($garantia_id, '_empresa_transporte_devolucion', $empresa_transporte);
        update_post_meta($garantia_id, '_fecha_envio_devolucion', current_time('mysql'));
        update_post_meta($garantia_id, '_estado', 'devolucion_en_transito');
        
        garantias_set_mensaje('Información de envío guardada correctamente.', 'success');
        
        // Notificar al admin
        $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
        $admin_email = get_option('admin_email_garantias', get_option('admin_email'));
        
        $mensaje = "DEVOLUCIÓN EN TRÁNSITO\n\n";
        $mensaje .= "Cdigo: {$codigo_unico}\n";
        $mensaje .= "Tracking: {$numero_seguimiento}\n";
        $mensaje .= "Empresa: {$empresa_transporte}\n\n";
        $mensaje .= "Revisar en: " . admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $garantia_id);
        
        wp_mail($admin_email, "Devolución en tránsito - {$codigo_unico}", $mensaje);
        
        wp_redirect(wc_get_account_endpoint_url('garantias'));
        exit;
    }
}