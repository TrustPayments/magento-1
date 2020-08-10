/**
 * Trust Payments Magento 1
 *
 * This Magento extension enables to process payments with Trust Payments (https://www.trustpayments.com//).
 *
 * @package TrustPayments_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
MageTrustPayments.Checkout.Type.TMFireCheckout = Class.create(
	MageTrustPayments.Checkout.Type, {
		initialize: function() {
			Payment.prototype.switchMethod = Payment.prototype.switchMethod.wrap(this.switchMethod.bind(this));
			payment.switchMethod(payment.currentMethod);

			Payment.prototype.validate = Payment.prototype.validate.wrap(this.validate.bind(this));
			
			document.observe('firecheckout:saveBefore', this.submitOrder.bind(this));
		},

		/**
		 * Initializes the payment iframe when the customer switches the payment method.
		 */
		switchMethod: function(callOriginal, method) {
			callOriginal(method);
			this.createHandler(
				method,
				function() {
					checkout.setLoadWaiting('payment');
				},
				function(validationResult) {
					if (validationResult.success) {
						this.createOrder();
					} else {
						checkout.setLoadWaiting(false);
					}
				}.bind(this),
				function() {
					checkout.setLoadWaiting(false);
				},
				function(){
					var container = $('review-buttons-container');
					container.removeClassName('disabled');
		            container.setStyle({opacity:1});
		            this._disableEnableAll(container, false);
				}.bind(this),
				function(){
					var container = $('review-buttons-container');
					container.addClassName('disabled');
		            container.setStyle({opacity:0.5});
		            this._disableEnableAll(container, true);
				}.bind(this)
			);
		},
		
		_disableEnableAll: function(element, isDisabled) {
	        var descendants = element.descendants();
	        for (var k in descendants) {
	            descendants[k].disabled = isDisabled;
	        }
	        element.disabled = isDisabled;
	    },

		validate: function(callOriginal) {
			var result = callOriginal();
			if (result && this.isSupportedPaymentMethod(payment.currentMethod) && this.getPaymentMethod(payment.currentMethod).handler) {
				checkout.setLoadWaiting('payment');
				this.getPaymentMethod(payment.currentMethod).handler.validate();
				return false;
			} else {
				return result;
			}
		},
		
		submitOrder: function(event) {
			if (this.isSupportedPaymentMethod(payment.currentMethod) && this.getPaymentMethod(payment.currentMethod).handler) {
  				checkout.setLoadWaiting('payment');
  				this.getPaymentMethod(payment.currentMethod).handler.validate();
  				event.memo.stopFurtherProcessing = true;
  			}
		},

		createOrder: function() {
			$('review-please-wait').show();
			new Ajax.Request(
				checkout.urls.save, {
					method: 'post',
					parameters: Form.serialize(checkout.form),
					onSuccess: this.onOrderCreated.bind(this),
					onFailure: checkout.ajaxFailure.bind(checkout)
				}
			)
		},

		onOrderCreated: function(transport) {
			if (transport) {
				var response = this.parseResponse(transport);

				if (response.redirect || response.order_created) {
					if (this.getPaymentMethod(payment.currentMethod).handler) {
						this.getPaymentMethod(payment.currentMethod).handler.submit();
					} else {
						location.href = MageTrustPayments.Checkout.paymentPageUrl + '&paymentMethodConfigurationId=' + this.getPaymentMethod(payment.currentMethod).configurationId;
					}
					return true;
				} else if (response.error) {
					alert(this.formatErrorMessages(response.error_messages));
					checkout.setLoadWaiting(false);
					$('review-please-wait').hide();
				} else {
					checkout.setReponse(transport);
				}
			}
		}
	}
);
MageTrustPayments.Checkout.type = MageTrustPayments.Checkout.Type.TMFireCheckout;