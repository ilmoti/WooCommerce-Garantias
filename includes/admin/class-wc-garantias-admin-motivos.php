<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Módulo de Administración de Motivos de Garantía
 * Separado del archivo principal para mejor mantenimiento
 */
class WC_Garantias_Admin_Motivos {
    
    /**
     * Mostrar página de configuración de motivos
     */
    public static function render_page() {
        // Procesar formulario si se envió
        if (
            isset($_POST['guardar_motivos_garantia']) &&
            isset($_POST['motivos_garantia_nonce']) &&
            wp_verify_nonce($_POST['motivos_garantia_nonce'], 'guardar_motivos_garantia')
        ) {
            update_option('motivos_garantia', sanitize_textarea_field($_POST['motivos_garantia']));
            update_option('motivos_rechazo_garantia', sanitize_textarea_field($_POST['motivos_rechazo_garantia']));
            echo '<div class="notice notice-success is-dismissible"><p>Motivos guardados correctamente.</p></div>';
        }
        ?>
        <div class="wrap">
            <h1>Motivos de Garantía</h1>
            <form method="post" action="">
                <?php wp_nonce_field('guardar_motivos_garantia', 'motivos_garantia_nonce'); ?>
                <h2>Motivos de reclamo (cliente)</h2>
                <textarea name="motivos_garantia" rows="6" style="width: 100%;"><?php
                    echo esc_textarea( get_option('motivos_garantia', "Producto defectuoso\nFalla técnica\nFaltan piezas\nOtro") );
                ?></textarea>
                <p class="description">Escribe un motivo por línea. Estos motivos los ve el cliente al reclamar una garantía.</p>
                <h2 style="margin-top:32px;">Motivos de rechazo (admin)</h2>
                <textarea name="motivos_rechazo_garantia" rows="6" style="width: 100%;"><?php
                    echo esc_textarea( get_option('motivos_rechazo_garantia', "Fuera de plazo\nProducto dañado\nNo corresponde a la compra\nOtro") );
                ?></textarea>
                <p class="description">Escribe un motivo por línea. Estos motivos aparecen al rechazar una garantía.</p>
                <p>
                    <button class="button button-primary" type="submit" name="guardar_motivos_garantia">Guardar Motivos</button>
                </p>
            </form>
        </div>
        <?php
    }
}