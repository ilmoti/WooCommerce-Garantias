<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Versión híbrida que mantiene las funciones críticas del código original
 * pero mejora la interfaz de "Todas las Garantías"
 */

// Cargar archivos modulares si existen
$admin_path = plugin_dir_path(__FILE__) . 'admin/';
if (file_exists($admin_path)) {
    // Cargar módulos gradualmente
    if (file_exists($admin_path . 'class-wc-garantias-admin-motivos.php')) {
        require_once $admin_path . 'class-wc-garantias-admin-motivos.php';
    }
    if (file_exists($admin_path . 'class-wc-garantias-admin-config.php')) {
        require_once $admin_path . 'class-wc-garantias-admin-config.php';
    }
    if (file_exists($admin_path . 'class-wc-garantias-admin-rma.php')) {
        require_once $admin_path . 'class-wc-garantias-admin-rma.php';
    }
    if (file_exists($admin_path . 'class-wc-garantias-admin-dashboard.php')) {
        require_once $admin_path . 'class-wc-garantias-admin-dashboard.php';
    }
     if (file_exists($admin_path . 'class-wc-garantias-admin-analisis.php')) {
        require_once $admin_path . 'class-wc-garantias-admin-analisis.php';
    }
    if (file_exists($admin_path . 'class-wc-garantias-admin-list.php')) {
        require_once $admin_path . 'class-wc-garantias-admin-list.php';
    }
    if (file_exists($admin_path . 'class-wc-garantias-admin-view.php')) {
        require_once $admin_path . 'class-wc-garantias-admin-view.php';
    }
    if (file_exists($admin_path . 'class-wc-garantias-admin-docs.php')) {
        require_once $admin_path . 'class-wc-garantias-admin-docs.php';
    }
    if (file_exists($admin_path . 'class-wc-garantias-admin-items-rechazados.php')) {
        require_once $admin_path . 'class-wc-garantias-admin-items-rechazados.php';
    }
}

class WC_Garantias_Admin {

    // MANTENER: Estados del sistema original
      private static $estados = [
        'nueva'       => 'Nueva',
        'en_proceso'  => 'En proceso', 
        'finalizada'  => 'Finalizada'
    ];

    public static function init() {
        // TEMPORAL - ELIMINAR DESPUÉS DE EJECUTAR
        if (isset($_GET['unificar_estados'])) {
            $actualizados = self::unificar_estados_finalizados();
            wp_die("Se actualizaron {$actualizados} garantías. <a href='" . admin_url('admin.php?page=wc-garantias') . "'>Volver</a>");
        }
        add_action( 'admin_menu', [ __CLASS__, 'add_admin_menu' ] );
        add_action( 'admin_notices', [ __CLASS__, 'show_admin_notice' ] );
        add_action( 'admin_enqueue_scripts', [ __CLASS__, 'enqueue_assets' ] );
        
        // NUEVO: Handlers AJAX para la tabla mejorada
        add_action( 'wp_ajax_garantias_get_table_data', [ __CLASS__, 'ajax_get_table_data' ] );
        add_action( 'wp_ajax_garantias_quick_view', [ __CLASS__, 'ajax_quick_view' ] );
        add_action( 'wp_ajax_garantias_delete', [ __CLASS__, 'ajax_delete_garantia' ] );
        add_action( 'wp_ajax_garantias_update_comment', [ __CLASS__, 'ajax_update_comment' ] );
        
        // AJAX para imprimir etiquetas individuales QR
        add_action('wp_ajax_imprimir_etiquetas_individuales', array(__CLASS__, 'ajax_imprimir_etiquetas_individuales'));
        // AJAX para imprimir etiqueta individual de un item específico
        add_action('wp_ajax_imprimir_etiqueta_individual_item', array(__CLASS__, 'ajax_imprimir_etiqueta_individual_item'));
    }

