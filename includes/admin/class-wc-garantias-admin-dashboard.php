<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Módulo Dashboard de Garantías
 */
class WC_Garantias_Admin_Dashboard {
    
    public static function render_page() {
        // Recopilar datos
        $total_items = 0;
        $items_por_estado = [
            'nueva' => 0,
            'en_proceso' => 0, 
            'finalizada' => 0
        ];
        
        // Estadísticas por estado
        $estados_count = [];
        $garantias_mes = [];
        $productos_reclamados = [];
        $clientes_top = [];
        
        $garantias = get_posts([
            'post_type' => 'garantia',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids'
        ]);
        
        // Procesar estadísticas
        foreach ($garantias as $garantia_id) {
            $estado = get_post_meta($garantia_id, '_estado', true);
            $fecha = get_post_field('post_date', $garantia_id);
            $mes = date('Y-m', strtotime($fecha));
            $cliente_id = get_post_meta($garantia_id, '_cliente', true);
            $items = get_post_meta($garantia_id, '_items_reclamados', true);
            
            // Contar estados
            if (!isset($estados_count[$estado])) {
                $estados_count[$estado] = 0;
            }
            $estados_count[$estado]++;
            
            // Contar items por estado
            $cantidad_items_garantia = 0;
            if (is_array($items) && !empty($items)) {
                $cantidad_items_garantia = count($items);
                
                // Contar items por estado individual
                foreach ($items as $item) {
                    $estado_item = $item['estado'] ?? 'Pendiente';
                    
                    if (in_array($estado_item, ['Pendiente', 'solicitar_info'])) {
                        $items_por_estado['nueva']++;
                    } elseif (in_array($estado_item, ['recibido', 'aprobado_destruir', 'aprobado_devolver', 'destruccion_subida'])) {
                        $items_por_estado['en_proceso']++;
                    } elseif (in_array($estado_item, ['aprobado', 'rechazado', 'retorno_cliente'])) {
                        $items_por_estado['finalizada']++;
                    }
                }
            } else {
                $cantidad_items_garantia = 1;
                if ($estado == 'nueva') {
                    $items_por_estado['nueva']++;
                } elseif ($estado == 'en_proceso') {
                    $items_por_estado['en_proceso']++;
                } else {
                    $items_por_estado['finalizada']++;
                }
            }
            
            $total_items += $cantidad_items_garantia;
            
            // Contar items por estado específico
            if (!isset($items_detallados)) {
                $items_detallados = [];
            }
            
            if (is_array($items) && !empty($items)) {
                foreach ($items as $item) {
                    $estado_item = $item['estado'] ?? 'Pendiente';
                    
                    if (!isset($items_detallados[$estado_item])) {
                        $items_detallados[$estado_item] = 0;
                    }
                    $items_detallados[$estado_item]++;
                }
            } else {
                // Compatibilidad con formato antiguo
                $estado_legacy = ($estado == 'nueva') ? 'Pendiente' : (($estado == 'en_proceso') ? 'recibido' : 'aprobado');
                if (!isset($items_detallados[$estado_legacy])) {
                    $items_detallados[$estado_legacy] = 0;
                }
                $items_detallados[$estado_legacy]++;
            }
            
            // Garantías por mes (últimos 6 meses)
            if (strtotime($fecha) > strtotime('-6 months')) {
                if (!isset($garantias_mes[$mes])) {
                    $garantias_mes[$mes] = 0;
                }
                $garantias_mes[$mes]++;
            }
            
            /*// Top clientes
            if (!isset($clientes_top[$cliente_id])) {
                $clientes_top[$cliente_id] = 0;
            }
            $clientes_top[$cliente_id]++;*/
            
            /*// Productos más reclamados
            if (is_array($items)) {
                foreach ($items as $item) {
                    $producto_id = $item['producto_id'] ?? 0;
                    if ($producto_id) {
                        if (!isset($productos_reclamados[$producto_id])) {
                            $productos_reclamados[$producto_id] = 0;
                        }
                        $productos_reclamados[$producto_id] += $item['cantidad'] ?? 1;
                    }
                }
            }*/
        }
        
        // Detectar items críticos
        $items_criticos = [];
        $fecha_limite_critico = strtotime('-3 days');
        
        foreach ($garantias as $garantia_id) {
            $items = get_post_meta($garantia_id, '_items_reclamados', true);
            $codigo_garantia = get_post_meta($garantia_id, '_codigo_unico', true);
            $cliente_id = get_post_meta($garantia_id, '_cliente', true);
            $user = get_userdata($cliente_id);
            $nombre_cliente = $user ? $user->display_name : 'Usuario eliminado';
            
            if (is_array($items)) {
                foreach ($items as $item) {
                    $estado_item = $item['estado'] ?? 'Pendiente';
                    $fecha_garantia = get_post_field('post_date', $garantia_id);
                    $timestamp_garantia = strtotime($fecha_garantia);
                    $dias_transcurridos = floor((time() - $timestamp_garantia) / (24 * 60 * 60));
                    
                    $es_critico = false;
                    $razon_critica = '';
                    
                    if ($estado_item == 'solicitar_info' && $dias_transcurridos >= 0) {
                        $es_critico = true;
                        $razon_critica = 'Info solicitada hace ' . $dias_transcurridos . ' días';
                    } elseif ($estado_item == 'Pendiente' && $dias_transcurridos >= 0) {
                        $es_critico = true;
                        $razon_critica = 'Pendiente hace ' . $dias_transcurridos . ' días';
                    }
                    
                    if ($es_critico) {
                        $items_criticos[] = [
                            'garantia_id' => $garantia_id,
                            'codigo_garantia' => $codigo_garantia,
                            'cliente' => $nombre_cliente,
                            'estado' => $estado_item,
                            'dias' => $dias_transcurridos,
                            'razon' => $razon_critica
                        ];
                    }
                }
            }
        }
        
        /*// Ordenar datos
        arsort($clientes_top);
        $clientes_top = array_slice($clientes_top, 0, 10, true);
        arsort($productos_reclamados);
        $productos_reclamados = array_slice($productos_reclamados, 0, 10, true);*/
        
        // Calcular cupones
        $cupones_generados = 0;
        $cupones_usados = 0;
        $monto_cupones = 0;
        
        foreach ($garantias as $garantia_id) {
            $cupon = get_post_meta($garantia_id, '_cupon_generado', true);
            if ($cupon) {
                $cupones_generados++;
                $cupon_post = get_page_by_title($cupon, OBJECT, 'shop_coupon');
                if ($cupon_post) {
                    $usage_count = get_post_meta($cupon_post->ID, 'usage_count', true);
                    if ($usage_count > 0) {
                        $cupones_usados++;
                    }
                    $monto = get_post_meta($cupon_post->ID, 'coupon_amount', true);
                    $monto_cupones += floatval($monto);
                }
            }
        }
        
        $estados_nombres = [
            'nueva' => 'Nueva',
            'en_proceso' => 'En proceso',
            'finalizada' => 'Finalizada'
        ];
        
        $colores_estados = [
            'nueva' => '#ffeaa7',
            'en_proceso' => '#74b9ff',
            'finalizada' => '#55efc4'
        ];
        
        // Renderizar HTML
            self::render_html($total_items, $items_por_estado, $cupones_generados, $cupones_usados, 
                    $monto_cupones, $items_detallados, $items_criticos, $garantias_mes, 
                    $estados_nombres, $colores_estados);
    }
    
