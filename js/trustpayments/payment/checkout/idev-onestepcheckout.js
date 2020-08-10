/**
 * Trust Payments Magento 1
 *
 * This Magento extension enables to process payments with Trust Payments (https://www.trustpayments.com//).
 *
 * @package TrustPayments_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
MageTrustPayments.Checkout.Type.IdevOneStepCheckout = Class.create(
	MageTrustPayments.Checkout.Type, {
		initialize: function() {
			Payment.prototype.switchMethod = Payment.prototype.switchMethod.wrap(this.switchMethod.bind(this));
			this.switchMethod(function() {}, payment.currentMethod);

			var self = this;
			Validation.prototype.validate = Validation.prototype.validate.wrap(
				function(callOriginal) {
					return self.validate.bind(self)(callOriginal, this.form);
				}
			);
		},

		disableSubmitButton: function() {
			var submitelement = $('onestepcheckout-place-order');
			submitelement.removeClassName('orange').addClassName('grey');
			submitelement.disabled = true;
		},

		enableSubmitButton: function() {
			var submitelement = $('onestepcheckout-place-order');
			submitelement.addClassName('orange').removeClassName('grey');
			submitelement.disabled = false;
		},

		/**
		 * Initializes the payment iframe when the customer switches the payment method.
		 */
		switchMethod: function(callOriginal, method) {
			callOriginal(method);
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
		},

		/**
		 * Validates the payment information when the customer submits the order.
		 */
		validate: function(callOriginal, form) {
			if (!callOriginal()) {
				return false;
			}

			if (form.identify() == 'onestepcheckout-form' && this.isSupportedPaymentMethod(payment.currentMethod) && this.getPaymentMethod(payment.currentMethod).handler) {
				this.disableSubmitButton();
				this.getPaymentMethod(payment.currentMethod).handler.validate();
				return false;
			}

			return true;
		},

		createOrder: function() {
			var loaderelement = new Element(
				'span', {
					'id': 'trustpayments-onestepcheckout-place-order-loading'
				}
			).addClassName('onestepcheckout-place-order-loading').update('Please wait, processing your order...');
			$('onestepcheckout-place-order').parentNode.appendChild(loaderelement);

			var form = $('onestepcheckout-form');
			new Ajax.Request(
				form.readAttribute('action'), {
					method: 'post',
					parameters: Form.serialize(form),
					onSuccess: this.onOrderCreated.bind(this),
					onFailure: function() {
						$('trustpayments-onestepcheckout-place-order-loading').remove();
						this.enableSubmitButton();
					}.bind(this)
				}
			);
		},

		onOrderCreated: function(response) {
			if (response && response.status == 200 && response.responseText == 'OK') {
				if (this.getPaymentMethod(payment.currentMethod).handler) {
					this.getPaymentMethod(payment.currentMethod).handler.submit();
				} else {
					location.href = MageTrustPayments.Checkout.paymentPageUrl + '&paymentMethodConfigurationId=' + this.getPaymentMethod(payment.currentMethod).configurationId;
				}
			} else {
				document.open('text/html');
				document.write(response.responseText);
				document.close();
			}
		}
	}
);
MageTrustPayments.Checkout.type = MageTrustPayments.Checkout.Type.IdevOneStepCheckout;