<?php
if ( ! defined( 'ABSPATH' ) ) exit;

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

// Dashboard mejorado de garantías del cliente

// Calcular estadísticas de items
$user_id = get_current_user_id();
$args = [
    'post_type'      => 'garantia',
    'post_status'    => 'publish',
    'meta_query'     => [
        ['key' => '_cliente', 'value' => $user_id]
    ],
    'posts_per_page' => -1
];
$garantias = get_posts($args);

// Contadores de items
$total_items = 0;
$items_pendientes = 0;
$items_aprobados = 0;
$items_rechazados = 0;

foreach ($garantias as $g) {
    $items = get_post_meta($g->ID, '_items_reclamados', true);
    
    if (is_array($items)) {
        foreach ($items as $item) {
            $total_items++;
            
            $estado_item = isset($item['estado']) ? $item['estado'] : 'Pendiente';
            
            // Contar según estado del item
            if ($estado_item === 'aprobado') {
                $items_aprobados++;
            } elseif ($estado_item === 'rechazado') {
                $items_rechazados++;
            } else {
                // Todo lo demás cuenta como pendiente
                $items_pendientes++;
            }
        }
    } else {
        // Compatibilidad con formato antiguo
        $total_items++;
        $estado = get_post_meta($g->ID, '_estado', true);
        
        if (in_array($estado, ['aprobado_cupon', 'finalizado_cupon', 'finalizado'])) {
            $items_aprobados++;
        } elseif ($estado === 'rechazado') {
            $items_rechazados++;
        } else {
            $items_pendientes++;
        }
    }
}
?>

<div id="garantias-dashboard-nuevo" class="garantias-dashboard-container">
    <h2 style="text-align: center; margin-bottom: 20px; color: #333;">MIS GARANTÍAS</h2>

    <!-- Menú de navegación de tabs -->
    <div class="garantias-nav-tabs" style="
        display: flex;
        justify-content: center;
        gap: 10px;
        margin-bottom: 30px;
        border-bottom: 2px solid #e0e0e0;
        padding-bottom: 0;
    ">
        <a href="<?php echo esc_url(wc_get_endpoint_url('garantias', '', wc_get_page_permalink('myaccount'))); ?>"
           class="nav-tab active"
           style="
               padding: 12px 30px;
               text-decoration: none;
               color: #667eea;
               font-weight: 600;
               border-bottom: 3px solid #667eea;
               transition: all 0.3s;
           ">
            Mis Garantías
        </a>
        <a href="<?php echo esc_url(wc_get_endpoint_url('cupones-garantia', '', wc_get_page_permalink('myaccount'))); ?>"
           class="nav-tab"
           style="
               padding: 12px 30px;
               text-decoration: none;
               color: #666;
               font-weight: 500;
               border-bottom: 3px solid transparent;
               transition: all 0.3s;
           "
           onmouseover="this.style.color='#667eea'; this.style.borderBottomColor='#667eea';"
           onmouseout="this.style.color='#666'; this.style.borderBottomColor='transparent';">
            Mis Cupones
        </a>
    </div>

    <div class="garantias-stats" style="
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    ">
        <div class="stat-card" style="
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        " onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
            <div class="stat-number" style="font-size: 3em; font-weight: bold; margin-bottom: 10px;">
                <?php echo $total_items; ?>
            </div>
            <div class="stat-label" style="font-size: 0.9em; opacity: 0.9;">
                Total Items en Garantía
            </div>
        </div>
        
        <div class="stat-card" style="
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        " onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
            <div class="stat-number" style="font-size: 3em; font-weight: bold; margin-bottom: 10px;">
                <?php echo $items_pendientes; ?>
            </div>
            <div class="stat-label" style="font-size: 0.9em; opacity: 0.9;">
                Pendientes
            </div>
        </div>
        
        <div class="stat-card" style="
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        " onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
            <div class="stat-number" style="font-size: 3em; font-weight: bold; margin-bottom: 10px;">
                <?php echo $items_aprobados; ?>
            </div>
            <div class="stat-label" style="font-size: 0.9em; opacity: 0.9;">
                Aprobados
            </div>
        </div>
        
        <div class="stat-card" style="
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
            padding: 25px;
            border-radius: 15px;
            text-align: center;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        " onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
            <div class="stat-number" style="font-size: 3em; font-weight: bold; margin-bottom: 10px;">
                <?php echo $items_rechazados; ?>
            </div>
            <div class="stat-label" style="font-size: 0.9em; opacity: 0.9;">
                Rechazados
            </div>
        </div>
    </div>
