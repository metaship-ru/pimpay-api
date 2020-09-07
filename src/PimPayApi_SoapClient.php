<?php

namespace PimPayRu\PimPayApi;

use SoapClient;
use SoapHeader;

class PimPayApi_SoapClient extends SoapClient
{
    /** @var PimPayApi */
    protected $_api;

    /** @var resource stream context */
    protected $_context;

    /**
     * @param PimPayApi $api
     * @param array     $wsdl
     * @param array     $options
     */
    public function __construct(AbstractPimpayApi $api, $wsdl, array $options = null)
    {
        $this->_context = stream_context_create();
        $options = array_merge($options, array('stream_context' => $this->_context));

        parent::__construct($wsdl, $options);

        $this->_api = $api;

        $clientHeader    = new SoapHeader('urn:PlatformApiWsdl', 'client',    'phpSdk @ 2020-08-17 11:13:31', false);
        $versionHeader   = new SoapHeader('urn:PlatformApiWsdl', 'version',   'v2_7', false);
        $signatureHeader = new SoapHeader('urn:PlatformApiWsdl', 'signature', null, false);
        $this->__setSoapHeaders(array($clientHeader, $versionHeader, $signatureHeader));
    }

    /**
     * @return resource
     */
    public function getStreamContext()
    {
        return $this->_context;
    }

    /**
     * @param string $request
     * @param string $location
     * @param string $action
     * @param int    $version
     * @param int    $one_way
     * @return string
     */
    public function __doRequest($request, $location, $action, $version, $one_way = 0)
    {
        $request = $this->_api->getCryptoHandler()->injectSignature($request, $this);

        $this->_api->beforeSoapClientRequest($request, $location, $action, $version, $one_way);

        $response = parent::__doRequest($request, $location, $action, $version, $one_way);

        $this->_api->afterSoapClientRequest($response);

        return $response;
    }
}