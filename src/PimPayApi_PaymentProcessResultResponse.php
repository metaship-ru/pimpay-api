<?php

namespace PimPayRu\PimPayApi;

class PimPayApi_PaymentProcessResultResponse
{
    public $count;
    /** @var PimPayApi_PaymentProcessResultItem[] */
    public $payments;
}