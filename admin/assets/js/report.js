/**
 * Site Checkup Pro Client Report Helper
 *
 * @package SiteCheckupPro
 * @version 1.0.0
 */

(function () {
	'use strict';

	document.addEventListener('DOMContentLoaded', function () {
		// Auto print trigger if query param ?print=1
		const urlParams = new URLSearchParams(window.location.search);
		if (urlParams.get('print') === '1') {
			setTimeout(function () {
				window.print();
			}, 600);
		}
	});
})();