</div>

<div class="garantias-tips" style="
    background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    border-radius: 8px;
    padding: 12px 15px;
    margin: 12px 0;
    border-left: 4px solid #007cba;
">
    <h4 style="margin-top: 0; color: #007cba;">📝 Consejos importantes al cargar una garantía:</h4>
    <div style="margin-bottom: 0; color: #495057;">
        <?php echo wp_kses_post(get_option('garantia_consejos_texto', '<ul><li><strong> Foto clara:</strong> Asegurate de que se vea bien el problema. Si es un módulo o herramienta, adjuntá un video.</li><li><strong>📝 Descripcin detallada:</strong> Explica qué está fallando, cómo y cuándo lo notaste.
</li><li><strong>📦 Conservá el empaque original:</strong> Es obligatorio para procesar la garantía.</li><li><strong> Revisá tu correo y respondé rápido:</strong> Podramos contactarte para completar la info y así evitar demoras.</li></ul>')); ?>
    </div>
</div>

<!-- Sección de cupones disponibles -->
<?php
$user_id = get_current_user_id();
$cupon_pendiente = get_user_meta($user_id, '_cupon_garantia_pendiente', true);

// Verificar que el cupón realmente existe Y no ha sido usado
$cupon_valido = false;
if ($cupon_pendiente) {
    $cupon_post = get_page_by_title($cupon_pendiente, OBJECT, 'shop_coupon');
    if ($cupon_post && $cupon_post->post_status === 'publish') {
        // Verificar si el cupón ya fue usado
        $usage_count = get_post_meta($cupon_post->ID, 'usage_count', true);
        
        if ($usage_count > 0) {
            // El cupón ya fue usado, eliminarlo del user_meta
            delete_user_meta($user_id, '_cupon_garantia_pendiente');
            $cupon_pendiente = false;
        } else {
            // El cupón existe y no ha sido usado
            $cupon_valido = true;
            // Obtener valor del cupón
            $cupon_valor = get_post_meta($cupon_post->ID, 'coupon_amount', true);
        }
    } else {
        // Limpiar cupón inexistente
        delete_user_meta($user_id, '_cupon_garantia_pendiente');
        $cupon_pendiente = false;
    }
}

if ($cupon_pendiente && $cupon_valido) :
?>
<div class="cupon-disponible" style="
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: white;
    border-radius: 10px;
    padding: 20px;
    margin: 20px 0;
    text-align: center;
    box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);
">
    <h4 style="margin-top: 0; color: white;">🎉 ¡Tienes un cupon disponible por $<?php echo number_format($cupon_valor, 0, ',', '.'); ?>!</h4>
    <div style="
        background: rgba(255,255,255,0.2);
        border-radius: 8px;
        padding: 15px;
        margin: 15px 0;
        font-size: 1.2em;
        font-weight: bold;
        letter-spacing: 2px;
    ">
        <?php echo esc_html($cupon_pendiente); ?>
    </div>
    <p style="margin-bottom: 10px;">
        Este cupón se aplicará automáticamente en tu próxima compra.
    </p>
    <a href="<?php echo esc_url(wc_get_page_permalink('shop')); ?>" 
       class="button" 
       style="background: white; color: #28a745; border: none; padding: 10px 20px; border-radius: 5px; font-weight: bold; text-decoration: none;">
        Ir a comprar
    </a>
</div>
<?php endif; ?>

<?php
// Verificar si hay etiquetas pendientes de descargar (solo distribuidores)
if ($is_distribuidor) {
    $args = [
        'post_type' => 'garantia',
        'post_status' => 'publish',
        'meta_query' => [
            ['key' => '_cliente', 'value' => $user_id],
            ['key' => '_etiqueta_envio_url', 'compare' => 'EXISTS']
        ],
        'posts_per_page' => -1
    ];
    
    $garantias_con_etiqueta = get_posts($args);
    $etiquetas_pendientes = 0;
    
    foreach ($garantias_con_etiqueta as $gar) {
        $estado = get_post_meta($gar->ID, '_estado', true);
        if ($estado === 'pendiente_envio') {
            $etiquetas_pendientes++;
        }
    }
    
    if ($etiquetas_pendientes > 0):
?>
<div class="etiquetas-pendientes" style="
    background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
    color: white;
    border-radius: 10px;
    padding: 20px;
    margin: 20px 0;
    text-align: center;
    box-shadow: 0 4px 15px rgba(23, 162, 184, 0.3);
">
    <h4 style="margin-top: 0; color: white;">📦 Tienes <?php echo $etiquetas_pendientes; ?> etiqueta(s) de envío disponible(s)</h4>
    <p style="margin-bottom: 10px;">
        Descarga las etiquetas y envía los productos lo antes posible.
    </p>
    <a href="#tabla-reclamos" 
       class="button" 
       style="background: white; color: #17a2b8; border: none; padding: 10px 20px; border-radius: 5px; font-weight: bold; text-decoration: none;">
        Ver mis garantías
    </a>
</div>
<?php endif;
} ?>

<!-- Seccin de acceso rápido -->
<div class="acceso-rapido" style="
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 15px;
    margin: 20px 0;
">
    <a href="#garantiaForm" class="quick-action-card" style="
        display: block;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 20px;
        border-radius: 10px;
        text-decoration: none;
        text-align: center;
        transition: transform 0.2s ease;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    " onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
        <div style="font-size: 2em; margin-bottom: 10px; height: 48px; display: flex; align-items: center; justify-content: center;">📋</div>
        <h4 style="margin: 0; color: white;">Nuevo Reclamo</h4>
        <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 0.9em;">
            Reportar un problema con un producto
        </p>
    </a>
    
    <a href="<?php echo add_query_arg('devolucion', '1', wc_get_account_endpoint_url('garantias')); ?>" class="quick-action-card" style="
        display: block;
        background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
        color: white;
        padding: 20px;
        border-radius: 10px;
        text-decoration: none;
        text-align: center;
        transition: transform 0.2s ease;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    " onmouseover="this.style.transform='translateY(-2px)'" onmouseout="this.style.transform='translateY(0)'">
        <div style="font-size: 2em; margin-bottom: 10px; height: 48px; display: flex; align-items: center; justify-content: center;">↩️</div>
        <h4 style="margin: 0; color: white;">Devolución por Error</h4>
        <p style="margin: 5px 0 0 0; opacity: 0.9; font-size: 0.9em;">
            Devolver producto (<?php echo get_option('dias_devolucion_error', 20); ?> días)
        </p>
    </a>
</div>

<!-- Información sobre la garantía -->
<div class="info-garantia" style="
    background: #f8f9fa;
    border-radius: 6px;
    padding: 12px 15px;
    margin: 12px 0;
    border: 1px solid #dee2e6;
">
    <h4 style="margin-top: 0; color: #495057;"> Información importante</h4>
    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 25px; text-align: left;">
    <div>
        <strong>Plazo de garantía:</strong><br>
        <span style="color: #007cba;"><?php echo esc_html(get_option('duracion_garantia', 180)); ?> días</span><br>
        <small>desde la compra</small>
    </div>
    <div>
        <strong>Tiempo de respuesta:</strong><br>
        <span style="color: #007cba;">24-48 horas</span><br>
        <small>das hábiles</small>
    </div>
    <div>
        <strong>Métodos de contacto:</strong><br>
        <span style="color: #007cba;">Email y esta plataforma</span>
    </div>
</div>
</div>

<style>
@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Responsivo para móviles */
@media (max-width: 768px) {
    .garantias-dashboard-container .stat-card {
        padding: 15px !important;
    }
    
    .garantias-dashboard-container .stat-number {
        font-size: 2em !important;
    }
    
    .acceso-rapido {
        grid-template-columns: 1fr !important;
    }
    
    .info-garantia div[style*="grid-template-columns"] {
        grid-template-columns: 1fr !important;
        gap: 15px !important;
    }
}

/* Ocultar dashboard en móvil si hay problemas de espacio */
@media (max-width: 480px) {
    .garantias-tips,
    .info-garantia {
        padding: 15px;
        margin: 15px 0;
    }
    
    .cupon-disponible {
        padding: 15px;
        margin: 15px 0;
    }
}
</style>

<script>
// Dashboard estático - sin actualización automática
</script>