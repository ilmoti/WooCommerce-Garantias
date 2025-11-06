/**
 * WooCommerce Garantías - Frontend AJAX
 * Funcionalidades modernas sin recargar página
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Variables globales
    const garantiasAjax = {
        
        /**
         * Inicializar todas las funcionalidades
         */
        init: function() {
            this.initAutocomplete();
            // this.initFormSubmit();  // DESACTIVADO - Conflicto con envío
            this.initStatusRefresh();
            this.initComments();
            this.initDashboard();
            this.initToastNotifications();
        },
        
        /**
         * Sistema de notificaciones toast
         */
        initToastNotifications: function() {
            if ($('#garantias-toast-container').length === 0) {
                $('body').append('<div id="garantias-toast-container" style="position:fixed;top:20px;right:20px;z-index:9999;"></div>');
            }
        },
        
        showToast: function(message, type = 'success') {
            const toast = $(`
                <div class="garantias-toast garantias-toast-${type}" style="
                    background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#17a2b8'};
                    color: white;
                    padding: 15px 20px;
                    margin-bottom: 10px;
                    border-radius: 5px;
                    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
                    opacity: 0;
                    transform: translateX(100%);
                    transition: all 0.3s ease;
                    max-width: 350px;
                    word-wrap: break-word;
                ">
                    ${message}
                </div>
            `);
            
            $('#garantias-toast-container').append(toast);
            
            // Animación de entrada
            setTimeout(() => {
                toast.css({opacity: 1, transform: 'translateX(0)'});
            }, 100);
            
            // Auto remove después de 5 segundos
            setTimeout(() => {
                toast.css({opacity: 0, transform: 'translateX(100%)'});
                setTimeout(() => toast.remove(), 300);
            }, 5000);
        },
        
        /**
         * Mejorar autocomplete de productos
         */
        initAutocomplete: function() {
                // Destruir autocompletes existentes que no tengan la funcionalidad completa
                $('.producto_autocomplete').each(function() {
                    const $this = $(this);
                    if ($this.data('ui-autocomplete') && !$this.data('garantias-initialized')) {
                        $this.autocomplete('destroy');
                    }
                });
                
                // Inicializar solo los campos que no estén marcados
                $('.producto_autocomplete:not([data-garantias-initialized])').each(function() {
                    const $input = $(this);
                    const $hiddenInput = $input.siblings('.producto_hidden');
                    
                    // Marcar como inicializado
                    $input.attr('data-garantias-initialized', 'true');
                    
                    $input.autocomplete({
                        minLength: 2,
                        source: function(request, response) {
                        // Usar el array local de productos en lugar de AJAX
                        if (typeof productos !== 'undefined' && productos.length > 0) {
                            var filtered = productos.filter(function(p) {
                                return p.label.toLowerCase().indexOf(request.term.toLowerCase()) > -1;
                            });
                            response(filtered);
                        } else {
                            // Fallback al AJAX si no hay productos locales
                            $.ajax({
                                url: wcGarantiasAjax.ajax_url,
                                type: 'POST',
                                dataType: 'json',
                                data: {
                                    action: 'wcgarantias_get_products',
                                    term: request.term,
                                    nonce: wcGarantiasAjax.nonce
                                },
                                beforeSend: function() {
                                    $input.addClass('loading');
                                },
                                success: function(data) {
                                    $input.removeClass('loading');
                                    if (data.success) {
                                        response(data.data);
                                    } else {
                                        garantiasAjax.showToast(data.data.message || 'Error en búsqueda', 'error');
                                        response([]);
                                    }
                                },
                                error: function() {
                                    $input.removeClass('loading');
                                    garantiasAjax.showToast('Error de conexión', 'error');
                                    response([]);
                                }
                            });
                        }
                    },
                    select: function(event, ui) {
                        event.preventDefault();
                        $input.val(ui.item.label);
                        $hiddenInput.val(ui.item.id);
                        
                        // Encontrar el campo de cantidad
                        const $cantidadInput = $input.closest('.producto-card').find('.cantidad-input');

                        // PRIMERO: Extraer cantidad del label
                        var maxQty = 1;
                        var match = ui.item.label.match(/\((\d+)\s+disponibles?\)/);
                        if (match && match[1]) {
                            maxQty = parseInt(match[1]);
                        }

                        // SEGUNDO: Actualizar el campo y su máximo
                        $cantidadInput.attr('max', maxQty).removeAttr('placeholder').val(1);

                        // TERCERO: Actualizar el label de cantidad
                        var $cantidadLabel = $cantidadInput.closest('div').find('label').first();
                        $cantidadLabel.html('📦 Cantidad');
                        
                        // CUARTO: Eliminar cualquier texto de mximo anterior
                        $cantidadInput.parent().find('.max-info').remove();
                        
                        // QUINTO: Agregar el texto del máximo debajo del campo
                        $cantidadInput.after('<div class="max-info" style="color: #6c757d; font-size: 12px; margin-top: 2px; text-align: center;">Máx: ' + maxQty + ' unidades</div>');
                        
                        // Buscar informacin de la orden más reciente

                        $.ajax({
                            url: wcGarantiasAjax.ajax_url,
                            method: 'POST',
                            data: {
                                action: 'get_order_info',
                                producto_id: ui.item.id,
                                nonce: wcGarantiasAjax.nonce
                            },
                            success: function(response) {

                                if (response.success && response.data) {
                                    const $card = $input.closest('.producto-card');
                                    $card.find('.order-number').val(response.data.order_id);
                                    $card.find('.order-date').val(response.data.order_date);
                                    garantiasAjax.showToast(`Orden #${response.data.order_id} encontrada`, 'info');
                                } else {
                                }
                            },
                            error: function(xhr, status, error) {

                            }
                        });
                        
                        return false;
                    },
                    focus: function(event, ui) {
                        event.preventDefault();
                        $input.val(ui.item.label);
                        return false;
                    }
                });
            });
        },
        
        /**
         * Envío de formulario con AJAX
         */
        initFormSubmit: function() {
            // DESACTIVADO TEMPORALMENTE - Conflicto con validación
            return;
            $('#garantiaForm').on('submit', function(e) {
                e.preventDefault();
                
                const $form = $(this);
                const $submitBtn = $form.find('button[type="submit"]');
                
                // Validar formulario
                if (!garantiasAjax.validateForm($form)) {
                    return false;
                }
                
                // Preparar datos
                const formData = new FormData();
                formData.append('action', 'wcgarantias_submit_claim');
                formData.append('nonce', wcGarantiasAjax.nonce);
                
                // Recopilar items
                const items = [];
                $form.find('.reclamo-row').each(function(index) {
                    const $row = $(this);
                    const productoId = $row.find('.producto_hidden').val();
                    
                    if (productoId) {
                        items.push({
                            producto_id: productoId,
                            cantidad: $row.find('.cantidad').val(),
                            motivo: $row.find('.motivo_select').val(),
                            motivo_otro: $row.find('.motivo_otro').val(),
                            order_id: $row.find('.producto_autocomplete').data('order-id') || 0
                        });
                        
                        // Agregar archivos si existen
                        const fotoFile = $row.find('input[name="foto[]"]')[0].files[0];
                        const videoFile = $row.find('input[name="video[]"]')[0].files[0];
                        
                        if (fotoFile) {
                            formData.append(`foto_${index}`, fotoFile);
                        }
                        if (videoFile) {
                            formData.append(`video_${index}`, videoFile);
                        }
                    }
                });
                
                formData.append('items', JSON.stringify(items));
                
                // Enviar
                $submitBtn.prop('disabled', true).text('Enviando...');
                
                $.ajax({
                    url: wcGarantiasAjax.ajax_url,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        if (response.success) {
                            garantiasAjax.showToast('¡Reclamo enviado correctamente!', 'success');
                            
                            // Mostrar código de garantía
                            const codigo = response.data.codigo_garantia;
                            garantiasAjax.showToast(`Código de seguimiento: ${codigo}`, 'info');
                            
                            // Limpiar formulario
                            $form[0].reset();
                            $form.find('.producto_hidden').val('');
                            $form.find('.maxqty-label').text('');
                            
                            // Recargar lista de reclamos
                            setTimeout(() => {
                                location.reload();
                            }, 2000);
                            
                        } else {
                            garantiasAjax.showToast(response.data.message || 'Error al enviar reclamo', 'error');
                        }
                    },
                    error: function() {
                        garantiasAjax.showToast('Error de conexión', 'error');
                    },
                    complete: function() {
                        $submitBtn.prop('disabled', false).text('ENVIAR RECLAMO');
                    }
                });
            });
        },
        
        /**
         * Validar formulario antes de enviar
         */
        validateForm: function($form) {
        let isValid = true;
        const errors = [];
        
        // CAMBIO: Buscar las tarjetas correctas (producto-card en vez de reclamo-row)
        const validRows = $form.find('.producto-card').filter(function() {
            var $hiddenInput = $(this).find('.producto_hidden');
            if ($hiddenInput.length === 0) {
                $hiddenInput = $(this).find('input[name="producto[]"]');
            }
            return $hiddenInput.val() !== '' && $hiddenInput.val() !== undefined;
        });
        
        if (validRows.length === 0) {
            errors.push('Debes seleccionar al menos un producto');
            isValid = false;
        }
            
            // Verificar cada fila válida
            validRows.each(function() {
                const $row = $(this);
                const motivo = $row.find('.motivo_select, select[name="motivo[]"]').val();
                const cantidad = parseInt($row.find('.cantidad, input[name="cantidad[]"]').val());
                const maxCantidad = parseInt($row.find('.cantidad').attr('max'));
                
                if (!motivo) {
                    errors.push('Debes seleccionar un motivo para cada producto');
                    isValid = false;
                }
                
                if (cantidad > maxCantidad) {
                    errors.push(`La cantidad no puede ser mayor a ${maxCantidad}`);
                    isValid = false;
                }
                
                // Verificar foto obligatoria
                const fotoFile = $row.find('input[name="foto[]"]')[0].files[0];
                if (!fotoFile) {
                    errors.push('La foto es obligatoria para cada producto');
                    isValid = false;
                }
            });
            
            // Mostrar errores
            if (!isValid) {
                errors.forEach(error => {
                    garantiasAjax.showToast(error, 'error');
                });
            }
            
            return isValid;
        },
        
        /**
         * Refrescar estado de garantías automáticamente
         */
        initStatusRefresh: function() {
            // Refrescar cada 30 segundos si estamos en la página de garantías
            if ($('.garantias-status-refresh').length > 0) {
                setInterval(() => {
                    garantiasAjax.refreshClaimStatuses();
                }, 30000);
            }
        },
        
        refreshClaimStatuses: function() {
            $('.garantia-row').each(function() {
                const $row = $(this);
                const garantiaId = $row.data('garantia-id');
                
                if (garantiaId) {
                    $.ajax({
                        url: wcGarantiasAjax.ajax_url,
                        type: 'POST',
                        data: {
                            action: 'wcgarantias_get_claim_status',
                            garantia_id: garantiaId,
                            nonce: wcGarantiasAjax.nonce
                        },
                        success: function(response) {
                            if (response.success) {
                                const $estadoCell = $row.find('.estado-cell');
                                const estadoActual = $estadoCell.text().trim();
                                const estadoNuevo = response.data.estado_nombre;
                                
                                if (estadoActual !== estadoNuevo) {
                                    $estadoCell.text(estadoNuevo).addClass('estado-actualizado');
                                    setTimeout(() => {
                                        $estadoCell.removeClass('estado-actualizado');
                                    }, 3000);
                                }
                            }
                        }
                    });
                }
            });
        },
        
        /**
         * Sistema de comentarios en tiempo real
         */
        initComments: function() {
            // Enviar comentario
            $(document).on('submit', '.comentario-form', function(e) {
                e.preventDefault();
                
                const $form = $(this);
                const $textarea = $form.find('textarea');
                const $submitBtn = $form.find('button[type="submit"]');
                const garantiaId = $form.data('garantia-id');
                const comentario = $textarea.val().trim();
                
                if (!comentario) {
                    garantiasAjax.showToast('Escribe un comentario', 'error');
                    return;
                }
                
                $submitBtn.prop('disabled', true).text('Enviando...');
                
                $.ajax({
                    url: wcGarantiasAjax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'wcgarantias_add_comment',
                        garantia_id: garantiaId,
                        comentario: comentario,
                        nonce: wcGarantiasAjax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            garantiasAjax.showToast('Comentario agregado', 'success');
                            $textarea.val('');
                            garantiasAjax.loadComments(garantiaId);
                        } else {
                            garantiasAjax.showToast(response.data.message || 'Error al agregar comentario', 'error');
                        }
                    },
                    error: function() {
                        garantiasAjax.showToast('Error de conexin', 'error');
                    },
                    complete: function() {
                        $submitBtn.prop('disabled', false).text('Enviar');
                    }
                });
            });
            
            // Cargar comentarios al abrir detalle
            $(document).on('click', '.ver-comentarios-btn', function() {
                const garantiaId = $(this).data('garantia-id');
                garantiasAjax.loadComments(garantiaId);
            });
        },
        
        loadComments: function(garantiaId) {
            const $container = $(`.comentarios-container[data-garantia-id="${garantiaId}"]`);
            
            $.ajax({
                url: wcGarantiasAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcgarantias_get_comments',
                    garantia_id: garantiaId,
                    nonce: wcGarantiasAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        garantiasAjax.renderComments($container, response.data.comentarios);
                    }
                }
            });
        },
        
        renderComments: function($container, comentarios) {
            $container.empty();
            
            if (comentarios.length === 0) {
                $container.html('<p><em>No hay comentarios aún.</em></p>');
                return;
            }
            
            comentarios.forEach(comentario => {
                const adminClass = comentario.es_admin ? 'comentario-admin' : 'comentario-cliente';
                const autorLabel = comentario.es_admin ? '👨‍💼 Admin' : ' Cliente';
                
                const comentarioHtml = `
                    <div class="comentario ${adminClass}" style="
                        margin-bottom: 15px;
                        padding: 12px;
                        border-left: 4px solid ${comentario.es_admin ? '#007cba' : '#28a745'};
                        background: ${comentario.es_admin ? '#f8f9fa' : '#f0f8f0'};
                        border-radius: 0 5px 5px 0;
                    ">
                        <div class="comentario-header" style="
                            display: flex;
                            justify-content: space-between;
                            margin-bottom: 8px;
                            font-size: 0.9em;
                            color: #666;
                        ">
                            <span><strong>${autorLabel} ${comentario.usuario_nombre}</strong></span>
                            <span>${comentario.fecha_legible}</span>
                        </div>
                        <div class="comentario-texto">${comentario.comentario}</div>
                    </div>
                `;
                
                $container.append(comentarioHtml);
            });
        },
        
        /**
         * Dashboard con estadsticas en tiempo real
         */
        initDashboard: function() {
            return;
            /*
            if ($('#garantias-dashboard').length > 0) {
                garantiasAjax.loadDashboardData();
                
                // Actualizar cada 60 segundos
                setInterval(() => {
                    garantiasAjax.loadDashboardData();
                }, 60000);
            }*/
        },
        
        loadDashboardData: function() {
            $.ajax({
                url: wcGarantiasAjax.ajax_url,
                type: 'POST',
                data: {
                    action: 'wcgarantias_get_dashboard_data',
                    nonce: wcGarantiasAjax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        garantiasAjax.renderDashboard(response.data);
                    }
                },
                error: function() {
                    // Fallar silenciosamente para no molestar al usuario
                }
            });
        },
        
        renderDashboard: function(data) {
            // Solo actualizar los nmeros, no reemplazar todo el HTML
            if ($('.garantias-stats').length > 0) {
                // Actualizar solo los números
                $('.garantias-stats .stat-card:eq(0) .stat-number').text(data.total_items || 0);
                $('.garantias-stats .stat-card:eq(1) .stat-number').text(data.items_pendientes || 0);
                $('.garantias-stats .stat-card:eq(2) .stat-number').text(data.items_aprobados || 0);
                $('.garantias-stats .stat-card:eq(3) .stat-number').text(data.items_rechazados || 0);
            } else {
                // Si no existe la estructura, crearla (fallback)
                const dashboardHtml = `
                    <h2 style="text-align: center; margin-bottom: 30px; color: #333;">MIS GARANTÍAS</h2>
                    
                    <div class="garantias-stats" style="
                        display: grid;
                        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                        gap: 20px;
                        margin-bottom: 30px;
                    ">
                        <div class="stat-card" style="...">
                            <div class="stat-number" style="...">${data.total_items || 0}</div>
                            <div class="stat-label">Total Items en Garanta</div>
                        </div>
                        
                        <div class="stat-card" style="...">
                            <div class="stat-number" style="...">${data.items_pendientes || 0}</div>
                            <div class="stat-label">Pendientes</div>
                        </div>
                        
                        <div class="stat-card" style="...">
                            <div class="stat-number" style="...">${data.items_aprobados || 0}</div>
                            <div class="stat-label">Aprobados</div>
                        </div>
                        
                        <div class="stat-card" style="...">
                            <div class="stat-number" style="...">${data.items_rechazados || 0}</div>
                            <div class="stat-label">Rechazados</div>
                        </div>
                    </div>
                `;
                
                $('#garantias-dashboard').html(dashboardHtml);
            }
        }
    };
    
    // CSS para animaciones
    const styles = `
        <style>
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .estado-actualizado {
            background-color: #fff3cd !important;
            transition: background-color 0.3s ease;
        }
        
        .producto_autocomplete.loading {
            background-image: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAiIGhlaWdodD0iMjAiIHZpZXdCb3g9IjAgMCAyMCAyMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KICAgIDxjaXJjbGUgY3g9IjEwIiBjeT0iMTAiIHI9IjMiIGZpbGw9Im5vbmUiIHN0cm9rZT0iIzk5OSIgc3Ryb2tlLXdpZHRoPSIyIj4KICAgICAgICA8YW5pbWF0ZSBhdHRyaWJ1dGVOYW1lPSJyIiB2YWx1ZXM9IjM7NjszIiBkdXI9IjFzIiByZXBlYXRDb3VudD0iaW5kZWZpbml0ZSIvPgogICAgPC9jaXJjbGU+Cjwvc3ZnPg==');
            background-repeat: no-repeat;
            background-position: right 10px center;
            background-size: 20px 20px;
        }
        
        /* Responsivo */
        @media (max-width: 768px) {
            .garantias-dashboard-stats {
                grid-template-columns: repeat(2, 1fr) !important;
                gap: 15px !important;
            }
            
            .stat-card {
                padding: 15px !important;
            }
            
            .stat-number {
                font-size: 2em !important;
            }
        }
        
        @media (max-width: 480px) {
            .garantias-dashboard-stats {
                grid-template-columns: 1fr !important;
            }
        }
        </style>
    `;
    
    $('head').append(styles);
    
    // Inicializar cuando el DOM esté listo
    garantiasAjax.init();
});