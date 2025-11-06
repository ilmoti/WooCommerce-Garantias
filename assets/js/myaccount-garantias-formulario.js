/**
 * Script principal del formulario de garantías
 * Maneja toda la lógica de pestañas, autocomplete y validaciones
 */

// Estado global
    let tabCount = 0;
    let activeTab = 1;

function initGarantiasForm() {
    const $ = jQuery;
    const config = window.garantiasFormConfig;

    // Limpiar cualquier contenido previo
    $('.tabs-navigation').empty();
    $('#productos-container').empty();
    
    /**
     * Inicializa el autocomplete en un input específico
     * @param {jQuery} $input - El input donde inicializar
     */
    function setupAutocomplete($input) {
        // Destruir autocomplete existente si existe
        if ($input.data('ui-autocomplete')) {
            $input.autocomplete('destroy');
        }
        
        // Inicializar autocomplete
        $input.autocomplete({
            minLength: 1,
            source: function(request, response) {
                const busqueda = request.term.toLowerCase().trim();
                const filtered = config.productos.filter(function(p) {
                    return p.label.toLowerCase().indexOf(busqueda) > -1;
                });
                response(filtered);
            },
            select: function(event, ui) {
                event.preventDefault();
                
                const $card = $(this).closest('.producto-card');
                const $hiddenInput = $card.find('.producto_hidden');
                
                // Establecer valores
                $(this).val(ui.item.label);
                $hiddenInput.val(ui.item.value);
                
                // Calcular cantidad máxima disponible
                let maxQty = ui.item.maxqty || 1;
                
                // Verificar cantidades ya usadas en otras pestañas
                const cantidadUsada = calcularCantidadUsada(ui.item.value, $card);
                const disponible = Math.max(0, maxQty - cantidadUsada);
                
                // Actualizar límite de cantidad
                const $cantidadInput = $card.find('.cantidad-input');
                $cantidadInput.attr('max', disponible);
                $cantidadInput.val(Math.min(1, disponible));
                
                // Mostrar información
                actualizarInfoCantidad($cantidadInput, disponible, cantidadUsada);
                
                // Buscar información de orden
                buscarInfoOrden(ui.item.value, $card);
                
                return false;
            }
        });
    }
    
    /**
     * Calcula cuántas unidades de un producto ya estn en uso
     */
    function calcularCantidadUsada(productoId, $currentCard) {
        let total = 0;
        $('.producto-card').each(function() {
            if ($(this).is($currentCard)) return;
            
            const $hiddenInput = $(this).find('.producto_hidden');
            if ($hiddenInput.val() == productoId) {
                const cantidad = parseInt($(this).find('.cantidad-input').val()) || 0;
                total += cantidad;
            }
        });
        return total;
    }
    
    /**
     * Actualiza la informacin de cantidad disponible
     */
    function actualizarInfoCantidad($input, disponible, usada) {
        $input.parent().find('.max-info').remove();
        
        let infoHtml = `<div class="max-info" style="color: #6c757d; font-size: 12px; margin-top: 2px; text-align: center;">
            Máx: ${disponible} unidades`;
        
        if (usada > 0) {
            infoHtml += `<br><small style="color: #dc3545;">(Ya agregaste ${usada} en otras pestañas)</small>`;
        }
        
        infoHtml += '</div>';
        $input.after(infoHtml);
    }
    
    /**
     * Busca información de la orden via AJAX
     */
    function buscarInfoOrden(productoId, $card) {
        $.ajax({
            url: config.ajaxUrl,
            method: 'POST',
            data: {
                action: 'get_order_info',
                producto_id: productoId,
                nonce: config.nonce
            },
            success: function(response) {
                if (response.success && response.data) {
                    $card.find('.order-number').val(response.data.order_id);
                    $card.find('.order-date').val(response.data.order_date);
                }
            }
        });
    }
    
    /**
     * Crea una nueva pestaña/tarjeta
     */
    function agregarNuevaPestana() {
        tabCount++;
        
        // Obtener template y reemplazar variables
        let template = $('#template-producto-card').html();
        template = template.replace(/{{TAB_NUMBER}}/g, tabCount);
        
        // Agregar al contenedor
        $('#productos-container').append(template);
        
        // Crear botón de pestaña
        const $tabButton = $(`
            <button type="button" class="tab-button" data-tab="${tabCount}">
                <span class="tab-number">${tabCount}</span>
                Producto ${tabCount}
                <div class="tab-indicator"></div>
            </button>
        `);
        
        $('.tabs-navigation').append($tabButton);
        
        // Activar nueva pestaña
        cambiarPestana(tabCount);
        
        // Inicializar componentes
        const $nuevaCard = $(`.producto-card[data-tab="${tabCount}"]`);
        setupAutocomplete($nuevaCard.find('.producto_autocomplete'));
        inicializarEventosCard($nuevaCard);
        
        // Mostrar botones de eliminar si hay más de una pestaña
        actualizarBotonesEliminar();
        
        // Enfocar el campo de búsqueda
        $nuevaCard.find('.producto_autocomplete').focus();
    }
    
    /**
     * Cambia a una pestaa específica
     */
    function cambiarPestana(numero) {
        activeTab = numero;
        
        // Actualizar contenido
        $('.tab-content').removeClass('active');
        $(`.tab-content[data-tab="${numero}"]`).addClass('active');
        
        // Actualizar botones
        $('.tab-button').removeClass('active');
        $(`.tab-button[data-tab="${numero}"]`).addClass('active');
    }
    
    /**
     * Elimina una pestaa
     */
    function eliminarPestana($card) {
        const tabNumber = $card.data('tab');
        
        // Eliminar tarjeta y botón
        $card.remove();
        $(`.tab-button[data-tab="${tabNumber}"]`).remove();
        
        // Si era la pestaña activa, cambiar a la primera
        if (tabNumber == activeTab) {
            const $firstTab = $('.tab-button').first();
            if ($firstTab.length) {
                cambiarPestana($firstTab.data('tab'));
            }
        }
        
        // Renumerar pestañas
        renumerarPestanas();
        
        // Actualizar botones de eliminar
        actualizarBotonesEliminar();
    }
    
    /**
     * Renumera todas las pestañas después de eliminar una
     */
    function renumerarPestanas() {
        $('.tab-button').each(function(index) {
            const newNumber = index + 1;
            const oldTab = $(this).data('tab');
            
            // Actualizar botón
            $(this).data('tab', newNumber);
            $(this).attr('data-tab', newNumber);
            $(this).find('.tab-number').text(newNumber);
            $(this).html($(this).html().replace(/Producto \d+/, `Producto ${newNumber}`));
            
            // Actualizar tarjeta
            $(`.producto-card[data-tab="${oldTab}"]`).attr('data-tab', newNumber);
        });
        
        tabCount = $('.tab-button').length;
    }
    
    /**
     * Muestra/oculta botones de eliminar según cantidad de pestañas
     */
    function actualizarBotonesEliminar() {
        if ($('.producto-card').length > 1) {
            $('.remove-producto').css('display', 'flex');
        } else {
            $('.remove-producto').hide();
        }
    }
    
    /**
     * Inicializa eventos de una tarjeta
     */
    function inicializarEventosCard($card) {
        // Cambio de motivo
        $card.find('.motivo_select').on('change', function() {
            const $otroInput = $card.find('.motivo_otro');
            if ($(this).val() === 'Otro') {
                $otroInput.slideDown(200).prop('required', true);
            } else {
                $otroInput.slideUp(200).prop('required', false);
            }
        });
        
        // Validación de cantidad mxima
        $card.find('.cantidad-input').on('input', function() {
            const max = parseInt($(this).attr('max')) || 999;
            const val = parseInt($(this).val()) || 0;
            if (val > max) {
                $(this).val(max);
            }
        });
        
        
        
        // Actualizar estado de archivo
        $card.find('.file-input').on('change', function() {
            const fileName = this.files[0] ? this.files[0].name : 'Arrastra o haz clic para seleccionar';
            $(this).siblings('.file-status').text(fileName);
        });
    }
    
    /**
     * Valida el formulario antes de enviar
     */
    function validarFormulario() {
        let valid = true;
        let tarjetasSinProducto = [];
        
        $('.producto-card').each(function() {
            const $card = $(this);
            const tabNumber = $card.data('tab');
            const productoId = $card.find('.producto_hidden').val();
            
            if (!productoId || productoId === '' || productoId === '0') {
                tarjetasSinProducto.push(tabNumber);
                $card.css('border-color', '#dc3545');
                $card.find('.producto_autocomplete').css('border-color', '#dc3545');
                valid = false;
            } else {
                $card.css('border-color', '#f1f3f4');
                $card.find('.producto_autocomplete').css('border-color', '#e9ecef');
            }
        });
        
        if (!valid) {
            const mensaje = tarjetasSinProducto.length === 1 
                ? `El Producto ${tarjetasSinProducto[0]} no tiene un producto seleccionado.`
                : `Los Productos ${tarjetasSinProducto.join(', ')} no tienen productos seleccionados.`;
            
            alert(mensaje + '\n\nDebes seleccionar productos o eliminar las tarjetas vacías.');
            cambiarPestana(tarjetasSinProducto[0]);
        }
        
        return valid;
    }
    
    function init() {

        // Limpiar contenido previo y REINICIAR contadores
        $('.tabs-navigation').empty();
        $('#productos-container').empty();
        tabCount = 0;  // Reiniciar a 0
        activeTab = 1; // Reiniciar a 1
        
        // Crear primera tarjeta
        agregarNuevaPestana();
        
        // Event handlers globales
        $('#add-producto-btn').on('click', agregarNuevaPestana);
        
        // Agregar este console.log ANTES del evento click
        console.log('4. Buscando botón Nuevo Reclamo:', $('a[href="#garantiaForm"]').length);
        
        $(document).on('click', '.tab-button', function() {
            cambiarPestana($(this).data('tab'));
        });
        
        $(document).on('click', '.remove-producto', function() {
            if ($('.producto-card').length > 1) {
                eliminarPestana($(this).closest('.producto-card'));
            }
        });
        
        $('#garantiaForm').on('submit', function(e) {
            if (!validarFormulario()) {
                e.preventDefault();
                return false;
            }
        });
        
        // Toggle del formulario - Usar evento delegado
        $(document).on('click', 'a[href="#garantiaForm"]', function(e) {
            e.preventDefault();
            const $container = $('#garantiaFormContainer');
            $container.slideToggle(400);
            if ($container.is(':visible')) {
                setTimeout(() => {
                    $('html, body').animate({
                        scrollTop: $container.offset().top - 100
                    }, 800);
                }, 450);
            }
        });
    }
    
    // Iniciar cuando el DOM esté listo
    init();
}

// Hacer la función disponible globalmente
window.initGarantiasForm = initGarantiasForm;

// Asegurar que jQuery esté disponible
if (typeof jQuery !== 'undefined') {
    jQuery(document).ready(function() {
        if (typeof window.garantiasFormConfig !== 'undefined') {
            console.log('Ejecutando initGarantiasForm automáticamente');
            initGarantiasForm();
        }
    });
}