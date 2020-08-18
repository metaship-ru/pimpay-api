<?php

namespace PimPayRu\PimPayApi;

class PimPayApi_RussianPostPaymentInfo {
    public $id;
    public $postId;
    /**
     * @var PimPayApi_RussianPostPayment[]
     */
    public $payments = [];
}