    // MANTENER: Función original del menú
    public static function add_admin_menu() {
        // Men principal
        add_menu_page(
            __( 'Garantas', 'woocommerce-garantias' ),
            __( 'Garantías', 'woocommerce-garantias' ),
            'manage_woocommerce',
            'wc-garantias-dashboard',
            [ __CLASS__, 'dashboard_page_content' ],
            'dashicons-shield',
            56
        );
        
        // Dashboard
        add_submenu_page(
            'wc-garantias-dashboard',
            'Dashboard',
            'Dashboard',
            'manage_woocommerce',
            'wc-garantias-dashboard',
            [ __CLASS__, 'dashboard_page_content' ]
        );
        
        // Todas las garantas 
        add_submenu_page(
            'wc-garantias-dashboard',
            'Todas las Garantías',
            'Todas las Garantas',
            'manage_woocommerce',
            'wc-garantias',
            [ __CLASS__, 'admin_page_content' ]
        );
        
        // Analisis
        add_submenu_page(
            'wc-garantias-dashboard',
            'Análisis de Garantías',
            '📊 Análisis',
            'manage_woocommerce',
            'wc-garantias-analisis',
            ['WC_Garantias_Admin_Analisis', 'render_page']
        );
        
        // Configuración
        add_submenu_page(
            'wc-garantias-dashboard',
            'Configuracin',
            'Configuracin',
            'manage_woocommerce',
            'wc-garantias-config',
            [ __CLASS__, 'config_page_content' ]
        );
        
        // Motivos
        add_submenu_page(
            'wc-garantias-dashboard',
            'Motivos',
            'Motivos',
            'manage_woocommerce',
            'wc-garantias-motivos',
            [ __CLASS__, 'motivos_page_content' ]
        );
        
        // Ver Garantía 
        add_submenu_page(
            null,
            'Ver Garantía',
            'Ver Garanta',
            'manage_woocommerce',
            'wc-garantias-ver',
            [ __CLASS__, 'ver_garantia_page' ]
        );
        
        // Panel RMA
        add_submenu_page(
            'wc-garantias-dashboard',
            'Devoluciones Pendientes',
            'Devoluciones Pendientes',
            'manage_woocommerce',
            'wc-garantias-rma',
            [ __CLASS__, 'rma_page_content' ]
        );
        
        // Items Rechazados
        add_submenu_page(
            'wc-garantias-dashboard',
            'Items Rechazados',
            'Items Rechazados',
            'manage_woocommerce',
            'wc-garantias-items-rechazados',
            [ __CLASS__, 'items_rechazados_page_content' ]
        );
        
        // Documentación
        add_submenu_page(
            'wc-garantias-dashboard',
            'Documentación',
            ' Documentación',
            'manage_woocommerce',
            'wc-garantias-docs',
            [ __CLASS__, 'docs_page_content' ]
        );
    }

