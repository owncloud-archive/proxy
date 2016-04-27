(function(OCA) {
	OCA.Connect = OCA.Connect || {};

	/**
	 * @namespace OCA.Connect.Settings
	 */
	OCA.Connect.Settings = {
		stopProcess: function(provider) {
			// FIXME: Send Provider
			$.post(OC.generateUrl('/apps/proxy/api/stop'), {providerClass: provider}, function(response) {
			}).success(function(response) {
				location.reload();
			}).fail(function(response) {
				OC.Notification.showTemporary('Service has been stopped.');
			});
		},
		startProcess: function(provider) {
			// FIXME: Send Provider
			$.post(OC.generateUrl('/apps/proxy/api/start'), {providerClass: provider}, function(response) {
			}).success(function(response) {
				location.reload();
			}).fail(function(response) {
				OC.Notification.showTemporary('Failed to start the process');
			});
		}
	};

	OCA.Connect.Login = {
		showSpinner: function() {
			$('.oca_proxy_login_submit').addClass('hidden');
			$('.oca_proxy_login_spinner').removeClass('hidden');
			$('.oca_proxy_login_existing input').prop('disabled', true);
		},
		hideSpinner: function() {
			$('.oca_proxy_login_submit').removeClass('hidden');
			$('.oca_proxy_login_spinner').addClass('hidden');
			$('.oca_proxy_login_existing input').prop('disabled', false);
		},
		tryLogin: function(data) {
			OCA.Connect.Login.showSpinner();
			$.post(OC.generateUrl('/apps/proxy/api/login'), data, function(response) {
			}).success(function(response) {
				window.location.replace(response.domain+window.location.pathname);
			}).fail(function(response) {
				OCA.Connect.Login.hideSpinner();
				OC.Notification.showTemporary(response.responseJSON);
			});
		}
	};

	OCA.Connect.Register = {
		hideForm: function() {
			$('.oca_proxy_register').addClass('hidden');
			$('.oca_proxy_registration_successful').removeClass('hidden');
		},
		tryRegister: function(data) {
			$('.oca_proxy_register input').attr('disabled', 'disabled');
			$('.oca_proxy_register_spinner').removeClass('hidden');
			$('.oca_proxy_register_submit').addClass('hidden');
			$.post(OC.generateUrl('/apps/proxy/api/register'), data, function(response) {
			}).success(function(response) {
				OCA.Connect.Register.hideForm();
			}).fail(function(response) {
				$('.oca_proxy_register_spinner').addClass('hidden');
				$('.oca_proxy_register_submit').removeClass('hidden');
				$('.oca_proxy_register input').prop('disabled', false);
				OC.Notification.showTemporary(response.responseJSON);
			});
		}
	};

})(OCA);

$(function() {
	var chosenProvider;

	// Step 1: Choose a provider
	$("#oca-proxy-step1 button").click(function(e) {
		e.preventDefault();
		chosenProvider = e.target.value;

		// Hide current view
		$("#oca-proxy-step1").addClass('hidden');
		$("#oca-proxy-step2-"+chosenProvider).removeClass('hidden');

		// Step 2: Dependency check
		$("#oca-proxy-step2-"+chosenProvider+" button").click(function(e) {
			$("#oca-proxy-step2-"+chosenProvider).addClass('hidden');
			$("#oca-proxy-step3-"+chosenProvider).removeClass('hidden');
		});

	});

	$(".oca_proxy_stop").click(function(e) {
		e.preventDefault();
		OCA.Connect.Settings.stopProcess($("#oca-proxy-active-provider-class").val());
	});
	$(".oca_proxy_restart").click(function(e) {
		$("#oca-connect-check-connection").css('display', 'inline');
		$("#oca-proxy-connection-working").css('display', 'none');
		$("#oca-proxy-no-connection").css('display', 'none');

		e.preventDefault();
		$(this).attr('disabled', 'disabled');
		OCA.Connect.Settings.startProcess($("#oca-proxy-active-provider-class").val());
	});

	$(".oca_proxy_register").submit(function(e) {
		e.preventDefault();
		var data = $(this).closest('form').serializeArray();
		OCA.Connect.Register.tryRegister(data);
	});

	$(".oca_proxy_login_existing").submit(function(e) {
		e.preventDefault();
		var data = $(this).serializeArray();
		OCA.Connect.Login.tryLogin(data);
	});

	$("#oca-proxy-technical-details-title").click(function(e) {
		$("#oca-proxy-technical-details").slideDown();
	});

	// Verify connection state
	$.get(OC.generateUrl('/apps/proxy/api/state'), function(response) {
	}).success(function(response) {
		$("#oca-connect-check-connection").css('display', 'none');
		$("#oca-proxy-connection-working").css('display', 'inline');
		$("#oca-proxy-domain-address-replacement").html("<a href=\""+response+"\">"+response+"</a>");
	}).fail(function(response) {
		$("#oca-connect-check-connection").css('display', 'none');
		$("#oca-proxy-no-connection").css('display', 'inline');
	});
});
