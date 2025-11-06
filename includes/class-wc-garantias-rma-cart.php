<?php
if (!defined('ABSPATH')) exit;

/**
 * Manejo del carrito para RMA con cantidades correctas
 */
class WC_Garantias_RMA_Cart {
    
    public static function init() {
        // Hook para cuando se aplica un cupón RMA
        add_action('woocommerce_applied_coupon', array(__CLASS__, 'ajustar_cantidad_rma'), 20, 1);
        
        // Hook alternativo para asegurar la cantidad
        add_action('woocommerce_after_cart_item_quantity_update', array(__CLASS__, 'verificar_cantidad_rma'), 10, 4);
    }
    
    /**
     * Ajustar cantidad cuando se aplica cupón RMA
     */
    public static function ajustar_cantidad_rma($coupon_code) {
        // Verificar si es un cupón RMA
        if (strpos($coupon_code, 'rma-devolucion-') !== 0) {
            return;
        }
        
        // Obtener el cupón
        $coupon = new WC_Coupon($coupon_code);
        if (!$coupon->get_id()) {
            return;
        }
        
        // Obtener la cantidad y producto del meta del cupón
        $cantidad_rma = get_post_meta($coupon->get_id(), '_cantidad_rma', true);
        $producto_rma_id = get_post_meta($coupon->get_id(), '_producto_rma_id', true);
        
        if (!$cantidad_rma || !$producto_rma_id) {
            return;
        }
        
        // Buscar el producto en el carrito
        $cart = WC()->cart;
        $producto_encontrado = false;
        
        foreach ($cart->get_cart() as $cart_item_key => $cart_item) {
            if ($cart_item['product_id'] == $producto_rma_id) {
                // Actualizar la cantidad
                $cart->set_quantity($cart_item_key, intval($cantidad_rma), false);
                $producto_encontrado = true;
                
                // Mensaje para el cliente
                wc_add_notice(
                    sprintf('Se han agregado %d unidades del producto RMA al carrito.', $cantidad_rma),
                    'success'
                );
                break;
            }
        }
        
        // Si el producto no está en el carrito, agregarlo con la cantidad correcta
        if (!$producto_encontrado) {
            $cart->add_to_cart($producto_rma_id, intval($cantidad_rma));
            wc_add_notice(
                sprintf('Se han agregado %d unidades del producto RMA al carrito.', $cantidad_rma),
                'success'
            );
        }
    }
    
    /**
     * Verificar que no se modifique la cantidad de productos RMA
     */
    public static function verificar_cantidad_rma($cart_item_key, $quantity, $old_quantity, $cart) {
        $cart_item = $cart->get_cart_item($cart_item_key);
        
        if (!$cart_item) {
            return;
        }
        
        // Verificar si hay un cupón RMA aplicado
        $applied_coupons = $cart->get_applied_coupons();
        
        foreach ($applied_coupons as $coupon_code) {
            if (strpos($coupon_code, 'rma-devolucion-') === 0) {
                $coupon = new WC_Coupon($coupon_code);
                $producto_rma_id = get_post_meta($coupon->get_id(), '_producto_rma_id', true);
                $cantidad_rma = get_post_meta($coupon->get_id(), '_cantidad_rma', true);
                
                // Si es el producto RMA, restaurar la cantidad correcta
                if ($cart_item['product_id'] == $producto_rma_id && $quantity != $cantidad_rma) {
                    $cart->set_quantity($cart_item_key, intval($cantidad_rma), false);
                    wc_add_notice(
                        sprintf('La cantidad del producto RMA debe ser %d unidades.', $cantidad_rma),
                        'notice'
                    );
                }
            }
        }
    }
}

// Inicializar
WC_Garantias_RMA_Cart::init();