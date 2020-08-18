<?php

namespace PimPayRu\PimPayApi;

class PimPayApi_VerificationRow {
    public $orderId;
    public $paymentFromRecipient;
    public $paymentToPimPay;
    public $deliveryCost;
    public $cashService;
    public $insurance;
    /** @var PimPayApi_CustomTransaction[] $customTransactions */
    public $customTransactions = array();
}