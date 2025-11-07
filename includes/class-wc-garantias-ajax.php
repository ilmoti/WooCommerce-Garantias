<?php
if (!defined('ABSPATH')) exit;

/**
 * ========================================
 * ULTIMA ACTUALIZACION: 2025-11-07 15:45
 * VERSION DE PRUEBA - DEPLOY TEST
 * ========================================
 *
 * Clase mejorada para manejar todas las operaciones AJAX del plugin de garantías
 * Incluye funcionalidades modernas, validaciones robustas y mejor UX
 */
class WC_Garantias_Ajax {
    
    private static $productos_cache = [];

    private static function get_cached_customer_products($customer_id) {
        if (!isset(self::$productos_cache[$customer_id])) {
            // Aquí va la lgica actual de obtener productos
            self::$productos_cache[$customer_id] = [];
        }
        return self::$productos_cache[$customer_id];
    }
    
    public static function init() {
        // Hooks para usuarios logueados
        add_action('wp_ajax_wcgarantias_get_products', [__CLASS__, 'get_products_autocomplete']);
        add_action('wp_ajax_wcgarantias_submit_claim', [__CLASS__, 'submit_claim']);
        add_action('wp_ajax_wcgarantias_get_claim_status', [__CLASS__, 'get_claim_status']);
        add_action('wp_ajax_wcgarantias_add_comment', [__CLASS__, 'add_comment']);
        add_action('wp_ajax_wcgarantias_get_comments', [__CLASS__, 'get_comments']);
        add_action('wp_ajax_wcgarantias_upload_file', [__CLASS__, 'upload_file']);
        add_action('wp_ajax_wcgarantias_get_dashboard_data', [__CLASS__, 'get_dashboard_data']);
        
        // Handler para obtener información de orden
        add_action('wp_ajax_get_order_info', [__CLASS__, 'get_order_info']);
        add_action('wp_ajax_nopriv_get_order_info', [__CLASS__, 'get_order_info']);
        
        // Handler para actualizar items de devolución a tránsito
        add_action('wp_ajax_actualizar_items_devolver_transito', [__CLASS__, 'actualizar_items_devolver_transito']);
        add_action('wp_ajax_actualizar_estado_transito_grupo', [__CLASS__, 'actualizar_items_devolver_transito']);
        
        // Hooks para administradores
        add_action('wp_ajax_wcgarantias_admin_update_status', [__CLASS__, 'admin_update_status']);
        add_action('wp_ajax_wcgarantias_admin_bulk_action', [__CLASS__, 'admin_bulk_action']);
        add_action('wp_ajax_wcgarantias_admin_add_note', [__CLASS__, 'admin_add_note']);
        add_action('wp_ajax_wcgarantias_admin_get_analytics', [__CLASS__, 'admin_get_analytics']);
        add_action('wp_ajax_wcgarantias_admin_assign_claim', [__CLASS__, 'admin_assign_claim']);
        
        // Hooks para usuarios no logueados (tracking público)
        add_action('wp_ajax_nopriv_wcgarantias_track_claim', [__CLASS__, 'track_claim_public']);
        
        // Agregar el hook para procesar destrucción
        add_action('wp_ajax_procesar_destruccion_item', array('WC_Garantias_Ajax', 'procesar_destruccion_item'));
        
        // Agregar después de los otros hooks de administradores
        add_action('wp_ajax_wcgarantias_admin_get_new_count', [__CLASS__, 'admin_get_new_count']);
        
        // Enqueue scripts
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_scripts']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_admin_scripts']);
    }
    
    /**
     * Enqueue scripts para frontend
     */
    public static function enqueue_scripts() {
        if (!is_account_page()) return;
        
        wp_enqueue_script(
            'wc-garantias-ajax',
            WC_GARANTIAS_URL . 'assets/js/garantias-ajax.js',
            ['jquery'],
            '1.2.0',
            true
        );
        
        wp_localize_script('wc-garantias-ajax', 'wcGarantiasAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcgarantias_nonce'),
            'strings' => [
                'loading' => __('Cargando...', 'wc-garantias'),
                'error' => __('Error al procesar la solicitud', 'wc-garantias'),
                'success' => __('Operación exitosa', 'wc-garantias'),
                'confirm_delete' => __('¿Estás seguro de eliminar este elemento?', 'wc-garantias'),
                'file_too_large' => __('El archivo es demasiado grande', 'wc-garantias'),
                'invalid_file_type' => __('Tipo de archivo no permitido', 'wc-garantias')
            ],
            'max_file_size' => wp_max_upload_size(),
            'allowed_file_types' => ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi'],
        ]);
    }
    
    /**
     * Enqueue scripts para admin
     */
    public static function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'wc-garantias') === false && $hook !== 'edit.php') return;
        
        wp_enqueue_script(
            'wc-garantias-admin-ajax',
            WC_GARANTIAS_URL . 'assets/js/garantias-admin-ajax.js',
            ['jquery', 'jquery-ui-sortable'],
            '1.2.0',
            true
        );
        
        wp_localize_script('wc-garantias-admin-ajax', 'wcGarantiasAdminAjax', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wcgarantias_admin_nonce'),
            'strings' => [
                'loading' => __('Procesando...', 'wc-garantias'),
                'saved' => __('Guardado correctamente', 'wc-garantias'),
                'error' => __('Error al guardar', 'wc-garantias'),
                'confirm_bulk' => __('¿Aplicar acción a los elementos seleccionados?', 'wc-garantias'),
            ],
        ]);
    }
    
    /**
     * Autocomplete de productos para el formulario de garantías
     */
    public static function get_products_autocomplete() {
        // DEBUG FORZADO - Escribir a archivo custom
        $debug_file = WP_CONTENT_DIR . '/garantias-debug.log';
        file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "] AJAX get_products_autocomplete llamado\n", FILE_APPEND);

        check_ajax_referer('wcgarantias_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Usuario no autenticado']);
        }

        $term = sanitize_text_field($_POST['term'] ?? '');
        file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "] Term: $term\n", FILE_APPEND);

        if (strlen($term) < 2) {
            wp_send_json_error(['message' => 'Término de búsqueda muy corto']);
        }

        $customer_id = get_current_user_id();
        file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "] Customer ID: $customer_id\n", FILE_APPEND);
        $duracion_garantia = get_option('duracion_garantia', 180);
        $fecha_limite = strtotime("-{$duracion_garantia} days");
        
        // Obtener productos comprados por el cliente
        $orders = wc_get_orders([
            'customer_id' => $customer_id,
            'status' => 'completed',
            'limit' => -1,
        ]);
        
        $productos_disponibles = [];
        foreach ($orders as $order) {
            $order_time = strtotime($order->get_date_completed() ? 
                $order->get_date_completed()->date('Y-m-d H:i:s') : 
                $order->get_date_created()->date('Y-m-d H:i:s')
            );
            
            if ($order_time < $fecha_limite) continue;
            
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $product = wc_get_product($product_id);
                if (!$product) continue;
                
                $product_name = $product->get_name();

                // EXCLUIR productos RMA del autocomplete
                if (stripos($product_name, 'RMA') === 0) {
                    continue; // Saltar productos RMA
                }
                
                if (stripos($product_name, $term) === false) continue;
                
                // Calcular cantidad disponible para reclamar
                $cantidad_comprada = $item->get_quantity();

                // NUEVO: Si es un BULTO, multiplicar la cantidad
                if (stripos($product_name, 'BULTO') === 0) {
                    // Buscar el patrón X seguido de números al final
                    if (preg_match('/X(\d+)$/i', $product_name, $matches)) {
                        $cantidad_por_bulto = intval($matches[1]);
                        $cantidad_comprada = $cantidad_comprada * $cantidad_por_bulto;
                    }
                }

                // FIX: Calcular solo lo reclamado de ESTA orden específica
                $cantidad_reclamada = self::get_claimed_quantity_by_order($customer_id, $product_id, $order->get_id());
                $cantidad_disponible = $cantidad_comprada - $cantidad_reclamada;

                // DEBUG TEMPORAL - ELIMINAR DESPUÉS
                if ($product_id == 48612) {
                    $debug_file = WP_CONTENT_DIR . '/garantias-debug.log';
                    file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "] === DEBUG TCL 40 SE ===\n", FILE_APPEND);
                    file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "] Product ID: $product_id\n", FILE_APPEND);
                    file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "] Customer ID: $customer_id\n", FILE_APPEND);
                    file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "] Cantidad comprada: $cantidad_comprada\n", FILE_APPEND);
                    file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "] Cantidad reclamada: $cantidad_reclamada\n", FILE_APPEND);
                    file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "] Cantidad disponible: $cantidad_disponible\n", FILE_APPEND);
                    file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "] ======================\n", FILE_APPEND);

                    error_log("=== DEBUG TCL 40 SE ===");
                    error_log("Product ID: " . $product_id);
                    error_log("Customer ID: " . $customer_id);
                    error_log("Cantidad comprada: " . $cantidad_comprada);
                    error_log("Cantidad reclamada: " . $cantidad_reclamada);
                    error_log("Cantidad disponible: " . $cantidad_disponible);
                    error_log("======================");
                }

                if ($cantidad_disponible <= 0) continue;
                
                $custom_sku = get_post_meta($product_id, '_alg_ean', true);
                if (is_array($custom_sku)) {
                    $custom_sku = reset($custom_sku);
                }

                // DEBUG: Agregar información de debug al label si es el producto 48612
                $label_debug = '';
                if ($product_id == 48612) {
                    $label_debug = sprintf(' [DEBUG: C:%d R:%d = %d]',
                        $cantidad_comprada,
                        $cantidad_reclamada,
                        $cantidad_disponible
                    );
                }

                // Crear clave única por producto + orden
                $key = $product_id . '_' . $order->get_id();

                $productos_disponibles[$key] = [
                    'value' => $product_id,  // <-- CAMBIO
                    'id' => $product_id,     // Mantener ambos por compatibilidad
                    'label' => sprintf('%s  %s (%d disponibles)%s',
                        $product_name,
                        $custom_sku ?: $product->get_sku(),
                        $cantidad_disponible,
                        $label_debug
                    ),
                    'name' => $product_name,
                    'sku' => $custom_sku ?: $product->get_sku(),
                    'max_quantity' => $cantidad_disponible,
                    'order_id' => $order->get_id(),
                ];
            }
        }

        // NO usar array_unique - ya están agrupados por clave única
        // Convertir array asociativo a numérico y limitar resultados
        $productos_disponibles = array_values($productos_disponibles);
        $productos_disponibles = array_slice($productos_disponibles, 0, 20);
        
        wp_send_json_success($productos_disponibles);
    }
    
    /**
     * Envío de reclamo de garanta mejorado con validaciones
     */
    public static function submit_claim() {
        check_ajax_referer('wcgarantias_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Usuario no autenticado']);
        }
        
        try {
            $user_id = get_current_user_id();
            $items_data = isset($_POST['items']) ? json_decode(stripslashes($_POST['items']), true) : [];

            // Verificar que la decodificación fue exitosa
            if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error(['message' => 'Error al procesar los datos del formulario']);
            return;
            }
            
            if (empty($items_data) || !is_array($items_data)) {
                wp_send_json_error(['message' => 'No se proporcionaron items para reclamar']);
            }
            
            $items_procesados = [];
            
            foreach ($items_data as $index => $item) {
                $producto_id = intval($item['producto_id'] ?? 0);
                $cantidad = max(1, intval($item['cantidad'] ?? 1));
                $motivo = sanitize_text_field($item['motivo'] ?? '');
                $motivo_otro = sanitize_text_field($item['motivo_otro'] ?? '');
                $order_id = intval($item['order_id'] ?? 0);
                
                // Validaciones
                if (!$producto_id || !$motivo) {
                    wp_send_json_error(['message' => "Datos incompletos en el item " . ($index + 1)]);
                }
                
                // Verificar que el usuario puede reclamar este producto
                if (!self::can_claim_product($user_id, $producto_id, $cantidad)) {
                    wp_send_json_error(['message' => "No puedes reclamar este producto o la cantidad especificada"]);
                }
                
                // Procesar motivo
                if ($motivo === 'Otro' && !empty($motivo_otro)) {
                    $motivo = 'Otro: ' . $motivo_otro;
                }
                
                $items_procesados[] = [
                    'codigo_item' => 'GRT-ITEM-' . strtoupper(wp_generate_password(8, false, false)),
                    'producto_id' => $producto_id,
                    'cantidad' => $cantidad,
                    'motivo' => $motivo,
                    'foto_url' => '', // Se subirá después si es necesario
                    'video_url' => '', // Se subir despus si es necesario
                    'order_id' => $order_id,
                    'estado' => 'Pendiente',
                    'fecha_creacion' => current_time('mysql'),
                ];
            }
            
            // Crear el post de garanta
            $garantia_post = [
                'post_type' => 'garantia',
                'post_status' => 'publish',
                'post_title' => 'Garanta - ' . $user_id . ' - ' . date('Y-m-d H:i:s'),
                'post_author' => $user_id,
            ];
            
            $post_id = wp_insert_post($garantia_post);
            
            if (is_wp_error($post_id)) {
                wp_send_json_error(['message' => 'Error al crear la garantía']);
            }
            
            // Metadatos
            $codigo_unico = 'GRT-' . date('Ymd') . '-' . strtoupper(wp_generate_password(5, false, false));
            update_post_meta($post_id, '_codigo_unico', $codigo_unico);
            update_post_meta($post_id, '_cliente', $user_id);
            update_post_meta($post_id, '_fecha', current_time('mysql'));
            update_post_meta($post_id, '_estado', 'nueva');
            update_post_meta($post_id, '_items_reclamados', $items_procesados);
            
            // Historial inicial
            $historial = [[
                'estado' => 'nueva',
                'fecha' => current_time('mysql'),
                'nota' => 'Garantía creada por el cliente',
                'usuario' => $user_id,
            ]];
            update_post_meta($post_id, '_historial', $historial);
            
            // Notificar admin
            self::notify_admin_new_claim($post_id, $codigo_unico);
            
            wp_send_json_success([
                'message' => 'Reclamo enviado correctamente',
                'codigo_garantia' => $codigo_unico,
                'garantia_id' => $post_id,
            ]);
            
        } catch (Exception $e) {
            error_log('Error en submit_claim: ' . $e->getMessage());
            wp_send_json_error(['message' => 'Error interno del servidor']);
        }
    }
    
    /**
     * Obtener estado actualizado de una garanta
     */
    public static function get_claim_status() {
        check_ajax_referer('wcgarantias_nonce', 'nonce');
        
        $garantia_id = intval($_POST['garantia_id'] ?? 0);
        if (!$garantia_id) {
            wp_send_json_error(['message' => 'ID de garantía no vlido']);
        }
        
        $garantia = get_post($garantia_id);
        if (!$garantia || $garantia->post_type !== 'garantia') {
            wp_send_json_error(['message' => 'Garantía no encontrada']);
        }
        
        // Verificar permisos
        if (!current_user_can('manage_woocommerce') && $garantia->post_author != get_current_user_id()) {
            wp_send_json_error(['message' => 'Sin permisos para ver esta garantía']);
        }
        
        $estados_nombres = [
            'nueva' => 'Pendiente de recibir',
            'en_revision' => 'En revisión',
            'pendiente_envio' => 'Pendiente de envío',
            'recibido' => 'Recibido - En análisis',
            'aprobado_cupon' => 'Aprobado - Cupón Enviado',
            'rechazado' => 'Rechazado',
            'finalizado_cupon' => 'Finalizado - Cupón utilizado',
            'finalizado' => 'Finalizado',
        ];
        
        $estado = get_post_meta($garantia_id, '_estado', true);
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        $historial = get_post_meta($garantia_id, '_historial', true);
        $comentarios = get_post_meta($garantia_id, '_comentarios', true);
        
        wp_send_json_success([
            'estado' => $estado,
            'estado_nombre' => $estados_nombres[$estado] ?? $estado,
            'items' => $items ?: [],
            'historial' => $historial ?: [],
            'comentarios' => $comentarios ?: [],
            'fecha_actualizacion' => get_post_modified_time('c', false, $garantia),
        ]);
    }
    
    /**
     * Agregar comentario a una garantía
     */
    public static function add_comment() {
        check_ajax_referer('wcgarantias_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Usuario no autenticado']);
        }
        
        $garantia_id = intval($_POST['garantia_id'] ?? 0);
        $comentario = trim(sanitize_textarea_field($_POST['comentario'] ?? ''));
        
        if (!$garantia_id || empty($comentario)) {
            wp_send_json_error(['message' => 'Datos incompletos']);
        }
        
        $garantia = get_post($garantia_id);
        if (!$garantia || $garantia->post_type !== 'garantia') {
            wp_send_json_error(['message' => 'Garantía no encontrada']);
        }
        
        $user_id = get_current_user_id();
        $is_admin = current_user_can('manage_woocommerce');
        
        // Verificar permisos
        if (!$is_admin && $garantia->post_author != $user_id) {
            wp_send_json_error(['message' => 'Sin permisos para comentar en esta garantía']);
        }
        
        $comentarios = get_post_meta($garantia_id, '_comentarios', true) ?: [];
        
        $nuevo_comentario = [
            'id' => uniqid(),
            'usuario_id' => $user_id,
            'usuario_nombre' => wp_get_current_user()->display_name,
            'es_admin' => $is_admin,
            'comentario' => $comentario,
            'fecha' => current_time('mysql'),
            'fecha_legible' => current_time('d/m/Y H:i'),
        ];
        
        $comentarios[] = $nuevo_comentario;
        update_post_meta($garantia_id, '_comentarios', $comentarios);
        
        // Notificar a la otra parte
        if ($is_admin) {
            self::notify_customer_comment($garantia_id, $comentario);
        } else {
            self::notify_admin_comment($garantia_id, $comentario);
        }
        
        wp_send_json_success([
            'message' => 'Comentario agregado correctamente',
            'comentario' => $nuevo_comentario,
        ]);
    }
    
    /**
     * Obtener comentarios de una garanta
     */
    public static function get_comments() {
        check_ajax_referer('wcgarantias_nonce', 'nonce');
        
        $garantia_id = intval($_POST['garantia_id'] ?? 0);
        if (!$garantia_id) {
            wp_send_json_error(['message' => 'ID de garantía no válido']);
        }
        
        $garantia = get_post($garantia_id);
        if (!$garantia || $garantia->post_type !== 'garantia') {
            wp_send_json_error(['message' => 'Garanta no encontrada']);
        }
        
        // Verificar permisos
        if (!current_user_can('manage_woocommerce') && $garantia->post_author != get_current_user_id()) {
            wp_send_json_error(['message' => 'Sin permisos para ver esta garantía']);
        }
        
        $comentarios = get_post_meta($garantia_id, '_comentarios', true) ?: [];
        
        wp_send_json_success(['comentarios' => $comentarios]);
    }
    
    /**
     * Subida de archivos mejorada con validaciones
     */
    public static function upload_file() {
        check_ajax_referer('wcgarantias_nonce', 'nonce');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Usuario no autenticado']);
        }
        
        if (empty($_FILES['file'])) {
            wp_send_json_error(['message' => 'No se proporcionó archivo']);
        }
        
        $file = $_FILES['file'];
        $garantia_id = intval($_POST['garantia_id'] ?? 0);
        $item_codigo = sanitize_text_field($_POST['item_codigo'] ?? '');
        $tipo_archivo = sanitize_text_field($_POST['tipo'] ?? 'foto'); // 'foto' o 'video'
        
        // Validaciones
        $max_size = wp_max_upload_size();
        if ($file['size'] > $max_size) {
            wp_send_json_error(['message' => 'Archivo demasiado grande']);
        }
        
        $allowed_types = [
            'foto' => ['jpg', 'jpeg', 'png', 'gif'],
            'video' => ['mp4', 'mov', 'avi', 'wmv'],
        ];
        
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($file_ext, $allowed_types[$tipo_archivo] ?? [])) {
            wp_send_json_error(['message' => 'Tipo de archivo no permitido']);
        }
        
        // Subir archivo
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $uploaded = wp_handle_upload($file, ['test_form' => false]);
        
        if (isset($uploaded['error'])) {
            wp_send_json_error(['message' => 'Error al subir archivo: ' . $uploaded['error']]);
        }
        
        // Actualizar item si se proporcionó
        if ($garantia_id && $item_codigo) {
            $items = get_post_meta($garantia_id, '_items_reclamados', true) ?: [];
            foreach ($items as &$item) {
                if ($item['codigo_item'] === $item_codigo) {
                    $item[$tipo_archivo . '_url'] = $uploaded['url'];
                    break;
                }
            }
            update_post_meta($garantia_id, '_items_reclamados', $items);
        }
        
        wp_send_json_success([
            'message' => 'Archivo subido correctamente',
            'url' => $uploaded['url'],
            'filename' => basename($uploaded['file']),
        ]);
    }
    
    /**
 * Obtener datos del dashboard del cliente
 */
