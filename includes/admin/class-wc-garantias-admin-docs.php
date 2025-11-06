<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Módulo de Documentación del Sistema de Garantías
 * Versión: 2.0
 * Última actualización: <?php echo date('d/m/Y'); ?>
 */
class WC_Garantias_Admin_Docs {
    
    public static function render_page() {
        ?>
        <div class="wrap" style="max-width: 1200px;">
            <h1 style="margin-bottom: 30px;"> Documentación del Sistema de Garantías</h1>
            
            <!-- Tabs de navegación -->
            <div style="border-bottom: 2px solid #ddd; margin-bottom: 30px;">
                <div style="display: flex; gap: 0;">
                    <button onclick="mostrarTab('estructura')" id="tab-estructura" class="doc-tab active">
                        Estructura de Archivos
                    </button>
                    <button onclick="mostrarTab('modulos')" id="tab-modulos" class="doc-tab">
                        Módulos y Funciones
                    </button>
                    <button onclick="mostrarTab('flujo')" id="tab-flujo" class="doc-tab">
                        Flujo de Trabajo
                    </button>
                    <button onclick="mostrarTab('base-datos')" id="tab-base-datos" class="doc-tab">
                        Base de Datos
                    </button>
                    <button onclick="mostrarTab('hooks')" id="tab-hooks" class="doc-tab">
                        Hooks y Filtros
                    </button>
                    <button onclick="mostrarTab('troubleshooting')" id="tab-troubleshooting" class="doc-tab">
                        Solución de Problemas
                    </button>
                    <button onclick="mostrarTab('cambios-recientes')" id="tab-cambios-recientes" class="doc-tab">
                        🆕 Cambios Recientes
                    </button>
                </div>
            </div>
            
            <!-- Contenido de los tabs -->
            <div id="contenido-estructura" class="doc-content">
                <?php self::render_estructura(); ?>
            </div>
            
            <div id="contenido-modulos" class="doc-content" style="display: none;">
                <?php self::render_modulos(); ?>
            </div>
            
            <div id="contenido-flujo" class="doc-content" style="display: none;">
                <?php self::render_flujo(); ?>
            </div>
            
            <div id="contenido-base-datos" class="doc-content" style="display: none;">
                <?php self::render_base_datos(); ?>
            </div>
            
            <div id="contenido-hooks" class="doc-content" style="display: none;">
                <?php self::render_hooks(); ?>
            </div>
            
            <div id="contenido-troubleshooting" class="doc-content" style="display: none;">
                <?php self::render_troubleshooting(); ?>
            </div>
            <div id="contenido-cambios-recientes" class="doc-content" style="display: none;">
                <?php self::render_cambios_recientes(); ?>
            </div>
            
            <!-- Info del sistema -->
            <div style="margin-top: 50px; padding: 20px; background: #f8f9fa; border-radius: 8px;">
                <h3>️ Información del Sistema</h3>
                <?php self::render_system_info(); ?>
            </div>
            
            <style>
                .doc-tab {
                    padding: 15px 25px;
                    background: none;
                    border: none;
                    border-bottom: 3px solid transparent;
                    cursor: pointer;
                    font-size: 15px;
                    transition: all 0.3s;
                }
                .doc-tab:hover {
                    background: #f8f9fa;
                }
                .doc-tab.active {
                    border-bottom-color: #667eea;
                    color: #667eea;
                    font-weight: 600;
                }
                .code-block {
                    background: #282c34;
                    color: #abb2bf;
                    padding: 15px;
                    border-radius: 8px;
                    overflow-x: auto;
                    font-family: 'Courier New', monospace;
                    font-size: 14px;
                    margin: 15px 0;
                }
                .file-structure {
                    background: #f8f9fa;
                    padding: 20px;
                    border-radius: 8px;
                    font-family: monospace;
                    line-height: 1.8;
                }
                .module-card {
                    background: white;
                    border: 1px solid #e0e0e0;
                    border-radius: 8px;
                    padding: 20px;
                    margin-bottom: 20px;
                }
                .module-card h3 {
                    margin-top: 0;
                    color: #333;
                    border-bottom: 2px solid #667eea;
                    padding-bottom: 10px;
                }
                .warning-box {
                    background: #fff3cd;
                    border: 1px solid #ffc107;
                    border-radius: 5px;
                    padding: 15px;
                    margin: 15px 0;
                }
                .info-box {
                    background: #d1ecf1;
                    border: 1px solid #bee5eb;
                    border-radius: 5px;
                    padding: 15px;
                    margin: 15px 0;
                }
                .success-box {
                    background: #d4edda;
                    border: 1px solid #c3e6cb;
                    border-radius: 5px;
                    padding: 15px;
                    margin: 15px 0;
                }
            </style>
            
            <script>
            function mostrarTab(tab) {
                // Ocultar todos los contenidos
                document.querySelectorAll('.doc-content').forEach(function(content) {
                    content.style.display = 'none';
                });
                
                // Desactivar todos los tabs
                document.querySelectorAll('.doc-tab').forEach(function(tabBtn) {
                    tabBtn.classList.remove('active');
                });
                
                // Mostrar el contenido seleccionado
                document.getElementById('contenido-' + tab).style.display = 'block';
                document.getElementById('tab-' + tab).classList.add('active');
            }
            </script>
        </div>
        <?php
    }
    
