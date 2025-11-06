<?php
if (!defined('ABSPATH')) exit;

/**
 * Clase para manejar aprobaciones parciales de items
 * Permite aprobar una parte y rechazar otra del mismo item
 */
class WC_Garantias_Partial_Approval {
    
    /**
     * Dividir un item en dos partes (aprobado y rechazado)
     * 
     * @param int $garantia_id
     * @param string $codigo_item
     * @param int $cantidad_aprobar
     * @param int $cantidad_rechazar
     * @param string $motivo_rechazo
     * @return bool
     */
    public static function split_item($garantia_id, $codigo_item, $cantidad_aprobar, $cantidad_rechazar, $motivo_rechazo = '') {
        $items = get_post_meta($garantia_id, '_items_reclamados', true);
        
        if (!is_array($items)) {
            return false;
        }
        
        // Buscar el item original
        $item_index = null;
        $item_original = null;
        
        foreach ($items as $index => $item) {
            if (isset($item['codigo_item']) && $item['codigo_item'] === $codigo_item) {
                $item_index = $index;
                $item_original = $item;
                break;
            }
        }
        
        if ($item_original === null) {
            return false;
        }
        
        // Validar cantidades
        $cantidad_total = intval($item_original['cantidad']);
        if (($cantidad_aprobar + $cantidad_rechazar) !== $cantidad_total) {
            error_log("Error: Las cantidades no suman el total. Aprobado: $cantidad_aprobar, Rechazado: $cantidad_rechazar, Total: $cantidad_total");
            return false;
        }
        
        // Actualizar el item original (parte aprobada)
        $items[$item_index]['cantidad'] = $cantidad_aprobar;
        $items[$item_index]['estado'] = 'aprobado';
        $items[$item_index]['nota_division'] = "Aprobado parcialmente: $cantidad_aprobar de $cantidad_total";
        
        // Crear nuevo item para la parte rechazada
        $item_rechazado = $item_original;
        $item_rechazado['codigo_item'] = $codigo_item . '-R'; // Agregar sufijo R de Rechazado
        $item_rechazado['cantidad'] = $cantidad_rechazar;
        $item_rechazado['estado'] = 'rechazado';
        $item_rechazado['motivo_rechazo'] = $motivo_rechazo;
        $item_rechazado['fecha_rechazo'] = current_time('mysql');
        $item_rechazado['nota_division'] = "Rechazado parcialmente: $cantidad_rechazar de $cantidad_total";
        $item_rechazado['es_division'] = true;
        $item_rechazado['item_original'] = $codigo_item;
        
        // Agregar el item rechazado al array
        $items[] = $item_rechazado;
        
        // Guardar los items actualizados
        update_post_meta($garantia_id, '_items_reclamados', $items);
        
        // Log para auditoría
        error_log("Item $codigo_item dividido: $cantidad_aprobar aprobados, $cantidad_rechazar rechazados");
        
        return true;
    }
}