    // MANTENER: Funcin de notificaciones
    public static function show_admin_notice() {
    if (isset($_GET['error_motivo_rechazo'])) {
        echo '<div class="notice notice-error"><p>Debes ingresar un motivo de rechazo para rechazar la garantía.</p></div>';
    }
    if (isset($_GET['etiqueta_solicitada'])) {
        echo '<div class="notice notice-success"><p>Etiqueta de envo solicitada correctamente. Se ha notificado al distribuidor.</p></div>';
    }
    if (isset($_GET['etiqueta_subida'])) {
        echo '<div class="notice notice-success"><p>Etiqueta subida correctamente. El distribuidor ha sido notificado.</p></div>';
    }
    if (isset($_GET['error_etiqueta']) && $_GET['error_etiqueta'] === 'tipo') {
        echo '<div class="notice notice-error"><p>Error: Solo se permiten archivos PDF para las etiquetas.</p></div>';
    }
    if (isset($_GET['destruccion_confirmada'])) {
        echo '<div class="notice notice-success"><p>Destrucción confirmada. Se ha generado el cupón automáticamente.</p></div>';
    }
    if (isset($_GET['etiqueta_eliminada'])) {
        echo '<div class="notice notice-success"><p>Etiqueta eliminada correctamente. El distribuidor ha sido notificado.</p></div>';
    }
    if (isset($_GET['items_aprobados'])) {
        $items_aprobados = intval($_GET['items_aprobados']);
        $mensaje = "Se aprobaron {$items_aprobados} items.";
        
        if (isset($_GET['items_pendientes'])) {
            $items_pendientes = intval($_GET['items_pendientes']);
            $mensaje .= " Aún quedan {$items_pendientes} items sin procesar. No se generará cupón hasta que todos estén aprobados, rechazados o retorno cliente.";
        }
        
        if (isset($_GET['cupon_generado'])) {
            $codigo_cupon = sanitize_text_field($_GET['cupon_generado']);
            $mensaje = "Items procesados y cupn generado: {$codigo_cupon}";
        }
        echo '<div class="notice notice-success"><p>' . $mensaje . '</p></div>';
    }
}
// FUNCIN TEMPORAL para arreglar códigos duplicados
    public static function arreglar_codigos_duplicados($garantia_id) {
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        
        if (!is_array($items)) return false;
        
        $codigos_vistos = [];
        $hay_duplicados = false;
        
        // Verificar y arreglar duplicados
        foreach ($items as &$item) {
            if (isset($item['codigo_item'])) {
                if (in_array($item['codigo_item'], $codigos_vistos)) {
                    // Cdigo duplicado encontrado - generar uno nuevo
                    $item['codigo_item'] = 'GRT-ITEM-' . strtoupper(wp_generate_password(8, false, false));
                    $hay_duplicados = true;
                    error_log('Código duplicado encontrado y regenerado: ' . $item['codigo_item']);
                }
                $codigos_vistos[] = $item['codigo_item'];
            }
        }
        
        if ($hay_duplicados) {
            update_post_meta($garantia_id, '_items_reclamados', $items);
            error_log('Códigos duplicados arreglados para garantía: ' . $garantia_id);
            return true;
        }
        
        return false;
    }

    // NUEVO: Enqueue assets para la tabla mejorada
    public static function enqueue_assets( $hook ) {
        if ( 'garantias_page_wc-garantias' !== $hook ) {
            return;
        }

        // CSS Framework
        wp_enqueue_style( 'bootstrap5', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css' );
        
        // DataTables
        wp_enqueue_style( 'datatables', 'https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css' );
        wp_enqueue_script( 'datatables', 'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js', ['jquery'] );
        wp_enqueue_script( 'datatables-bootstrap5', 'https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js', ['datatables'] );
        
        // Font Awesome
        wp_enqueue_style( 'fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css' );
        
        // SweetAlert2
        wp_enqueue_script( 'sweetalert2', 'https://cdn.jsdelivr.net/npm/sweetalert2@11' );
        
        // CSS personalizado inline
        wp_add_inline_style( 'datatables', '
            .garantias-table-container { padding: 20px; background: #fff; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
            .badge-estado { padding: 5px 12px; border-radius: 20px; font-size: 12px; }
            #garantias-table tbody tr:hover { background: #f8f9fa; }
            .btn-actions { padding: 4px 8px; font-size: 12px; }
        ' );
        
        // Localization
        wp_localize_script( 'datatables', 'garantias_ajax', [
            'url' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'garantias_nonce' )
        ] );
    }

    public static function admin_page_content() {
        // Delegar al módulo
        if (class_exists('WC_Garantias_Admin_List')) {
            WC_Garantias_Admin_List::render_page();
            return;
        }
        // Fallback mnimo
        echo '<div class="wrap"><h1>Error</h1><p>Módulo de lista no encontrado.</p></div>';
    }

    public static function ajax_delete_garantia() {
        check_ajax_referer('garantias_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Sin permisos');
        }
        
        $garantia_id = intval($_POST['garantia_id']);
        if (wp_delete_post($garantia_id, true)) {
            wp_send_json_success();
        } else {
            wp_send_json_error('Error al eliminar');
        }
    }

    public static function ajax_update_comment() {
        check_ajax_referer('garantias_nonce', 'nonce');
        
        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error('Sin permisos');
        }
        
        $garantia_id = intval($_POST['garantia_id']);
        $comentario = sanitize_textarea_field($_POST['comentario']);
        
        update_post_meta($garantia_id, '_comentario_interno', $comentario);
        wp_send_json_success();
    }