public static function get_dashboard_data() {
    check_ajax_referer('wcgarantias_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Usuario no autenticado']);
    }
    
    $user_id = get_current_user_id();
    
    // Obtener todas las garantas del cliente
    $args = [
        'post_type' => 'garantia',
        'post_status' => 'publish',
        'meta_query' => [['key' => '_cliente', 'value' => $user_id]],
        'posts_per_page' => -1,
    ];
    
    $garantias = get_posts($args);
    
    // Contadores de ITEMS (no garantías)
    $stats = [
        'total' => count($garantias), // Mantenemos esto por compatibilidad
        'pendientes' => 0,
        'aprobadas' => 0,
        'rechazadas' => 0,
        // NUEVOS contadores para items
        'total_items' => 0,
        'items_pendientes' => 0,
        'items_aprobados' => 0,
        'items_rechazados' => 0,
        'ultimas' => [],
    ];
    
    foreach ($garantias as $garantia) {
        $estado = get_post_meta($garantia->ID, '_estado', true);
        
        // Contar garantías (compatibilidad)
        switch ($estado) {
            case 'nueva':
            case 'en_revision':
            case 'pendiente_envio':
            case 'recibido':
                $stats['pendientes']++;
                break;
            case 'aprobado_cupon':
            case 'finalizado_cupon':
            case 'finalizado':
                $stats['aprobadas']++;
                break;
            case 'rechazado':
                $stats['rechazadas']++;
                break;
        }
        
        // NUEVO: Contar ITEMS
        $items = get_post_meta($garantia->ID, '_items_reclamados', true);
        
        if (is_array($items)) {
            foreach ($items as $item) {
                $stats['total_items']++;
                
                $estado_item = isset($item['estado']) ? $item['estado'] : 'Pendiente';
                
                // Contar segn estado del item
                if ($estado_item === 'aprobado') {
                    $stats['items_aprobados']++;
                } elseif ($estado_item === 'rechazado') {
                    $stats['items_rechazados']++;
                } else {
                    // Todo lo demás cuenta como pendiente
                    $stats['items_pendientes']++;
                }
            }
        } else {
            // Compatibilidad con formato antiguo (sin items)
            $stats['total_items']++;
            
            if (in_array($estado, ['aprobado_cupon', 'finalizado_cupon', 'finalizado'])) {
                $stats['items_aprobados']++;
            } elseif ($estado === 'rechazado') {
                $stats['items_rechazados']++;
            } else {
                $stats['items_pendientes']++;
            }
        }
        
        // Últimas garantas
        if (count($stats['ultimas']) < 5) {
            $stats['ultimas'][] = [
                'id' => $garantia->ID,
                'codigo' => get_post_meta($garantia->ID, '_codigo_unico', true),
                'estado' => $estado,
                'fecha' => get_post_time('d/m/Y', false, $garantia),
            ];
        }
    }
    
    wp_send_json_success($stats);
}
    
    /**
 * Obtener información de la orden ms reciente para un producto
 */
