<?php

namespace PimPayRu\PimPayApi;

/**
 * PimPay Platform Api SDK
 *
 * Дата генерации SDK: 2020-08-17 11:13:31
 * Версия API: v2_7
 * Ссылка на WSDL:     http://platform.api.pimpay.ru/v2_7/soap/wsdl
 * Ссылка сайт API:    http://platform.api.pimpay.ru/
 *
 * Минимальные требования:
 *  PHP 5.2.0+
 *  Расширения:
 *      dom, zlib, soap
 *      Для GnuPG: gnupg
 *      Для OpenSSL: openssl + php 5.3
 */
interface IPimPayApi_CryptoHandler
{
    /**
     * @param  string $data
     * @return string
     */
    function sign($data);

    /**
     * @param string  $requestXml
     * @param PimPayApi_SoapClient $soapClient
     * @return string Request XML
     */
    function injectSignature($requestXml, PimPayApi_SoapClient $soapClient);
}