jQuery(function($) {
	$(document).ready(function() {
		/* Checkout Form */
		jQuery('form.checkout').on('checkout_place_order_areto', function( event ) {
			event.preventDefault();

			var card_number = $('#areto-card-number').val();
			var card_type = $.payment.cardType(card_number);

			// insert card type into the form so it gets submitted to the server
			var $form = jQuery("form.checkout, form#order_review");
			if ($form.find("[name='areto-card-type']").length === 0) {
				$form.append("<input type='hidden' name='areto-card-type' value='" + card_type + "'/>");
			} else {
				$form.find("[name='areto-card-type']").val(card_type);
			}
		});
	});

	$(document).ajaxComplete(function (event, xhr, settings) {
		if (typeof wc_checkout_params !== 'undefined' && settings.url === wc_checkout_params.checkout_url) {
			var code = xhr.responseText;
			// Get the valid JSON only from the returned string
			if (code.indexOf('<!--WC_START-->') >= 0)
				code = code.split('<!--WC_START-->')[1]; // Strip off before after WC_START

			if (code.indexOf('<!--WC_END-->') >= 0)
				code = code.split('<!--WC_END-->')[0]; // Strip off anything after WC_END

			// Parse
			var result = $.parseJSON(code);
			console.log(result);
			if (result.hasOwnProperty('is_areto')) {
				// Generate inputs
				var inputs = '';
				$.each(result.params, function( index, value ) {
					inputs += '<input type="hidden" name="' + index + '" value="' + value + '" />';
				});

				setTimeout(function() {
					// Generate form
					$('<form>', {
						'id': 'areto_checkout',
						'action': result.url,
						'method': result.method,
						'html': inputs
					}).appendTo(document.body).submit();
				}, 1000);
			}
		}
	});
})
