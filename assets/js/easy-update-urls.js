jQuery(document).ready(function (jQuery) {
	// Substituímos o '*' pelo ID específico do botão para melhor performance.
	jQuery('#easy-update-urls-run-update').on('click', function (event) {
		var idname = event.target.id;

		if (idname === 'easy-update-urls-run-update') {
			event.stopPropagation();
			event.preventDefault();

			console.log('id ' + idname);

			jQuery('#easy-update-urls-spinner').show();
			jQuery('#easy-update-urls-help-run').hide();
			jQuery('#easy-update-urls-spinner').css("display", "block");
			jQuery('#easy-update-urls-run-update').prop("disabled", true);

			setTimeout(function () {
				document.getElementById("easy-update-urls-form-run").submit();
			}, 5000);
		}
	});

	jQuery(document).ajaxStart(function () {
		jQuery("#easy-update-urls-spinner-help2").show();
	});

	// Removidos os parâmetros não utilizados (event, request, settings).
	jQuery(document).ajaxComplete(function () {
		jQuery("#easy-update-urls-spinner-help2").hide();
	});
}); // end jQuery ready.