    private static function render_html($total_items, $items_por_estado, $cupones_generados, 
                                       $cupones_usados, $monto_cupones, $items_detallados, 
                                       $items_criticos, $garantias_mes, $estados_nombres, 
                                       $colores_estados) {
        ?>
        <div class="wrap">
            <h1 style="margin-bottom: 30px;">
                📊 Dashboard de Garantías
                <a href="<?php echo admin_url('admin.php?page=wc-garantias'); ?>" class="page-title-action">Ver todas</a>
                <a href="<?php echo admin_url('admin.php?page=wc-garantias-analisis'); ?>" class="page-title-action" style="background: #667eea; color: white;">📊 Análisis Top 10</a>
                <a href="<?php echo admin_url('admin.php?page=wc-garantias&export=csv'); ?>" class="page-title-action">Exportar CSV</a>
            </h1>
            
            <!-- KPIs principales -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
                <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="margin: 0; color: white; font-size: 14px; opacity: 0.9;">Total Items</h3>
                            <p style="font-size: 36px; margin: 10px 0; font-weight: bold;"><?php echo number_format($total_items); ?></p>
                        </div>
                        <div style="font-size: 48px; opacity: 0.3;"></div>
                    </div>
                </div>
                
                <div style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="margin: 0; color: white; font-size: 14px; opacity: 0.9;">Items Pendientes</h3>
                            <p style="font-size: 36px; margin: 10px 0; font-weight: bold;"><?php echo $items_por_estado['nueva']; ?></p>
                        </div>
                        <div style="font-size: 48px; opacity: 0.3;">⏳</div>
                    </div>
                </div>
                
                <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="margin: 0; color: white; font-size: 14px; opacity: 0.9;">Cupones Generados</h3>
                            <p style="font-size: 36px; margin: 10px 0; font-weight: bold;"><?php echo $cupones_generados; ?></p>
                            <small style="opacity: 0.8;">Usados: <?php echo $cupones_usados; ?></small>
                        </div>
                        <div style="font-size: 48px; opacity: 0.3;">🎟️</div>
                    </div>
                </div>
                
                <div style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); color: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <h3 style="margin: 0; color: white; font-size: 14px; opacity: 0.9;">Valor en Cupones</h3>
                            <p style="font-size: 28px; margin: 10px 0; font-weight: bold;">$<?php echo number_format($monto_cupones, 0, ',', '.'); ?></p>
                        </div>
                        <div style="font-size: 48px; opacity: 0.3;">💰</div>
                    </div>
                </div>
            </div>
            
