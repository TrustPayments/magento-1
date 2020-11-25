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
 * This service provides functions to deal with Trust Payments refunds.
 */
class TrustPayments_Payment_Model_Service_Refund extends TrustPayments_Payment_Model_Service_Abstract
{

    /**
     * The refund API service.
     *
     * @var \TrustPayments\Sdk\Service\RefundService
     */
    protected $_refundService;

    /**
     * Returns the refund by the given external id.
     *
     * @param int $spaceId
     * @param string $externalId
     * @return \TrustPayments\Sdk\Model\Refund
     */
    public function getRefundByExternalId($spaceId, $externalId)
    {
        $query = new \TrustPayments\Sdk\Model\EntityQuery();
        $query->setFilter($this->createEntityFilter('externalId', $externalId));
        $query->setNumberOfEntities(1);
        $result = $this->getRefundService()->search($spaceId, $query);
        if ($result != null && ! empty($result)) {
            return current($result);
        } else {
            Mage::throwException('The refund could not be found.');
        }
    }

    public function createForPayment(Mage_Sales_Model_Order_Payment $payment)
    {
        /* @var TrustPayments_Payment_Model_Service_Transaction $transactionService */
        $transactionService = Mage::getSingleton('trustpayments_payment/service_transaction');
        $transaction = new \TrustPayments\Sdk\Model\Transaction();
        $transaction->setId($payment->getOrder()
            ->getTrustpaymentsTransactionId());

        $baseLineItems = $this->getBaseLineItems($payment->getOrder()
            ->getTrustpaymentsSpaceId(), $transaction);
        $reductions = array();
        foreach ($baseLineItems as $lineItem) {
            $reduction = new \TrustPayments\Sdk\Model\LineItemReductionCreate();
            $reduction->setLineItemUniqueId($lineItem->getUniqueId());
            $reduction->setQuantityReduction($lineItem->getQuantity());
            $reduction->setUnitPriceReduction(0);
            $reductions[] = $reduction;
        }

        $refund = new \TrustPayments\Sdk\Model\RefundCreate();
        $refund->setExternalId(uniqid($payment->getOrder()
            ->getId() . '-'));
        $refund->setReductions($reductions);
        $refund->setTransaction($transaction);
        $refund->setType(\TrustPayments\Sdk\Model\RefundType::MERCHANT_INITIATED_ONLINE);
        return $refund;
    }

    /**
     * Creates a refund request model for the given creditmemo.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     * @return \TrustPayments\Sdk\Model\RefundCreate
     */
    public function create(Mage_Sales_Model_Order_Payment $payment, Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        /* @var TrustPayments_Payment_Model_Service_Transaction $transactionService */
        $transactionService = Mage::getSingleton('trustpayments_payment/service_transaction');
        $transaction = new \TrustPayments\Sdk\Model\Transaction();
        $transaction->setId($payment->getOrder()
            ->getTrustpaymentsTransactionId());

        $baseLineItems = $this->getBaseLineItems($creditmemo->getOrder()
            ->getTrustpaymentsSpaceId(), $transaction);

        /* @var TrustPayments_Payment_Helper_LineItem $lineItemHelper */
        $lineItemHelper = Mage::helper('trustpayments_payment/lineItem');
        if ($this->compareAmounts($lineItemHelper->getTotalAmountIncludingTax($baseLineItems),
            $creditmemo->getGrandTotal(), $creditmemo->getOrderCurrencyCode()) == 0) {
            $reductions = array();
            foreach ($baseLineItems as $lineItem) {
                $reduction = new \TrustPayments\Sdk\Model\LineItemReductionCreate();
                $reduction->setLineItemUniqueId($lineItem->getUniqueId());
                $reduction->setQuantityReduction($lineItem->getQuantity());
                $reduction->setUnitPriceReduction(0);
                $reductions[] = $reduction;
            }
        } else {
            $reductions = $this->getProductReductions($creditmemo, $baseLineItems);
            $shippingReduction = $this->getShippingReduction($creditmemo);
            if ($shippingReduction != null) {
                $reductions[] = $shippingReduction;
            }
        }

        $refund = new \TrustPayments\Sdk\Model\RefundCreate();
        $refund->setExternalId(uniqid($creditmemo->getOrderId() . '-'));

        if ($this->validateReductions($creditmemo, $transaction, $reductions, $baseLineItems)) {
            $refund->setReductions($reductions);
        } else {
            $refund->setAmount($this->roundAmount($creditmemo->getGrandTotal(), $creditmemo->getOrderCurrencyCode()));
        }

        $refund->setTransaction($transaction);
        $refund->setType(\TrustPayments\Sdk\Model\RefundType::MERCHANT_INITIATED_ONLINE);
        return $refund;
    }

