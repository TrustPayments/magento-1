<?php

/**
 * Trust Payments Magento 1
 *
 * This Magento extension enables to process payments with Trust Payments (https://www.trustpayments.com//).
 *
 * @package TrustPayments_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

/**
 * This block renders the payment method column in the token grid.
 */
class TrustPayments_Payment_Block_Adminhtml_Customer_Token_PaymentMethod extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Text
{

    public function _getValue(Varien_Object $row)
    {
        /* @var TrustPayments_Payment_Model_Entity_PaymentMethodConfiguration $paymentMethod */
        $paymentMethod = Mage::getModel('trustpayments_payment/entity_paymentMethodConfiguration')->load(
            $row->payment_method_id);
        return $paymentMethod->getConfigurationName();
    }
}