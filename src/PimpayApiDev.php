<?php

namespace PimPayRu\PimPayApi;

class PimpayApiDev extends AbstractPimpayApi
{
    protected function _setWdsl()
    {
        $this->_wsdl = gzuncompress(base64_decode($this->_wsdlDevEncoded));
    }
}