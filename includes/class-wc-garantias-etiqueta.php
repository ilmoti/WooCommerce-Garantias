<?php
if (!defined('ABSPATH')) exit;
// Limpiar cualquier salida previa
if (ob_get_level()) {
    ob_end_clean();
}

// Definir la constante si no existe
if (!defined('WC_GARANTIAS_PATH')) {
    define('WC_GARANTIAS_PATH', plugin_dir_path(dirname(__FILE__)));
}
class WC_Garantias_Etiqueta {
    
    public static function generar_etiqueta_devolucion($devolucion_id) {
        $devolucion = get_post($devolucion_id);
        if (!$devolucion || $devolucion->post_type !== 'garantia') {
            return false;
        }
        
        $codigo_unico = get_post_meta($devolucion_id, '_codigo_unico', true);
        $cliente_id = get_post_meta($devolucion_id, '_cliente', true);
        $user = get_userdata($cliente_id);
        $nombre_cliente = $user ? $user->display_name : 'Cliente';
        $items = get_post_meta($devolucion_id, '_items_reclamados', true);
        
        // Generar QR usando Google Charts API
        $qr_data = $codigo_unico;
        $qr_size = 200;
        $qr_url = 'https://api.qrserver.com/v1/create-qr-code/?size=' . $qr_size . 'x' . $qr_size . '&data=' . urlencode($qr_data);
        
        // Generar HTML de la etiqueta
        ob_start();
        ?>
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <title>Etiqueta de Devolución - <?php echo esc_html($codigo_unico); ?></title>
            <style>
                * { margin: 0; padding: 0; box-sizing: border-box; }
                body { font-family: Arial, sans-serif; }
                .etiqueta {
                    width: 10cm;
                    min-height: 10cm;
                    margin: 0 auto;
                    padding: 1cm;
                    border: 2px dashed #000;
                    background: white;
                }
                .header {
                    text-align: center;
                    margin-bottom: 20px;
                    padding-bottom: 20px;
                    border-bottom: 2px solid #000;
                }
                .logo {
                    font-size: 24px;
                    font-weight: bold;
                    margin-bottom: 10px;
                }
                .codigo {
                    font-size: 20px;
                    font-weight: bold;
                    margin: 10px 0;
                }
                .qr-container {
                    text-align: center;
                    margin: 20px 0;
                }
                .info {
                    margin: 15px 0;
                    font-size: 14px;
                }
                .info-item {
                    margin: 8px 0;
                    padding: 5px 0;
                }
                .productos {
                    margin-top: 20px;
                    border-top: 1px solid #ccc;
                    padding-top: 15px;
                }
                .instrucciones {
                    margin-top: 20px;
                    padding: 10px;
                    background: #f0f0f0;
                    border: 1px solid #ddd;
                    font-size: 12px;
                }
                @media print {
                    body { margin: 0; }
                    .etiqueta { border: none; }
                    .no-print { display: none; }
                }
            </style>
        </head>
        <body>
            <div class="etiqueta">
                <div class="header">
                    <div class="logo">ETIQUETA DE DEVOLUCIÓN</div>
                    <div class="codigo"><?php echo esc_html($codigo_unico); ?></div>
                </div>
                
                <div class="qr-container">
                    <img src="<?php echo esc_url($qr_url); ?>" alt="QR Code">
                    <div style="font-size: 12px; margin-top: 5px;">Escanea para ver el estado</div>
                </div>
                
                <div class="info">
                    <div class="info-item"><strong>Cliente:</strong> <?php echo esc_html($nombre_cliente); ?></div>
                    <div class="info-item"><strong>Fecha:</strong> <?php echo date('d/m/Y'); ?></div>
                    <div class="info-item"><strong>Motivo:</strong> Devolución por error de compra</div>
                </div>
                
                <?php if (is_array($items) && !empty($items)): ?>
                <div class="productos">
                    <strong>Productos:</strong>
                    <?php foreach ($items as $item): 
                        $producto = wc_get_product($item['producto_id']);
                        if ($producto):
                    ?>
                        <div style="margin: 5px 0; font-size: 13px;">
                            • <?php echo esc_html($producto->get_name()); ?> (x<?php echo $item['cantidad']; ?>)
                        </div>
                    <?php 
                        endif;
                    endforeach; ?>
                </div>
                <?php endif; ?>
                
                <div class="instrucciones">
                    <strong>IMPORTANTE:</strong><br>
                    1. Agrega esta etiqueta en el interior del paquete<br>
                    2. El producto debe estar en perfectas condiciones<br>
                    3. Incluye todos los accesorios originales<br>
                    4. Agrega la informacion de envio para poder hacer el seguimiento
                </div>
            </div>
            
            <div class="no-print" style="text-align: center; margin: 20px;">
                <button onclick="window.print();" style="padding: 10px 30px; font-size: 16px; cursor: pointer;">
                    Imprimir Etiqueta
                </button>
            </div>
        </body>
        </html>
        <?php
        return ob_get_clean();
    }
    public static function generar_etiquetas_items_a4($garantia_id) {
        $garantia = get_post($garantia_id);
        if (!$garantia || $garantia->post_type !== 'garantia') {
            return false;
        }
        
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        if (!is_array($items) || empty($items)) {
            return false;
        }
        
        // Incluir TCPDF desde includes/TCPDF
        $tcpdf_path = plugin_dir_path(dirname(__FILE__)) . 'includes/TCPDF/tcpdf.php';
        if (!file_exists($tcpdf_path)) {
            wp_die('Error: No se encuentra TCPDF en ' . $tcpdf_path);
        }
        
        // Limpiar cualquier salida previa IMPORTANTE
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        require_once $tcpdf_path;
        
        // Crear nuevo PDF (sin namespace porque es la versión clásica)
        $pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
        
        // Configurar documento
        $pdf->SetCreator('WooCommerce Garantías');
        $pdf->SetAuthor('Sistema de Garantías');
        $pdf->SetTitle('Etiquetas de Items');
        
        // Quitar header y footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Configurar márgenes (10mm en todos los lados)
        $pdf->SetMargins(10, 10, 10);
        $pdf->SetAutoPageBreak(true, 10);
        
        // Agregar página
        $pdf->AddPage();
        
        // Configurar fuente
        $pdf->SetFont('helvetica', '', 10);
        
        // Variables para posicin
        $etiqueta_ancho = 60;
        $etiqueta_alto = 40;
        $espacio = 5;
        $columnas = 3;
        $x_inicial = 10;
        $y_inicial = 10;
        
        $col = 0;
        $fila = 0;
        
        foreach ($items as $item) {
            $producto = wc_get_product($item['producto_id']);
            if (!$producto) continue;
            
            $codigo_item = $item['codigo_item'] ?? 'SIN-CODIGO';
            
            // Calcular posicin
            $x = $x_inicial + ($col * ($etiqueta_ancho + $espacio));
            $y = $y_inicial + ($fila * ($etiqueta_alto + $espacio));
            
            // Verificar si necesitamos nueva página
            if ($y + $etiqueta_alto > 280) { // A4 = 297mm - márgenes
                $pdf->AddPage();
                $fila = 0;
                $y = $y_inicial;
            }
            
            // Dibujar borde de etiqueta
            $pdf->Rect($x, $y, $etiqueta_ancho, $etiqueta_alto);
            
            // Generar código QR (usando barcode de TCPDF)
            $style = array(
                'border' => 0,
                'vpadding' => 0,
                'hpadding' => 0,
                'fgcolor' => array(0, 0, 0),
                'bgcolor' => false,
                'module_width' => 1,
                'module_height' => 1
            );
            
            // QR Code
            $pdf->write2DBarcode($codigo_item, 'QRCODE,L', $x + 2, $y + 2, 15, 15, $style);
            
            // Código texto
            $pdf->SetXY($x + 20, $y + 3);
            $pdf->SetFont('helvetica', 'B', 11);
            $pdf->Cell(38, 5, $codigo_item, 0, 1);
            
            // Nombre producto
            $pdf->SetXY($x + 2, $y + 20);
            $pdf->SetFont('helvetica', '', 9);
            $nombre_producto = substr($producto->get_name(), 0, 80);
            $pdf->MultiCell(56, 3, $nombre_producto, 0, 'L');
            
            // Motivo
            $pdf->SetXY($x + 2, $y + 32);
            $pdf->SetFont('helvetica', '', 8);
            $pdf->SetTextColor(100, 100, 100);
            $motivo = substr($item['motivo'] ?? 'Sin motivo', 0, 50);
            $pdf->Cell(56, 4, $motivo, 0, 1);
            $pdf->SetTextColor(0, 0, 0);
            
            // Siguiente posición
            $col++;
            if ($col >= $columnas) {
                $col = 0;
                $fila++;
            }
        }
        // Limpiar buffer antes de enviar PDF
        ob_clean();
        // Salida del PDF
        $pdf->Output('etiquetas_items_' . $garantia_id . '.pdf', 'D');
        exit;
    }
    
