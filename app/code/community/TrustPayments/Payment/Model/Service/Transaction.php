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
 * This service provides functions to deal with Trust Payments transactions.
 */
class TrustPayments_Payment_Model_Service_Transaction extends TrustPayments_Payment_Model_Service_Abstract
{

    /**
     * Cache for quote transactions.
     *
     * @var \TrustPayments\Sdk\Model\Transaction[]
     */
    protected static $_transactionCache = array();

    /**
     * Cache for possible payment methods by quote.
     *
     * @var \TrustPayments\Sdk\Model\PaymentMethodConfiguration[]
     */
    protected static $_possiblePaymentMethodCache = array();

    /**
     * Cache for JavaScript URLs.
     *
     * @var string[]
     */
    protected static $_javascriptUrlCache = array();

    /**
     * Cache for payment page URLs.
     *
     * @var string[]
     */
    protected static $_paymentPageUrlCache = array();

    /**
     * The transaction API service.
     *
     * @var \TrustPayments\Sdk\Service\TransactionService
     */
    protected $_transactionService;
    
    /**
     * The transaction invoice API service.
     *
     * @var \TrustPayments\Sdk\Service\TransactionInvoiceService
     */
    protected $_transactionPaymentPageService;
    
    /**
     * The transaction iframe API service.
     *
     * @var \TrustPayments\Sdk\Service\TransactionIframeService
     */
    protected $_transactionIframeService;
    
    /**
     * Returns the transaction API service.
     *
     * @return \TrustPayments\Sdk\Service\TransactionService
     */
    protected function getTransactionService()
    {
    	if ($this->_transactionService == null) {
    		$this->_transactionService = new \TrustPayments\Sdk\Service\TransactionService(
    				$this->getHelper()->getApiClient());
    	}
    	
    	return $this->_transactionService;
    }
    
    /**
     * Returns the transaction API service.
     *
     * @return \TrustPayments\Sdk\Service\TransactionPaymentPageService
     */
    protected function getTransactionPaymentPageService()
    {
    	if ($this->_transactionPaymentPageService == null) {
    		$this->_transactionPaymentPageService = new \TrustPayments\Sdk\Service\TransactionPaymentPageService(
    				$this->getHelper()->getApiClient());
    	}
    	
    	return $this->_transactionPaymentPageService;
    }
    
    /**
     * Returns the transaction API service.
     *
     * @return \TrustPayments\Sdk\Service\TransactionIframeService
     */
    protected function getTransactionIframeService()
    {
    	if ($this->_transactionIframeService == null) {
    		$this->_transactionIframeService = new \TrustPayments\Sdk\Service\TransactionIframeService(
    				$this->getHelper()->getApiClient());
    	}
    	
    	return $this->_transactionIframeService;
    }

    /**
     * Wait for the transaction to be in one of the given states.
     *
     * @param Mage_Sales_Model_Order $order
     * @param array $states
     * @param int $maxWaitTime
     * @return boolean
     */
    public function waitForTransactionState(Mage_Sales_Model_Order $order, array $states, $maxWaitTime = 10)
    {
        $startTime = microtime(true);
        while (true) {
            if (microtime(true) - $startTime >= $maxWaitTime) {
                return false;
            }

            /* @var TrustPayments_Payment_Model_Entity_TransactionInfo $transactionInfo */
            $transactionInfo = Mage::getModel('trustpayments_payment/entity_transactionInfo');
            $transactionInfo->loadByOrder($order);
            if (in_array($transactionInfo->getState(), $states)) {
                return true;
            }

            sleep(2);
        }
    }

    /**
     * Returns the URL to Trust Payments's JavaScript library that is necessary to display the payment form.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return string
     */
    public function getJavaScriptUrl(Mage_Sales_Model_Quote $quote)
    {
        if (! isset(self::$_javascriptUrlCache[$quote->getId()]) || self::$_javascriptUrlCache[$quote->getId()] == null) {
            $transaction = $this->getTransactionByQuote($quote);
            self::$_javascriptUrlCache[$quote->getId()] = $this->getTransactionIframeService()->javascriptUrl(
                $transaction->getLinkedSpaceId(), $transaction->getId());
        }
        return self::$_javascriptUrlCache[$quote->getId()];
    }