    /**
     * Validates whether the given reductions total amount matches the one of the creditmemo.
     *
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     * @param \TrustPayments\Sdk\Model\Transaction $transaction
     * @param \TrustPayments\Sdk\Model\LineItemReductionCreate[] $reductions
     * @param \TrustPayments\Sdk\Model\LineItem[] $baseLineItems
     * @return boolean
     */
    protected function validateReductions(Mage_Sales_Model_Order_Creditmemo $creditmemo,
        \TrustPayments\Sdk\Model\Transaction $transaction, array $reductions, array $baseLineItems)
    {
        /* @var TrustPayments_Payment_Helper_LineItem $lineItemHelper */
        $lineItemHelper = Mage::helper('trustpayments_payment/lineItem');
        $reductionAmount = $lineItemHelper->getReductionAmount($baseLineItems, $reductions,
            $creditmemo->getOrderCurrencyCode());

        if ($this->compareAmounts($reductionAmount, $creditmemo->getGrandTotal(), $creditmemo->getOrderCurrencyCode()) != 0) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Returns the line item reductions for the creditmemo's items.
     *
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     * @param \TrustPayments\Sdk\Model\LineItem[] $baseLineItems
     * @return \TrustPayments\Sdk\Model\LineItemReductionCreate[]
     */
    protected function getProductReductions(Mage_Sales_Model_Order_Creditmemo $creditmemo, array $baseLineItems)
    {
        $reductions = array();
        foreach ($creditmemo->getAllItems() as $item) {
            /* @var Mage_Sales_Model_Order_Creditmemo_Item $item */
            $orderItem = $item->getOrderItem();
            if ($orderItem->getParentItemId() != null &&
                $orderItem->getParentItem()->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE) {
                continue;
            }

            if ($orderItem->getProductType() == Mage_Catalog_Model_Product_Type::TYPE_BUNDLE &&
                $orderItem->getParentItemId() == null) {
                continue;
            }

            $reduction = new \TrustPayments\Sdk\Model\LineItemReductionCreate();
            $reduction->setLineItemUniqueId($orderItem->getQuoteItemId());
            $reduction->setQuantityReduction($item->getQty());
            $reduction->setUnitPriceReduction(0);
            $reductions[] = $reduction;

            if ($orderItem->getDiscountAmount() != 0) {
                if ($this->hasBaseLineItem($baseLineItems, $orderItem->getQuoteItemId() . '-discount')) {
                    $reduction = new \TrustPayments\Sdk\Model\LineItemReductionCreate();
                    $reduction->setLineItemUniqueId($orderItem->getQuoteItemId() . '-discount');
                    $reduction->setQuantityReduction($item->getQty());
                    $reduction->setUnitPriceReduction(0);
                    $reductions[] = $reduction;
                }
            }
        }

        return $reductions;
    }

    /**
     *
     * @param \TrustPayments\Sdk\Model\LineItem[] $baseLineItems
     * @param string $uniqueId
     * @return boolean
     */
    protected function hasBaseLineItem(array $baseLineItems, $uniqueId)
    {
        foreach ($baseLineItems as $lineItem) {
            if ($lineItem->getUniqueId() == $uniqueId) {
                return true;
            }
        }
        return false;
    }

    /**
     * Returns the line item reduction for the shipping.
     *
     * @param Mage_Sales_Model_Order_Creditmemo $creditmemo
     * @return \TrustPayments\Sdk\Model\LineItemReductionCreate
     */
    protected function getShippingReduction(Mage_Sales_Model_Order_Creditmemo $creditmemo)
    {
        if ($creditmemo->getShippingAmount() > 0) {
            $reduction = new \TrustPayments\Sdk\Model\LineItemReductionCreate();
            $reduction->setLineItemUniqueId('shipping');
            if ($creditmemo->getShippingAmount() + $creditmemo->getShippingTaxAmount() ==
                $creditmemo->getOrder()->getShippingInclTax()) {
                $reduction->setQuantityReduction(1);
                $reduction->setUnitPriceReduction(0);
            } else {
                $reduction->setQuantityReduction(0);
                $reduction->setUnitPriceReduction(
                    $this->roundAmount($creditmemo->getShippingAmount() + $creditmemo->getShippingTaxAmount(),
                        $creditmemo->getOrderCurrencyCode()));
            }

            return $reduction;
        } else {
            return null;
        }
    }

    /**
     * Sends the refund to the gateway.
     *
     * @param int $spaceId
     * @param \TrustPayments\Sdk\Model\RefundCreate $refund
     * @return \TrustPayments\Sdk\Model\Refund
     */
    public function refund($spaceId, \TrustPayments\Sdk\Model\RefundCreate $refund)
    {
        return $this->getRefundService()->refund($spaceId, $refund);
    }

    /**
     * Registers a refund notification, creates a creditmemo.
     *
     * @param \TrustPayments\Sdk\Model\Refund $refund
     * @param Mage_Sales_Model_Order $order
     */
    public function registerRefundNotification(\TrustPayments\Sdk\Model\Refund $refund,
        Mage_Sales_Model_Order $order)
    {
        /* @var Mage_Sales_Model_Service_Order $serviceModel */
        $serviceModel = Mage::getModel('sales/service_order', $order);
        $creditmemo = $serviceModel->prepareCreditmemo($this->getCreditmemoData($refund, $order));

        $creditmemo->setPaymentRefundDisallowed(true);
        $creditmemo->setAutomaticallyCreated(true);
        $creditmemo->register();
        $creditmemo->addComment(Mage::helper('sales')->__('Credit memo has been created automatically'));
        $creditmemo->setTrustpaymentsExternalId($refund->getExternalId());
        $creditmemo->save();

        $this->updateTotals($order->getPayment(),
            array(
                'amount_refunded' => $creditmemo->getGrandTotal(),
                'base_amount_refunded_online' => $refund->getAmount()
            ));

        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true,
            Mage::helper('sales')->__('Registered notification about refunded amount of %s.',
                $this->formatPrice($order, $refund->getAmount())));
        $order->save();
    }

