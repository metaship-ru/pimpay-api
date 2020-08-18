<?php

namespace PimPayRu\PimPayApi;

class PimPayApi extends AbstractPimpayApi
{
    protected function _setWdsl()
    {
        $this->_wsdl = gzuncompress(base64_decode($this->_wsdlEncoded));
    }
}


