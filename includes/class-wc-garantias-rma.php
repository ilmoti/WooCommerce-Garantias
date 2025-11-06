<?php
if (!defined('ABSPATH')) exit;

class WC_Garantias_RMA {
    
    /**
     * Crear producto RMA cuando se rechaza definitivamente
     */
    public static function crear_producto_rma($item, $garantia_id) {
        // Obtener el producto original
        $producto_original = wc_get_product($item['producto_id']);
        if (!$producto_original) {
            return false;
        }
        
        // Preparar datos del producto RMA
        $codigo_item = $item['codigo_item'];
        $nombre_rma = 'RMA - ' . $producto_original->get_name();
        
        // Crear el producto
        $producto_rma = new WC_Product_Simple();
        $producto_rma->set_name($nombre_rma);
        $producto_rma->set_status('publish');
        $producto_rma->set_catalog_visibility('hidden'); // Oculto del catálogo
        $producto_rma->set_regular_price(0);
        $producto_rma->set_price(0);
        $producto_rma->set_manage_stock(true);
        $producto_rma->set_stock_quantity(1);
        $producto_rma->set_stock_status('instock');
        
        // Asignar a categoría RMA
        $categoria_rma = get_term_by('slug', 'rma', 'product_cat');
        if ($categoria_rma) {
            $producto_rma->set_category_ids([$categoria_rma->term_id]);
        }
        
        // Guardar producto
        $producto_id = $producto_rma->save();
        
        if ($producto_id) {
            // Guardar el SKU en el meta campo correcto
            update_post_meta($producto_id, '_alg_ean', $codigo_item);
            
            // Guardar referencia a la garantía original
            update_post_meta($producto_id, '_garantia_id', $garantia_id);
            update_post_meta($producto_id, '_es_producto_rma', 'yes');
            update_post_meta($producto_id, '_fecha_creacion_rma', current_time('mysql'));
            
            return $producto_id;
        }
        
        return false;
    }
    
