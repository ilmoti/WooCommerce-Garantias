jQuery(function($){
  $("#producto").autocomplete({
    source: function(request, response) {
       $.ajax({
        url: wcGarantiasAjax.ajax_url,
        dataType: "json",
        data: {
          action: "wcgarantias_get_products",
          term: request.term,
          nonce: wcGarantiasAjax.nonce
        },
        success: function(data) {
          response($.map(data, function(item) {
            return {
              label: item.label,
              value: item.label,
              id:    item.id
            };
          }));
        }
      });
    },
    minLength: 2,
    select: function(event, ui) {
      $(this).data('product-id', ui.item.id);
    }
  });
});
