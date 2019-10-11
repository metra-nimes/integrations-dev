!function() {
	/*

	javascript:setTimeout(function(){var s=document.createElement("script");s.src="https://app.convertful.com/assets/js/base/debug.js?v=1";document.getElementsByTagName("head")[0].appendChild(s);},1);void(0);

	*/
	if (Convertful === undefined) {
		alert('Plugin not detected!');
		return;
	}
	function cfGetCookie(name) {
		var matches = document.cookie.match(new RegExp(
			"(?:^|; )" + name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g, '\\$1') + "=([^;]*)"
		));
		return matches ? decodeURIComponent(matches[1]) : undefined;
	}

	var cfProblem = (Convertful.ver) ? 'Version: ' + Convertful.ver : 'Very old script!';
		cfProblem += "\n\n" + navigator.userAgent + "\n\n";

	var $cfScript = window.cQuery('script#convertful-api, script#optinguru-api, script#optin-api'),
		cfAjaxUrl = $cfScript.attr('src').split('/').slice(0, -1).join('/'),
		cfOowner = +$cfScript.data('owner');

	window.cQuery.ajax({
		url: cfAjaxUrl + '/api/widget/export',
		data: {
			owner: cfOowner,
			domain: location.host
		},
		success: function (r) {
			['lastEvents', 'session'].forEach(function (key) {
				cfProblem += 'Cookie ogr_' + key + ': ' + cfGetCookie('ogr_' + key) + "\n\n";
				cfProblem += 'localStorage ogr_' + key + ': ' + localStorage.getItem('ogr_' + key)  + "\n\n";
				cfProblem += 'Cookie conv_' + key + ': ' + cfGetCookie('conv_' + key) + "\n\n";
				cfProblem += 'localStorage conv_' + key + ': ' + localStorage.getItem('conv_' + key)  + "\n\n";
			});

			cfProblem += JSON.stringify(r, undefined, 4);

			window.cQuery('body')
				.append('<div style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; z-index: 600000; background:rgba(255,255,255,0.9);">' +
					'<textarea onclick="this.focus();this.select()" readonly style="position: fixed; top: 50%; left: 50%; width: 80%; height: 80%; z-index: 600000; resize: vertical; -webkit-transform: translate(-50%, -50%); transform: translate(-50%, -50%);">' + cfProblem + '</textarea>' +
					'</div>');
		},
		allowCookies: 1
	});

	// if (!Convertful.ver) {
	// 	Convertful.reset();
	// }

}();
