<?php
if (!defined('ABSPATH')) exit;

class WC_Garantias_Carga_Masiva {
    
    private $motivos_validos = [];
    private $duracion_garantia = 180;
    
    public function __construct() {
        // Obtener motivos válidos de la configuración
        $motivos_txt = get_option('motivos_garantia', "Producto defectuoso\nFalla técnica\nFaltan piezas\nOtro");
        $this->motivos_validos = array_filter(array_map('trim', explode("\n", $motivos_txt)));
        
        // Obtener duración de garantía
        $this->duracion_garantia = get_option('duracion_garantia', 180);
    }
    
    public function procesar_archivo($archivo, $usuario_id) {
        $resultado = [
            'total_filas' => 0,
            'validas' => 0,
            'rechazadas' => 0,
            'items' => [],
            'items_validos' => []
        ];
        
        // Leer el archivo según su tipo
        $extension = strtolower(pathinfo($archivo['name'], PATHINFO_EXTENSION));
        $datos = [];
        
        if (in_array($extension, ['xlsx', 'xls'])) {
            // Para Excel, intentar múltiples métodos
            $datos = $this->leer_excel_universal($archivo['tmp_name'], $extension);
            
            // Si falló, intentar convertir a CSV
            if (empty($datos) || (count($datos) === 1 && $datos[0][0] === 'ERROR')) {
                error_log('GARANTIAS DEBUG - Intentando conversión alternativa de Excel');
                $datos = $this->leer_excel_alternativo($archivo['tmp_name']);
            }
        } else {
            return [
                'error' => true,
                'mensaje' => 'Formato no válido. Solo se aceptan archivos Excel (.xlsx, .xls)'
            ];
        }
        
        // Si no hay datos, error
        if (empty($datos)) {
            return [
                'error' => true,
                'mensaje' => 'No se pudieron leer datos del archivo. Verifique que el archivo contiene datos.'
            ];
        }
        
        // Límite de 150 items
        if (count($datos) > 150) {
            return [
                'error' => true,
                'mensaje' => 'El archivo contiene más de 150 filas. Por favor, reduce la cantidad de items.'
            ];
        }
        
        // NUEVO: Array para rastrear cantidades acumuladas por producto_id en ESTE archivo
        $cantidades_acumuladas_archivo = [];
        
        // Procesar cada fila
        foreach ($datos as $index => $fila) {
            // Saltar filas vacías
            if (empty($fila[0]) && empty($fila[1]) && empty($fila[2])) {
                continue;
            }
            
            $resultado['total_filas']++;
            
            // Limpiar el motivo: quitar puntos, espacios extras, etc.
            $motivo_limpio = trim($fila[2] ?? '');
            $motivo_limpio = ltrim($motivo_limpio, '•·-'); // Quitar puntos o guiones al inicio
            $motivo_limpio = trim($motivo_limpio); // Quitar espacios que pudieron quedar
            
            $item = [
                'sku' => trim($fila[0] ?? ''),
                'cantidad' => intval($fila[1] ?? 0),
                'cantidad_original' => intval($fila[1] ?? 0), // NUEVO: Guardar cantidad original
                'motivo' => $motivo_limpio,
                'valido' => true,
                'mensaje' => 'OK',
                'producto_id' => null,
                'nombre_producto' => null,
                'order_id' => null
            ];
            
            // Validar SKU
            if (empty($item['sku'])) {
                $item['valido'] = false;
                $item['mensaje'] = 'SKU vacío';
            } else {
                // Buscar producto por SKU
                $producto = $this->buscar_producto_por_sku($item['sku']);
                
                if (!$producto) {
                    $item['valido'] = false;
                    $item['mensaje'] = 'Producto no encontrado';
                } else {
                    $item['producto_id'] = $producto->get_id();
                    $item['nombre_producto'] = $producto->get_name();
                    
                    // Verificar si el usuario compró este producto
                    $compra_info = $this->verificar_compra_usuario($usuario_id, $item['producto_id']);
                    
                    if (!$compra_info['comprado']) {
                        $item['valido'] = false;
                        $item['mensaje'] = 'No has comprado este producto';
                    } elseif (!$compra_info['en_garantia']) {
                        $item['valido'] = false;
                        $item['mensaje'] = 'Fuera del período de garantía (180 días)';
                    } else {
                        // NUEVO: Calcular cantidad disponible considerando lo acumulado en este archivo
                        $cantidad_ya_acumulada = isset($cantidades_acumuladas_archivo[$item['producto_id']]) 
                            ? $cantidades_acumuladas_archivo[$item['producto_id']] 
                            : 0;
                        
                        $cantidad_disponible_real = $compra_info['cantidad_disponible'] - $cantidad_ya_acumulada;
                        
                        // Verificar cantidad disponible
                        if ($cantidad_disponible_real <= 0) {
                            $item['valido'] = false;
                            $item['mensaje'] = 'No hay cantidad disponible para reclamar (ya agotada en filas anteriores)';
                        } elseif ($item['cantidad'] > $cantidad_disponible_real) {
                            // AJUSTAR cantidad al máximo disponible
                            $item['cantidad'] = $cantidad_disponible_real;
                            $item['mensaje'] = 'Cantidad ajustada a ' . $cantidad_disponible_real . ' (mximo disponible)';
                            
                            // Acumular la cantidad ajustada
                            if (!isset($cantidades_acumuladas_archivo[$item['producto_id']])) {
                                $cantidades_acumuladas_archivo[$item['producto_id']] = 0;
                            }
                            $cantidades_acumuladas_archivo[$item['producto_id']] += $item['cantidad'];
                        } else {
                            // La cantidad es válida, acumularla
                            if (!isset($cantidades_acumuladas_archivo[$item['producto_id']])) {
                                $cantidades_acumuladas_archivo[$item['producto_id']] = 0;
                            }
                            $cantidades_acumuladas_archivo[$item['producto_id']] += $item['cantidad'];
                        }
                        
                        $item['order_id'] = $compra_info['order_id'];
                    }
                }
            }
            
            // Validar cantidad
            if ($item['valido'] && $item['cantidad'] < 1) {
                $item['valido'] = false;
                $item['mensaje'] = 'Cantidad debe ser mayor a 0';
            }
            
            // Validar motivo
            if ($item['valido']) {
                if (empty($item['motivo'])) {
                    $item['valido'] = false;
                    $item['mensaje'] = 'Motivo vacío';
                } else {
                    // Si el motivo no está en la lista, usar "Otro: [motivo]"
                    if (!in_array($item['motivo'], $this->motivos_validos)) {
                        $item['motivo'] = 'Otro: ' . $item['motivo'];
                    }
                }
            }
            
            // Agregar al resultado
            $resultado['items'][] = $item;
            
            if ($item['valido']) {
                $resultado['validas']++;
                $resultado['items_validos'][] = $item;
            } else {
                $resultado['rechazadas']++;
            }
        }
        
        return $resultado;
    }
    
