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
 * Resource model of transaction info.
 */
class TrustPayments_Payment_Model_Resource_TransactionInfo extends Mage_Core_Model_Resource_Db_Abstract
{

    /**
     * DB read connection
     *
     * @var Zend_Db_Adapter_Abstract
     */
    protected $_read;

    protected $_serializableFields = array(
        'labels' => array(
            null,
            array()
        ),
        'failure_reason' => array(
            null,
            array()
        )
    );

    protected function _construct()
    {
        $this->_init('trustpayments_payment/transaction_info', 'entity_id');
        $this->_read = $this->_getReadAdapter();
    }

    /**
     * Load the transaction info by space and transaction.
     *
     * @param TrustPayments_Payment_Model_Entity_TransactionInfo $model
     * @param int $spaceId
     * @param int $transactionId
     * @return array
     */
    public function loadByTransaction(TrustPayments_Payment_Model_Entity_TransactionInfo $model, $spaceId,
        $transactionId)
    {
        $select = $this->_read->select()
            ->from($this->getMainTable())
            ->where('space_id=:space_id AND transaction_id=:transaction_id');

        $data = $this->_read->fetchRow($select, array(
            'space_id' => $spaceId,
            'transaction_id' => $transactionId
        ));

        $model->setData($data);
        $this->unserializeFields($model);
        $this->_afterLoad($model);
    }
}