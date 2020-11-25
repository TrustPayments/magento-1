<?php

/**
 * Trust Payments Magento 1
 *
 * This Magento extension enables to process payments with Trust Payments (https://www.trustpayments.com//).
 *
 * @package TrustPayments_Payment
 * @author wallee AG (http://www.wallee.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */

/**
 * Resource model of refund job.
 */
class TrustPayments_Payment_Model_Resource_RefundJob extends Mage_Core_Model_Resource_Db_Abstract
{

    protected $_serializableFields = array(
        'refund' => array(
            null,
            array()
        )
    );

    protected function _construct()
    {
        $this->_init('trustpayments_payment/refund_job', 'entity_id');
    }
}