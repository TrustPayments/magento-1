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
 * Provider of label descriptor group information from the gateway.
 */
class TrustPayments_Payment_Model_Provider_LabelDescriptorGroup extends TrustPayments_Payment_Model_Provider_Abstract
{

    public function __construct()
    {
        parent::__construct('trustpayments_payment_label_descriptor_group');
    }

    /**
     * Returns the label descriptor group by the given code.
     *
     * @param int $id
     * @return \TrustPayments\Sdk\Model\LabelDescriptorGroup
     */
    public function find($id)
    {
        return parent::find($id);
    }

    /**
     * Returns a list of label descriptor groups.
     *
     * @return \TrustPayments\Sdk\Model\LabelDescriptorGroup[]
     */
    public function getAll()
    {
        return parent::getAll();
    }

    protected function fetchData()
    {
        $labelDescriptorGroupService = new \TrustPayments\Sdk\Service\LabelDescriptionGroupService(
            Mage::helper('trustpayments_payment')->getApiClient());
        return $labelDescriptorGroupService->all();
    }

    protected function getId($entry)
    {
        /* @var \TrustPayments\Sdk\Model\LabelDescriptorGroup $entry */
        return $entry->getId();
    }
}