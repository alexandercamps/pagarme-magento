<?php

class PagarMe_Core_Model_Postback_Boleto extends Mage_Core_Model_Abstract
{
    const POSTBACK_STATUS_PAID = 'paid';
    const POSTBACK_STATUS_REFUNDED = 'refunded';

    /**
     * @var PagarMe_Core_Model_Service_Invoice
     */
    protected $invoiceService;

    /**
     * @var array
     */
    protected $validStatus = [
        self::POSTBACK_STATUS_PAID,
        self::POSTBACK_STATUS_REFUNDED
    ];

    /**
     * @param Mage_Sales_Model_Order $order
     * @param type $currentStatus
     *
     * @return bool
     */
    private function canProceedWithPostback(Mage_Sales_Model_Order $order, $currentStatus)
    {
        return $order->canInvoice() && $this->isValidStatus($currentStatus);
    }

    /**
     * @param string $status
     */
    private function isValidStatus($status)
    {
        return in_array($status, $this->validStatus);
    }

    /**
     * @codeCoverageIgnore
     * @return PagarMe_Core_Model_Service_Order
     */
    public function getOrderService()
    {
        if (is_null($this->orderService)) {
            $this->orderService = Mage::getModel('pagarme_core/service_order');
        }

        return $this->orderService;
    }

    /**
     * @codeCoverageIgnore
     * @param PagarMe_Core_Model_Service_Order $orderService
     */
    public function setOrderService(PagarMe_Core_Model_Service_Order $orderService)
    {
        $this->orderService = $orderService;
    }

    /**
     * @codeCoverageIgnore
     * @return PagarMe_Core_Model_Service_Invoice
     */
    public function getInvoiceService()
    {
        if (is_null($this->invoiceService)) {
            $this->invoiceService = Mage::getModel('pagarme_core/service_invoice');
        }

        return $this->invoiceService;
    }

    /**
     * @codeCoverageIgnore
     * @param PagarMe_Core_Model_Service_Invoice $invoiceService
     */
    public function setInvoiceService(PagarMe_Core_Model_Service_Invoice $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * @param int $transactionId
     * @param string $currentStatus
     *
     * @return type
     * @throws Exception
     */
    public function processPostback($transactionId, $currentStatus)
    {
        $order = $this->getOrderService()
            ->getOrderByTransactionId($transactionId);

        if (!$this->canProceedWithPostback($order, $currentStatus)) {
            throw new Exception(
                Mage::helper('pagarme_core')->__('Can\'t proccess postback.')
            );
        }

        switch ($currentStatus) {
            case self::POSTBACK_STATUS_PAID:
                $this->setOrderAsPaid($order);
                break;
            case self::POSTBACK_STATUS_REFUNDED:
                $this->setOrderAsRefunded($order);
                break;
        }

        return $order;
    }

    public function setOrderAsPaid($order)
    {
        $invoice = $this->getInvoiceService()
            ->createInvoiceFromOrder($order);

        $invoice->register()
            ->pay();

        $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING, true, "pago");

        $transactionSave = Mage::getModel('core/resource_transaction')
            ->addObject($order)
            ->addObject($invoice)
            ->save();
    }

    public function setOrderAsRefunded($order)
    {
        $order->setState(Mage_Sales_Model_Order::STATE_CLOSED);
    }
}
