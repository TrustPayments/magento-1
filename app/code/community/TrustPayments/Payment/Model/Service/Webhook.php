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
 * This service handles webhooks.
 */
class TrustPayments_Payment_Model_Service_Webhook extends TrustPayments_Payment_Model_Service_Abstract
{

    /**
     * The webhook listener API service.
     *
     * @var \TrustPayments\Sdk\Service\WebhookListenerService
     */
    protected $_webhookListenerService;

    /**
     * The webhook url API service.
     *
     * @var \TrustPayments\Sdk\Service\WebhookUrlService
     */
    protected $_webhookUrlService;

    protected $_webhookEntities = array();

    /**
     * Constructor to register the webhook entites.
     */
    public function __construct()
    {
        $this->_webhookEntities[] = new TrustPayments_Payment_Model_Webhook_Entity(1487165678181, 'Manual Task',
            array(
                \TrustPayments\Sdk\Model\ManualTaskState::DONE,
                \TrustPayments\Sdk\Model\ManualTaskState::EXPIRED,
                \TrustPayments\Sdk\Model\ManualTaskState::OPEN
            ));
        $this->_webhookEntities[] = new TrustPayments_Payment_Model_Webhook_Entity(1472041857405,
            'Payment Method Configuration',
            array(
                \TrustPayments\Sdk\Model\CreationEntityState::ACTIVE,
                \TrustPayments\Sdk\Model\CreationEntityState::DELETED,
                \TrustPayments\Sdk\Model\CreationEntityState::DELETING,
                \TrustPayments\Sdk\Model\CreationEntityState::INACTIVE
            ), true);
        $this->_webhookEntities[] = new TrustPayments_Payment_Model_Webhook_Entity(1472041829003, 'Transaction',
            array(
                \TrustPayments\Sdk\Model\TransactionState::AUTHORIZED,
                \TrustPayments\Sdk\Model\TransactionState::DECLINE,
                \TrustPayments\Sdk\Model\TransactionState::FAILED,
                \TrustPayments\Sdk\Model\TransactionState::FULFILL,
                \TrustPayments\Sdk\Model\TransactionState::VOIDED,
                \TrustPayments\Sdk\Model\TransactionState::COMPLETED,
                \TrustPayments\Sdk\Model\TransactionState::PROCESSING,
                \TrustPayments\Sdk\Model\TransactionState::CONFIRMED
            ));
        $this->_webhookEntities[] = new TrustPayments_Payment_Model_Webhook_Entity(1472041819799,
            'Delivery Indication',
            array(
                \TrustPayments\Sdk\Model\DeliveryIndicationState::MANUAL_CHECK_REQUIRED
            ));
        $this->_webhookEntities[] = new TrustPayments_Payment_Model_Webhook_Entity(1472041816898,
            'Transaction Invoice',
            array(
                \TrustPayments\Sdk\Model\TransactionInvoiceState::NOT_APPLICABLE,
                \TrustPayments\Sdk\Model\TransactionInvoiceState::PAID
            ));
        $this->_webhookEntities[] = new TrustPayments_Payment_Model_Webhook_Entity(1472041831364,
            'Transaction Completion', array(
                \TrustPayments\Sdk\Model\TransactionCompletionState::FAILED
            ));
        $this->_webhookEntities[] = new TrustPayments_Payment_Model_Webhook_Entity(1472041839405, 'Refund',
            array(
                \TrustPayments\Sdk\Model\RefundState::FAILED,
                \TrustPayments\Sdk\Model\RefundState::SUCCESSFUL
            ));
        $this->_webhookEntities[] = new TrustPayments_Payment_Model_Webhook_Entity(1472041806455, 'Token',
            array(
                \TrustPayments\Sdk\Model\CreationEntityState::ACTIVE,
                \TrustPayments\Sdk\Model\CreationEntityState::DELETED,
                \TrustPayments\Sdk\Model\CreationEntityState::DELETING,
                \TrustPayments\Sdk\Model\CreationEntityState::INACTIVE
            ));
        $this->_webhookEntities[] = new TrustPayments_Payment_Model_Webhook_Entity(1472041811051, 'Token Version',
            array(
                \TrustPayments\Sdk\Model\TokenVersionState::ACTIVE,
                \TrustPayments\Sdk\Model\TokenVersionState::OBSOLETE
            ));
    }

    /**
     * Installs the necessary webhooks in Trust Payments.
     */
    public function install()
    {
        $spaceIds = array();
        foreach (Mage::app()->getWebsites() as $website) {
            $spaceId = $website->getConfig('trustpayments_payment/general/space_id');
            if ($spaceId && ! in_array($spaceId, $spaceIds)) {
                $webhookUrl = $this->getWebhookUrl($spaceId);
                if ($webhookUrl == null) {
                    $webhookUrl = $this->createWebhookUrl($spaceId);
                }

                $existingListeners = $this->getWebhookListeners($spaceId, $webhookUrl);
                foreach ($this->_webhookEntities as $webhookEntity) {
                    /* @var TrustPayments_Payment_Model_Webhook_Entity $webhookEntity */
                    $exists = false;
                    foreach ($existingListeners as $existingListener) {
                        if ($existingListener->getEntity() == $webhookEntity->getId()) {
                            $exists = true;
                        }
                    }

                    if (! $exists) {
                        $this->createWebhookListener($webhookEntity, $spaceId, $webhookUrl);
                    }
                }

                $spaceIds[] = $spaceId;
            }
        }
    }

