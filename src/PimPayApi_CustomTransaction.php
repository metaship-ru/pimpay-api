<?php

namespace PimPayRu\PimPayApi;

class PimPayApi_CustomTransaction {
    public $value;
    public $comment;

    public  function __construct($value, $comment)
    {
        $this->value = $value;
        $this->comment = $comment;
    }
}