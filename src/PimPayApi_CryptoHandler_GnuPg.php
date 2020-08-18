<?php

namespace PimPayRu\PimPayApi;

class PimPayApi_CryptoHandler_GnuPg implements IPimPayApi_CryptoHandler
{
    /** @var string Содержимое переменной окружения GNUPGHOME, путь до .gnupg */
    protected $_gnuPgHome;

    /** @var string Отпечаток ключа, используемого для подписания запросов */
    protected $_singKeyFingerprint;

    /** @var string|null Пароль от ключа (если есть) */
    protected $_signKeyPassphrase;

    /** @var  resource GNUPG identifier */
    protected $_gnuPg;

    /**
     * @param  $gnuPgHome            string       Содержимое переменной окружения GNUPGHOME, путь до .gnupg
     * @param  $singKeyFingerprint   string       Отпечаток ключа, используемого для подписания запросов
     * @param  $signKeyPassphrase    string|null  Пароль от ключа (если есть)
     * @throws PimPayApi_Exception
     */
    public function __construct($gnuPgHome, $singKeyFingerprint, $signKeyPassphrase)
    {
        if (!extension_loaded('gnupg'))
        {
            throw new PimPayApi_Exception("gnupg extension is not loaded.");
        }

        $this->_gnuPgHome          = $gnuPgHome;
        $this->_singKeyFingerprint = $singKeyFingerprint;
        $this->_signKeyPassphrase  = $signKeyPassphrase;

        $this->_initGnuPg();
    }

    /**
     * @param  string $data
     * @return string
     */
    public function sign($data)
    {
        return gnupg_sign($this->_getGnuPg(), $data);
    }

    /**
     * @param string $requestXml
     * @param PimPayApi_SoapClient $soapClient
     * @return string Request XML
     */
    public function injectSignature($requestXml, PimPayApi_SoapClient $soapClient)
    {
        $dom = new DOMDocument();
        $dom->loadXML($requestXml);

        $bodyNodesList = $dom->getElementsByTagName('Body');
        $bodyNode = $bodyNodesList->item(0);

        $c14n = $bodyNode->C14N();

        $signatureNodesList = $dom->getElementsByTagName('signature');
        $signatureNode = $signatureNodesList->item(0);

        $signatureNode->nodeValue = $this->sign($c14n);

        return $dom->saveXML();
    }

    /**
     * Инициализация GNUPG
     */
    protected function _initGnuPg()
    {
        putenv('GNUPGHOME=' . $this->_gnuPgHome);

        $this->_gnuPg = gnupg_init();
        gnupg_seterrormode($this->_gnuPg, GNUPG_ERROR_EXCEPTION);

        if (!gnupg_addsignkey($this->_gnuPg, str_replace(' ', '', $this->_singKeyFingerprint), $this->_signKeyPassphrase))
        {
            throw new PimPayApi_Exception("Не удалось добавить GNUPG ключ для подписи запросов");
        }

        if (!gnupg_setsignmode($this->_gnuPg, GNUPG_SIG_MODE_DETACH))
        {
            throw new PimPayApi_Exception("Не удалось установить режим раздельной подписи");
        }

        $info = gnupg_keyinfo($this->_gnuPg, '');
    }

    /**
     * @return resource
     */
    protected function _getGnuPg()
    {
        return $this->_gnuPg;
    }
}