            <!-- Items por Estado Detallado -->
            <?php self::render_items_detallados($items_detallados); ?>
            
            <!-- Alertas Items Críticos -->
            <?php if (!empty($items_criticos)): ?>
                <?php self::render_items_criticos($items_criticos); ?>
            <?php endif; ?>
            
            <!-- Grficos y estadísticas -->
            <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-bottom: 30px;">
                <?php self::render_grafico_tendencia($garantias_mes); ?>
                <?php self::render_estados_actuales($items_por_estado, $estados_nombres, $colores_estados, $total_items); ?>
            </div>
            
            <!-- Footer con acciones rápidas -->
            <?php self::render_footer_acciones(); ?>
        </div>
        
        <style>
        .widefat td, .widefat th {
            padding: 12px 10px;
        }
        .button-small {
            font-size: 12px !important;
            padding: 4px 8px !important;
            height: auto !important;
        }
        </style>
        <?php
    }
    
    private static function render_items_detallados($items_detallados) {
        ?>
        <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 30px;">
            <h3 style="margin-top: 0;">📊 Items por Estado Detallado</h3>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
                <?php 
                $colores_items = [
                    'Pendiente' => '#ffc107',
                    'solicitar_info' => '#17a2b8',
                    'recibido' => '#6c757d',
                    'aprobado_destruir' => '#dc3545',
                    'aprobado_devolver' => '#007bff',
                    'destruccion_subida' => '#fd7e14',
                    'devolucion_en_transito' => '#20c997',
                    'aprobado' => '#28a745',
                    'rechazado' => '#dc3545',
                    'apelacion' => '#6f42c1'
                ];
                
                $nombres_estados = [
                    'Pendiente' => '⏳ Pendiente',
                    'solicitar_info' => '❓ Info Solicitada',
                    'recibido' => '📦 Recibido',
                    'aprobado_destruir' => '🔥 Destruir',
                    'aprobado_devolver' => '↩ Devolver',
                    'destruccion_subida' => ' Destrucción Subida',
                    'devolucion_en_transito' => '🚚 En Tránsito',
                    'aprobado' => '✅ Aprobado',
                    'rechazado' => '❌ Rechazado',
                    'apelacion' => '⚖️ Apelacin'
                ];
                
                if (isset($items_detallados)):
                    arsort($items_detallados);
                    foreach ($items_detallados as $estado_item => $cantidad):
                        if ($cantidad > 0):
                            $color = $colores_items[$estado_item] ?? '#6c757d';
                            $nombre = $nombres_estados[$estado_item] ?? $estado_item;
                ?>
                    <div style="background: <?php echo $color; ?>; color: white; padding: 15px; border-radius: 8px; text-align: center;">
                        <div style="font-size: 28px; font-weight: bold; margin-bottom: 5px;"><?php echo $cantidad; ?></div>
                        <div style="font-size: 12px; opacity: 0.9;"><?php echo $nombre; ?></div>
                    </div>
                <?php 
                        endif;
                    endforeach;
                endif; 
                ?>
            </div>
        </div>
        <?php
    }
    
    private static function render_items_criticos($items_criticos) {
        ?>
        <div style="background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 10px; margin-bottom: 30px;">
            <h3 style="margin-top: 0; color: #856404;">⚠️ Items Críticos (<?php echo count($items_criticos); ?>)</h3>
            <p style="color: #856404; margin-bottom: 15px;">Items que requieren atención urgente:</p>
            <div style="max-height: 200px; overflow-y: auto;">
                <?php foreach ($items_criticos as $critico): ?>
                <div style="background: white; padding: 12px; margin-bottom: 10px; border-radius: 6px; border-left: 4px solid #dc3545;">
                    <div style="display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <strong><?php echo esc_html($critico['codigo_garantia']); ?></strong> - 
                            <?php echo esc_html($critico['cliente']); ?>
                            <br>
                            <small style="color: #666;"><?php echo esc_html($critico['razon']); ?></small>
                        </div>
                        <div>
                            <a href="<?php echo admin_url('admin.php?page=wc-garantias-ver&garantia_id=' . $critico['garantia_id']); ?>" 
                               class="button button-small button-primary">Ver</a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    private static function render_grafico_tendencia($garantias_mes) {
        ?>
        <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <h3 style="margin-top: 0;"> Tendencia últimos 6 meses</h3>
            <div style="height: 300px; position: relative;">
                <?php
                $max_value = max($garantias_mes) ?: 1;
                $meses_nombres = ['01' => 'Ene', '02' => 'Feb', '03' => 'Mar', '04' => 'Abr', '05' => 'May', '06' => 'Jun', 
                                  '07' => 'Jul', '08' => 'Ago', '09' => 'Sep', '10' => 'Oct', '11' => 'Nov', '12' => 'Dic'];
                ?>
                <div style="display: flex; align-items: flex-end; justify-content: space-around; height: 250px; border-bottom: 2px solid #ddd;">
                    <?php foreach ($garantias_mes as $mes => $count): 
                        $height = ($count / $max_value) * 200;
                        $mes_num = substr($mes, 5, 2);
                        $año = substr($mes, 0, 4);
                    ?>
                    <div style="text-align: center; flex: 1; margin: 0 5px;">
                        <div style="background: linear-gradient(to top, #667eea, #764ba2); width: 100%; height: <?php echo $height; ?>px; border-radius: 5px 5px 0 0; position: relative; transition: all 0.3s;">
                            <span style="position: absolute; top: -25px; left: 50%; transform: translateX(-50%); font-weight: bold;"><?php echo $count; ?></span>
                        </div>
                        <div style="margin-top: 10px; font-size: 12px;"><?php echo $meses_nombres[$mes_num]; ?><br><small><?php echo $año; ?></small></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    private static function render_estados_actuales($items_por_estado, $estados_nombres, $colores_estados, $total_items) {
        ?>
        <div style="background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05);">
            <h3 style="margin-top: 0;">📊 Estados de Items</h3>
            <?php foreach ($items_por_estado as $estado => $count): ?>
            <div style="margin-bottom: 15px;">
                <div style="display: flex; justify-content: space-between; margin-bottom: 5px;">
                    <span style="font-size: 14px;"><?php echo esc_html($estados_nombres[$estado] ?? ucfirst($estado)); ?></span>
                    <span style="font-weight: bold;"><?php echo $count; ?></span>
                </div>
                <div style="background: #f0f0f0; border-radius: 10px; overflow: hidden;">
                    <div style="background: <?php echo $colores_estados[$estado] ?? '#666'; ?>; height: 20px; width: <?php echo $total_items > 0 ? ($count / $total_items) * 100 : 0; ?>%; transition: width 0.3s;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
    }
    
    private static function render_footer_acciones() {
        ?>
        <div style="margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 10px; text-align: center;">
            <h4 style="margin-top: 0;">Acciones rápidas</h4>
            <a href="<?php echo admin_url('admin.php?page=wc-garantias&items_pendientes=1'); ?>" class="button button-primary">Ver Items Pendientes</a>
            <a href="<?php echo admin_url('admin.php?page=wc-garantias-config'); ?>" class="button">Configuración</a>
            <a href="<?php echo admin_url('admin.php?page=wc-garantias-motivos'); ?>" class="button">Editar motivos</a>
            <a href="<?php echo admin_url('admin.php?page=wc-garantias&export=csv'); ?>" class="button">Exportar datos</a>
        </div>
        <?php
    }
}