    /**
     * Returns the data needed to create the creditmemo.
     *
     * @param \TrustPayments\Sdk\Model\Refund $refund
     * @param Mage_Sales_Model_Order $order
     * @return array
     */
    protected function getCreditmemoData(\TrustPayments\Sdk\Model\Refund $refund, Mage_Sales_Model_Order $order)
    {
        $orderItemMap = array();
        foreach ($order->getAllItems() as $orderItem) {
            /* @var Mage_Sales_Model_Order_Item $orderItem */
            $orderItemMap[$orderItem->getQuoteItemId()] = $orderItem;
        }

        $lineItems = array();
        foreach ($refund->getTransaction()->getLineItems() as $lineItem) {
            $lineItems[$lineItem->getUniqueId()] = $lineItem;
        }

        $baseLineItems = array();
        foreach ($this->getBaseLineItems($order->getTrustpaymentsSpaceId(), $refund->getTransaction(), $refund) as $lineItem) {
            $baseLineItems[$lineItem->getUniqueId()] = $lineItem;
        }

        $refundQuantities = array();
        foreach ($order->getAllItems() as $orderItem) {
            $refundQuantities[$orderItem->getQuoteItemId()] = 0;
        }

        $creditmemoAmount = 0;
        $shippingAmount = 0;
        $shippingTaxRate = 0;
        foreach ($refund->getReductions() as $reduction) {
            $lineItem = $lineItems[$reduction->getLineItemUniqueId()];
            switch ($lineItem->getType()) {
                case \TrustPayments\Sdk\Model\LineItemType::PRODUCT:
                    if ($reduction->getQuantityReduction() > 0) {
                        $refundQuantities[$orderItemMap[$reduction->getLineItemUniqueId()]->getId()] = $reduction->getQuantityReduction();
                        $creditmemoAmount += $reduction->getQuantityReduction() * $lineItem->getUnitPriceIncludingTax();
                    }
                    break;
                case \TrustPayments\Sdk\Model\LineItemType::FEE:
                case \TrustPayments\Sdk\Model\LineItemType::DISCOUNT:
                    break;
                case \TrustPayments\Sdk\Model\LineItemType::SHIPPING:
                    $shippingTaxRate = $baseLineItems[$reduction->getLineItemUniqueId()]->getAggregatedTaxRate();
                    if ($reduction->getQuantityReduction() > 0) {
                        $shippingAmount = $baseLineItems[$reduction->getLineItemUniqueId()]->getAmountIncludingTax();
                    } elseif ($reduction->getUnitPriceReduction() > 0) {
                        $shippingAmount = $reduction->getUnitPriceReduction();
                    } else {
                        $shippingAmount = 0;
                    }

                    if ($shippingAmount <= $order->getShippingInclTax() - $order->getShippingRefunded()) {
                        $creditmemoAmount += $shippingAmount;
                    } else {
                        $shippingAmount = 0;
                    }
                    break;
            }
        }

        $positiveAdjustment = 0;
        $negativeAdjustment = 0;
        if ($creditmemoAmount > $refund->getAmount()) {
            $negativeAdjustment = $creditmemoAmount - $refund->getAmount();
        } elseif ($creditmemoAmount < $refund->getAmount()) {
            $positiveAdjustment = $refund->getAmount() - $creditmemoAmount;
        }

        $shippingAmountExcludingTax = 0;
        if ($shippingAmount > 0) {
            $shippingAmountExcludingTax = $this->roundAmount($shippingAmount / (1 + $shippingTaxRate / 100), $refund->getTransaction()->getCurrency());
        }
        
        return array(
            'qtys' => $refundQuantities,
            'shipping_amount' => $shippingAmountExcludingTax,
            'adjustment_positive' => $positiveAdjustment,
            'adjustment_negative' => $negativeAdjustment
        );
    }