    private static function render_estructura() {
        ?>
        <div class="module-card">
            <h3>📂 Estructura de Archivos (Post-Refactorización)</h3>
            
            <div class="info-box">
                <strong>✨ Refactorización completada:</strong> El sistema fue modularizado el <?php echo date('d/m/Y'); ?>, 
                reduciendo el archivo principal de 4200 líneas a solo 300 líneas (93% de reducción).
            </div>
            
            <div class="file-structure">
<pre>
wc-garantias/
├─ <strong>wc-garantias.php</strong> (archivo principal del plugin)
├── <strong>class-wc-garantias-admin.php</strong> (~300 líneas - controlador principal)
├── class-wc-garantias-frontend.php (frontend - sin cambios)
── class-wc-garantias-emails.php (emails - sin cambios)
── class-wc-garantias-cupones.php (cupones - sin cambios)
├── class-wc-garantias-partial-approval.php (aprobacin parcial - sin cambios)
│
└─ <strong style="color: #667eea;">admin/</strong> (NUEVOS MDULOS CREADOS)
    ├── <strong>class-wc-garantias-admin-motivos.php</strong> (50 líneas)
    │   └─ Gestión de motivos de rechazo
    │
    ├── <strong>class-wc-garantias-admin-config.php</strong> (200 líneas)
    │   └─ Toda la configuración del sistema
    │
    ├── <strong>class-wc-garantias-admin-rma.php</strong> (180 líneas)
       └─ Panel de devoluciones pendientes
    │
    ├ <strong>class-wc-garantias-admin-dashboard.php</strong> (500 líneas)
    │   └─ Dashboard con estadísticas y KPIs
    │
    ├── <strong>class-wc-garantias-admin-list.php</strong> (400 líneas)
       ─ Lista de garantías con filtros y búsqueda
    │
    ── <strong>class-wc-garantias-admin-view.php</strong> (600 líneas)
    │   └ Lógica para ver/procesar garantía individual
    │
    ├── <strong>class-wc-garantias-admin-view-render.php</strong> (700 líneas)
    │   └─ Renderizado HTML de la vista de garantía
    │
    └─ <strong>class-wc-garantias-admin-docs.php</strong> (ESTE ARCHIVO)
        └─ Documentación del sistema
</pre>
            </div>
            
            <div class="warning-box">
                <strong>⚠️ Importante:</strong> Al agregar nuevas funcionalidades, crear nuevos archivos modulares 
                en la carpeta <code>admin/</code> en lugar de modificar el archivo principal.
            </div>
        </div>
        <?php
    }
    