public static function get_order_info() {
    check_ajax_referer('wcgarantias_nonce', 'nonce');
    
    if (!is_user_logged_in()) {
        wp_send_json_error(['message' => 'Usuario no autenticado']);
    }
    
    $producto_id = intval($_POST['producto_id'] ?? 0);
    $customer_id = get_current_user_id();
    
    if (!$producto_id) {
        wp_send_json_error(['message' => 'ID de producto no vlido']);
    }
    
    // Buscar la orden más reciente donde el cliente compró este producto
    $orders = wc_get_orders([
        'customer_id' => $customer_id,
        'status' => 'completed',
        'limit' => -1,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);
    
    foreach ($orders as $order) {
        foreach ($order->get_items() as $item) {
            if ($item->get_product_id() == $producto_id) {
                // Encontramos la orden
                $order_date = $order->get_date_completed() ? 
                    $order->get_date_completed()->date('d/m/Y') : 
                    $order->get_date_created()->date('d/m/Y');
                
                wp_send_json_success([
                    'order_id' => $order->get_id(),
                    'order_date' => $order_date
                ]);
                return;
            }
        }
    }
    
    wp_send_json_error(['message' => 'No se encontró informacin de orden']);
}
    /**
     * Funciones de utilidad
     */
    
    private static function get_claimed_quantity($customer_id, $product_id) {
        $debug_file = WP_CONTENT_DIR . '/garantias-debug.log';

        $args = [
            'post_type' => 'garantia',
            'post_status' => 'publish',
            'meta_query' => [
                ['key' => '_cliente', 'value' => $customer_id],
            ],
            'posts_per_page' => -1,
        ];

        $garantias = get_posts($args);
        $cantidad_reclamada = 0;

        // Obtener la duración de garantía configurada (default: 180 días)
        $duracion_garantia = get_option('duracion_garantia', 180);
        $fecha_limite = strtotime("-{$duracion_garantia} days");

        // DEBUG TEMPORAL
        if ($product_id == 48612) {
            file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "] --- get_claimed_quantity DEBUG ---\n", FILE_APPEND);
            file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "] Total garantías encontradas: " . count($garantias) . "\n", FILE_APPEND);
            file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "] Duración garantía: $duracion_garantia días\n", FILE_APPEND);
            file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "] Fecha límite: " . date('Y-m-d', $fecha_limite) . "\n", FILE_APPEND);

            error_log("--- get_claimed_quantity DEBUG ---");
            error_log("Total garantías encontradas: " . count($garantias));
            error_log("Duración garantía: " . $duracion_garantia . " días");
            error_log("Fecha límite: " . date('Y-m-d', $fecha_limite));
        }

        foreach ($garantias as $garantia) {
            // Obtener los items de esta garantía
            $items = get_post_meta($garantia->ID, '_items_reclamados', true) ?: [];

            if ($product_id == 48612) {
                file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "] Garantía {$garantia->ID}: " . count($items) . " items\n", FILE_APPEND);
            }

            foreach ($items as $item) {
                // Verificar si este item es del producto que buscamos
                if (intval($item['producto_id']) !== $product_id) {
                    continue;
                }

                // Obtener el order_id del ITEM (no del post de garantía)
                $order_id = $item['order_id'] ?? null;

                if (!$order_id) {
                    if ($product_id == 48612) {
                        file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "]   Item sin order_id - SALTADO\n", FILE_APPEND);
                    }
                    continue;
                }

                // Obtener la orden para verificar su fecha
                $order = wc_get_order($order_id);
                if (!$order) {
                    if ($product_id == 48612) {
                        file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "]   Orden {$order_id} no existe - SALTADO\n", FILE_APPEND);
                    }
                    continue;
                }

                // Verificar si la orden está dentro del período válido de garantía
                $order_time = strtotime($order->get_date_completed() ?
                    $order->get_date_completed()->date('Y-m-d H:i:s') :
                    $order->get_date_created()->date('Y-m-d H:i:s')
                );

                // DEBUG TEMPORAL
                if ($product_id == 48612) {
                    file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "]   Item con orden {$order_id}, Fecha: " . date('Y-m-d', $order_time) . "\n", FILE_APPEND);
                    file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "]   ¿Dentro del período? " . ($order_time >= $fecha_limite ? 'SÍ' : 'NO') . "\n", FILE_APPEND);
                }

                // Solo contar items de órdenes dentro del período válido
                if ($order_time < $fecha_limite) {
                    if ($product_id == 48612) {
                        file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "]   SALTADO por fecha antigua\n", FILE_APPEND);
                    }
                    continue;
                }

                // Contar este item
                $cantidad_item = intval($item['cantidad'] ?? 1);
                $cantidad_reclamada += $cantidad_item;

                if ($product_id == 48612) {
                    file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "]   ✓ CONTADO: cantidad {$cantidad_item}\n", FILE_APPEND);
                }
            }
        }

        if ($product_id == 48612) {
            file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "] Total reclamado: $cantidad_reclamada\n", FILE_APPEND);
            file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "] ----------------------------------\n", FILE_APPEND);

            error_log("Total reclamado: " . $cantidad_reclamada);
            error_log("----------------------------------");
        }

        return $cantidad_reclamada;
    }

    /**
     * Calcular cantidad reclamada de un producto específico de UNA orden específica
     */
    private static function get_claimed_quantity_by_order($customer_id, $product_id, $order_id) {
        $args = [
            'post_type' => 'garantia',
            'post_status' => 'publish',
            'meta_query' => [
                ['key' => '_cliente', 'value' => $customer_id],
            ],
            'posts_per_page' => -1,
        ];

        $garantias = get_posts($args);
        $cantidad_reclamada = 0;

        // Obtener la duración de garantía configurada (default: 180 días)
        $duracion_garantia = get_option('duracion_garantia', 180);
        $fecha_limite = strtotime("-{$duracion_garantia} days");

        foreach ($garantias as $garantia) {
            // Obtener los items de esta garantía
            $items = get_post_meta($garantia->ID, '_items_reclamados', true) ?: [];

            foreach ($items as $item) {
                // Verificar si este item es del producto que buscamos
                if (intval($item['producto_id']) !== $product_id) {
                    continue;
                }

                // Obtener el order_id del ITEM
                $item_order_id = $item['order_id'] ?? null;

                // IGNORAR items sin order_id (datos antiguos o corruptos)
                if (empty($item_order_id)) {
                    continue;
                }

                // Solo contar items de LA ORDEN ESPECÍFICA que buscamos
                if ($item_order_id != $order_id) {
                    continue;
                }

                // Verificar que la orden esté dentro del período válido
                $order = wc_get_order($item_order_id);
                if (!$order) continue;

                $order_time = strtotime($order->get_date_completed() ?
                    $order->get_date_completed()->date('Y-m-d H:i:s') :
                    $order->get_date_created()->date('Y-m-d H:i:s')
                );

                // Solo contar si está dentro del período válido
                if ($order_time < $fecha_limite) {
                    continue;
                }

                // Contar este item
                $cantidad_item = intval($item['cantidad'] ?? 1);
                $cantidad_reclamada += $cantidad_item;
            }
        }

        return $cantidad_reclamada;
    }

    private static function can_claim_product($customer_id, $product_id, $cantidad_solicitada) {
        $duracion_garantia = get_option('duracion_garantia', 180);
        $fecha_limite = strtotime("-{$duracion_garantia} days");
        
        $orders = wc_get_orders([
            'customer_id' => $customer_id,
            'status' => 'completed',
            'limit' => -1,
        ]);
        
        $cantidad_comprada = 0;
        foreach ($orders as $order) {
            $order_time = strtotime($order->get_date_completed() ? 
                $order->get_date_completed()->date('Y-m-d H:i:s') : 
                $order->get_date_created()->date('Y-m-d H:i:s')
            );
            
            if ($order_time < $fecha_limite) continue;
            
            foreach ($order->get_items() as $item) {
                if ($item->get_product_id() == $product_id) {
                    $cantidad_comprada += $item->get_quantity();
                }
            }
        }
        
        $cantidad_reclamada = self::get_claimed_quantity($customer_id, $product_id);
        $cantidad_disponible = $cantidad_comprada - $cantidad_reclamada;
        
        return $cantidad_disponible >= $cantidad_solicitada;
    }
    
    private static function notify_admin_new_claim($garantia_id, $codigo_unico) {
        $admin_email = get_option('admin_email_garantias', get_option('admin_email'));
        
        $subject = 'Nueva garanta registrada - ' . $codigo_unico;
        $message = "Se ha registrado una nueva garanta.\n\n";
        $message .= "Código: {$codigo_unico}\n";
        $message .= "Ver detalles: " . admin_url("admin.php?page=wc-garantias-ver&garantia_id={$garantia_id}");
        
        wp_mail($admin_email, $subject, $message);
    }
    
    private static function notify_customer_comment($garantia_id, $comentario) {
        $garantia = get_post($garantia_id);
        $customer = get_userdata($garantia->post_author);
        $codigo = get_post_meta($garantia_id, '_codigo_unico', true);
        
        if ($customer && $customer->user_email) {
            $subject = 'Nuevo comentario en tu garantía - ' . $codigo;
            $message = "Hay un nuevo comentario en tu garanta {$codigo}:\n\n";
            $message .= $comentario . "\n\n";
            $message .= "Ver en tu cuenta: " . wc_get_account_endpoint_url('garantias');
            
            wp_mail($customer->user_email, $subject, $message);
        }
    }
    
    private static function notify_admin_comment($garantia_id, $comentario) {
        $admin_email = get_option('admin_email_garantias', get_option('admin_email'));
        $codigo = get_post_meta($garantia_id, '_codigo_unico', true);
        
        $subject = 'Nuevo comentario del cliente - ' . $codigo;
        $message = "El cliente ha agregado un comentario a la garanta {$codigo}:\n\n";
        $message .= $comentario . "\n\n";
        $message .= "Ver detalles: " . admin_url("admin.php?page=wc-garantias-ver&garantia_id={$garantia_id}");
        
        wp_mail($admin_email, $subject, $message);
    }
    
    /**
     * Obtener contador de garantías nuevas
     */
    public static function admin_get_new_count() {
        // Verificar nonce
        if (!check_ajax_referer('wcgarantias_nonce', 'nonce', false)) {
            wp_send_json_error('Nonce invlido');
            return;
        }
        
        // Verificar permisos
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Sin permisos');
            return;
        }
        
        // Contar garantías nuevas
        $args = array(
            'post_type' => 'garantia',
            'post_status' => 'publish',
            'meta_query' => array(
                array(
                    'key' => '_estado',
                    'value' => 'nueva',
                    'compare' => '='
                )
            ),
            'posts_per_page' => -1
        );
        
        $query = new WP_Query($args);
        $new_count = $query->found_posts;
        
        wp_send_json_success(array(
            'new_count' => $new_count
        ));
    }
    /**
 * Actualizar items con estado "aprobado_devolver" a "devolucion_en_transito"
 */
    public static function actualizar_items_devolver_transito() {
        // Verificar nonce
        if (!check_ajax_referer('actualizar_transito', 'nonce', false)) {
            wp_send_json_error(['message' => 'Error de seguridad']);
            return;
        }
        
        $garantia_id = intval($_POST['garantia_id'] ?? 0);
        
        if (!$garantia_id) {
            wp_send_json_error(['message' => 'ID de garantía no válido']);
            return;
        }
        
        // Verificar permisos
        $garantia = get_post($garantia_id);
        $user_id = get_current_user_id();
        
        if (!$garantia || $garantia->post_author != $user_id) {
            wp_send_json_error(['message' => 'Sin permisos para modificar esta garantía']);
            return;
        }
        
        // Obtener items
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        
        if (!is_array($items)) {
            wp_send_json_error(['message' => 'No se encontraron items']);
            return;
        }
        
        // CAMBIO: Actualizar items con estado "Pendiente" O "aprobado_devolver"
        $items_actualizados = 0;
        $hay_items_para_devolver = false;
        
        foreach ($items as &$item) {
            $estado_actual = isset($item['estado']) ? $item['estado'] : 'Pendiente';
            
            // ACTUALIZAR: Incluir Pendiente además de aprobado_devolver
            if ($estado_actual === 'Pendiente' || 
                $estado_actual === 'aprobado_devolver' || 
                !isset($item['estado'])) {
                
                $item['estado'] = 'devolucion_en_transito';
                $item['fecha_transito'] = current_time('mysql');
                $items_actualizados++;
                $hay_items_para_devolver = true;
            }
        }
        
        if ($items_actualizados > 0) {
            // Guardar items actualizados
            update_post_meta($garantia_id, '_items_reclamados', $items);
            
            // Actualizar estado general si hay items para devolver
            if ($hay_items_para_devolver) {
                update_post_meta($garantia_id, '_estado', 'en_proceso');
            }
            
            // Agregar al historial
            $historial = get_post_meta($garantia_id, '_historial', true) ?: [];
            $historial[] = [
                'fecha' => current_time('mysql'),
                'accion' => 'Items marcados como enviados a WiFix',
                'usuario' => $user_id,
                'items_actualizados' => $items_actualizados
            ];
            update_post_meta($garantia_id, '_historial', $historial);
            
            wp_send_json_success([
                'message' => 'Items actualizados correctamente',
                'items_actualizados' => $items_actualizados
            ]);
        } else {
            // Mensaje más detallado para debug
            $estados_items = [];
            foreach ($items as $item) {
                $estados_items[] = isset($item['estado']) ? $item['estado'] : 'SIN ESTADO';
            }
            
            wp_send_json_error([
                'message' => 'No hay items para actualizar. Estados actuales: ' . implode(', ', $estados_items)
            ]);
        }
    }
        /**
     * Procesar aprobación/rechazo de destrucción
     */
    public static function procesar_destruccion_item() {
        // Verificar nonce
        if (!wp_verify_nonce($_POST['nonce'], 'procesar_destruccion')) {
            wp_send_json_error('Nonce inválido');
        }
        
        // Verificar permisos
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Sin permisos suficientes');
        }
        
        $garantia_id = intval($_POST['garantia_id']);
        $codigo_item = sanitize_text_field($_POST['codigo_item']);
        $decision = sanitize_text_field($_POST['decision']);
        
        // Obtener items
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        
        if (is_array($items)) {
            $item_procesado = false;
            
            foreach ($items as &$item) {
                if (isset($item['codigo_item']) && $item['codigo_item'] === $codigo_item) {
                    if ($decision === 'aprobar') {
                        // Aprobar destrucción
                        $item['estado'] = 'aprobado';
                        $item['destruccion']['aprobada'] = true;
                        $item['destruccion']['fecha_aprobacion'] = current_time('mysql');
                        $item_procesado = true;
                        
                        error_log('Destrucción aprobada para item: ' . $codigo_item);
                    } else {
                        // Rechazar destrucción - vuelve al estado anterior
                        $item['estado'] = 'aprobado_destruir';
                        $item['destruccion']['rechazada'] = true;
                        $item['destruccion']['fecha_rechazo'] = current_time('mysql');
                        $item['destruccion']['confirmado'] = false; // Reset confirmación
                        $item_procesado = true;
                        
                        // Guardar el motivo de rechazo si se proporcionó
                        $motivo_rechazo = isset($_POST['motivo_rechazo']) 
                            ? sanitize_textarea_field($_POST['motivo_rechazo']) 
                            : 'La evidencia de destrucción no cumple con los requisitos. Por favor, destruye completamente el producto y sube nueva evidencia.';
                        
                        $item['destruccion']['motivo_rechazo'] = $motivo_rechazo;
                        
                        error_log('Destrucción rechazada para item: ' . $codigo_item);
                    }
                    break;
                }
            }
            
            if ($item_procesado) {
                // Guardar cambios
                update_post_meta($garantia_id, '_items_reclamados', $items);
                
                // Actualizar estado general de la garantía
                if (class_exists('WC_Garantias_Admin')) {
                    WC_Garantias_Admin::actualizar_estado_garantia($garantia_id);
                }
                
                // Enviar notificación según la decisión
                    if ($decision === 'aprobar') {
                        // Si se aprueba, verificar si generar cupón
                        self::verificar_generar_cupon_si_completo($garantia_id, $items);
                    } else {
                        // Si se rechaza, notificar al cliente

                        $cliente_id = get_post_meta($garantia_id, '_cliente', true);

                        $user = get_userdata($cliente_id);
                        
                        if ($user) {

                            if ($user->user_email && class_exists('WC_Garantias_Emails')) {

                                $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);

                                // Preparar variables para el email
                                $variables = array(
                                    'cliente' => $user->display_name ?: $user->user_login,
                                    'codigo' => $codigo_unico,
                                    'motivo' => $motivo_rechazo,
                                    'link_cuenta' => wc_get_account_endpoint_url('garantias'),
                                    'garantia_id' => $garantia_id,
                                    'item_codigo_procesado' => $codigo_item
                                );
                                

                                // Enviar email de destrucción rechazada
                                $resultado = WC_Garantias_Emails::enviar_email('destruccion_rechazada', $user->user_email, $variables);
                                
                            } else {
                            }
                        } else {
                        }
                    }
                
                // Verificar si generar cupón (si todos los items están procesados)
                self::verificar_generar_cupon_si_completo($garantia_id, $items);
                
                wp_send_json_success([
                    'message' => 'Destrucción ' . ($decision === 'aprobar' ? 'aprobada' : 'rechazada') . ' correctamente'
                ]);
            } else {
                wp_send_json_error('Item no encontrado');
            }
        } else {
            wp_send_json_error('No hay items en esta garantía');
        }
    }
    
    /**
     * Verificar si generar cupón después de procesar destrucción
     */
    private static function verificar_generar_cupon_si_completo($garantia_id, $items) {
        $todos_procesados = true;
        $hay_aprobados = false;
        
        foreach ($items as $item) {
            $estado = strtolower(trim($item['estado']));
            
            if ($estado === 'aprobado') {
                $hay_aprobados = true;
            }
            
            // Estados finales
            if (!in_array($estado, ['aprobado', 'rechazado', 'retorno_cliente'])) {
                $todos_procesados = false;
            }
        }
        
        // Si todos están procesados y hay al menos uno aprobado, generar cupón
        if ($todos_procesados && $hay_aprobados) {
            if (class_exists('WC_Garantias_Cupones')) {
                $codigo_cupon = WC_Garantias_Cupones::generar_cupon_garantia($garantia_id);
                if ($codigo_cupon) {
                    update_post_meta($garantia_id, '_estado', 'finalizado_cupon');
                    error_log('Cupón generado automáticamente: ' . $codigo_cupon);
                }
            }
        }
    }
}

// Inicializar la clase
WC_Garantias_Ajax::init();