    private function leer_csv($archivo) {
        $datos = [];
        $fila_numero = 0;
        
        if (($handle = fopen($archivo, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Saltar la primera fila (títulos)
                if ($fila_numero === 0) {
                    $fila_numero++;
                    continue;
                }
                $datos[] = $data;
                $fila_numero++;
            }
            fclose($handle);
        }
        return $datos;
    }
    
    private function leer_excel($archivo) {
        // Incluir SimpleXLSX
        require_once dirname(__FILE__) . '/libs/SimpleXLSX.php';
        
        $datos = [];
        
        // Intentar abrir el archivo
        $xlsx = \Shuchkin\SimpleXLSX::parse($archivo);
        
        if (!$xlsx) {
            return [
                ['ERROR', '1', 'No se pudo abrir el archivo Excel']
            ];
        }
        
        // Intentar diferentes formas de obtener los datos
        // Método 1: rows() sin parámetros
        $filas = $xlsx->rows();
        
        if (empty($filas)) {
            // Método 2: dimension() para ver el tamaño
            $dimension = $xlsx->dimension();
            error_log('GARANTIAS DEBUG - Dimension: ' . json_encode($dimension));
            
            // Método 3: Intentar leer celda por celda
            if ($dimension && $dimension[1] > 0) {
                for ($row = 1; $row <= min($dimension[1], 150); $row++) {
                    $fila_datos = [];
                    $fila_vacia = true;
                    
                    // Leer las primeras 3 columnas
                    for ($col = 0; $col < 3; $col++) {
                        $valor = $xlsx->getCell($col, $row);
                        $fila_datos[] = $valor;
                        if (!empty(trim($valor))) {
                            $fila_vacia = false;
                        }
                    }
                    
                    // Si no es la primera fila y no está vacía
                    if ($row > 1 && !$fila_vacia) {
                        $datos[] = [
                            trim($fila_datos[0]),           // SKU
                            intval($fila_datos[1]),         // Cantidad
                            trim($fila_datos[2])            // Motivo
                        ];
                    }
                }
            }
        } else {
            // Si rows() funcion, procesar normalmente
            foreach ($filas as $index => $fila) {
                if ($index === 0) continue; // Saltar encabezados
                
                if (!empty(trim($fila[0] ?? ''))) {
                    $datos[] = [
                        trim($fila[0]),
                        isset($fila[1]) ? intval($fila[1]) : 0,
                        isset($fila[2]) ? trim($fila[2]) : ''
                    ];
                }
            }
        }
        
        error_log('GARANTIAS DEBUG - Datos extraídos: ' . count($datos));
        
        return $datos;
    }
    
    private function buscar_producto_por_sku($sku) {
        // Primero buscar por SKU normal
        $args = [
            'post_type' => 'product',
            'meta_key' => '_sku',
            'meta_value' => $sku,
            'posts_per_page' => 1
        ];
        $products = get_posts($args);
        
        if (!empty($products)) {
            return wc_get_product($products[0]->ID);
        }
        
        // Si no encuentra, buscar por custom SKU (_alg_ean)
        $args['meta_key'] = '_alg_ean';
        $products = get_posts($args);
        
        if (!empty($products)) {
            return wc_get_product($products[0]->ID);
        }
        
        return null;
    }
    
    private function verificar_compra_usuario($usuario_id, $producto_id) {
        $resultado = [
            'comprado' => false,
            'en_garantia' => false,
            'cantidad_disponible' => 0,
            'order_id' => null
        ];
        
        // Fecha límite para garantía
        $fecha_limite = strtotime("-{$this->duracion_garantia} days");
        
        // Obtener pedidos del usuario
        $orders = wc_get_orders([
            'customer_id' => $usuario_id,
            'status' => ['completed', 'wc-completed', 'delivered', 'wc-delivered'],
            'limit' => -1
        ]);
        
        $cantidad_comprada = 0;
        $pedido_mas_reciente = null;
        
        foreach ($orders as $order) {
            $order_date = $order->get_date_completed() ?: $order->get_date_created();
            $order_time = strtotime($order_date->date('Y-m-d H:i:s'));
            
            // Solo considerar pedidos dentro del período de garantía
            if ($order_time >= $fecha_limite) {
                foreach ($order->get_items() as $item) {
                    if ($item->get_product_id() == $producto_id) {
                        $cantidad_comprada += $item->get_quantity();
                        $resultado['comprado'] = true;
                        $resultado['en_garantia'] = true;
                        
                        if (!$pedido_mas_reciente || $order_time > strtotime($pedido_mas_reciente->get_date_created())) {
                            $pedido_mas_reciente = $order;
                            $resultado['order_id'] = $order->get_id();
                        }
                    }
                }
            }
        }
        
        if ($resultado['comprado']) {
            // Calcular cantidad ya reclamada
            $cantidad_reclamada = $this->obtener_cantidad_reclamada($usuario_id, $producto_id);
            $resultado['cantidad_disponible'] = max(0, $cantidad_comprada - $cantidad_reclamada);
        }
        
        return $resultado;
    }
    
    private function obtener_cantidad_reclamada($usuario_id, $producto_id) {
        $cantidad_reclamada = 0;
        
        $garantias = get_posts([
            'post_type' => 'garantia',
            'meta_key' => '_cliente',
            'meta_value' => $usuario_id,
            'posts_per_page' => -1
        ]);
        
        foreach ($garantias as $garantia) {
            $items = get_post_meta($garantia->ID, '_items_reclamados', true);
            if (is_array($items)) {
                foreach ($items as $item) {
                    if (isset($item['producto_id']) && $item['producto_id'] == $producto_id) {
                        $cantidad_reclamada += intval($item['cantidad'] ?? 1);
                    }
                }
            }
        }
        
        return $cantidad_reclamada;
    }
    private function leer_excel_universal($archivo, $extension) {
        $datos = [];
        
        // Usar PHPSpreadsheet si está disponible
        if (file_exists(dirname(__FILE__) . '/../vendor/autoload.php')) {
            require_once dirname(__FILE__) . '/../vendor/autoload.php';
            
            try {
                error_log('GARANTIAS DEBUG - Usando PHPSpreadsheet');
                
                $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($archivo);
                $worksheet = $spreadsheet->getActiveSheet();
                $filas = $worksheet->toArray();
                
                error_log('GARANTIAS DEBUG - Filas encontradas: ' . count($filas));
                
                foreach ($filas as $index => $fila) {
                    if ($index === 0) continue; // Saltar encabezados
                    
                    // Verificar que la fila tenga contenido
                    if (!empty($fila[0])) {
                        $datos[] = [
                            trim($fila[0]),
                            intval($fila[1] ?? 0),
                            trim($fila[2] ?? '')
                        ];
                    }
                }
                
                error_log('GARANTIAS DEBUG - Datos procesados: ' . count($datos));
                
            } catch (Exception $e) {
                error_log('GARANTIAS ERROR - PHPSpreadsheet: ' . $e->getMessage());
                return [
                    ['ERROR', '1', 'Error al leer archivo: ' . $e->getMessage()]
                ];
            }
        } else {
            // Fallback a SimpleXLSX si no está PHPSpreadsheet
            error_log('GARANTIAS DEBUG - PHPSpreadsheet no encontrado, usando SimpleXLSX');
            // ... código anterior de SimpleXLSX ...
        }
        
        return $datos;
    }
    
    private function leer_excel_alternativo($archivo) {
        $datos = [];
        
        // Método alternativo: Leer como si fuera HTML/XML
        $contenido = file_get_contents($archivo);
        
        // DEBUG: Ver los primeros 500 caracteres
        error_log('GARANTIAS DEBUG - Primeros 500 chars: ' . substr($contenido, 0, 500));
        
        // Buscar patrones de celdas en Excel
        if (strpos($contenido, '<table') !== false || strpos($contenido, '<tr') !== false) {
            // Es un Excel guardado como HTML
            $filas = [];
            preg_match_all('/<tr[^>]*>(.*?)<\/tr>/si', $contenido, $filas_match);
            
            error_log('GARANTIAS DEBUG - Filas HTML encontradas: ' . count($filas_match[1]));
            
            foreach ($filas_match[1] as $index => $fila_html) {
                if ($index === 0) continue; // Saltar encabezados
                
                preg_match_all('/<td[^>]*>(.*?)<\/td>/si', $fila_html, $celdas);
                
                // DEBUG: Ver qué hay en las celdas
                if ($index < 3) {
                    error_log('GARANTIAS DEBUG - Fila ' . $index . ' celdas: ' . json_encode($celdas[1]));
                }
                
                if (!empty($celdas[1]) && !empty(trim(strip_tags($celdas[1][0])))) {
                    $datos[] = [
                        trim(strip_tags($celdas[1][0] ?? '')),
                        intval(strip_tags($celdas[1][1] ?? 0)),
                        trim(strip_tags($celdas[1][2] ?? ''))
                    ];
                }
            }
        }
        
        // Si aún no hay datos, último intento: buscar texto separado por tabs
        if (empty($datos)) {
            $lineas = explode("\n", $contenido);
            foreach ($lineas as $index => $linea) {
                if ($index === 0) continue;
                
                // Buscar valores separados por tabs o múltiples espacios
                $valores = preg_split('/\t+|\s{2,}/', trim($linea));
                if (count($valores) >= 3 && !empty($valores[0])) {
                    $datos[] = [
                        trim($valores[0]),
                        intval($valores[1]),
                        trim($valores[2])
                    ];
                }
            }
        }
        
        return $datos;
    }
}