    private static function render_modulos() {
        ?>
        <div class="module-card">
            <h3> Módulos del Sistema</h3>
            
            <h4>1. class-wc-garantias-admin.php (Principal)</h4>
            <ul>
                <li><strong>Función:</strong> Controlador principal y cargador de módulos</li>
                <li><strong>Métodos principales:</strong>
                    <ul>
                        <li><code>init()</code> - Inicializa el sistema</li>
                        <li><code>add_admin_menu()</code> - Crea el menú del admin</li>
                        <li><code>actualizar_estado_garantia()</code> - Actualiza estados globales</li>
                    </ul>
                </li>
                <li><strong>Delega a módulos:</strong> Todas las pginas del admin</li>
            </ul>
            
            <h4>2. admin/class-wc-garantias-admin-dashboard.php</h4>
            <ul>
                <li><strong>Función:</strong> Dashboard con estadsticas</li>
                <li><strong>Características:</strong>
                    <ul>
                        <li>KPIs principales (items, cupones, valores)</li>
                        <li>Gráficos de tendencia</li>
                        <li>Top productos y clientes</li>
                        <li>Items críticos que requieren atención</li>
                    </ul>
                </li>
            </ul>
            
            <h4>3. admin/class-wc-garantias-admin-list.php</h4>
            <ul>
                <li><strong>Función:</strong> Lista y gestión de garantías</li>
                <li><strong>Características:</strong>
                    <ul>
                        <li>Tabla con filtros por estado</li>
                        <li>Búsqueda por código, cliente, teléfono</li>
                        <li>Exportación a CSV</li>
                        <li>Eliminación de garantías</li>
                    </ul>
                </li>
            </ul>
            
            <h4>4. admin/class-wc-garantias-admin-view.php</h4>
            <ul>
                <li><strong>Función:</strong> Procesamiento de garantía individual</li>
                <li><strong>Procesa:</strong>
                    <ul>
                        <li>Acciones masivas sobre items</li>
                        <li>Aprobaciones/rechazos</li>
                        <li>División de items (aprobación parcial)</li>
                        <li>Subida de etiquetas</li>
                        <li>Gestión de tracking</li>
                    </ul>
                </li>
            </ul>
            
            <h4>5. admin/class-wc-garantias-admin-view-render.php</h4>
            <ul>
                <li><strong>Función:</strong> Renderizado HTML de la vista de garantía</li>
                <li><strong>Renderiza:</strong>
                    <ul>
                        <li>Header con información del cliente</li>
                        <li>Tabla de items reclamados</li>
                        <li>Panel de acciones disponibles</li>
                        <li>Gestión de etiquetas y tracking</li>
                        <li>Modales y JavaScript necesario</li>
                    </ul>
                </li>
            </ul>
            
            <h4>6. admin/class-wc-garantias-admin-config.php</h4>
            <ul>
                <li><strong>Función:</strong> Configuración del sistema</li>
                <li><strong>Gestiona:</strong>
                    <ul>
                        <li>Email de notificaciones</li>
                        <li>Duración de garantías</li>
                        <li>Instrucciones de destrucción/devolución</li>
                        <li>Configuración de cajas de envío</li>
                        <li>Tiempos límite y timeouts</li>
                    </ul>
                </li>
            </ul>
            
            <h4>7. admin/class-wc-garantias-admin-rma.php</h4>
            <ul>
                <li><strong>Función:</strong> Panel de RMA (devoluciones)</li>
                <li><strong>Muestra:</strong>
                    <ul>
                        <li>Cupones RMA activos</li>
                        <li>Estado de uso de cupones</li>
                        <li>Enlaces a órdenes donde se usaron</li>
                    </ul>
                </li>
            </ul>
            
            <h4>8. admin/class-wc-garantias-admin-motivos.php</h4>
            <ul>
                <li><strong>Función:</strong> Gestin de motivos de rechazo</li>
                <li><strong>Permite:</strong>
                    <ul>
                        <li>Agregar/editar/eliminar motivos</li>
                        <li>Un motivo por línea</li>
                    </ul>
                </li>
            </ul>
        </div>
        <?php
    }
    
