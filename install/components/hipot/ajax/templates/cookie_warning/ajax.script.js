$(function(){
	if (document.cookie.indexOf('COOKIE_WARNING_READ') < 0) {
		$('.js-cookie').show().fadeTo(0, 0.9).on('mouseenter', function (){
			$(this).fadeTo(100, 1);
		}).on('mouseleave', function (){
			$(this).fadeTo(100, 0.9);
		});

		$('.js-cookie-close').on('click', function  () {
			let lang = $(this).data('lang');
			BX.ajax.runComponentAction('mgu:ajax', 'setCookieWarningRead', {
				mode: 'class',
				data: {
					'value' : 'Y',
					'lang'  : lang,
				},
				method: 'GET'
			}).then(function(response){
				if (response.status === 'success') {
				} else {
					console.log(response);
				}
			});
			$('.js-cookie').off('mouseleave').hide();
		});
	} else {
		$('.js-cookie').remove();
	}
});