    /**
     * Returns the URL to Trust Payments's payment page.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return string
     */
    public function getPaymentPageUrl(Mage_Sales_Model_Quote $quote)
    {
        if (! isset(self::$_paymentPageUrlCache[$quote->getId()]) || self::$_paymentPageUrlCache[$quote->getId()] == null) {
            $transaction = $this->getTransactionByQuote($quote);
            self::$_paymentPageUrlCache[$quote->getId()] = $this->getTransactionPaymentPageService()->paymentPageUrl(
                $transaction->getLinkedSpaceId(), $transaction->getId());
        }
        return self::$_paymentPageUrlCache[$quote->getId()];
    }

    /**
     * Returns the transaction with the given id.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return \TrustPayments\Sdk\Model\Transaction
     */
    public function getTransaction($spaceId, $transactionId)
    {
        return $this->getTransactionService()->read($spaceId, $transactionId);
    }

    /**
     * Returns the last failed charge attempt of the transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @return \TrustPayments\Sdk\Model\ChargeAttempt
     */
    public function getFailedChargeAttempt($spaceId, $transactionId)
    {
        $chargeAttemptService = new \TrustPayments\Sdk\Service\ChargeAttemptService(
            Mage::helper('trustpayments_payment')->getApiClient());
        $query = new \TrustPayments\Sdk\Model\EntityQuery();
        $filter = new \TrustPayments\Sdk\Model\EntityQueryFilter();
        $filter->setType(\TrustPayments\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->createEntityFilter('charge.transaction.id', $transactionId),
                $this->createEntityFilter('state', \TrustPayments\Sdk\Model\ChargeAttemptState::FAILED)
            ));
        $query->setFilter($filter);
        $query->setOrderBys(array(
            $this->createEntityOrderBy('failedOn')
        ));
        $query->setNumberOfEntities(1);
        $result = $chargeAttemptService->search($spaceId, $query);
        if ($result != null && ! empty($result)) {
            return current($result);
        } else {
            return null;
        }
    }

    /**
     * Updates the line items of the given transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param \TrustPayments\Sdk\Model\LineItem[] $lineItems
     * @return \TrustPayments\Sdk\Model\TransactionLineItemVersion
     */
    public function updateLineItems($spaceId, $transactionId, $lineItems)
    {
        $updateRequest = new \TrustPayments\Sdk\Model\TransactionLineItemUpdateRequest();
        $updateRequest->setTransactionId($transactionId);
        $updateRequest->setNewLineItems($lineItems);
        return $this->getTransactionService()->updateTransactionLineItems($spaceId, $updateRequest);
    }

    /**
     * Stores the transaction data in the database.
     *
     * @param \TrustPayments\Sdk\Model\Transaction $transaction
     * @param Mage_Sales_Model_Order $order
     * @return TrustPayments_Payment_Model_Entity_TransactionInfo
     */
    public function updateTransactionInfo(\TrustPayments\Sdk\Model\Transaction $transaction,
        Mage_Sales_Model_Order $order)
    {
        /* @var TrustPayments_Payment_Model_Entity_TransactionInfo $info */
        $info = Mage::getModel('trustpayments_payment/entity_transactionInfo')->loadByTransaction(
            $transaction->getLinkedSpaceId(), $transaction->getId());

        if ($info->getId() && $info->getOrderId() != $order->getId()) {
            Mage::throwException(
                $this->getHelper()->__('The Trust Payments transaction info is already linked to a different order.'));
        }

        $info->setTransactionId($transaction->getId());
        $info->setAuthorizationAmount($transaction->getAuthorizationAmount());
        $info->setOrderId($order->getId());
        $info->setState($transaction->getState());
        $info->setSpaceId($transaction->getLinkedSpaceId());
        $info->setSpaceViewId($transaction->getSpaceViewId());
        $info->setLanguage($transaction->getLanguage());
        $info->setCurrency($transaction->getCurrency());
        $info->setConnectorId(
            $transaction->getPaymentConnectorConfiguration() != null ? $transaction->getPaymentConnectorConfiguration()
                ->getConnector() : null);
        $info->setPaymentMethodId(
            $transaction->getPaymentConnectorConfiguration() != null &&
            $transaction->getPaymentConnectorConfiguration()
                ->getPaymentMethodConfiguration() != null ? $transaction->getPaymentConnectorConfiguration()
                ->getPaymentMethodConfiguration()
                ->getPaymentMethod() : null);
        $info->setImage($this->getPaymentMethodImage($transaction, $order));
        $info->setLabels($this->getTransactionLabels($transaction));
        if ($transaction->getState() == \TrustPayments\Sdk\Model\TransactionState::FAILED ||
            $transaction->getState() == \TrustPayments\Sdk\Model\TransactionState::DECLINE) {
            $info->setFailureReason(
                $transaction->getFailureReason() instanceof \TrustPayments\Sdk\Model\FailureReason ? $transaction->getFailureReason()
                    ->getDescription() : null);
        }

        $info->save();
        return $info;
    }

    /**
     * Returns an array of the transaction's labels.
     *
     * @param \TrustPayments\Sdk\Model\Transaction $transaction
     * @return string[]
     */
    protected function getTransactionLabels(\TrustPayments\Sdk\Model\Transaction $transaction)
    {
        $chargeAttempt = $this->getChargeAttempt($transaction);
        if ($chargeAttempt != null) {
            $labels = array();
            foreach ($chargeAttempt->getLabels() as $label) {
                $labels[$label->getDescriptor()->getId()] = $label->getContentAsString();
            }

            return $labels;
        } else {
            return array();
        }
    }

    /**
     * Returns the successful charge attempt of the transaction.
     *
     * @return \TrustPayments\Sdk\Model\ChargeAttempt
     */
    protected function getChargeAttempt(\TrustPayments\Sdk\Model\Transaction $transaction)
    {
        $chargeAttemptService = new \TrustPayments\Sdk\Service\ChargeAttemptService(
            Mage::helper('trustpayments_payment')->getApiClient());
        $query = new \TrustPayments\Sdk\Model\EntityQuery();
        $filter = new \TrustPayments\Sdk\Model\EntityQueryFilter();
        $filter->setType(\TrustPayments\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->createEntityFilter('charge.transaction.id', $transaction->getId()),
                $this->createEntityFilter('state', \TrustPayments\Sdk\Model\ChargeAttemptState::SUCCESSFUL)
            ));
        $query->setFilter($filter);
        $query->setNumberOfEntities(1);
        $result = $chargeAttemptService->search($transaction->getLinkedSpaceId(), $query);
        if ($result != null && ! empty($result)) {
            return current($result);
        } else {
            return null;
        }
    }

    /**
     * Returns the payment method's image.
     *
     * @param \TrustPayments\Sdk\Model\Transaction $transaction
     * @param Mage_Sales_Model_Order $order
     * @return string
     */
    protected function getPaymentMethodImage(\TrustPayments\Sdk\Model\Transaction $transaction,
        Mage_Sales_Model_Order $order)
    {
        if ($transaction->getPaymentConnectorConfiguration() != null &&
            $transaction->getPaymentConnectorConfiguration()->getPaymentMethodConfiguration() != null) {
            return $this->getImagePath(
                $transaction->getPaymentConnectorConfiguration()
                    ->getPaymentMethodConfiguration()
                    ->getResolvedImageUrl());
        } else {
            return $order->getPayment()
                ->getMethodInstance()
                ->getPaymentMethodConfiguration()
                ->getImage();
        }
    }

    /**
     *
     * @param string $resolvedImageUrl
     * @return string
     */
    protected function getImagePath($resolvedImageUrl)
    {
        $index = strpos($resolvedImageUrl, 'resource/');
        return substr($resolvedImageUrl, $index + strlen('resource/'));
    }

    /**
     * Returns the payment methods that can be used with the given quote.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return \TrustPayments\Sdk\Model\PaymentMethodConfiguration[]
     */
    public function getPossiblePaymentMethods(Mage_Sales_Model_Quote $quote)
    {
        if (! isset(self::$_possiblePaymentMethodCache[$quote->getId()]) ||
            self::$_possiblePaymentMethodCache[$quote->getId()] == null) {
            $transaction = $this->getTransactionByQuote($quote);

            try {
                $paymentMethods = $this->getTransactionService()->fetchPaymentMethods(
                    $transaction->getLinkedSpaceId(), $transaction->getId(), 'iframe');
            } catch (\WhitelabelMachineName\Sdk\ApiException $e) {
                self::$_possiblePaymentMethodCache[$quote->getId()] = array();
                throw $e;
            }

            /* @var TrustPayments_Payment_Model_Service_PaymentMethodConfiguration $paymentMethodConfigurationService */
            $paymentMethodConfigurationService = Mage::getSingleton(
                'trustpayments_payment/service_paymentMethodConfiguration');
            foreach ($paymentMethods as $paymentMethod) {
                $paymentMethodConfigurationService->updateData($paymentMethod);
            }

            self::$_possiblePaymentMethodCache[$quote->getId()] = $paymentMethods;
        }

        return self::$_possiblePaymentMethodCache[$quote->getId()];
    }

    /**
     * Update the transaction with the given order's data.
     *
     * @param \TrustPayments\Sdk\Model\Transaction $transaction
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param bool $chargeFlow
     * @return \TrustPayments\Sdk\Model\Transaction
     */
    public function confirmTransaction(\TrustPayments\Sdk\Model\Transaction $transaction,
        Mage_Sales_Model_Order $order, Mage_Sales_Model_Order_Invoice $invoice, $chargeFlow = false,
        \TrustPayments\Sdk\Model\Token $token = null)
    {
        if ($transaction->getState() == \TrustPayments\Sdk\Model\TransactionState::CONFIRMED) {
            return $transaction;
        }

        $spaceId = $transaction->getLinkedSpaceId();
        $transactionId = $transaction->getId();

        for ($i = 0; $i < 5; $i ++) {
            try {
                if ($i > 0) {
                    $transaction = $this->getTransactionService()->read($spaceId, $transactionId);
                    if ($transaction instanceof \TrustPayments\Sdk\Model\Transaction &&
                        $transaction->getState() == \TrustPayments\Sdk\Model\TransactionState::CONFIRMED) {
                        return $transaction;
                    } else if (! ($transaction instanceof \TrustPayments\Sdk\Model\Transaction) ||
                        $transaction->getState() != \TrustPayments\Sdk\Model\TransactionState::PENDING) {
                        Mage::throwException('The order failed because the payment timed out.');
                    }
                    
                    $customerId = $transaction->getCustomerId();
                    if (! empty($customerId) && $customerId != $order->getCustomerId()) {
                        Mage::throwException('The order failed because the payment timed out.');
                    }
                }

                $pendingTransaction = new \TrustPayments\Sdk\Model\TransactionPending();
                $pendingTransaction->setId($transaction->getId());
                $pendingTransaction->setVersion($transaction->getVersion());
                $this->assembleOrderTransactionData($order, $invoice, $pendingTransaction, $chargeFlow);
                if ($token != null) {
                    $pendingTransaction->setToken($token->getId());
                }

                return $this->getTransactionService()->confirm($spaceId, $pendingTransaction);
            } catch (\TrustPayments\Sdk\VersioningException $e) {
                // Try to update the transaction again, if a versioning exception occurred.
                Mage::log('A versioning exception occurred while updating a transaction: ' . $e->getMessage(), null,
                    'trustpayments.log');
            }
        }

        throw new \TrustPayments\Sdk\VersioningException();
    }

    /**
     * Assemble the transaction data for the given order and invoice.
     *
     * @param Mage_Sales_Model_Order $order
     * @param Mage_Sales_Model_Order_Invoice $invoice
     * @param \TrustPayments\Sdk\Model\AbstractTransactionPending $transaction
     * @param bool $chargeFlow
     */
    protected function assembleOrderTransactionData(Mage_Sales_Model_Order $order,
        Mage_Sales_Model_Order_Invoice $invoice, \TrustPayments\Sdk\Model\TransactionPending $transaction,
        $chargeFlow = false)
    {
        $transaction->setCurrency($order->getOrderCurrencyCode());
        $transaction->setBillingAddress($this->getOrderBillingAddress($order));
        $transaction->setShippingAddress($this->getOrderShippingAddress($order));
        $transaction->setCustomerEmailAddress(
            $this->getCustomerEmailAddress($order->getCustomerEmail(), $order->getCustomerId()));
        $customerId = $order->getCustomerId();
        if (! empty($customerId)) {
            $transaction->setCustomerId($customerId);
        }
        $transaction->setLanguage($order->getStore()
            ->getConfig('general/locale/code'));
        if ($order->getShippingAddress()) {
            $transaction->setShippingMethod(
                $this->fixLength($this->getFirstLine($order->getShippingAddress()
                    ->getShippingDescription()), 200));
        }

        if ($transaction instanceof \TrustPayments\Sdk\Model\TransactionCreate) {
            $transaction->setSpaceViewId(
                $order->getStore()
                    ->getConfig('trustpayments_payment/general/store_view_id'));
            $transaction->setDeviceSessionIdentifier($this->getDeviceSessionIdentifier());
        }

        /* @var TrustPayments_Payment_Model_Service_LineItem $lineItems */
        $lineItems = Mage::getSingleton('trustpayments_payment/service_lineItem');
        $transaction->setLineItems($lineItems->collectLineItems($order));
        $this->logAdjustmentLineItemInfo($order, $transaction);

        $transaction->setMerchantReference($order->getIncrementId());
        $transaction->setInvoiceMerchantReference($invoice->getIncrementId());
        
        $transaction->setAllowedPaymentMethodConfigurations(
            array(
                $order->getPayment()
                    ->getMethodInstance()
                    ->getPaymentMethodConfiguration()
                    ->getConfigurationId()
            ));
        
        if (!$chargeFlow) {
            $transaction->setSuccessUrl(
                Mage::getUrl('trustpayments/transaction/success',
                    array(
                        '_secure' => true,
                        'order_id' => $order->getId(),
                        'secret' => $this->getHelper()
                            ->hash($order->getId())
                    )) . '?utm_nooverride=1');
            $transaction->setFailedUrl(
                Mage::getUrl('trustpayments/transaction/failure',
                    array(
                        '_secure' => true,
                        'order_id' => $order->getId(),
                        'secret' => $this->getHelper()
                            ->hash($order->getId())
                    )) . '?utm_nooverride=1');
        }
    }

    /**
     * Checks whether an adjustment line item has been added to the transaction and adds a log message if so.
     *
     * @param Mage_Sales_Model_Order $order
     * @param \TrustPayments\Sdk\Model\TransactionPending $transaction
     */
    protected function logAdjustmentLineItemInfo(Mage_Sales_Model_Order $order,
        \TrustPayments\Sdk\Model\TransactionPending $transaction)
    {
        foreach ($transaction->getLineItems() as $lineItem) {
            if ($lineItem->getUniqueId() == 'adjustment') {
                $expectedSum = Mage::helper('trustpayments_payment/lineItem')->getTotalAmountIncludingTax(
                    $transaction->getLineItems()) - $lineItem->getAmountIncludingTax();
                Mage::log(
                    'An adjustment line item has been added to the transaction ' . $transaction->getId() .
                    ', because the line item total amount of ' .
                    $this->roundAmount($order->getGrandTotal(), $order->getOrderCurrencyCode()) .
                    ' did not match the invoice amount of ' . $expectedSum . ' of the order ' . $order->getId() . '.',
                    null, 'trustpayments.log');
                return;
            }
        }
    }

    /**
     * Returns the billing address of the given order.
     *
     * @param Mage_Sales_Model_Order $order
     * @return \TrustPayments\Sdk\Model\AddressCreate
     */
    protected function getOrderBillingAddress(Mage_Sales_Model_Order $order)
    {
        if (! $order->getBillingAddress()) {
            return null;
        }

        $address = $this->getAddress($order->getBillingAddress());
        $address->setDateOfBirth($this->getDateOfBirth($order->getCustomerDob(), $order->getCustomerId()));
        $address->setEmailAddress($this->getCustomerEmailAddress($order->getCustomerEmail(), $order->getCustomerId()));
        $address->setGender($this->getGender($order->getCustomerGender(), $order->getCustomerId()));
        return $address;
    }

    /**
     * Returns the shipping address of the given order.
     *
     * @param Mage_Sales_Model_Order $order
     * @return \TrustPayments\Sdk\Model\AddressCreate
     */
    protected function getOrderShippingAddress(Mage_Sales_Model_Order $order)
    {
        if (! $order->getShippingAddress()) {
            return null;
        }

        $address = $this->getAddress($order->getShippingAddress());
        $address->setEmailAddress($this->getCustomerEmailAddress($order->getCustomerEmail(), $order->getCustomerId()));
        return $address;
    }

    /**
     * Returns the transaction for the given quote.
     *
     * If no transaction exists, a new one is created.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return \TrustPayments\Sdk\Model\Transaction
     */
    public function getTransactionByQuote(Mage_Sales_Model_Quote $quote)
    {
        if (! isset(self::$_transactionCache[$quote->getId()]) || self::$_transactionCache[$quote->getId()] == null) {
            if ($quote->getTrustpaymentsTransactionId() == null) {
                $transaction = $this->createTransactionByQuote($quote);
            } else {
                $transaction = $this->loadAndUpdateTransaction($quote);
            }

            self::$_transactionCache[$quote->getId()] = $transaction;
        }

        return self::$_transactionCache[$quote->getId()];
    }

    /**
     * Creates a transaction for the given quote.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return \TrustPayments\Sdk\Model\TransactionCreate
     */
    protected function createTransactionByQuote(Mage_Sales_Model_Quote $quote)
    {
        $spaceId = $quote->getStore()->getConfig('trustpayments_payment/general/space_id');
        $createTransaction = new \TrustPayments\Sdk\Model\TransactionCreate();
        $createTransaction->setCustomersPresence(\TrustPayments\Sdk\Model\CustomersPresence::VIRTUAL_PRESENT);
        $createTransaction->setAutoConfirmationEnabled(false);
        $createTransaction->setChargeRetryEnabled(false);
        $this->assembleQuoteTransactionData($quote, $createTransaction);
        $transaction = $this->getTransactionService()->create($spaceId, $createTransaction);
        $quote->setTrustpaymentsSpaceId($transaction->getLinkedSpaceId());
        $quote->setTrustpaymentsTransactionId($transaction->getId());
        $quote->save();
        return $transaction;
    }

    /**
     * Loads the transaction for the given quote and updates it if necessary.
     *
     * If the transaction is not in pending state, a new one is created.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return \TrustPayments\Sdk\Model\TransactionPending
     */
    protected function loadAndUpdateTransaction(Mage_Sales_Model_Quote $quote)
    {
        for ($i = 0; $i < 5; $i ++) {
            try {
                $transaction = $this->getTransactionService()->read($quote->getTrustpaymentsSpaceId(),
                    $quote->getTrustpaymentsTransactionId());
                if (! ($transaction instanceof \TrustPayments\Sdk\Model\Transaction) ||
                    $transaction->getState() != \TrustPayments\Sdk\Model\TransactionState::PENDING) {
                    return $this->createTransactionByQuote($quote);
                }
                
                $customerId = $transaction->getCustomerId();
                if (! empty($customerId) && $customerId != $quote->getCustomerId()) {
                    return $this->createTransactionByQuote($quote);
                }

                $pendingTransaction = new \TrustPayments\Sdk\Model\TransactionPending();
                $pendingTransaction->setId($transaction->getId());
                $pendingTransaction->setVersion($transaction->getVersion());
                $this->assembleQuoteTransactionData($quote, $pendingTransaction);
                return $this->getTransactionService()->update($quote->getTrustpaymentsSpaceId(),
                    $pendingTransaction);
            } catch (\TrustPayments\Sdk\VersioningException $e) {
                // Try to update the transaction again, if a versioning exception occurred.
                Mage::log('A versioning exception occurred while updating a transaction: ' . $e->getMessage(), null,
                    'trustpayments.log');
            }
        }

        throw new \TrustPayments\Sdk\VersioningException();
    }

    /**
     * Assemble the transaction data for the given quote.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @param \TrustPayments\Sdk\Model\AbstractTransactionPending $transaction
     */
    protected function assembleQuoteTransactionData(Mage_Sales_Model_Quote $quote,
        \TrustPayments\Sdk\Model\AbstractTransactionPending $transaction)
    {
        $transaction->setCurrency($quote->getQuoteCurrencyCode());
        $transaction->setBillingAddress($this->getQuoteBillingAddress($quote));
        $transaction->setShippingAddress($this->getQuoteShippingAddress($quote));
        $transaction->setCustomerEmailAddress(
            $this->getCustomerEmailAddress($quote->getCustomerEmail(), $quote->getCustomerId()));
        $customerId = $quote->getCustomerId();
        if (! empty($customerId)) {
            $transaction->setCustomerId($customerId);
        }
        $transaction->setLanguage($quote->getStore()
            ->getConfig('general/locale/code'));
        if ($quote->getShippingAddress()) {
            $transaction->setShippingMethod(
                $this->fixLength($this->getFirstLine($quote->getShippingAddress()
                    ->getShippingDescription()), 200));
        }

        if ($transaction instanceof \TrustPayments\Sdk\Model\TransactionCreate) {
            $transaction->setSpaceViewId(
                $quote->getStore()
                    ->getConfig('trustpayments_payment/general/store_view_id'));
            $transaction->setDeviceSessionIdentifier($this->getDeviceSessionIdentifier());
        }

        /* @var TrustPayments_Payment_Model_Service_LineItem $lineItems */
        $lineItems = Mage::getSingleton('trustpayments_payment/service_lineItem');
        $transaction->setLineItems($lineItems->collectLineItems($quote));
        $transaction->setAllowedPaymentMethodConfigurations(array());
        $transaction->setFailedUrl(
            Mage::getUrl('trustpayments/transaction/failure', array(
                '_secure' => true
            )));
    }

    /**
     * Returns the billing address of the given quote.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return \TrustPayments\Sdk\Model\AddressCreate
     */
    protected function getQuoteBillingAddress(Mage_Sales_Model_Quote $quote)
    {
        if (! $quote->getBillingAddress()) {
            return null;
        }

        $address = $this->getAddress($quote->getBillingAddress());
        $address->setDateOfBirth($this->getDateOfBirth($quote->getCustomerDob(), $quote->getCustomerId()));
        $address->setEmailAddress($this->getCustomerEmailAddress($quote->getCustomerEmail(), $quote->getCustomerId()));
        $address->setGender($this->getGender($quote->getCustomerGender(), $quote->getCustomerId()));
        $address->setSalesTaxNumber($this->getTaxNumber($quote->getCustomerTaxvat(), $quote->getCustomerId()));
        return $address;
    }

    /**
     * Returns the shipping address of the given quote.
     *
     * @param Mage_Sales_Model_Quote $quote
     * @return \TrustPayments\Sdk\Model\AddressCreate
     */
    protected function getQuoteShippingAddress(Mage_Sales_Model_Quote $quote)
    {
        if (! $quote->getShippingAddress()) {
            return null;
        }

        $address = $this->getAddress($quote->getShippingAddress());
        $address->setEmailAddress($this->getCustomerEmailAddress($quote->getCustomerEmail(), $quote->getCustomerId()));
        return $address;
    }

    /**
     * Returns the customer's email address.
     *
     * @param string $customerEmailAddress
     * @param int $customerId
     * @return string
     */
    protected function getCustomerEmailAddress($customerEmailAddress, $customerId)
    {
        if ($customerEmailAddress != null) {
            return $customerEmailAddress;
        } else {
            $customer = Mage::getModel('customer/customer')->load($customerId);
            $customerMail = $customer->getEmail();
            if (! empty($customerMail)) {
                return $customerMail;
            } else {
                return null;
            }
        }
    }

    /**
     * Returns the customer's gender.
     *
     * @param string $gender
     * @param int $customerId
     * @return string
     */
    protected function getGender($gender, $customerId)
    {
        $customer = Mage::getModel('customer/customer')->load($customerId);
        if ($gender !== null) {
            $gender = $customer->getAttribute('gender')
                ->getSource()
                ->getOptionText($gender);
            return strtoupper($gender);
        }

        if ($customer->getGender() !== null) {
            $gender = $customer->getAttribute('gender')
                ->getSource()
                ->getOptionText($customer->getGender());
            return strtoupper($gender);
        }
    }

    /**
     * Returns the customer's date of birth.
     *
     * @param string $customerDob
     * @param int $customerId
     * @return string
     */
    protected function getDateOfBirth($dateOfBirth, $customerId)
    {
        if ($dateOfBirth === null) {
            $customer = Mage::getModel('customer/customer')->load($customerId);
            $dateOfBirth = $customer->getDob();
        }

        if ($dateOfBirth !== null) {
            $date = new DateTime($dateOfBirth);
            return $date->format(DateTime::W3C);
        }
    }

    /**
     * Returns the customer's tax number.
     *
     * @param string $taxNumber
     * @param int $customerId
     * @return string
     */
    protected function getTaxNumber($taxNumber, $customerId)
    {
        if ($taxNumber !== null) {
            return $taxNumber;
        }

        $customer = Mage::getModel('customer/customer')->load($customerId);
        return $customer->getTaxvat();
    }

    /**
     * Converts the Magento address model to a Trust Payments API address model.
     *
     * @param Mage_Customer_Model_Address_Abstract $customerAddress
     * @return \TrustPayments\Sdk\Model\AddressCreate
     */
    protected function getAddress(Mage_Customer_Model_Address_Abstract $customerAddress)
    {
        $address = new \TrustPayments\Sdk\Model\AddressCreate();
        $address->setSalutation($this->fixLength($this->removeLinebreaks($customerAddress->getPrefix()), 20));
        $address->setCity($this->fixLength($this->removeLinebreaks($customerAddress->getCity()), 100));
        $address->setCountry($customerAddress->getCountryId());
        $address->setFamilyName($this->fixLength($this->removeLinebreaks($customerAddress->getLastname()), 100));
        $address->setGivenName($this->fixLength($this->removeLinebreaks($customerAddress->getFirstname()), 100));
        $address->setOrganizationName($this->fixLength($this->removeLinebreaks($customerAddress->getCompany()), 100));
        $address->setPhoneNumber($customerAddress->getTelephone());
        if (! empty($customerAddress->getCountryId()) && ! empty($customerAddress->getRegionCode())) {
            $address->setPostalState($customerAddress->getCountryId() . '-' . $customerAddress->getRegionCode());
        }
        $address->setPostCode($this->fixLength($this->removeLinebreaks($customerAddress->getPostcode()), 40));
        $address->setStreet($this->fixLength($customerAddress->getStreetFull(), 300));
        return $address;
    }

    /**
     * Gets the device session identifier from the cookie.
     *
     * @return string|NULL
     */
    protected function getDeviceSessionIdentifier()
    {
        /* @var Mage_Core_Model_Cookie $cookie */
        $cookie = Mage::getSingleton('core/cookie');
        $deviceId = $cookie->get('trustpayments_device_id');
        if (! empty($deviceId)) {
            return $deviceId;
        } else {
            return null;
        }
    }
}