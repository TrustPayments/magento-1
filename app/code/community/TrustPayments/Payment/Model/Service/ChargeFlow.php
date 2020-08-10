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
 * This service provides functions to deal with Trust Payments charge flows.
 */
class TrustPayments_Payment_Model_Service_ChargeFlow extends TrustPayments_Payment_Model_Service_Abstract
{

    /**
     * The charge flow API service.
     *
     * @var \TrustPayments\Sdk\Service\ChargeFlowService
     */
    protected $_chargeFlowService;

    /**
     * Apply a charge flow to the given transaction.
     *
     * @param \TrustPayments\Sdk\Model\Transaction $transaction
     */
    public function applyFlow(\TrustPayments\Sdk\Model\Transaction $transaction)
    {
        $this->getChargeFlowService()->applyFlow($transaction->getLinkedSpaceId(), $transaction->getId());
    }

    /**
     * Returns the charge flow API service.
     *
     * @return \TrustPayments\Sdk\Service\ChargeFlowService
     */
    protected function getChargeFlowService()
    {
        if ($this->_chargeFlowService == null) {
            $this->_chargeFlowService = new \TrustPayments\Sdk\Service\ChargeFlowService(
                $this->getHelper()->getApiClient());
        }

        return $this->_chargeFlowService;
    }
}