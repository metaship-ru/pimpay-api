<?php

namespace PimPayRu\PimPayApi;

class PimPayApi_PaymentProcessResultItem
{
    public $id;
    public $externalId;
    public $status;
    public $errorMessage;

    public function __construct($id, $externalId, $status, $errorMessage)
    {
        $this->id           = $id;
        $this->externalId   = $externalId;
        $this->status       = $status;
        $this->errorMessage = $errorMessage;
    }
}