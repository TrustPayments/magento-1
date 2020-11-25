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
 * Provider of payment connector information from the gateway.
 */
class TrustPayments_Payment_Model_Provider_PaymentConnector extends TrustPayments_Payment_Model_Provider_Abstract
{

    public function __construct()
    {
        parent::__construct('trustpayments_payment_connectors');
    }

    /**
     * Returns the payment connector by the given id.
     *
     * @param int $id
     * @return \TrustPayments\Sdk\Model\PaymentConnector
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Returns a list of payment connectors.
     *
     * @return \TrustPayments\Sdk\Model\PaymentConnector[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $connectorService = new \TrustPayments\Sdk\Service\PaymentConnectorService(
            Mage::helper('trustpayments_payment')->getApiClient());
        return $connectorService->all();
    }

    protected function getId($entry)
    {
        /* @var \TrustPayments\Sdk\Model\PaymentConnector $entry */
        return $entry->getId();
    }
}