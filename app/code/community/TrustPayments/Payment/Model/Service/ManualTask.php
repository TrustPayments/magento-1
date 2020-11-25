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
 * This service provides methods to handle manual tasks.
 */
class TrustPayments_Payment_Model_Service_ManualTask extends TrustPayments_Payment_Model_Service_Abstract
{

    const CONFIG_KEY = 'trustpayments_payment/general/manual_tasks';

    /**
     * Returns the number of open manual tasks.
     *
     * @return array
     */
    public function getNumberOfManualTasks()
    {
        $numberOfManualTasks = array();
        foreach (Mage::app()->getWebsites() as $website) {
            $websiteNumberOfManualTasks = $website->getConfig(self::CONFIG_KEY);
            if ($websiteNumberOfManualTasks != null && $websiteNumberOfManualTasks > 0) {
                $numberOfManualTasks[$website->getId()] = $websiteNumberOfManualTasks;
            }
        }

        return $numberOfManualTasks;
    }

    /**
     * Updates the number of open manual tasks.
     *
     * @return array
     */
    public function update()
    {
        $numberOfManualTasks = array();
        $spaceIds = array();
        $manualTaskService = new \TrustPayments\Sdk\Service\ManualTaskService($this->getHelper()->getApiClient());
        foreach (Mage::app()->getWebsites() as $website) {
            $spaceId = $website->getConfig('trustpayments_payment/general/space_id');
            if ($spaceId && ! in_array($spaceId, $spaceIds)) {
                $websiteNumberOfManualTasks = $manualTaskService->count($spaceId,
                    $this->createEntityFilter('state', \TrustPayments\Sdk\Model\ManualTaskState::OPEN));
                Mage::getConfig()->saveConfig(self::CONFIG_KEY, $websiteNumberOfManualTasks, 'websites',
                    $website->getId());
                if ($websiteNumberOfManualTasks > 0) {
                    $numberOfManualTasks[$website->getId()] = $websiteNumberOfManualTasks;
                }

                $spaceIds[] = $spaceId;
            } else {
                Mage::getConfig()->deleteConfig(self::CONFIG_KEY, 'websites', $website->getId());
            }
        }

        return $numberOfManualTasks;
    }
}