    private static function render_flujo() {
        ?>
        <div class="module-card">
            <h3> Flujo de Trabajo de Garantías</h3>
            
            <h4>Estados de Garantía</h4>
            <div class="code-block">
Estados principales:
- nueva: Garantía recién creada
- en_proceso: Tiene items siendo procesados
- finalizada: Todos los items procesados
            </div>
            
            <h4>Estados de Items</h4>
            <div class="code-block">
Estados posibles de cada item:
- Pendiente: Esperando revisión inicial
- solicitar_info: Se requiere más información del cliente
- recibido: Producto recibido en WiFix
- aprobado: Garantía aprobada
- aprobado_destruir: Aprobado para destrucción por el cliente
- aprobado_devolver: Aprobado para devolución a WiFix
- destruccion_subida: Cliente subió evidencia de destrucción
- devolucion_en_transito: En camino a WiFix
- rechazado: Garantía rechazada (puede apelar)
- retorno_cliente: Rechazo definitivo (no puede apelar)
- apelacion: Cliente apeló el rechazo
            </div>
            
            <h4>Flujo típico de aprobación</h4>
            <ol>
                <li>Cliente crea garantía  Estado: <code>Pendiente</code></li>
                <li>Admin puede:
                    <ul>
                        <li>Solicitar más info → <code>solicitar_info</code></li>
                        <li>Marcar como recibido → <code>recibido</code></li>
                        <li>Aprobar directamente → <code>aprobado</code></li>
                        <li>Rechazar → <code>rechazado</code></li>
                    </ul>
                </li>
                <li>Si aprobado:
                    <ul>
                        <li>Para destrucción  <code>aprobado_destruir</code></li>
                        <li>Para devolución → <code>aprobado_devolver</code></li>
                    </ul>
                </li>
                <li>Cliente sube evidencia  <code>destruccion_subida</code></li>
                <li>Admin aprueba final → <code>aprobado</code></li>
                <li>Sistema genera cupón automáticamente</li>
            </ol>
            
            <h4>Flujo de rechazo y apelación</h4>
            <ol>
                <li>Admin rechaza → <code>rechazado</code></li>
                <li>Cliente puede:
                    <ul>
                        <li>Aceptar el rechazo</li>
                        <li>Apelar → <code>apelacion</code></li>
                    </ul>
                </li>
                <li>Si apela, admin puede:
                    <ul>
                        <li>Aprobar la apelación → <code>aprobado</code></li>
                        <li>Rechazar definitivamente → <code>retorno_cliente</code></li>
                    </ul>
                </li>
            </ol>
        </div>
        <?php
    }
    