        public static function dashboard_page_content() {
        // Delegar al módulo
        if (class_exists('WC_Garantias_Admin_Dashboard')) {
            WC_Garantias_Admin_Dashboard::render_page();
            return;
        }
        // Fallback mínimo
        echo '<div class="wrap"><h1>Error</h1><p>Módulo Dashboard no encontrado.</p></div>';
    }
    
    public static function motivos_page_content() {
        // Delegar al módulo
        if (class_exists('WC_Garantias_Admin_Motivos')) {
            WC_Garantias_Admin_Motivos::render_page();
            return;
        }
        // Fallback mnimo si no existe el módulo
        echo '<div class="wrap"><h1>Error</h1><p>Módulo de motivos no encontrado.</p></div>';
    }
    
    public static function config_page_content() {
        // Delegar al módulo
        if (class_exists('WC_Garantias_Admin_Config')) {
            WC_Garantias_Admin_Config::render_page();
            return;
        }
        // Fallback mnimo
        echo '<div class="wrap"><h1>Error</h1><p>Mdulo de configuración no encontrado.</p></div>';
    }
    
    public static function ver_garantia_page() {
        // Delegar al mdulo
        if (class_exists('WC_Garantias_Admin_View')) {
            WC_Garantias_Admin_View::render_page();
            return;
        }
        // Fallback mínimo
        echo '<div class="wrap"><h1>Error</h1><p>Mdulo de vista no encontrado.</p></div>';
    }
    
    public static function export_csv() {
        // Delegar al módulo
        if (class_exists('WC_Garantias_Admin_List')) {
            WC_Garantias_Admin_List::export_csv();
            return;
        }
    }

    public static function actualizar_estado_garantia($garantia_id) {
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        
        if (!is_array($items) || empty($items)) return;
        
        // Contar estados
        $todos_finalizados = true;
        $alguno_procesado = false;
        $hay_items_aprobados = false;
        
        foreach ($items as $item) {
            $estado_item = $item['estado'] ?? 'Pendiente';
            
            // Verificar si hay items aprobados
            if ($estado_item === 'aprobado') {
                $hay_items_aprobados = true;
            }
            
            // Un item está finalizado si está aprobado, rechazado o retorno_cliente con RMA
            if ($estado_item === 'retorno_cliente') {
                // Si tiene cupón RMA, considerarlo finalizado
                if (!empty($item['cupon_rma'])) {
                    // Está finalizado
                } else {
                    $todos_finalizados = false;
                }
            } elseif (!in_array($estado_item, ['aprobado', 'rechazado', 'rechazado_no_recibido'])) {
                $todos_finalizados = false;
            }
            
            // Si hay algún item que ya no está pendiente
            if ($estado_item !== 'Pendiente') {
                $alguno_procesado = true;
            }
        }
        
        // Determinar estado general
        if ($todos_finalizados) {
            $estado_general = 'finalizada';
            
            // Si hay items aprobados y no existe cupón, generarlo
            if ($hay_items_aprobados) {
                $cupon_existente = get_post_meta($garantia_id, '_cupon_generado', true);
                if (!$cupon_existente) {
                    WC_Garantias_Cupones::generar_cupon_garantia($garantia_id);
                }
            }
        } elseif ($alguno_procesado) {
            $estado_general = 'en_proceso';
        } else {
            $estado_general = 'nueva';
        }
        
        update_post_meta($garantia_id, '_estado', $estado_general);
    }

