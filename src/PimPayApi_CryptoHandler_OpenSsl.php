<?php

namespace PimPayRu\PimPayApi;

class PimPayApi_CryptoHandler_OpenSsl implements IPimPayApi_CryptoHandler
{
    /** @var string Private key */
    protected $_privateKey;

    /** @var string Digest algorithm */
    protected $_digestAlgo;

    /** @var array Supported digest algos */
    protected $_supportedDigestAlgos = array('SHA', 'SHA1', 'SHA224', 'SHA256', 'SHA384', 'SHA512', 'DSA', 'DSA-SHA', 'ecdsa-with-SHA1', 'MD4', 'MD5', 'RIPEMD160', 'whirlpool');

    /**
     * @param  $privateKey string Приватный ключ
     * @param  $digestAlgo string Алгоритм хэширования содержимого
     * @throws PimPayApi_Exception
     */
    public function __construct($privateKey, $digestAlgo)
    {
        if (!extension_loaded('openssl'))
        {
            throw new PimPayApi_Exception("openssl extension is not loaded.");
        }

        if (!in_array($digestAlgo, $this->_supportedDigestAlgos, true))
        {
            throw new PimPayApi_Exception("Unsupported OpenSSL digest algo: $digestAlgo.");
        }

        $this->_privateKey = $privateKey;
        $this->_digestAlgo = $digestAlgo;
    }

    /**
     * @param  string $data
     * @return string
     */
    public function sign($data)
    {
        $signature = '';
        openssl_sign($data, $signature, $this->_privateKey, $this->_digestAlgo);

        return base64_encode($signature);
    }

    /**
     * @param string $requestXml
     * @param PimPayApi_SoapClient $soapClient
     * @return string Request XML
     */
    public function injectSignature($requestXml, PimPayApi_SoapClient $soapClient)
    {
        $signature = $this->sign($requestXml);
        stream_context_set_option($soapClient->getStreamContext(), 'http', 'header', 'X-Request-Signature: ' . $signature);

        return $requestXml;
    }
}