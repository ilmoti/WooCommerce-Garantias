<?php
if (!defined('ABSPATH')) exit;
/**
 * Sistema de notificaciones mejorado para el plugin de garantías
 */
class WC_Garantias_Notifications {
    
    /**
     * Inicializar el sistema de notificaciones
     */
    public static function init() {
        // Hooks para diferentes eventos
        add_action('wcgarantias_nueva_garantia', [__CLASS__, 'notificar_nueva_garantia'], 10, 2);
        add_action('wcgarantias_estado_actualizado', [__CLASS__, 'notificar_cambio_estado'], 10, 3);
        add_action('wcgarantias_comentario_agregado', [__CLASS__, 'notificar_nuevo_comentario'], 10, 3);
        add_action('wcgarantias_cupon_generado', [__CLASS__, 'notificar_cupon_generado'], 10, 3);
        
        // Email templates
        add_filter('woocommerce_email_classes', [__CLASS__, 'agregar_email_classes']);
    }
    
       public static function notificar_nueva_garantia($garantia_id, $codigo_unico) {
    $admin_email = get_option('admin_email_garantias', get_option('admin_email'));
    $garantia = get_post($garantia_id);
    $cliente_id = get_post_meta($garantia_id, '_cliente', true);
    $user = get_userdata($cliente_id);
    $items = get_post_meta($garantia_id, '_items_reclamados', true);
    
    if (!$user) {
        error_log('WC_Garantias_Notifications: Usuario no encontrado para cliente_id: ' . $cliente_id);
        return;
    }
    
    // Email al admin
    $subject = 'Nueva garantía registrada - ' . $codigo_unico;
    
    $message = self::get_email_header('Nueva Garantía Registrada');
    $message .= '<div style="padding: 20px; background: #f8f9fa; border-radius: 5px; margin: 20px 0;">';
    $message .= '<h3 style="color: #333; margin-top: 0;">Detalles del reclamo:</h3>';
    $message .= '<table style="width: 100%; border-collapse: collapse;">';
    $message .= '<tr><td style="padding: 8px 0;"><strong>Código:</strong></td><td>' . esc_html($codigo_unico) . '</td></tr>';
    $message .= '<tr><td style="padding: 8px 0;"><strong>Cliente:</strong></td><td>' . esc_html($user->display_name) . '</td></tr>';
    $message .= '<tr><td style="padding: 8px 0;"><strong>Email:</strong></td><td>' . esc_html($user->user_email) . '</td></tr>';
    $message .= '<tr><td style="padding: 8px 0;"><strong>Fecha:</strong></td><td>' . current_time('d/m/Y H:i') . '</td></tr>';
    $message .= '<tr><td style="padding: 8px 0;"><strong>Items:</strong></td><td>' . (is_array($items) ? count($items) : 0) . ' producto(s)</td></tr>';
    $message .= '</table>';
    $message .= '</div>';
    
    // Botón de acción
    $message .= '<div style="text-align: center; margin: 30px 0;">';
    $message .= '<a href="' . admin_url("admin.php?page=wc-garantias-ver&garantia_id={$garantia_id}") . '" ';
    $message .= 'style="display: inline-block; padding: 12px 30px; background: #007cba; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">';
    $message .= 'Ver Garantía</a>';
    $message .= '</div>';
    
    $message .= self::get_email_footer();
    
    wp_mail($admin_email, $subject, $message, self::get_email_headers());
    // Email al cliente
    self::enviar_confirmacion_cliente($garantia_id, $codigo_unico);
}
/**
 * Enviar confirmación al cliente
 */
private static function enviar_confirmacion_cliente($garantia_id, $codigo_unico) {
    $cliente_id = get_post_meta($garantia_id, '_cliente', true);
    $user = get_userdata($cliente_id);
    
    if (!$user || !$user->user_email) return;
    
    $subject = 'Confirmación de reclamo - ' . $codigo_unico;
    
    $message = self::get_email_header('Reclamo Registrado Exitosamente');
    $message .= '<div style="padding: 20px; text-align: center;">';
    $message .= '<div style="font-size: 48px; margin-bottom: 20px;">📋</div>';
    $message .= '<h2 style="color: #333; margin-bottom: 10px;">¡Tu reclamo ha sido registrado!</h2>';
    $message .= '<p style="font-size: 16px; color: #666;">Hemos recibido tu solicitud de garantía correctamente.</p>';
    
    // Código destacado
    $message .= '<div style="background: #f8f9fa; border: 2px dashed #dee2e6; padding: 20px; border-radius: 10px; margin: 30px 0;">';
    $message .= '<p style="margin: 0 0 10px 0; color: #666;">Tu código de seguimiento es:</p>';
    $message .= '<h1 style="margin: 0; color: #007cba; font-size: 28px;">' . esc_html($codigo_unico) . '</h1>';
    $message .= '</div>';
    
    $message .= '</div>';
    
    // Botón de seguimiento
    $message .= '<div style="text-align: center; margin: 30px 0;">';
    $message .= '<a href="' . wc_get_account_endpoint_url('garantias') . '" ';
    $message .= 'style="display: inline-block; padding: 12px 30px; background: #28a745; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">';
    $message .= 'Seguir mi Reclamo</a>';
    $message .= '</div>';
    
    $message .= self::get_email_footer();
    
    wp_mail($user->user_email, $subject, $message, self::get_email_headers());
}
    public static function notificar_cambio_estado($garantia_id, $estado_anterior, $estado_nuevo) {
    $garantia = get_post($garantia_id);
    $cliente_id = get_post_meta($garantia_id, '_cliente', true);
    $user = get_userdata($cliente_id);
    $codigo_unico = get_post_meta($garantia_id, '_codigo_unico', true);
    
    if (!$user || !$user->user_email) return;
    
    $estados_notificar = ['recibido', 'aprobado_cupon', 'rechazado'];
    if (!in_array($estado_nuevo, $estados_notificar)) return;
    
    $estados_nombres = [
        'recibido' => 'Recibido - En análisis',
        'aprobado_cupon' => 'Aprobado - Cupón Enviado',
        'rechazado' => 'Rechazado',
    ];
    
    $subject = 'Actualización de tu garantía - ' . $codigo_unico;
    
    $message = self::get_email_header('Actualización de Garantía');
    $message .= '<div style="padding: 20px; text-align: center;">';
    $message .= '<h2 style="color: #333;">Tu garantía ha sido actualizada</h2>';
    $message .= '<p style="font-size: 16px; color: #666;">Código: <strong>' . esc_html($codigo_unico) . '</strong></p>';
    
    // Estado visual
    $color = $estado_nuevo === 'rechazado' ? '#dc3545' : ($estado_nuevo === 'aprobado_cupon' ? '#28a745' : '#17a2b8');
    $message .= '<div style="display: inline-block; padding: 10px 20px; background: ' . $color . '; color: white; border-radius: 25px; font-weight: bold; margin: 20px 0;">';
    $message .= esc_html($estados_nombres[$estado_nuevo]);
    $message .= '</div>';
    
    // Mensaje específico según estado
    if ($estado_nuevo === 'rechazado') {
        $motivo = get_post_meta($garantia_id, '_motivo_rechazo', true);
        $message .= '<div style="background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 20px 0;">';
        $message .= '<p style="margin: 0; color: #721c24;"><strong>Motivo:</strong> ' . esc_html($motivo) . '</p>';
        $message .= '</div>';
    } elseif ($estado_nuevo === 'aprobado_cupon') {
        $cupon = get_post_meta($garantia_id, '_cupon_generado', true);
        $message .= '<div style="background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;">';
        $message .= '<p style="margin: 0; color: #155724;">¡Se ha generado un cupón para tu próxima compra!</p>';
        $message .= '</div>';
    }
    
    $message .= '</div>';
    
    // Botón para ver detalles
    $message .= '<div style="text-align: center; margin: 30px 0;">';
    $message .= '<a href="' . wc_get_account_endpoint_url('garantias') . '" ';
    $message .= 'style="display: inline-block; padding: 12px 30px; background: #007cba; color: white; text-decoration: none; border-radius: 5px; font-weight: bold;">';
    $message .= 'Ver Mis Garantías</a>';
    $message .= '</div>';
    
    $message .= self::get_email_footer();
    
    wp_mail($user->user_email, $subject, $message, self::get_email_headers());
}
    // Métodos vacíos por ahora
    public static function notificar_nuevo_comentario($garantia_id, $comentario, $es_admin) {}
    public static function notificar_cupon_generado($garantia_id, $cupon_codigo, $monto) {}
    public static function agregar_email_classes($email_classes) { return $email_classes; }
    
