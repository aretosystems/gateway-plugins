jQuery(function($) {
	$(document).ready(function() {
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
				if (result.hasOwnProperty('is_areto_quickpay')) {
					// Generate inputs
					var ignore = ['result', 'redirect', 'is_areto_quickpay'];
					var inputs = '';
					$.each(result.params, function( index, value ) {
						if (ignore.indexOf(index) !== -1) {
							inputs += '<input type="hidden" name="' + index + '" value="' + value + '" />';
						}
					});

					setTimeout(function() {
						// Generate form
						$('<form>', {
							'id': 'areto_checkout',
							'action': 'https://pay.aretosystems.com/pwall/Api/v1/PaymentWall/QuickPay',
							'method': 'POST',
							'html': inputs
						}).appendTo(document.body).submit();
					}, 1000);
				}
			}
		});
	});
});