    private static function render_base_datos() {
        ?>
        <div class="module-card">
            <h3>💾 Estructura de Base de Datos</h3>
            
            <h4>Post Type: garantia</h4>
            <div class="code-block">
Post Type personalizado: 'garantia'
Post Status: 'publish'
            </div>
            
            <h4>Meta Keys principales</h4>
            <table style="width: 100%; border-collapse: collapse;">
                <thead>
                    <tr style="background: #f8f9fa;">
                        <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Meta Key</th>
                        <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Descripción</th>
                        <th style="padding: 10px; text-align: left; border: 1px solid #ddd;">Tipo</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd;"><code>_codigo_unico</code></td>
                        <td style="padding: 10px; border: 1px solid #ddd;">Código único de la garanta (GRT-YYYYMMDD-XXXX)</td>
                        <td style="padding: 10px; border: 1px solid #ddd;">string</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd;"><code>_estado</code></td>
                        <td style="padding: 10px; border: 1px solid #ddd;">Estado general de la garantía</td>
                        <td style="padding: 10px; border: 1px solid #ddd;">string</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd;"><code>_cliente</code></td>
                        <td style="padding: 10px; border: 1px solid #ddd;">ID del usuario cliente</td>
                        <td style="padding: 10px; border: 1px solid #ddd;">int</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd;"><code>_items_reclamados</code></td>
                        <td style="padding: 10px; border: 1px solid #ddd;">Array serializado con todos los items</td>
                        <td style="padding: 10px; border: 1px solid #ddd;">array</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd;"><code>_cupon_generado</code></td>
                        <td style="padding: 10px; border: 1px solid #ddd;">Código del cupón generado</td>
                        <td style="padding: 10px; border: 1px solid #ddd;">string</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd;"><code>_etiqueta_devolucion_url</code></td>
                        <td style="padding: 10px; border: 1px solid #ddd;">URL de la etiqueta de devolución</td>
                        <td style="padding: 10px; border: 1px solid #ddd;">string</td>
                    </tr>
                    <tr>
                        <td style="padding: 10px; border: 1px solid #ddd;"><code>_numero_tracking_devolucion</code></td>
                        <td style="padding: 10px; border: 1px solid #ddd;">Número de tracking del envío</td>
                        <td style="padding: 10px; border: 1px solid #ddd;">string</td>
                    </tr>
                </tbody>
            </table>
            
            <h4>Estructura del array _items_reclamados</h4>
            <div class="code-block">
[
    {
        'codigo_item': 'ITEM-XXXXX',
        'producto_id': 123,
        'nombre_producto': 'Nombre del producto',
        'cantidad': 1,
        'motivo': 'Motivo del reclamo',
        'foto_url': 'URL de la foto',
        'video_url': 'URL del video',
        'order_id': 456,
        'estado': 'Pendiente',
        'motivo_rechazo': 'Si fue rechazado',
        'fecha_rechazo': '2024-01-01',
        'destruccion': {
            'confirmado': true,
            'foto_url': 'URL foto destrucción',
            'video_url': 'URL video destrucción',
            'fecha': '2024-01-01'
        }
    }
]
            </div>
        </div>
        <?php
    }
    
    private static function render_hooks() {
        ?>
        <div class="module-card">
            <h3>🎣 Hooks y Filtros</h3>
            
            <h4>Actions principales</h4>
            <ul>
                <li><code>admin_menu</code> - Registra el menú del admin</li>
                <li><code>init</code> - Registra el post type garantia</li>
                <li><code>wp_ajax_delete_garantia</code> - Eliminar garantía via AJAX</li>
                <li><code>wp_ajax_buscar_usuario_garantia</code> - Buscar usuarios</li>
                <li><code>woocommerce_order_status_completed</code> - Marca items como comprados</li>
            </ul>
            
            <h4>Filtros personalizados</h4>
            <ul>
                <li><code>wc_garantias_duracion</code> - Modificar duracin de garantía</li>
                <li><code>wc_garantias_email_admin</code> - Email del administrador</li>
                <li><code>wc_garantias_motivos_rechazo</code> - Lista de motivos</li>
            </ul>
            
            <h4>Shortcodes disponibles</h4>
            <ul>
                <li><code>[garantias_form]</code> - Formulario de nueva garantía</li>
                <li><code>[mis_garantias]</code> - Lista de garantías del cliente</li>
            </ul>
        </div>
        <?php
    }
    
