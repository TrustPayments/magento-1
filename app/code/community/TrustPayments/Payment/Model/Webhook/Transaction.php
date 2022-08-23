<?php

use \TrustPayments\Sdk\Model\Gender;
use \TrustPayments\Sdk\Model\Transaction;
use \TrustPayments\Sdk\Model\TransactionState;
use \TrustPayments\Sdk\Service\TransactionService;

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
 * Webhook processor to handle transaction state transitions.
 */
class TrustPayments_Payment_Model_Webhook_Transaction extends TrustPayments_Payment_Model_Webhook_AbstractOrderRelated
{

    /**
     *
     * @see TrustPayments_Payment_Model_Webhook_AbstractOrderRelated::loadEntity()
     * @return Transaction
     */
    protected function loadEntity(TrustPayments_Payment_Model_Webhook_Request $request)
    {
        $transactionService = new TransactionService(
            Mage::helper('trustpayments_payment')->getApiClient());
        return $transactionService->read($request->getSpaceId(), $request->getEntityId());
    }

    protected function getTransactionId($transaction)
    {
        /* @var Transaction $transaction */
        return $transaction->getId();
    }

    protected function processOrderRelatedInner(Mage_Sales_Model_Order $order, $transaction)
    {
        /* @var Transaction $transaction */
        /* @var TrustPayments_Payment_Model_Entity_TransactionInfo $transactionInfo */
        $transactionInfo = Mage::getModel('trustpayments_payment/entity_transactionInfo')->loadByOrder($order);

        $finalStates = [
            TransactionState::FAILED,
            TransactionState::VOIDED,
            TransactionState::DECLINE,
            TransactionState::FULFILL
        ];

        if (!in_array($transactionInfo->getState(), $finalStates)) {
            /* @var TrustPayments_Payment_Model_Service_Transaction $transactionStoreService */
            $transactionStoreService = Mage::getSingleton('trustpayments_payment/service_transaction');
            $transactionStoreService->updateTransactionInfo($transaction, $order);

            if ($transaction->getState() != $transactionInfo->getState()) {
                switch ($transaction->getState()) {
                    case TransactionState::AUTHORIZED:
                    case TransactionState::COMPLETED:
                        $this->authorize($transaction, $order);
                        break;
                    case TransactionState::DECLINE:
                        $this->authorize($transaction, $order);
                        $this->decline($transaction, $order);
                        break;
                    case TransactionState::FAILED:
                        $this->failed($transaction, $order);
                        break;
                    case TransactionState::FULFILL:
                        $this->authorize($transaction, $order);
                        $this->fulfill($transaction, $order);
                        break;
                    case TransactionState::VOIDED:
                        $this->authorize($transaction, $order);
                        $this->voided($transaction, $order);
                        break;
                    default:
                        // Nothing to do.
                        break;
                }
            }
        }
    }

