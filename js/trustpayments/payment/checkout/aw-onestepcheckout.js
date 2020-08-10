/**
 * Trust Payments Magento 1
 *
 * This Magento extension enables to process payments with Trust Payments (https://www.trustpayments.com//).
 *
 * @package TrustPayments_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
MageTrustPayments.Checkout.Type.AWOneStepCheckout = Class.create(
	MageTrustPayments.Checkout.Type, {
		initialize: function() {
			AWOnestepcheckoutPayment.prototype.switchToMethod = AWOnestepcheckoutPayment.prototype.switchToMethod.wrap(this.switchToMethod.bind(this));
			awOSCPayment.switchToMethod(awOSCPayment.currentMethod);

			AWOnestepcheckoutForm.prototype.validate = AWOnestepcheckoutForm.prototype.validate.wrap(this.validate.bind(this));
		},

		showLoader: function(blockName) {
			var targetBlockForAddLoader = AWOnestepcheckoutCore.updater.blocks[blockName].select('.' + AWOnestepcheckoutCore.updater.loaderToBlockCssClass).first();
			if (!targetBlockForAddLoader) {
				targetBlockForAddLoader = AWOnestepcheckoutCore.updater.blocks[blockName];
			}

			AWOnestepcheckoutCore.addLoaderOnBlock(targetBlockForAddLoader, AWOnestepcheckoutCore.updater.loaderConfig);
		},

		hideLoader: function(blockName) {
			var targetBlockForAddLoader = AWOnestepcheckoutCore.updater.blocks[blockName].select('.' + AWOnestepcheckoutCore.updater.loaderToBlockCssClass).first();
			if (!targetBlockForAddLoader) {
				targetBlockForAddLoader = AWOnestepcheckoutCore.updater.blocks[blockName];
			}

			AWOnestepcheckoutCore.removeLoaderFromBlock(targetBlockForAddLoader, AWOnestepcheckoutCore.updater.loaderConfig);
		},

		switchToMethod: function(callOriginal, method) {
			callOriginal(method);
			this.createHandler(
				method,
				function() {
					this.showLoader('payment_method');
				}.bind(this),
				function(validationResult) {
					if (validationResult.success) {
						this.createOrder();
					}
				}.bind(this),
				function() {
					this.hideLoader('payment_method');
				}.bind(this),
				function() {},
				function() {}
			);
		},

		validate: function(callOriginal) {
			var result = callOriginal();
			if (result && this.isSupportedPaymentMethod(awOSCPayment.currentMethod) && this.getPaymentMethod(payment.currentMethod).handler) {
				this.getPaymentMethod(awOSCPayment.currentMethod).handler.validate();
				return false;
			} else {
				return result;
			}
		},

		createOrder: function() {
			awOSCForm.showOverlay();
			awOSCForm.showPleaseWaitNotice();
			awOSCForm.disablePlaceOrderButton();
			new Ajax.Request(
				awOSCForm.placeOrderUrl, {
					method: 'post',
					parameters: Form.serialize(awOSCForm.form.form, true),
					onComplete: this.onOrderCreated.bind(this)
				}
			)
		},

		onOrderCreated: function(transport) {
			if (transport) {
				var response = this.parseResponse(transport);

				if (response.redirect || response.success) {
					if (this.getPaymentMethod(awOSCPayment.currentMethod).handler) {
						this.getPaymentMethod(awOSCPayment.currentMethod).handler.submit();
					} else {
						setLocation(MageTrustPayments.Checkout.paymentPageUrl + '&paymentMethodConfigurationId=' + this.getPaymentMethod(awOSCPayment.currentMethod).configurationId);
					}
				} else {
					if (response.messages) {
						alert(this.formatErrorMessages(response.messages));
					}

					awOSCForm.enablePlaceOrderButton();
					awOSCForm.hidePleaseWaitNotice();
					awOSCForm.hideOverlay();
				}
			}
		}
	}
);
MageTrustPayments.Checkout.type = MageTrustPayments.Checkout.Type.AWOneStepCheckout;