    /**
     * Helpers para emails
     */
    private static function get_email_headers() {
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
        ];
        return $headers;
    }
    
    private static function get_email_header($title) {
        $logo_url = get_site_icon_url();
        $site_name = get_bloginfo('name');
        
        $header = '<!DOCTYPE html>';
        $header .= '<html><head><meta charset="UTF-8"></head>';
        $header .= '<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background: #f5f5f5;">';
        $header .= '<div style="max-width: 600px; margin: 0 auto; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">';
        
        // Header con logo
        $header .= '<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center;">';
        if ($logo_url) {
            $header .= '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr($site_name) . '" style="max-height: 60px; margin-bottom: 10px;">';
        }
        $header .= '<h1 style="color: white; margin: 0; font-size: 24px;">' . esc_html($title) . '</h1>';
        $header .= '</div>';
        
        $header .= '<div style="padding: 30px;">';
        
        return $header;
    }
    
    private static function get_email_footer() {
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        
        $footer = '</div>'; // Cierra contenido
        
        // Footer
        $footer .= '<div style="background: #f8f9fa; padding: 20px; text-align: center; border-top: 1px solid #dee2e6;">';
        $footer .= '<p style="margin: 0 0 10px 0; color: #666; font-size: 14px;">Este es un email automático, por favor no respondas a este mensaje.</p>';
        $footer .= '<p style="margin: 0; color: #666; font-size: 14px;">';
        $footer .= '© ' . date('Y') . ' ' . esc_html($site_name) . ' - ';
        $footer .= '<a href="' . esc_url($site_url) . '" style="color: #007cba; text-decoration: none;">Visitar sitio web</a>';
        $footer .= '</p>';
        $footer .= '</div>';
        
        $footer .= '</div></body></html>';
        
        return $footer;
    }
    
} // <-- AQUÍ CIERRA LA CLASE

// Inicializar
WC_Garantias_Notifications::init();