    private static function render_troubleshooting() {
        ?>
        <div class="module-card">
            <h3>🔧 Solución de Problemas Comunes</h3>
            
            <h4>1. No se cargan los módulos</h4>
            <div class="warning-box">
                <strong>Problema:</strong> Aparece "Módulo no encontrado"<br>
                <strong>Solución:</strong>
                <ol>
                    <li>Verificar que existe la carpeta <code>admin/</code></li>
                    <li>Verificar permisos de archivos (644 para archivos, 755 para carpetas)</li>
                    <li>Verificar nombres exactos de archivos (sensible a mayúsculas)</li>
                </ol>
            </div>
            
            <h4>2. Error 500 al acceder a garantías</h4>
            <div class="warning-box">
                <strong>Causas posibles:</strong>
                <ul>
                    <li>Error de sintaxis en algún mdulo</li>
                    <li>Memoria PHP insuficiente</li>
                    <li>Conflicto con otro plugin</li>
                </ul>
                <strong>Debug:</strong> Activar WP_DEBUG en wp-config.php
            </div>
            
            <h4>3. No se generan cupones automáticamente</h4>
            <div class="warning-box">
                <strong>Verificar:</strong>
                <ol>
                    <li>Todos los items deben estar en estado final (aprobado/rechazado)</li>
                    <li>Debe haber al menos un item aprobado</li>
                    <li>WooCommerce debe estar activo</li>
                    <li>Revisar logs de errores</li>
                </ol>
            </div>
            
            <h4>4. Las etiquetas no se muestran</h4>
            <div class="warning-box">
                <strong>Verificar meta keys:</strong>
                <ul>
                    <li><code>_etiqueta_devolucion_url</code></li>
                    <li><code>_etiqueta_envio_url</code></li>
                    <li><code>_andreani_etiqueta_url</code></li>
                    <li><code>_etiqueta_subida</code> (formato antiguo)</li>
                </ul>
            </div>
            
            <h4>5. Códigos de depuración útiles</h4>
            <div class="code-block">
// Ver todos los meta de una garantía
$garantia_id = 123;
$all_meta = get_post_meta($garantia_id);
error_log(print_r($all_meta, true));

// Verificar items
$items = get_post_meta($garantia_id, '_items_reclamados', true);
error_log('Items: ' . print_r($items, true));

// Forzar actualización de estado
WC_Garantias_Admin::actualizar_estado_garantia($garantia_id);
            </div>
        </div>
        <?php
    }
    
