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
 * Webhook request model.
 */
class TrustPayments_Payment_Model_Webhook_Request
{

    protected $_eventId;

    protected $_entityId;

    protected $_listenerEntityId;

    protected $_listenerEntityTechnicalName;

    protected $_spaceId;

    protected $_webhookListenerId;

    protected $_timestamp;

    /**
     * Constructor.
     *
     * @param stdClass $model
     */
    public function __construct($model)
    {
        $this->_eventId = $model->eventId;
        $this->_entityId = $model->entityId;
        $this->_listenerEntityId = $model->listenerEntityId;
        $this->_listenerEntityTechnicalName = $model->listenerEntityTechnicalName;
        $this->_spaceId = $model->spaceId;
        $this->_webhookListenerId = $model->webhookListenerId;
        $this->_timestamp = $model->timestamp;
    }

    /**
     * Returns the webhook event's id.
     *
     * @return int
     */
    public function getEventId()
    {
        return $this->_eventId;
    }

    /**
     * Returns the id of the webhook event's entity.
     *
     * @return int
     */
    public function getEntityId()
    {
        return $this->_entityId;
    }

    /**
     * Returns the id of the webhook's listener entity.
     *
     * @return int
     */
    public function getListenerEntityId()
    {
        return $this->_listenerEntityId;
    }

    /**
     * Returns the technical name of the webhook's listener entity.
     *
     * @return string
     */
    public function getListenerEntityTechnicalName()
    {
        return $this->_listenerEntityTechnicalName;
    }

    /**
     * Returns the space id.
     *
     * @return int
     */
    public function getSpaceId()
    {
        return $this->_spaceId;
    }

    /**
     * Returns the id of the webhook listener.
     *
     * @return int
     */
    public function getWebhookListenerId()
    {
        return $this->_webhookListenerId;
    }

    /**
     * Returns the webhook's timestamp.
     *
     * @return string
     */
    public function getTimestamp()
    {
        return $this->_timestamp;
    }
}