    /**
     * Returns the formatted price.
     *
     * @param Mage_Sales_Model_Order $order
     * @param number $amount
     * @return string
     */
    protected function formatPrice(Mage_Sales_Model_Order $order, $amount)
    {
        return $order->getBaseCurrency()->formatTxt($amount, array());
    }

    /**
     * Updates the total of the order payment.
     *
     * @param Mage_Sales_Model_Order_Payment $payment
     * @param array $data
     */
    protected function updateTotals(Mage_Sales_Model_Order_Payment $payment, array $data)
    {
        foreach ($data as $key => $amount) {
            if (null !== $amount) {
                $was = $payment->getDataUsingMethod($key);
                $payment->setDataUsingMethod($key, $was + $amount);
            }
        }
    }

    /**
     * Returns the line items that are to be used to calculate the refund.
     *
     * This returns the line items of the latest refund if there is one or else of the transaction invoice.
     *
     * @param int $spaceId
     * @param \TrustPayments\Sdk\Model\Transaction $transaction
     * @param \TrustPayments\Sdk\Model\Refund $refund
     * @return \TrustPayments\Sdk\Model\LineItem[]
     */
    protected function getBaseLineItems($spaceId, \TrustPayments\Sdk\Model\Transaction $transaction,
        \TrustPayments\Sdk\Model\Refund $refund = null)
    {
        $lastSuccessfulRefund = $this->getLastSuccessfulRefund($spaceId, $transaction, $refund);
        if ($lastSuccessfulRefund) {
            return $lastSuccessfulRefund->getReducedLineItems();
        } else {
            return $this->getTransactionInvoice($spaceId, $transaction)->getLineItems();
        }
    }

