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
 * The block renders the payment information.
 */
class TrustPayments_Payment_Block_Payment_Info extends Mage_Payment_Block_Info
{

    protected $_transactionInfo = null;

    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('trustpayments/payment/info.phtml');
    }

    /**
     * Returns whether the payment information are to be displayed in the creditmemo detail view in the backend.
     *
     * @return boolean
     */
    public function isCreditmemo()
    {
        return Mage::app()->getStore()->isAdmin() &&
            strstr($this->getRequest()->getControllerName(), 'creditmemo') !== false;
    }

    /**
     * Returns whether the payment information are to be displayed in the invoice detail view in the backend.
     *
     * @return boolean
     */
    public function isInvoice()
    {
        return Mage::app()->getStore()->isAdmin() &&
            strstr($this->getRequest()->getControllerName(), 'invoice') !== false;
    }

    /**
     * Returns whether the payment information are to be displayed in the shipment detail view in the backend.
     *
     * @return boolean
     */
    public function isShipment()
    {
        return Mage::app()->getStore()->isAdmin() &&
            strstr($this->getRequest()->getControllerName(), 'shipment') !== false;
    }

    /**
     * Returns whether the customer is allowed to download invoice documents.
     *
     * @return boolean
     */
    public function isCustomerDownloadInvoiceAllowed()
    {
        return $this->getInfo()->getOrder() != null && Mage::getStoreConfigFlag(
            'trustpayments_payment/document/customer_download_invoice',
            $this->getInfo()
                ->getOrder()
                ->getStore());
    }

    /**
     * Returns whether the customer is allowed to download packing slips.
     *
     * @return boolean
     */
    public function isCustomerDownloadPackingSlipAllowed()
    {
        return $this->getInfo()->getOrder() != null && Mage::getStoreConfigFlag(
            'trustpayments_payment/document/customer_download_packing_slip',
            $this->getInfo()
                ->getOrder()
                ->getStore());
    }

    /**
     * Returns the URL to update the transaction's information.
     *
     * @return string
     */
    public function getUpdateTransactionUrl()
    {
        if ($this->getTransactionInfo() && Mage::app()->getStore()->isAdmin()) {
            /* @var Mage_Adminhtml_Helper_Data $adminHelper */
            $adminHelper = Mage::helper('adminhtml');
            return $adminHelper->getUrl('adminhtml/trustpayments_transaction/update',
                array(
                    'transaction_id' => $this->getTransactionInfo()
                        ->getTransactionId(),
                    'space_id' => $this->getTransactionInfo()
                        ->getSpaceId(),
                    '_secure' => true
                ));
        }
    }

    /**
     * Returns the URL to download the transaction's invoice PDF document.
     *
     * @return string
     */
    public function getDownloadInvoiceUrl()
    {
        if (! $this->getTransactionInfo() || ! in_array($this->getTransactionInfo()->getState(),
            array(
                \TrustPayments\Sdk\Model\TransactionState::COMPLETED,
                \TrustPayments\Sdk\Model\TransactionState::FULFILL,
                \TrustPayments\Sdk\Model\TransactionState::DECLINE
            ))) {
            return false;
        }

        if (Mage::app()->getStore()->isAdmin()) {
            /* @var Mage_Adminhtml_Helper_Data $adminHelper */
            $adminHelper = Mage::helper('adminhtml');
            return $adminHelper->getUrl('adminhtml/trustpayments_transaction/downloadInvoice',
                array(
                    'transaction_id' => $this->getTransactionInfo()
                        ->getTransactionId(),
                    'space_id' => $this->getTransactionInfo()
                        ->getSpaceId(),
                    '_secure' => true
                ));
        } else {
            return $this->getUrl('trustpayments/transaction/downloadInvoice',
                array(
                    'order_id' => $this->getInfo()
                        ->getOrder()
                        ->getId()
                ));
        }
    }

    /**
     * Returns the URL to download the transaction's packing slip PDF document.
     *
     * @return string
     */
    public function getDownloadPackingSlipUrl()
    {
        if (! $this->getTransactionInfo() ||
            $this->getTransactionInfo()->getState() != \TrustPayments\Sdk\Model\TransactionState::FULFILL) {
            return false;
        }

        if (Mage::app()->getStore()->isAdmin()) {
            /* @var Mage_Adminhtml_Helper_Data $adminHelper */
            $adminHelper = Mage::helper('adminhtml');
            return $adminHelper->getUrl('adminhtml/trustpayments_transaction/downloadPackingSlip',
                array(
                    'transaction_id' => $this->getTransactionInfo()
                        ->getTransactionId(),
                    'space_id' => $this->getTransactionInfo()
                        ->getSpaceId(),
                    '_secure' => true
                ));
        } else {
            return $this->getUrl('trustpayments/transaction/downloadPackingSlip',
                array(
                    'order_id' => $this->getInfo()
                        ->getOrder()
                        ->getId()
                ));
        }
    }

    /**
     * Returns the URL to download the refund PDF document.
     *
     * @return string
     */
    public function getDownloadRefundUrl()
    {
        /* @var Mage_Sales_Model_Order_Creditmemo $creditmemo */
        $creditmemo = Mage::registry('current_creditmemo');
        if ($creditmemo == null || $creditmemo->getTrustpaymentsExternalId() == null) {
            return false;
        }

        /* @var Mage_Adminhtml_Helper_Data $adminHelper */
        $adminHelper = Mage::helper('adminhtml');
        return $adminHelper->getUrl('adminhtml/trustpayments_transaction/downloadRefund',
            array(
                'external_id' => $creditmemo->getTrustpaymentsExternalId(),
                'space_id' => $this->getTransactionInfo()
                    ->getSpaceId(),
                '_secure' => true
            ));
    }

    /**
     * Returns the transaction info.
     *
     * @return TrustPayments_Payment_Model_Entity_TransactionInfo
     */
    public function getTransactionInfo()
    {
        if ($this->_transactionInfo === null) {
            if ($this->getInfo() instanceof Mage_Sales_Model_Order_Payment) {
                try {
                    /* @var TrustPayments_Payment_Model_Entity_TransactionInfo $transactionInfo */
                    $transactionInfo = Mage::getModel('trustpayments_payment/entity_transactionInfo')->loadByOrder(
                        $this->getInfo()
                            ->getOrder());
                    if ($transactionInfo->getId()) {
                        $this->_transactionInfo = $transactionInfo;
                    } else {
                        $this->_transactionInfo = false;
                    }
                } catch (Exception $e) {
                    $this->_transactionInfo = false;
                }
            } else {
                $this->_transactionInfo = false;
            }
        }

        return $this->_transactionInfo;
    }

    /**
     * Returns the URL to the payment method image.
     *
     * @return string
     */
    public function getImageUrl()
    {
        /* @var TrustPayments_Payment_Model_Payment_Method_Abstract $methodInstance */
        $methodInstance = $this->getMethod();
        $spaceId = $methodInstance->getPaymentMethodConfiguration()->getSpaceId();
        $spaceViewId = $this->getTransactionInfo() ? $this->getTransactionInfo()->getSpaceViewId() : null;
        $language = $this->getTransactionInfo() ? $this->getTransactionInfo()->getLanguage() : null;
        /* @var TrustPayments_Payment_Helper_Data $helper */
        $helper = $this->helper('trustpayments_payment');
        return $helper->getResourceUrl($methodInstance->getPaymentMethodConfiguration()
            ->getImage(), $language, $spaceId, $spaceViewId);
    }

    /**
     * Returns the URL to the transaction detail view in Trust Payments.
     *
     * @return string
     */
    public function getTransactionUrl()
    {
        return Mage::helper('trustpayments_payment')->getBaseGatewayUrl() . '/s/' .
            $this->getTransactionInfo()->getSpaceId() . '/payment/transaction/view/' .
            $this->getTransactionInfo()->getTransactionId();
    }
    
    /**
     * Returns the URL to the customer detail view in Trust Payments.
     *
     * @return string
     */
    public function getCustomerUrl()
    {
        return Mage::helper('trustpayments_payment')->getBaseGatewayUrl() . '/s/' .
            $this->getTransactionInfo()->getSpaceId() . '/payment/customer/transaction/view/' .
            $this->getTransactionInfo()->getTransactionId();
    }

    /**
     * Returns the translated name of the transaction's state.
     *
     * @return string
     */
    public function getTransactionState()
    {
        /* @var TrustPayments_Payment_Helper_Data $helper */
        $helper = $this->helper('trustpayments_payment');
        switch ($this->getTransactionInfo()->getState()) {
            case \TrustPayments\Sdk\Model\TransactionState::AUTHORIZED:
                return $helper->__('Authorized');
            case \TrustPayments\Sdk\Model\TransactionState::COMPLETED:
                return $helper->__('Completed');
            case \TrustPayments\Sdk\Model\TransactionState::CONFIRMED:
                return $helper->__('Confirmed');
            case \TrustPayments\Sdk\Model\TransactionState::DECLINE:
                return $helper->__('Decline');
            case \TrustPayments\Sdk\Model\TransactionState::FAILED:
                return $helper->__('Failed');
            case \TrustPayments\Sdk\Model\TransactionState::FULFILL:
                return $helper->__('Fulfill');
            case \TrustPayments\Sdk\Model\TransactionState::PENDING:
                return $helper->__('Pending');
            case \TrustPayments\Sdk\Model\TransactionState::PROCESSING:
                return $helper->__('Processing');
            case \TrustPayments\Sdk\Model\TransactionState::VOIDED:
                return $helper->__('Voided');
            default:
                return $helper->__('Unknown State');
        }
    }

    /**
     * Returns the transaction's currency.
     *
     * @return Mage_Directory_Model_Currency
     */
    public function getTransactionCurrency()
    {
        return Mage::getModel('directory/currency')->load($this->getTransactionInfo()
            ->getCurrency());
    }

    /**
     * Returns the charge attempt's labels by their groups.
     *
     * @return \TrustPayments\Sdk\Model\Label[]
     */
    public function getGroupedChargeAttemptLabels()
    {
        if ($this->getTransactionInfo()) {
            /* @var TrustPayments_Payment_Model_Provider_LabelDescriptor $labelDescriptorProvider */
            $labelDescriptorProvider = Mage::getSingleton('trustpayments_payment/provider_labelDescriptor');

            /* @var TrustPayments_Payment_Model_Provider_LabelDescriptorGroup $labelDescriptorGroupProvider */
            $labelDescriptorGroupProvider = Mage::getSingleton(
                'trustpayments_payment/provider_labelDescriptorGroup');

            $labelsByGroupId = array();
            foreach ($this->getTransactionInfo()->getLabels() as $descriptorId => $value) {
                $descriptor = $labelDescriptorProvider->find($descriptorId);
                if ($descriptor) {
                    $labelsByGroupId[$descriptor->getGroup()][] = array(
                        'descriptor' => $descriptor,
                        'value' => $value
                    );
                }
            }

            $labelsByGroup = array();
            foreach ($labelsByGroupId as $groupId => $labels) {
                $group = $labelDescriptorGroupProvider->find($groupId);
                if ($group) {
                    usort($labels,
                        function ($a, $b) {
                            return $a['descriptor']->getWeight() - $b['descriptor']->getWeight();
                        });
                    $labelsByGroup[] = array(
                        'group' => $group,
                        'labels' => $labels
                    );
                }
            }

            usort($labelsByGroup, function ($a, $b) {
                return $a['group']->getWeight() - $b['group']->getWeight();
            });
            return $labelsByGroup;
        } else {
            return array();
        }
    }
}