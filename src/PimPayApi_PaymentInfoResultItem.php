<?php

namespace PimPayRu\PimPayApi;

class PimPayApi_PaymentInfoResultItem
{
    public $id;
    public $externalId;
    public $tin;
    public $amount;
    public $paymentSum;
    public $feeSum;
    public $status;
    public $purposeOfPayment;
    public $comment;
    public $vatValue;
}