    private static function render_system_info() {
        global $wpdb;
        
        // Contar garantías
        $total_garantias = wp_count_posts('garantia')->publish;
        
        // Contar por estado
        $estados = $wpdb->get_results("
            SELECT meta_value as estado, COUNT(*) as cantidad 
            FROM {$wpdb->postmeta} 
            WHERE meta_key = '_estado' 
            AND post_id IN (SELECT ID FROM {$wpdb->posts} WHERE post_type = 'garantia')
            GROUP BY meta_value
        ");
        
        // Verificar archivos
        $admin_path = plugin_dir_path(dirname(__FILE__)) . 'admin/';
        $archivos_modulos = [
            'class-wc-garantias-admin-motivos.php',
            'class-wc-garantias-admin-config.php',
            'class-wc-garantias-admin-rma.php',
            'class-wc-garantias-admin-dashboard.php',
            'class-wc-garantias-admin-list.php',
            'class-wc-garantias-admin-view.php',
            'class-wc-garantias-admin-view-render.php',
            'class-wc-garantias-admin-docs.php'
        ];
        ?>
        
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;"><strong>Total de Garantías:</strong></td>
                <td style="padding: 10px; border: 1px solid #ddd;"><?php echo $total_garantias; ?></td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;"><strong>Estados:</strong></td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                    <?php foreach ($estados as $estado): ?>
                        <?php echo esc_html($estado->estado); ?>: <?php echo $estado->cantidad; ?><br>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;"><strong>Versión de PHP:</strong></td>
                <td style="padding: 10px; border: 1px solid #ddd;"><?php echo PHP_VERSION; ?></td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;"><strong>Versión de WordPress:</strong></td>
                <td style="padding: 10px; border: 1px solid #ddd;"><?php echo get_bloginfo('version'); ?></td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;"><strong>Versión de WooCommerce:</strong></td>
                <td style="padding: 10px; border: 1px solid #ddd;"><?php echo defined('WC_VERSION') ? WC_VERSION : 'No instalado'; ?></td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;"><strong>Versión del Plugin:</strong></td>
                <td style="padding: 10px; border: 1px solid #ddd;">4.6 (Actualizado: 15/08/2025)</td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;"><strong>Módulos cargados:</strong></td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                    <?php foreach ($archivos_modulos as $archivo): ?>
                        <?php $existe = file_exists($admin_path . $archivo); ?>
                        <span style="color: <?php echo $existe ? 'green' : 'red'; ?>;">
                            <?php echo $existe ? '✓' : '✗'; ?> <?php echo $archivo; ?>
                        </span><br>
                    <?php endforeach; ?>
                </td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;"><strong>Ruta del plugin:</strong></td>
                <td style="padding: 10px; border: 1px solid #ddd;">
                    <code><?php echo plugin_dir_path(dirname(__FILE__)); ?></code>
                </td>
            </tr>
        </table>
        
        <div class="success-box" style="margin-top: 20px;">
            <strong>📋 Para compartir con soporte:</strong><br>
            Copia toda esta información cuando necesites ayuda. Incluye:
            <ul>
                <li>La pestaña "Estructura de Archivos" completa</li>
                <li>Esta información del sistema</li>
                <li>Cualquier mensaje de error específico</li>
            </ul>
        </div>
        <?php
    }
    private static function render_cambios_recientes() {
        ?>
        <div class="module-card">
            <h3>🆕 Cambios y Correcciones - Agosto 2025</h3>
            
            <div class="success-box">
                <strong>📅 Última actualización:</strong> 15 de Agosto 2025<br>
                <strong>🔧 Versión:</strong> 4.6<br>
                <strong> Implementado por:</strong> Soporte WiFix
            </div>
            
            <h4>1. Sistema RMA - Cupones para Devoluciones</h4>
            <div class="info-box">
                <strong>✅ Funcionalidad Implementada:</strong>
                <ul>
                    <li><strong>Botón RMA</strong> en columna Info para items en estado <code>retorno_cliente</code></li>
                    <li><strong>Generación automática</strong> de cupones cuando existe producto RMA con mismo SKU</li>
                    <li><strong>Formato cupón:</strong> <code>RMA-Devolucion-[SKU]-x[CANTIDAD]</code></li>
                    <li><strong>CRON Job</strong> verifica cada hora productos RMA pendientes</li>
                </ul>
                
                <strong>📁 Archivos Modificados:</strong>
                <ul>
                    <li><code>includes/class-wc-garantias-rma.php</code> - Función crear_cupon_rma actualizada</li>
                    <li><code>includes/class-wc-garantias-ajax-rma.php</code> - Handler AJAX</li>
                    <li><code>includes/class-wc-garantias-cron-rma.php</code> - Verificación automática</li>
                    <li><code>admin/class-wc-garantias-admin-view-render.php</code> - Botón RMA agregado</li>
                </ul>
            </div>
            
            <h4>2. Etiquetas QR Individuales (60x20mm)</h4>
            <div class="info-box">
                <strong>✅ Nueva Funcionalidad:</strong>
                <ul>
                    <li><strong>Impresión masiva</strong> desde tarjeta "Etiquetas y Tracking"</li>
                    <li><strong>Impresión individual</strong> por item desde columna Acciones</li>
                    <li>Formato para impresora Brother 60x20mm</li>
                    <li>QR contiene código del item (GRT-ITEM-XXXXX)</li>
                </ul>
                
                <strong>🖨️ Contenido de etiquetas:</strong>
                <ul>
                    <li>Código QR con código del item</li>
                    <li>SKU del producto</li>
                    <li>Nombre del producto (truncado a 35 caracteres)</li>
                    <li>Nombre del cliente (truncado a 20 caracteres)</li>
                </ul>
                
                <strong>📁 Archivos Modificados:</strong>
                <ul>
                    <li><code>includes/class-wc-garantias-admin.php</code> - Funciones ajax_imprimir_etiquetas_individuales y ajax_imprimir_etiqueta_individual_item</li>
                    <li><code>admin/class-wc-garantias-admin-view-render.php</code> - Botones de impresión agregados</li>
                </ul>
            </div>
            
            <h4>3. Corrección de Cambios de Estado Masivos</h4>
            <div class="warning-box">
                <strong>🐛 Problema Resuelto:</strong><br>
                Los items no cambiaban de estado (siempre mostraba "0 items procesados")
                
                <strong> Solución:</strong>
                <ul>
                    <li>JavaScript corregido para enviar items seleccionados como array PHP</li>
                    <li>Normalización de estados a minúsculas en funciones de procesamiento</li>
                    <li>Estados <code>aprobado_devolver</code> y <code>aprobado_destruir</code> ahora seleccionables</li>
                </ul>
                
                <strong>📁 Archivos Corregidos:</strong>
                <ul>
                    <li><code>admin/class-wc-garantias-admin-view.php</code> - Funciones marcar_items_recibidos y aprobar_items</li>
                    <li><code>admin/class-wc-garantias-admin-view-render.php</code> - JavaScript del formulario bulk</li>
                </ul>
            </div>
            
            <h4>4. Mejora en Visualización de Productos Eliminados</h4>
            <div class="info-box">
                <strong>✅ Mejora Implementada:</strong>
                <ul>
                    <li>Muestra nombre del producto aunque esté eliminado del sistema</li>
                    <li>Búsqueda en cascada: Producto actual → Nombre guardado → Orden original</li>
                    <li>Badge "Eliminado" visible junto al nombre del producto</li>
                </ul>
                
                <strong>📁 Archivo Modificado:</strong>
                <ul>
                    <li><code>admin/class-wc-garantias-admin-view-render.php</code> - Función render_tabla_items</li>
                </ul>
            </div>
            
            <h4>5. Flujo RMA Completo</h4>
            <div class="code-block">
1. Item rechazado definitivamente  Estado: retorno_cliente
2. Admin crea producto RMA en CRM con SKU = código_item
3. Sistema detecta producto (botón manual o CRON automático)
4. Genera cupón: RMA-Devolucion-SKU-xCANTIDAD
5. Cliente realiza compra
6. Cupón se aplica automáticamente
            </div>
            
            <h4>6. Comandos Útiles para Debug</h4>
            <div class="code-block">
// Verificar CRON jobs
wp cron event list | grep garantias

// Forzar ejecución del CRON RMA
wp cron event run wc_garantias_check_pending_rma

// Ver cupones RMA en base de datos
SELECT post_title, post_status 
FROM wp_posts 
WHERE post_type = 'shop_coupon' 
AND post_title LIKE 'RMA-Devolucion-%';

// Ver items en retorno_cliente
SELECT post_id, meta_value 
FROM wp_postmeta 
WHERE meta_key = '_items_reclamados' 
AND meta_value LIKE '%retorno_cliente%';

// Logs de RMA (buscar en debug.log)
grep "VERIFICANDO PRODUCTOS RMA" wp-content/debug.log
grep "Cupón RMA creado" wp-content/debug.log
            </div>
            
            <h4>7. Checklist de Verificación Post-Actualización</h4>
            <div class="success-box">
                <strong>✓ Verificar después de actualizar:</strong>
                <ol>
                    <li>✅ Botón RMA aparece para items en <code>retorno_cliente</code></li>
                    <li>✅ Cupones se generan con formato correcto</li>
                    <li>✅ CRON job está programado (verificar con WP Crontrol plugin)</li>
                    <li>✅ Etiquetas QR se imprimen correctamente</li>
                    <li>✅ Cambios de estado masivos funcionan</li>
                    <li>✅ Productos eliminados muestran nombre correcto</li>
                </ol>
            </div>
            
            <h4>8. Notas Importantes</h4>
            <div class="warning-box">
                <strong>⚠️ Recordatorios:</strong>
                <ul>
                    <li>Los productos RMA deben crearse en el CRM primero</li>
                    <li>El SKU del producto RMA debe coincidir exactamente con el cdigo del item</li>
                    <li>Los cupones RMA tienen validez de 120 días (configurable)</li>
                    <li>Si se borra un cupón RMA, se puede regenerar con el botón</li>
                    <li>Las etiquetas son para impresora Brother 60x20mm</li>
                </ul>
            </div>
        </div>
        <?php
    }
}