    /**
     * Crear cupón para producto RMA
     */
    /**
     * Crear cupón para producto RMA
     */
    public static function crear_cupon_rma($producto_rma_id, $cliente_id, $codigo_item) {
        $producto = wc_get_product($producto_rma_id);
        if (!$producto) {
            return false;
        }
        
        // Obtener el SKU real del producto RMA
        $sku_real = get_post_meta($producto_rma_id, '_alg_ean', true);
        if (!$sku_real) {
            $sku_real = $producto->get_sku();
        }
        if (!$sku_real) {
            $sku_real = $codigo_item; // Fallback al código del item
        }
        
        // Obtener la cantidad del stock del producto RMA
        $cantidad = $producto->get_stock_quantity();
        if (!$cantidad || $cantidad <= 0) {
            $cantidad = 1; // Default si no hay stock definido
        }
        
        // Generar código del cupón con formato: RMA-Devolucion-SKU-xCANTIDAD
        $codigo_cupon = 'RMA-Devolucion-' . $sku_real . '-x' . $cantidad;
        
        // Verificar si ya existe un cupón con este código
        $cupon_existente = get_page_by_title($codigo_cupon, OBJECT, 'shop_coupon');
        if ($cupon_existente) {
            // Si ya existe y no está usado, retornar ese código
            $usage_count = get_post_meta($cupon_existente->ID, 'usage_count', true);
            if ($usage_count == 0) {
                // Actualizar la asociación con el cliente por si acaso
                update_user_meta($cliente_id, '_cupon_rma_pendiente_' . $codigo_item, $codigo_cupon);
                return $codigo_cupon;
            }
            // Si está usado, agregar un timestamp para hacerlo único
            $codigo_cupon = $codigo_cupon . '-' . date('His');
        }
        
        // Obtener datos del cliente
        $user = get_userdata($cliente_id);
        $nombre_cliente = $user ? $user->display_name : 'Cliente';
        
        // Crear descripción
        $descripcion = "Cupón de retorno al cliente\n";
        $descripcion .= "Producto: {$producto->get_name()}\n";
        $descripcion .= "Cliente: {$nombre_cliente}\n";
        $descripcion .= "SKU: {$sku_real}\n";
        $descripcion .= "Cantidad: {$cantidad}\n";
        $descripcion .= "Código item garantía: {$codigo_item}\n";
        $descripcion .= "Fecha: " . date('d/m/Y H:i');
        
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
            update_post_meta($cupon_id, 'discount_type', 'specific_product');
            update_post_meta($cupon_id, 'coupon_amount', 0);
            update_post_meta($cupon_id, 'product_ids', $producto_rma_id);
            update_post_meta($cupon_id, 'usage_limit', 1);
            update_post_meta($cupon_id, 'individual_use', 'no');
            update_post_meta($cupon_id, 'free_shipping', 'no');
            
            // Configurar expiración
            $dias_validez = get_option('dias_validez_cupon_rma', 120);
            $fecha_expiracion = date('Y-m-d', strtotime("+{$dias_validez} days"));
            update_post_meta($cupon_id, 'expiry_date', $fecha_expiracion);
            update_post_meta($cupon_id, 'date_expires', strtotime($fecha_expiracion));
            
            // Asociar con el cliente
            if ($user && $user->user_email) {
                update_post_meta($cupon_id, 'customer_email', array($user->user_email));
            }
            
            // Marcar como cupón RMA
            update_post_meta($cupon_id, '_es_cupon_rma', 'yes');
            update_post_meta($cupon_id, '_producto_rma_id', $producto_rma_id);
            update_post_meta($cupon_id, '_sku_producto', $sku_real);
            update_post_meta($cupon_id, '_cantidad_rma', $cantidad);
            
            // Guardar para auto-aplicación
            update_user_meta($cliente_id, '_cupon_rma_pendiente_' . $codigo_item, $codigo_cupon);
            
            // Buscar la garantía para obtener el código
            global $wpdb;
            $garantia_id = $wpdb->get_var($wpdb->prepare("
                SELECT post_id 
                FROM {$wpdb->postmeta} 
                WHERE meta_key = '_items_reclamados' 
                AND meta_value LIKE %s
                LIMIT 1
            ", '%' . $codigo_item . '%'));
            
            // Enviar email de confirmación RMA
            $dias_validez = get_option('dias_validez_cupon_rma', 120);
            $fecha_vencimiento = date('d/m/Y', strtotime("+{$dias_validez} days"));
            
            if ($user && $user->user_email) {
                WC_Garantias_Emails::enviar_email('rma_confirmado', $user->user_email, [
                    'cliente' => $user->display_name,
                    'codigo' => $garantia_id ? get_post_meta($garantia_id, '_codigo_unico', true) : 'N/A',
                    'producto' => $producto->get_name(),
                    'cupon_rma' => $codigo_cupon,
                    'dias_validez' => $dias_validez,
                    'fecha_vencimiento' => $fecha_vencimiento,
                    'cantidad' => $cantidad,
                    'sku' => $sku_real
                ]);
            }
            
            return $codigo_cupon;
        }
        
        return false;
    }
    
    /**
     * Generar etiqueta RMA
     */
    public static function generar_etiqueta_rma($garantia_id, $item) {
        if (!class_exists('TCPDF')) {
            require_once(WC_GARANTIAS_PATH . 'includes/TCPDF/tcpdf.php');
        }
        
        $codigo_item = $item['codigo_item'];
        $producto = wc_get_product($item['producto_id']);
        $nombre_producto = $producto ? $producto->get_name() : 'Producto';
        
        // Crear PDF 60x20mm
        $pdf = new TCPDF('L', 'mm', array(20, 60), true, 'UTF-8', false);
        
        $pdf->SetCreator('WooCommerce Garantías');
        $pdf->SetTitle('Etiqueta RMA - ' . $codigo_item);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(1, 1, 1);
        $pdf->SetAutoPageBreak(false);
        
        $pdf->AddPage();
        
        // Borde
        $pdf->Rect(0.5, 0.5, 59, 19, 'D');
        
        // QR Code
        $style = array(
            'border' => 0,
            'vpadding' => 0,
            'hpadding' => 0,
            'fgcolor' => array(0, 0, 0),
            'bgcolor' => false,
            'module_width' => 1,
            'module_height' => 1
        );
        
        $pdf->write2DBarcode($codigo_item, 'QRCODE,L', 2, 2, 16, 16, $style);
        
        // Código (más grande y más arriba)
        $pdf->SetXY(20, 3);
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(38, 5, $codigo_item, 0, 1, 'C');
        
        // Nombre producto (más grande)
        $pdf->SetXY(20, 9);
        $pdf->SetFont('helvetica', 'B', 8);
        $nombre_corto = substr($nombre_producto, 0, 35);
        $pdf->MultiCell(38, 4, $nombre_corto, 0, 'C');
        
        // Guardar en base de datos
        $pdf_content = $pdf->Output('', 'S');
        $upload_dir = wp_upload_dir();
        $file_path = $upload_dir['basedir'] . '/garantias-rma/';
        
        if (!file_exists($file_path)) {
            wp_mkdir_p($file_path);
        }
        
        $filename = 'rma_' . $codigo_item . '_' . time() . '.pdf';
        file_put_contents($file_path . $filename, $pdf_content);
        
        return $upload_dir['baseurl'] . '/garantias-rma/' . $filename;
    }
}