    // Notificar al cliente sobre cambios en items
        public static function notificar_cliente_cambio_items($garantia_id, $items_seleccionados, $accion) {
        $cliente_id = get_post_meta($garantia_id, '_cliente', true);
        $user = get_userdata($cliente_id);
        
        if ($user && $user->user_email) {
            $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
            
            // Mapear acciones a tipos de email
            $email_types = [
                'aprobado_destruir' => 'destruccion_aprobada',
                'aprobado_devolver' => 'aprobada_devolucion',
                'rechazado' => 'rechazada'
            ];
            
            if (isset($email_types[$accion])) {
                $variables = [
                    'cliente' => $user->display_name,
                    'codigo' => $codigo_unico,
                    'link_cuenta' => wc_get_account_endpoint_url('garantias')
                ];
                
                // Agregar direccin si es devolucin
                if ($accion === 'aprobado_devolver') {
                    $variables['direccion_devolucion'] = get_option('direccion_devolucion_garantias', 
                        'Contactar al administrador para obtener la direccin');
                }
                
                WC_Garantias_Emails::enviar_email($email_types[$accion], $user->user_email, $variables);
            }
        }
    }
    public static function rma_page_content() {
        // Delegar al mdulo
        if (class_exists('WC_Garantias_Admin_RMA')) {
            WC_Garantias_Admin_RMA::render_page();
            return;
        }
        // Fallback mínimo
        echo '<div class="wrap"><h1>Error</h1><p>Mdulo RMA no encontrado.</p></div>';
    }
    public static function docs_page_content() {
        // Delegar al mdulo
        if (class_exists('WC_Garantias_Admin_Docs')) {
            WC_Garantias_Admin_Docs::render_page();
            return;
        }
        // Fallback mínimo
        echo '<div class="wrap"><h1>Error</h1><p>Módulo de documentación no encontrado.</p></div>';
    }
    public static function ajax_imprimir_etiquetas_individuales() {
        if (!isset($_GET['garantia_id'])) {
            wp_die('ID de garanta no proporcionado');
        }
        
        $garantia_id = intval($_GET['garantia_id']);
        $codigo_garantia = get_post_meta($garantia_id, '_codigo_unico', true);
        $cliente_id = get_post_meta($garantia_id, '_cliente', true);
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        
        // Obtener nombre del cliente
        $nombre_cliente = 'Cliente';
        if ($cliente_id) {
            $user = get_userdata($cliente_id);
            if ($user) {
                $nombre_cliente = $user->display_name;
            }
        }
        
        // Filtrar solo items en retorno_cliente y preparar etiquetas
        $etiquetas = [];
        if (is_array($items)) {
            foreach ($items as $item) {
                if (isset($item['estado']) && $item['estado'] === 'retorno_cliente') {
                    $producto_nombre = 'Producto';
                    $sku = 'SIN-SKU';
                    $codigo_item = $item['codigo_item'] ?? '';
                    
                    if ($item['producto_id'] ?? 0) {
                        $producto = wc_get_product($item['producto_id']);
                        if ($producto) {
                            $producto_nombre = $producto->get_name();
                            // Obtener SKU real del producto
                            $sku_real = get_post_meta($item['producto_id'], '_alg_ean', true);
                            if (!$sku_real) {
                                $sku_real = $producto->get_sku();
                            }
                            if ($sku_real) {
                                $sku = $sku_real;
                            }
                        } else {
                            $producto_nombre = $item['nombre_producto'] ?? 'Producto eliminado';
                        }
                    }
                    
                    // Crear una etiqueta por cada unidad
                    $cantidad = intval($item['cantidad'] ?? 1);
                    for ($i = 0; $i < $cantidad; $i++) {
                        $etiquetas[] = [
                            'codigo_garantia_item' => $codigo_item, // Este es el código único del item (GRT-ITEM-XXXXX)
                            'sku' => $sku, // SKU original del producto
                            'nombre_producto' => substr($producto_nombre, 0, 35), // Limitar longitud
                            'nombre_cliente' => substr($nombre_cliente, 0, 20), // Limitar longitud
                        ];
                    }
                }
            }
        }
        
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Etiquetas Brother - <?php echo esc_html($codigo_garantia); ?></title>
            <style>
                @page {
                    size: 60mm 20mm; /* Tamaño exacto para Brother */
                    margin: 0;
                }
                
                body {
                    margin: 0;
                    padding: 0;
                    font-family: Arial, sans-serif;
                }
                
                .etiqueta-page {
                    width: 60mm;
                    height: 20mm;
                    page-break-after: always;
                    position: relative;
                    display: flex;
                    align-items: center;
                    padding: 0.5mm;
                    box-sizing: border-box;
                    overflow: hidden;
                }
                
                .etiqueta-page:last-child {
                    page-break-after: avoid;
                }
                
                .qr-section {
                    width: 18mm;
                    height: 18mm;
                    margin-right: 1.5mm;
                    flex-shrink: 0;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                
                .qr-section img {
                    width: 17mm;
                    height: 17mm;
                }
                
                .info-section {
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    font-size: 6.5pt;
                    line-height: 1.1;
                    overflow: hidden;
                }
                
                .codigo-garantia {
                    font-weight: bold;
                    font-size: 7pt;
                    margin-bottom: 0.5mm;
                    color: #000;
                }
                
                .sku-line {
                    font-size: 6.5pt;
                    margin-bottom: 0.5mm;
                }
                
                .sku-line strong {
                    font-weight: bold;
                }
                
                .producto {
                    font-size: 6pt;
                    margin-bottom: 0.5mm;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                    color: #333;
                }
                
                .cliente {
                    font-size: 6pt;
                    color: #333;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }
                
                @media screen {
                    /* Vista previa en pantalla */
                    body {
                        background: #f0f0f0;
                        padding: 20px;
                    }
                    
                    .etiqueta-page {
                        background: white;
                        border: 1px solid #ccc;
                        margin-bottom: 10px;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                        transform: scale(2);
                        margin: 20px auto;
                        transform-origin: top left;
                    }
                    
                    .controls {
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        z-index: 1000;
                        background: white;
                        padding: 20px;
                        border-radius: 8px;
                        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    }
                    
                    .preview-container {
                        margin-left: 140px;
                    }
                }
                
                @media print {
                    .controls {
                        display: none !important;
                    }
                    
                    .preview-container {
                        margin: 0 !important;
                    }
                    
                    .etiqueta-page {
                        border: none !important;
                        box-shadow: none !important;
                        margin: 0 !important;
                        transform: none !important;
                    }
                }
                
                .btn-imprimir {
                    background: #4CAF50;
                    color: white;
                    padding: 12px 24px;
                    border: none;
                    border-radius: 4px;
                    cursor: pointer;
                    font-size: 16px;
                    margin-bottom: 10px;
                    display: block;
                    width: 100%;
                }
                
                .btn-imprimir:hover {
                    background: #45a049;
                }
                
                .info-text {
                    font-size: 12px;
                    color: #666;
                    margin-top: 10px;
                    border-top: 1px solid #ddd;
                    padding-top: 10px;
                }
                
                .label-preview {
                    background: #f9f9f9;
                    padding: 10px;
                    border-radius: 4px;
                    margin-bottom: 10px;
                    font-size: 11px;
                }
            </style>
        </head>
        <body>
            <div class="controls">
                <h3 style="margin-top: 0;">Etiquetas Brother 60x20mm</h3>
                <p style="font-size: 14px; margin: 10px 0;">
                    <strong><?php echo count($etiquetas); ?></strong> etiqueta(s) a imprimir
                </p>
                
                <div class="label-preview">
                    <strong>Contenido de cada etiqueta:</strong><br>
                    • QR + Código garanta<br>
                    • SKU del producto<br>
                    • Nombre del item<br>
                    • Nombre del cliente
                </div>
                
                <button onclick="window.print(); return false;" class="btn-imprimir">
                    🖨️ IMPRIMIR ETIQUETAS
                </button>
                
                <div class="info-text">
                    Configurar impresora:<br>
                     Tamaño: 60x20mm<br>
                    • Sin márgenes<br>
                    • Una etiqueta por página
                </div>
            </div>
            
            <div class="preview-container">
                <?php 
                $contador = 0;
                foreach ($etiquetas as $etiqueta): 
                    $contador++;
                    // El QR contiene el código de garantía del item
                    $qr_data = $etiqueta['codigo_garantia_item'];
                    $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&margin=0&data=' . urlencode($qr_data);
                ?>
                    <div class="etiqueta-page">
                        <div class="qr-section">
                            <img src="<?php echo esc_url($qr_url); ?>" alt="QR">
                        </div>
                        <div class="info-section">
                            <div class="codigo-garantia"><?php echo esc_html($etiqueta['codigo_garantia_item']); ?></div>
                            <div class="sku-line"><strong>SKU:</strong> <?php echo esc_html($etiqueta['sku']); ?></div>
                            <div class="producto"><?php echo esc_html($etiqueta['nombre_producto']); ?></div>
                            <div class="cliente">Cliente: <?php echo esc_html($etiqueta['nombre_cliente']); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <script>
                // Auto-abrir diálogo de impresión
                window.onload = function() {
                    // Mostrar alerta informativa si son muchas etiquetas
                    if (<?php echo count($etiquetas); ?> > 10) {
                        alert('Se imprimirán <?php echo count($etiquetas); ?> etiquetas.\n\nAsegúrate de tener suficientes etiquetas en la impresora Brother.');
                    }
                    
                    setTimeout(function() {
                        window.print();
                    }, 1000);
                };
            </script>
        </body>
        </html>
        <?php
        wp_die();
    }
    public static function ajax_imprimir_etiqueta_individual_item() {
        if (!isset($_GET['garantia_id']) || !isset($_GET['codigo_item'])) {
            wp_die('Datos incompletos');
        }
        
        $garantia_id = intval($_GET['garantia_id']);
        $codigo_item = sanitize_text_field($_GET['codigo_item']);
        
        $codigo_garantia = get_post_meta($garantia_id, '_codigo_unico', true);
        $cliente_id = get_post_meta($garantia_id, '_cliente', true);
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        
        // Obtener nombre del cliente
        $nombre_cliente = 'Cliente';
        if ($cliente_id) {
            $user = get_userdata($cliente_id);
            if ($user) {
                $nombre_cliente = $user->display_name;
            }
        }
        
        // Buscar el item específico
        $etiquetas = [];
        if (is_array($items)) {
            foreach ($items as $item) {
                if (isset($item['codigo_item']) && $item['codigo_item'] === $codigo_item) {
                    $producto_nombre = 'Producto';
                    $sku = 'SIN-SKU';
                    
                    if ($item['producto_id'] ?? 0) {
                        $producto = wc_get_product($item['producto_id']);
                        if ($producto) {
                            $producto_nombre = $producto->get_name();
                            $sku_real = get_post_meta($item['producto_id'], '_alg_ean', true);
                            if (!$sku_real) {
                                $sku_real = $producto->get_sku();
                            }
                            if ($sku_real) {
                                $sku = $sku_real;
                            }
                        } else {
                            $producto_nombre = $item['nombre_producto'] ?? 'Producto eliminado';
                        }
                    }
                    
                    // Crear una etiqueta por cada unidad
                    $cantidad = intval($item['cantidad'] ?? 1);
                    for ($i = 0; $i < $cantidad; $i++) {
                        $etiquetas[] = [
                            'codigo_garantia_item' => $codigo_item,
                            'sku' => $sku,
                            'nombre_producto' => substr($producto_nombre, 0, 35),
                            'nombre_cliente' => substr($nombre_cliente, 0, 20),
                        ];
                    }
                    break;
                }
            }
        }
        
        if (empty($etiquetas)) {
            wp_die('Item no encontrado');
        }
        
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Etiqueta - <?php echo esc_html($codigo_item); ?></title>
            <style>
                @page {
                    size: 60mm 20mm;
                    margin: 0;
                }
                
                body {
                    margin: 0;
                    padding: 0;
                    font-family: Arial, sans-serif;
                }
                
                .etiqueta-page {
                    width: 60mm;
                    height: 20mm;
                    page-break-after: always;
                    position: relative;
                    display: flex;
                    align-items: center;
                    padding: 0.5mm;
                    box-sizing: border-box;
                    overflow: hidden;
                }
                
                .etiqueta-page:last-child {
                    page-break-after: avoid;
                }
                
                .qr-section {
                    width: 18mm;
                    height: 18mm;
                    margin-right: 1.5mm;
                    flex-shrink: 0;
                }
                
                .qr-section img {
                    width: 17mm;
                    height: 17mm;
                }
                
                .info-section {
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                    justify-content: center;
                    font-size: 8pt;
                    line-height: 1.1;
                }
                
                .codigo-garantia {
                    font-weight: bold;
                    font-size: 9pt;
                    margin-bottom: 0.5mm;
                }
                
                .sku-line {
                    font-size: 9pt;
                    margin-bottom: 0.5mm;
                }
                
                .producto {
                    font-size: 7pt;
                    margin-bottom: 0.5mm;
                    word-wrap: break-word;
                    line-height: 1.1;
                    max-height: 5mm;     /* Limitar altura máxima */
                    overflow: hidden;    /* Por si acaso es muy largo */
                }
                
                .cliente {
                    font-size: 7.5pt;
                    overflow: hidden;
                    text-overflow: ellipsis;
                    white-space: nowrap;
                }
                
                @media screen {
                    body {
                        background: #f0f0f0;
                        padding: 20px;
                    }
                    
                    .etiqueta-page {
                        background: white;
                        border: 1px solid #ccc;
                        margin-bottom: 10px;
                        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                        transform: scale(2);
                        margin: 20px auto;
                        transform-origin: top left;
                    }
                    
                    .controls {
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        background: white;
                        padding: 20px;
                        border-radius: 8px;
                        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    }
                }
                
                @media print {
                    .controls {
                        display: none !important;
                    }
                    
                    .etiqueta-page {
                        transform: none !important;
                    }
                }
            </style>
        </head>
        <body>
            <div class="controls">
                <h3>Etiqueta Individual</h3>
                <p><strong><?php echo count($etiquetas); ?></strong> etiqueta(s)</p>
                <button onclick="window.print();" style="padding: 10px 20px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    ️ IMPRIMIR
                </button>
            </div>
            
            <?php foreach ($etiquetas as $etiqueta): 
                $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&margin=0&data=' . urlencode($etiqueta['codigo_garantia_item']);
            ?>
                <div class="etiqueta-page">
                    <div class="qr-section">
                        <img src="<?php echo esc_url($qr_url); ?>" alt="QR">
                    </div>
                    <div class="info-section">
                        <div class="codigo-garantia"><?php echo esc_html($etiqueta['codigo_garantia_item']); ?></div>
                        <div class="sku-line"><strong>SKU:</strong> <?php echo esc_html($etiqueta['sku']); ?></div>
                        <div class="producto"><?php echo esc_html($etiqueta['nombre_producto']); ?></div>
                        <div class="cliente">Cliente: <?php echo esc_html($etiqueta['nombre_cliente']); ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
            
            <script>
                window.onload = function() {
                    setTimeout(function() {
                        window.print();
                    }, 1000);
                };
            </script>
        </body>
        </html>
        <?php
        wp_die();
    }
    public static function unificar_estados_finalizados() {
        global $wpdb;
        
        // Actualizar todas las garantías con estado 'finalizado_cupon' a 'finalizada'
        $updated = $wpdb->update(
            $wpdb->postmeta,
            ['meta_value' => 'finalizada'],
            [
                'meta_key' => '_estado',
                'meta_value' => 'finalizado_cupon'
            ]
        );
        
        return $updated;
    }
    
    // Página de items rechazados
    public static function items_rechazados_page_content() {
        $module_path = plugin_dir_path(__FILE__) . 'admin/class-wc-garantias-admin-items-rechazados.php';
        if (file_exists($module_path)) {
            require_once $module_path;
            WC_Garantias_Admin_Items_Rechazados::render_page();
        } else {
            echo '<div class="notice notice-error"><p>Error: No se pudo cargar el módulo de items rechazados.</p></div>';
        }
    }
    
}



// Aplica el cupn de garanta automticamente
foreach(['woocommerce_before_cart', 'woocommerce_before_checkout_form'] as $hook) {
    add_action($hook, function() {
        if ( is_user_logged_in() ) {
            $user_id = get_current_user_id();
            $cupon = get_user_meta($user_id, '_cupon_garantia_pendiente', true);
            if ($cupon && !WC()->cart->has_discount($cupon)) {
                WC()->cart->add_discount($cupon);
            }
        }
    });
}