    /**
     * Create a webhook listener.
     *
     * @param TrustPayments_Payment_Model_Webhook_Entity $entity
     * @param int $spaceId
     * @param \TrustPayments\Sdk\Model\WebhookUrl $webhookUrl
     * @return \TrustPayments\Sdk\Model\WebhookListenerCreate
     */
    protected function createWebhookListener(TrustPayments_Payment_Model_Webhook_Entity $entity, $spaceId,
        \TrustPayments\Sdk\Model\WebhookUrl $webhookUrl)
    {
        $webhookListener = new \TrustPayments\Sdk\Model\WebhookListenerCreate();
        $webhookListener->setEntity($entity->getId());
        $webhookListener->setEntityStates($entity->getStates());
        $webhookListener->setName('Magento ' . $entity->getName());
        $webhookListener->setState(\TrustPayments\Sdk\Model\CreationEntityState::ACTIVE);
        $webhookListener->setUrl($webhookUrl->getId());
        $webhookListener->setNotifyEveryChange($entity->isNotifyEveryChange());
        return $this->getWebhookListenerService()->create($spaceId, $webhookListener);
    }

    /**
     * Returns the existing webhook listeners.
     *
     * @param int $spaceId
     * @param \TrustPayments\Sdk\Model\WebhookUrl $webhookUrl
     * @return \TrustPayments\Sdk\Model\WebhookListener[]
     */
    protected function getWebhookListeners($spaceId, \TrustPayments\Sdk\Model\WebhookUrl $webhookUrl)
    {
        $query = new \TrustPayments\Sdk\Model\EntityQuery();
        $filter = new \TrustPayments\Sdk\Model\EntityQueryFilter();
        $filter->setType(\TrustPayments\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->createEntityFilter('state', \TrustPayments\Sdk\Model\CreationEntityState::ACTIVE),
                $this->createEntityFilter('url.id', $webhookUrl->getId())
            ));
        $query->setFilter($filter);
        return $this->getWebhookListenerService()->search($spaceId, $query);
    }

    /**
     * Creates a webhook url.
     *
     * @param int $spaceId
     * @return \TrustPayments\Sdk\Model\WebhookUrlCreate
     */
    protected function createWebhookUrl($spaceId)
    {
        $webhookUrl = new \TrustPayments\Sdk\Model\WebhookUrlCreate();
        $webhookUrl->setUrl($this->getUrl());
        $webhookUrl->setState(\TrustPayments\Sdk\Model\CreationEntityState::ACTIVE);
        $webhookUrl->setName('Magento');
        return $this->getWebhookUrlService()->create($spaceId, $webhookUrl);
    }

    /**
     * Returns the existing webhook url if there is one.
     *
     * @param int $spaceId
     * @return \TrustPayments\Sdk\Model\WebhookUrl
     */
    protected function getWebhookUrl($spaceId)
    {
        $query = new \TrustPayments\Sdk\Model\EntityQuery();
        $query->setNumberOfEntities(1);
        $filter = new \TrustPayments\Sdk\Model\EntityQueryFilter();
        $filter->setType(\TrustPayments\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->createEntityFilter('state', \TrustPayments\Sdk\Model\CreationEntityState::ACTIVE),
                $this->createEntityFilter('url', $this->getUrl())
            ));
        $query->setFilter($filter);
        $result = $this->getWebhookUrlService()->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        } else {
            return null;
        }
    }

    /**
     * Returns the webhook endpoint URL.
     *
     * @return string
     */
    protected function getUrl()
    {
        return Mage::getUrl('trustpayments/webhook',
            array(
                '_secure' => true,
                '_store' => Mage::app()->getDefaultStoreView()->getId()
            ));
    }

    /**
     * Returns the webhook listener API service.
     *
     * @return \TrustPayments\Sdk\Service\WebhookListenerService
     */
    protected function getWebhookListenerService()
    {
        if ($this->_webhookListenerService == null) {
            $this->_webhookListenerService = new \TrustPayments\Sdk\Service\WebhookListenerService(
                Mage::helper('trustpayments_payment')->getApiClient());
        }

        return $this->_webhookListenerService;
    }

    /**
     * Returns the webhook url API service.
     *
     * @return \TrustPayments\Sdk\Service\WebhookUrlService
     */
    protected function getWebhookUrlService()
    {
        if ($this->_webhookUrlService == null) {
            $this->_webhookUrlService = new \TrustPayments\Sdk\Service\WebhookUrlService(
                Mage::helper('trustpayments_payment')->getApiClient());
        }

        return $this->_webhookUrlService;
    }
}