    public static function generar_etiqueta_item_individual($garantia_id, $item_codigo) {
        $garantia = get_post($garantia_id);
        if (!$garantia || $garantia->post_type !== 'garantia') {
            return false;
        }
        
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        if (!is_array($items)) {
            return false;
        }
        
        // Buscar el item específico
        $item_encontrado = null;
        foreach ($items as $item) {
            if (isset($item['codigo_item']) && $item['codigo_item'] === $item_codigo) {
                $item_encontrado = $item;
                break;
            }
        }
        
        if (!$item_encontrado) {
            return false;
        }
        
        $producto = wc_get_product($item_encontrado['producto_id']);
        if (!$producto) {
            return false;
        }
        
        // Incluir TCPDF desde includes/TCPDF
        $tcpdf_path = plugin_dir_path(dirname(__FILE__)) . 'includes/TCPDF/tcpdf.php';
        if (!file_exists($tcpdf_path)) {
            wp_die('Error: No se encuentra TCPDF en ' . $tcpdf_path);
        }
        
        // Limpiar cualquier salida previa IMPORTANTE
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        require_once $tcpdf_path;
        
        // Crear PDF con tamaño personalizado (60x20mm)
        $pdf = new TCPDF('L', 'mm', array(20, 60), true, 'UTF-8', false);
        
        // Configurar documento
        $pdf->SetCreator('WooCommerce Garantías');
        $pdf->SetAuthor('Sistema de Garantas');
        $pdf->SetTitle('Etiqueta - ' . $item_codigo);
        
        // Quitar header y footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Sin mrgenes
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        
        // Agregar página
        $pdf->AddPage();
        
        // Dibujar borde (opcional)
        $pdf->Rect(0.5, 0.5, 59, 19, 'D');
        
        // Generar código QR
        $style = array(
            'border' => 0,
            'vpadding' => 0,
            'hpadding' => 0,
            'fgcolor' => array(0, 0, 0),
            'bgcolor' => false,
            'module_width' => 1,
            'module_height' => 1
        );
        
        // QR Code (más pequeño)
        $pdf->write2DBarcode($item_codigo, 'QRCODE,L', 2, 2, 12, 12, $style);
        
        // Código texto
        $pdf->SetXY(16, 2);
        $pdf->SetFont('helvetica', 'B', 7);
        $pdf->Cell(42, 4, $item_codigo, 0, 1);
        
        // Nombre producto
        $pdf->SetXY(16, 6);
        $pdf->SetFont('helvetica', '', 6);
        $nombre_producto = substr($producto->get_name(), 0, 50);
        $pdf->Cell(42, 3, $nombre_producto, 0, 1);
        
        // Motivo
        $pdf->SetXY(16, 10);
        $pdf->SetFont('helvetica', '', 5);
        $pdf->SetTextColor(100, 100, 100);
        $motivo = substr($item_encontrado['motivo'] ?? 'Sin motivo', 0, 40);
        $pdf->Cell(42, 3, $motivo, 0, 1);
        
        // Limpiar buffer antes de enviar PDF
        ob_clean();
        
        // Salida del PDF
        $pdf->Output('etiqueta_' . $item_codigo . '.pdf', 'D');
        exit;
    }
    public static function generar_todas_etiquetas_individuales($garantia_id) {
        $garantia = get_post($garantia_id);
        if (!$garantia || $garantia->post_type !== 'garantia') {
            return false;
        }
        
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        if (!is_array($items) || empty($items)) {
            return false;
        }
        
        // Incluir TCPDF desde includes/TCPDF
        $tcpdf_path = plugin_dir_path(dirname(__FILE__)) . 'includes/TCPDF/tcpdf.php';
        if (!file_exists($tcpdf_path)) {
            wp_die('Error: No se encuentra TCPDF en ' . $tcpdf_path);
        }
        
        // Limpiar cualquier salida previa IMPORTANTE
        while (ob_get_level()) {
            ob_end_clean();
        }
        
        require_once $tcpdf_path;
        
        // Crear PDF con tamao personalizado (60x20mm)
        $pdf = new TCPDF('L', 'mm', array(20, 60), true, 'UTF-8', false);
        
        // Configurar documento
        $pdf->SetCreator('WooCommerce Garantías');
        $pdf->SetAuthor('Sistema de Garantías');
        $pdf->SetTitle('Todas las Etiquetas Individuales');
        
        // Quitar header y footer
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        
        // Sin márgenes
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false);
        