    /**
     * Returns the transaction invoice for the given transaction.
     *
     * @param int $spaceId
     * @param \TrustPayments\Sdk\Model\Transaction $transaction
     * @throws Exception
     * @return \TrustPayments\Sdk\Model\TransactionInvoice
     */
    protected function getTransactionInvoice($spaceId, \TrustPayments\Sdk\Model\Transaction $transaction)
    {
        $query = new \TrustPayments\Sdk\Model\EntityQuery();

        $filter = new \TrustPayments\Sdk\Model\EntityQueryFilter();
        $filter->setType(\TrustPayments\Sdk\Model\EntityQueryFilterType::_AND);
        $filter->setChildren(
            array(
                $this->createEntityFilter('state', \TrustPayments\Sdk\Model\TransactionInvoiceState::CANCELED,
                    \TrustPayments\Sdk\Model\CriteriaOperator::NOT_EQUALS),
                $this->createEntityFilter('completion.lineItemVersion.transaction.id', $transaction->getId())
            ));
        $query->setFilter($filter);

        $query->setNumberOfEntities(1);

        $invoiceService = new \TrustPayments\Sdk\Service\TransactionInvoiceService(
            $this->getHelper()->getApiClient());
        $result = $invoiceService->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        } else {
            Mage::throwException('The transaction invoice could not be found.');
        }
    }

    /**
     * Returns the last successful refund of the given transaction, excluding the given refund.
     *
     * @param int $spaceId
     * @param \TrustPayments\Sdk\Model\Transaction $transaction
     * @param \TrustPayments\Sdk\Model\Refund $refund
     * @return \TrustPayments\Sdk\Model\Refund
     */
    protected function getLastSuccessfulRefund($spaceId, \TrustPayments\Sdk\Model\Transaction $transaction,
        \TrustPayments\Sdk\Model\Refund $refund = null)
    {
        $query = new \TrustPayments\Sdk\Model\EntityQuery();

        $filter = new \TrustPayments\Sdk\Model\EntityQueryFilter();
        $filter->setType(\TrustPayments\Sdk\Model\EntityQueryFilterType::_AND);
        $filters = array(
            $this->createEntityFilter('state', \TrustPayments\Sdk\Model\RefundState::SUCCESSFUL),
            $this->createEntityFilter('transaction.id', $transaction->getId())
        );
        if ($refund != null) {
            $filters[] = $this->createEntityFilter('id', $refund->getId(),
                \TrustPayments\Sdk\Model\CriteriaOperator::NOT_EQUALS);
        }

        $filter->setChildren($filters);
        $query->setFilter($filter);

        $query->setOrderBys(
            array(
                $this->createEntityOrderBy('createdOn', \TrustPayments\Sdk\Model\EntityQueryOrderByType::DESC)
            ));

        $query->setNumberOfEntities(1);

        $result = $this->getRefundService()->search($spaceId, $query);
        if (! empty($result)) {
            return $result[0];
        } else {
            return false;
        }
    }

    /**
     * Returns the refund API service.
     *
     * @return \TrustPayments\Sdk\Service\RefundService
     */
    protected function getRefundService()
    {
        if ($this->_refundService == null) {
            $this->_refundService = new \TrustPayments\Sdk\Service\RefundService(
                $this->getHelper()->getApiClient());
        }

        return $this->_refundService;
    }
}