(function( $ ) {
	'use strict';

	$(function() {
		var $logContent = $('#h5p-log-content');
		var $logStatus  = $('#h5p-log-status');
		var $clearBtn   = $('#h5p-clear-logs');
		var pollTimer   = null;

		if ( $logContent.length ) {
			fetchLogs();
			pollTimer = setInterval(fetchLogs, 3000);
		}

		function fetchLogs() {
			$.post( h5pTranslatorAdmin.ajax_url, {
				action: 'h5p_wpml_fetch_logs',
				nonce:  h5pTranslatorAdmin.nonce
			}, function(response) {
				if ( response.success ) {
					var wasAtBottom = isScrolledToBottom($logContent[0]);
					$logContent.val(response.data.logs);
					if ( wasAtBottom ) {
						scrollToBottom($logContent[0]);
					}
					var now = new Date();
					$logStatus.text('Last updated: ' + now.toLocaleTimeString());
				}
			});
		}

		$clearBtn.on('click', function(e) {
			e.preventDefault();
			if ( ! confirm('Are you sure you want to clear all logs?') ) {
				return;
			}

			$.post( h5pTranslatorAdmin.ajax_url, {
				action: 'h5p_wpml_clear_logs',
				nonce:  h5pTranslatorAdmin.nonce
			}, function(response) {
				if ( response.success ) {
					$logContent.val('');
					$logStatus.text('Logs cleared.');
				}
			});
		});

		function isScrolledToBottom(el) {
			return el.scrollHeight - el.clientHeight <= el.scrollTop + 1;
		}

		function scrollToBottom(el) {
			el.scrollTop = el.scrollHeight;
		}
	});

})( jQuery );
