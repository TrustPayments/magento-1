/**
 * Trust Payments Magento 1
 *
 * This Magento extension enables to process payments with Trust Payments (https://www.trustpayments.com//).
 *
 * @package TrustPayments_Payment
 * @author wallee AG (http://www.wallee.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
MageTrustPayments.Checkout.Type.MagentoOnePage = Class.create(
	MageTrustPayments.Checkout.Type, {
		originalPaymentSave: function() {},

		initialize: function() {
			Payment.prototype.init = Payment.prototype.init.wrap(this.initPaymentSection.bind(this));
			
			Payment.prototype.switchMethod = Payment.prototype.switchMethod.wrap(this.switchMethod.bind(this));

			this.originalPaymentSave = Payment.prototype.save.bind(payment);
			Payment.prototype.save = Payment.prototype.save.wrap(this.savePayment.bind(this));

			Review.prototype.save = Review.prototype.save.wrap(this.placeOrder.bind(this));
		},
		
		resetValidationErrors: function(){
			$(this.getPaymentMethod(payment.currentMethod).container + '_errors').innerHTML = '';
		},
		
		setValidationErrors: function(errors){
			var formattedErrors = '<ul class="messages"><li class="error-msg"><ul>';
			for (var i = 0; i < errors.length; i++) {
				formattedErrors += '<li><span>' + errors[i] + '</span></li>';
			}
			formattedErrors += '</ul></li></ul>';
			
			$(this.getPaymentMethod(payment.currentMethod).container + '_errors').innerHTML = formattedErrors;
		},
		
		initPaymentSection: function(callOriginal){
			MageTrustPayments.Checkout.fetchInformation(callOriginal);
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
				}.bind(this),
				function(validationResult) {
					checkout.setLoadWaiting(false);
					if (validationResult.success) {
						this.originalPaymentSave();
					} else {
						if (validationResult.errors) {
							this.setValidationErrors(validationResult.errors);
						}
					}
				}.bind(this),
				function() {
					checkout.setLoadWaiting(false);
				},
				function(){
					var container = $('payment-buttons-container');
					container.removeClassName('disabled');
                    container.setStyle({opacity:1});
                    this._disableEnableAll(container, false);
				}.bind(this),
				function(){
					var container = $('payment-buttons-container');
		            container.addClassName('disabled');
		            container.setStyle({opacity:.5});
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

		/**
		 * Validates the payment information when the customer saves the payment method.
		 */
		savePayment: function(callOriginal) {
			if (this.isSupportedPaymentMethod(payment.currentMethod) && this.getPaymentMethod(payment.currentMethod).handler) {
				checkout.setLoadWaiting('payment');
				this.resetValidationErrors();
				this.getPaymentMethod(payment.currentMethod).handler.validate();
				return false;
			} else {
				callOriginal();
			}
		},

		/**
		 * Sends the payment information to Trust Payments after the customer submitted the order.
		 */
		placeOrder: function(callOriginal) {
			if (this.isSupportedPaymentMethod(payment.currentMethod)) {
				if (checkout.loadWaiting != false) {
					return;
				}

				checkout.setLoadWaiting('review');
				var params = Form.serialize(payment.form);
				if (review.agreementsForm) {
					params += '&' + Form.serialize(review.agreementsForm);
				}

				params.save = true;
				new Ajax.Request(
					review.saveUrl, {
						method: 'post',
						parameters: params,
						onSuccess: this.onOrderCreated.bind(this),
						onFailure: function() {
							review.onComplete();
							checkout.ajaxFailure();
						}
					}
				);
			} else {
				callOriginal();
			}
		},

		onOrderCreated: function(transport) {
			if (transport) {
				var response = this.parseResponse(transport);

				if (response.success) {
					if (this.getPaymentMethod(payment.currentMethod).handler) {
						this.getPaymentMethod(payment.currentMethod).handler.submit();
					} else {
						location.href = MageTrustPayments.Checkout.paymentPageUrl + '&paymentMethodConfigurationId=' + this.getPaymentMethod(payment.currentMethod).configurationId;
					}
					return;
				} else {
					if (response.error_messages) {
						alert(this.formatErrorMessages(response.error_messages));
					}
					checkout.setLoadWaiting(false);
				}

				if (response.update_section) {
					$('checkout-' + response.update_section.name + '-load').update(response.update_section.html);
				}

				if (response.goto_section) {
					checkout.gotoSection(response.goto_section, true);
				}
			}
		}
	}
);
MageTrustPayments.Checkout.type = MageTrustPayments.Checkout.Type.MagentoOnePage;