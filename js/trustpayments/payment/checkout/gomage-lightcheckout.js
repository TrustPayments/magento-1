/**
 * Trust Payments Magento 1
 *
 * This Magento extension enables to process payments with Trust Payments (https://www.trustpayments.com//).
 *
 * @package TrustPayments_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
MageTrustPayments.Checkout.Type.GoMageLightCheckout = Class.create(
	MageTrustPayments.Checkout.Type, {
		originalSaveOrder: function() {},
		inObservePaymentMethods: false,

		initialize: function() {
			Lightcheckout.prototype.observePaymentMethods = Lightcheckout.prototype.observePaymentMethods.wrap(this.observePaymentMethods.bind(this));

			paymentForm.prototype.switchMethod = paymentForm.prototype.switchMethod.wrap(this.switchMethod.bind(this));
			this.inObservePaymentMethods = true;
			this.switchMethod(function() {}, payment.currentMethod);
			this.inObservePaymentMethods = false;

			this.originalSaveOrder = Lightcheckout.prototype.saveorder.bind(checkout);
			Lightcheckout.prototype.saveorder = Lightcheckout.prototype.saveorder.wrap(this.saveorder.bind(this));
		},

		disableSubmitButton: function() {
			$('submit-btn').disabled = 'disabled';
			$('submit-btn').addClassName('disabled');
		},

		enableSubmitButton: function() {
			var loadInfo = $$('div.gcheckout-onepage-wrap .loadinfo');
			if (loadInfo && loadInfo[0]) {
				loadInfo[0].parentNode.removeChild(loadInfo[0]);
			}

			$('submit-btn').disabled = false;
			$('submit-btn').removeClassName('disabled');
		},

		observePaymentMethods: function(callOriginal) {
			this.inObservePaymentMethods = true;
			callOriginal();
			this.inObservePaymentMethods = false;
		},

		/**
		 * Initializes the payment iframe when the customer switches the payment method.
		 */
		switchMethod: function(callOriginal, method) {
			callOriginal(method);
			if (this.inObservePaymentMethods) {
				this.createHandler(
					method,
					function() {
						this.disableSubmitButton();
					}.bind(this),
					function(validationResult) {
						if (validationResult.success) {
							this.createOrder();
						} else {
							this.enableSubmitButton();
						}
					}.bind(this),
					function() {
						this.enableSubmitButton();
					}.bind(this),
					function() {
						this.enableSubmitButton();
					}.bind(this),
					function() {
						this.disableSubmitButton();
					}.bind(this)
				);
			}
		},

		saveorder: function(callOriginal) {
			if (this.isSupportedPaymentMethod(payment.currentMethod) && this.getPaymentMethod(payment.currentMethod).handler) {
				this.getPaymentMethod(payment.currentMethod).handler.validate();
			} else {
				callOriginal();
			}
		},

		createOrder: function() {
			new Ajax.Request(
				checkout.save_order_url, {
					method: 'post',
					parameters: checkout.getFormData(),
					onSuccess: this.onOrderCreated.bind(this),
					onFailure: function() {
						this.enableSubmitButton();
					}.bind(this)
				}
			);
		},

		onOrderCreated: function(transport) {
			if (transport) {
				var response = this.parseResponse(transport);

				if (response.redirect) {
					if (this.getPaymentMethod(payment.currentMethod).handler) {
						this.getPaymentMethod(payment.currentMethod).handler.submit();
					} else {
						location.href = MageTrustPayments.Checkout.paymentPageUrl + '&paymentMethodConfigurationId=' + this.getPaymentMethod(payment.currentMethod).configurationId;
					}
					return;
				} else if (response.error) {
					if (response.message) {
						alert(this.formatErrorMessages(response.message));
					}
				} else if (response.update_section) {
					checkout.accordion.currentSection = 'opc-review';
					checkout.innerHTMLwithScripts($('checkout-update-section'), response.update_section.html);
				}

				this.enableSubmitButton();
			}
		}
	}
);
MageTrustPayments.Checkout.type = MageTrustPayments.Checkout.Type.GoMageLightCheckout;