    protected function authorize(Transaction $transaction, Mage_Sales_Model_Order $order)
    {
        if (! $order->getTrustpaymentsAuthorized()) {
            $order->getPayment()
                ->setTransactionId($transaction->getLinkedSpaceId() . '_' . $transaction->getId())
                ->setIsTransactionClosed(false);
            $order->getPayment()->registerAuthorizationNotification($transaction->getAuthorizationAmount());
            $this->sendOrderEmail($order);
            if ($transaction->getState() != TransactionState::FULFILL) {
                $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, 'processing_trustpayments',
                    Mage::helper('trustpayments_payment')->__(
                        'The order should not be fulfilled yet, as the payment is not guaranteed.'));
            }
            $order->setTrustpaymentsAuthorized(true);
            $order->save();
            try {
                $this->updateShopCustomer($transaction, $order);
            } catch (Exception $e) {
                // Try to update the customer, ignore if it fails.
                Mage::log('Failed to update the customer: ' . $e->getMessage(), null, 'trustpayments.log');
            }
        }
    }

    protected function decline(Transaction $transaction, Mage_Sales_Model_Order $order)
    {
        if ($order->getState() != Mage_Sales_Model_Order::STATE_CANCELED) {
            $order->setTrustpaymentsPaymentInvoiceAllowManipulation(true);
            $order->getPayment()->setNotificationResult(true);
            $order->getPayment()->registerPaymentReviewAction(Mage_Sales_Model_Order_Payment::REVIEW_ACTION_DENY, false);
        }

        $order->save();
    }

    protected function failed(Transaction $transaction, Mage_Sales_Model_Order $order)
    {
        $invoice = $this->getInvoiceForTransaction($transaction->getLinkedSpaceId(), $transaction->getId(), $order);
        if ($invoice != null && $invoice->canCancel()) {
            $order->setTrustpaymentsPaymentInvoiceAllowManipulation(true);
            $invoice->cancel();
            $order->addRelatedObject($invoice);
        }

        if (! $order->isCanceled()) {
            $order->registerCancellation(null, false)->save();
        } else {
            Mage::log('Tried to cancel the order ' . $order->getIncrementId() . ' but it was already cancelled.', null,
                'trustpayments.log');
        }
    }

    protected function fulfill(Transaction $transaction, Mage_Sales_Model_Order $order)
    {
        if ($order->getState() == Mage_Sales_Model_Order::STATE_PAYMENT_REVIEW) {
            $order->getPayment()->setNotificationResult(true);
            $order->getPayment()->registerPaymentReviewAction(Mage_Sales_Model_Order_Payment::REVIEW_ACTION_ACCEPT,
                false);
        } elseif ($order->getStatus() == 'processing_trustpayments') {
            $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true,
                Mage::helper('trustpayments_payment')->__('The order can be fulfilled now.'));
        }

        $order->save();
    }

    protected function voided(Transaction $transaction, Mage_Sales_Model_Order $order)
    {
        $order->getPayment()->registerVoidNotification();
        $invoice = $this->getInvoiceForTransaction($transaction->getLinkedSpaceId(), $transaction->getId(), $order);
        if ($invoice != null && $invoice->canCancel()) {
            $order->setTrustpaymentsPaymentInvoiceAllowManipulation(true);
            $invoice->cancel();
            $order->addRelatedObject($invoice);
        }

        $order->save();
    }

    /**
     * Sends the order email if not already sent.
     *
     * @param Mage_Sales_Model_Order $order
     */
    protected function sendOrderEmail(Mage_Sales_Model_Order $order)
    {
        if ($order->getStore()->getConfig('trustpayments_payment/email/order') &&
            $order->getPayment()
                ->getMethodInstance()
                ->getConfigData('order_email') && ! $order->getEmailSent()) {
            $order->sendNewOrderEmail();
        }
    }

    /**
     * Returns the invoice for the given transaction.
     *
     * @param int $spaceId
     * @param int $transactionId
     * @param Mage_Sales_Model_Order $order
     * @return Mage_Sales_Model_Order_Invoice
     */
    protected function getInvoiceForTransaction($spaceId, $transactionId, Mage_Sales_Model_Order $order)
    {
        foreach ($order->getInvoiceCollection() as $invoice) {
            if (strpos($invoice->getTransactionId(), $spaceId . '_' . $transactionId) === 0 &&
                $invoice->getState() != Mage_Sales_Model_Order_Invoice::STATE_CANCELED) {
                $invoice->load($invoice->getId());
                return $invoice;
            }
        }

        return null;
    }

    protected function updateShopCustomer(Transaction $transaction, Mage_Sales_Model_Order $order)
    {
        if ($order->getCustomerIsGuest() || $order->getBillingAddress() == null ||
            ! $order->getBillingAddress()->getCustomerAddressId()) {
            return;
        }

        /* @var Mage_Customer_Model_Customer $customer */
        $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());

        $billingAddress = $customer->getAddressById($order->getBillingAddress()
            ->getCustomerAddressId());

        $this->updateDateOfBirth($customer, $transaction);
        $this->updateSalutation($customer, $billingAddress, $transaction);
        $this->updateGender($customer, $transaction);
        $this->updateSalesTaxNumber($customer, $billingAddress, $transaction);
        $this->updateCompany($customer, $billingAddress, $transaction);

        $billingAddress->save();
        $customer->save();
    }

    protected function updateDateOfBirth(Mage_Customer_Model_Customer $customer, Transaction $transaction)
    {
        if ($customer->getDob() == null && $transaction->getBillingAddress()->getDateOfBirth() != null) {
            $customer->setDob($transaction->getBillingAddress()
                ->getDateOfBirth());
        }
    }

    protected function updateSalutation(Mage_Customer_Model_Customer $customer, Mage_Customer_Model_Address $billingAddress, Transaction $transaction)
    {
        if ($transaction->getBillingAddress()->getSalutation() != null) {
            if ($customer->getPrefix() == null) {
                $customer->setPrefix($transaction->getBillingAddress()
                    ->getSalutation());
            }

            if ($billingAddress->getPrefix() == null) {
                $billingAddress->setPrefix($transaction->getBillingAddress()
                    ->getSalutation());
            }
        }
    }

    protected function updateGender(Mage_Customer_Model_Customer $customer, Transaction $transaction)
    {
        if ($customer->getGender() == null && $transaction->getBillingAddress()->getGender() != null) {
            if ($transaction->getBillingAddress()->getGender() == Gender::MALE) {
                $customer->setGender(1);
            } elseif ($transaction->getBillingAddress()->getGender() == Gender::FEMALE) {
                $customer->setGender(2);
            }
        }
    }

    protected function updateSalesTaxNumber(Mage_Customer_Model_Customer $customer,
        Mage_Customer_Model_Address $billingAddress, Transaction $transaction)
    {
        if ($transaction->getBillingAddress()->getSalesTaxNumber() != null) {
            if ($customer->getTaxvat() == null) {
                $customer->setTaxvat($transaction->getBillingAddress()
                    ->getSalesTaxNumber());
            }

            if ($billingAddress->getVatId() == null) {
                $billingAddress->setVatId($transaction->getBillingAddress()
                    ->getSalesTaxNumber());
            }
        }
    }

    protected function updateCompany(Mage_Customer_Model_Customer $customer, Mage_Customer_Model_Address $billingAddress,
        Transaction $transaction)
    {
        if ($billingAddress->getCompany() == null && $transaction->getBillingAddress()->getOrganizationName() != null) {
            $billingAddress->setCompany($transaction->getBillingAddress()
                ->getOrganizationName());
        }
    }
}