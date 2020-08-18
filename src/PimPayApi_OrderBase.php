<?php

namespace PimPayRu\PimPayApi;

class PimPayApi_OrderBase {
    public $postId;
    public $params;

    public function __construct($postIdOrParams)
    {
        if ($postIdOrParams instanceof PimPayApi_OrderParams)
        {
            $this->params = $postIdOrParams;
        }
        elseif (is_scalar($postIdOrParams))
        {
            $this->postId = $postIdOrParams;
        }
        else
        {
            throw new \PimPayApi_Exception("Either postId or instance of PimPayApi_OrderParams was expected.");
        }
    }
}