        // Configurar para que cada etiqueta esté en una página
        foreach ($items as $item) {
            $producto = wc_get_product($item['producto_id']);
            if (!$producto) continue;
            
            $codigo_item = $item['codigo_item'] ?? 'SIN-CODIGO';
            
            // Agregar nueva página para cada etiqueta
            $pdf->AddPage();
            
            // Dibujar borde (opcional)
            $pdf->Rect(0.5, 0.5, 59, 19, 'D');
            
            // Generar código QR
            $style = array(
                'border' => 0,
                'vpadding' => 0,
                'hpadding' => 0,
                'fgcolor' => array(0, 0, 0),
                'bgcolor' => false,
                'module_width' => 1,
                'module_height' => 1
            );
            
            // QR Code
            $pdf->write2DBarcode($codigo_item, 'QRCODE,L', 2, 2, 12, 12, $style);
            
            // Código texto
            $pdf->SetXY(18, 2);
            $pdf->SetFont('helvetica', 'B', 7);
            $pdf->Cell(42, 4, $codigo_item, 0, 1);
            
            // Nombre producto
            $pdf->SetXY(18, 6);
            $pdf->SetFont('helvetica', '', 6);
            $nombre_producto = substr($producto->get_name(), 0, 50);
            $pdf->Cell(40, 3, $nombre_producto, 0, 1);  
            
            // Motivo
            $pdf->SetXY(18, 10);
            $pdf->SetFont('helvetica', '', 5);
            $pdf->SetTextColor(100, 100, 100);
            $motivo = substr($item['motivo'] ?? 'Sin motivo', 0, 40);
            $pdf->Cell(40, 3, $motivo, 0, 1); 
        }
        
        // Limpiar buffer antes de enviar PDF
        ob_clean();
        
        // Salida del PDF
        $codigo_garantia = get_post_meta($garantia_id, '_codigo_unico', true);
        $pdf->Output('etiquetas_individuales_' . $codigo_garantia . '.pdf', 'D');
        exit;
    }
}