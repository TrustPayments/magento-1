/**
 * Trust Payments Magento 1
 *
 * This Magento extension enables to process payments with Trust Payments (https://www.trustpayments.com//).
 *
 * @package TrustPayments_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
if (typeof MageTrustPayments == 'undefined') {
	var MageTrustPayments = {};
}

MageTrustPayments.Checkout = {
	paymentPageUrl: null,
	informationUrl: null,
	paymentMethods: {},
	type: null,
	checkoutHandlerIdentifier: 'IframeCheckoutHandler',

	initialize: function() {
		new this.type();
	},

	registerMethod: function(code, configurationId, container) {
		this.paymentMethods[code] = {
			configurationId: configurationId,
			container: container,
			handler: null,
			submitDisabled: false
		};
	},
	
	fetchInformation: function(callback) {
		if(!this.informationUrl) {
			return;
		}
		new Ajax.Request(
            this.informationUrl,
            {
                method: 'get',
                onSuccess: (function(transport) {
            		if (transport) {
            			var response = this.parseResponse(transport),
            				handlerIdentifier = 'IframeCheckoutHandler' + response.transactionId;
            			
            			if (response.paymentPageUrl) {
            				this.paymentPageUrl = response.paymentPageUrl;
            			}
            			
            			if (response.javascriptUrl) {
            				if (typeof window[handlerIdentifier] == 'undefined') {
	            				this.checkoutHandlerIdentifier = handlerIdentifier;
	            				this.loadJS(response.javascriptUrl + '&className=' + this.checkoutHandlerIdentifier, callback);
	            			} else {
	            				callback();
	            			}
            			}
            		}
            	}).bind(this),
                onFailure: checkout.ajaxFailure.bind(checkout)
            }
        );
	},
	
	loadJS: function(src, cb, ordered) {
		"use strict";
		var tmp;
		var ref = window.document.getElementsByTagName( "script" )[ 0 ];
		var script = window.document.createElement( "script" );

		if (typeof(cb) === 'boolean') {
			tmp = ordered;
			ordered = cb;
			cb = tmp;
		}

		script.src = src;
		script.async = !ordered;
		ref.parentNode.insertBefore( script, ref );

		if (cb && typeof(cb) === "function") {
			script.onload = cb;
		}
		return script;
	},
	
	parseResponse: function(transport) {
		try {
			return transport.responseJSON || transport.responseText.evalJSON(true) || {};
		} catch (e) {
			return {};
		}
	}
};

MageTrustPayments.Checkout.Type = Class.create({
	isSupportedPaymentMethod: function(code) {
		return code && code.startsWith('trustpayments_payment_');
	},

	getPaymentMethod: function(code) {
		return MageTrustPayments.Checkout.paymentMethods[code];
	},

	createHandler: function(code, onStart, onValidation, onDone, onEnableSubmit, onDisableSubmit) {
		if (typeof window[MageTrustPayments.Checkout.checkoutHandlerIdentifier] == 'undefined') {
			return;
		}

		if (this.isSupportedPaymentMethod(code) && !this.getPaymentMethod(code).handler) {
			if (typeof onStart == 'function') {
				onStart();
			}

			this.getPaymentMethod(code).handler = window[MageTrustPayments.Checkout.checkoutHandlerIdentifier](this.getPaymentMethod(code).configurationId);
			this.getPaymentMethod(code).handler.setResetPrimaryActionCallback(function(){
				this.getPaymentMethod(code).submitDisabled = false;
				onEnableSubmit();
			}.bind(this));
			this.getPaymentMethod(code).handler.setReplacePrimaryActionCallback(function(){
				this.getPaymentMethod(code).submitDisabled = true;
				onDisableSubmit();
			}.bind(this));
			this.getPaymentMethod(code).handler.create(
				this.getPaymentMethod(code).container,
				function(validationResult) {
					if (typeof onValidation == 'function') {
						onValidation(validationResult);
					}
				}.bind(this),
				function() {
					if (typeof onDone == 'function') {
						onDone();
					}
				}
			);
		} else if (this.isSupportedPaymentMethod(code)) {
			if (this.getPaymentMethod(code).submitDisabled) {
				onDisableSubmit();
			} else {
				onEnableSubmit();
			}
		} else {
			onEnableSubmit();
		}
	},

	parseResponse: function(transport) {
		return MageTrustPayments.Checkout.parseResponse(transport);
	},

	formatErrorMessages: function(messages) {
		var formattedMessage;
		if (typeof(messages) == 'object') {
			formattedMessage = messages.join("\n");
		} else if (Object.isArray(messages)) {
			formattedMessage = messages.join("\n").stripTags().toString();
		} else {
			formattedMessage = messages
		}

		return formattedMessage;
	}
});

document.observe(
	'dom:loaded',
	function() {
		MageTrustPayments.Checkout.initialize();
	}
);