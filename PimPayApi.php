<?php
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


abstract class AbstractPimpayApi
{
    /** @var string Сжатый WSDL */
    protected $_wsdlEncoded = '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';

    /** @var string Сжатый WSDL с адресом тестового стенда */
    protected $_wsdlDevEncoded = 'eNrtXVtzG8eVfuevQHFf7FqLIGVvnLAkpShSslixJBZJKZs4LlZzpgH2cjAz6u4hCXtdZUvJplJx2VVb+7APW3t520fZG8WKLMl/AfhH290zAOcKYHoaBDA4rMQ2ZqZvX58+fW59+sYvLzpO4wxTRjz35urG2vpqA7uWZxO3fXM14K1rP1/95a2VGzZuEZdw8RFriBIuu7l6wrm/2Wwy6wR3EFsTT5mH/DWPtpvnzHaaqyuN+J8qtcllyYC6m3sO4i2PdrZ88mvxde7Hsr7x7chf+Y1dMHtY/Pz8fO38fVXq+vr6RvMf7398oGrMLSkr1hyh/OSagHBkcdXnAc7pelzUwTdXYwCl3nNE25g/EF8xH1k4H89bK6LQDdnNTduzgg52OZLTd0vVdYDpGbHwZiNWTL14HFLCZuPs+tGH6snDcxfTzcbHH2839nbvq0e7riyiqttsRKP0o4rWkE/WfNLxUXeNBs2w5zeaeR1R78Iu8q6PWdi1G2LWNkPMJh1pHJywuGjfwYei0ghMTlz5K/Vt+k+VpZhxSizZxcYxYqKwqlE8c9ux8iu5hX3EOaZu4ww5gSj5yfq1X3z6+cb6exvXv1htNKMBNlPN3MrtSzM5kElG2fGOSfjgqgf6u7//nf35xsZVDDJwiZz9PUViO9ghgnl1DwRNBczwyAvLY1eQMVVUPADAx65cysPxT1yOYh9RbJcuSFxOkcsIL12ScU+nQTuEWqOkGCAnyNnHXCzf0qWpXjHHY+WhwZR6tHSpwD11vfNsF1eSZD6NxUADxghy98Rgd+1ZLPx3Ptm69ttPP7/+hVj/v/hi8OPdf35H8oMPvnj3ChiCFVAqttLuDFf//qPbpanmV789LF3m0cFO6TJ3Hu2XLvPR7b3SZbYf/KZ0mdu/eVAeg6175bH+6KB0ma375bE+3C+PweG927PgHB3Pxd19bBGfCIlshmsn2le6RyyUSUsDaDlyBOV3JSVAlC4mZSyNvdNtk7PPEOl0ZzHXA4gjqX+Gk+14bXLsXZSfLHxaugy6IF4nYKXLHV+3fKd8Ke/iWIgP5QnKYuVJ3vbLS2G4Ux4K0jmSM8Y4scoX7ogJ6KAjfCFkXMY01qd1euR7RGdpe25Xo5CGwPiEnJMjrZLM1iDpY8xR+ZZ8Vl6mRZblUVtvaLKXeiW55x8NmJUWvek1S/G5R09xeZTYieiw/go5wbSDyxdzsOuVX//Id0h5xnuqoWC2WPnetSlGXJtXnImdyjvzuhoTSHyuofKJOWekfD/bjneMHH0SJ66NL7RRQk7bs7xO+Z3jQo/Dif1Qf22gz3SHaXkBJZgSjS7byHGItiDKToUQKxrXKGqfIXsWkqGgQtIilurLwtrQOohbJzoWLcIsRG18FciLdSdeXsSg37Is7PNtpbzsIYqEeDYatsKhCaItX9LUXwiug6VdPxqZg9vIueNywrvSdL/akNb9BH2U722HuA8tK6Ds5urGavnS6KJc6ebsAB0BLCfuAEwufWmRUwPQ1EITCZlD/TZNoOtLC6mUh2HJm0QUdxBxAE5TcIYe0jgTjflMAVM9Er3gFAEPHQ1bcyiorVSrIyZITiRmhgKmjNioi3gJUpDRLVtpfbB8TeFJ2H3pWTuUwRktTPc8xsixk5CIjj3PwcgFitVFeEuo4mfmIV1eovVRV/56SG1BsQH1PQYivDFwPYkq2/aCpKZJXI7bmALJzoWYpEifSRMo1rHDjSSHqLVtT8y4y7Vsq4Og4s0tSlF3gg6GkhsX1RwHHDcobsUqQbISJTQ1VPTt8HcoUSksFBSffLo6ZvazBtKViSZmIjCmNM1qaHWRhokNnNoUlpZyc12i2XI8xIFB64E5Ik47rr6NC+cGUtYj5YBxr5MPOzCKquimAuqmC/P6Evu7kq4EW+zbh/IZ4KknK3AVBMiTkt6ufBpJvgDs/CgjoIYIFOqpgYDyATIFuDWmiaaMRLhzIU+qIWfXBpHMFK5yH8hIELflQyBUPUVNBj5je4ubF3SXWT9jgn+qYM8t2w6jiC9pdvAIWIAWuB4lbQK4GseVDg5jxjEdntAEVI3rvACpHqQQOGsc0tbG+vtxIr0rfwOWWlgmT7YnYz0zZ94BYz2p1UGkE4d2Wz0ANDXF1dB/IEX+XXcHdRkEihgUq2T+IBUAm5YC9i9fAbjVZNYHHh8eJMyVXxMfANqV+MQO5og4Cal2J/UKEI4hOVtHjrKOaRr8GX4SYNfCc2X191W2sTj1ZdOQAf3pQRseBE5vVNH5YAA1vSAv18csVna9jm0P8ujFqS+RWw+M0ZprWgXX36VeJ6OVGooyXGZLv1BFKbbVetw2H7+5xMc6GScd6ZoaiJbbEB1rXpQ/GOSaueS5edn6gIZNoDy9gIANCPOGMO+ZyGwQ5n1l7APCvKcDcxhQcIBdGRkJqJpVO+5jfuJBAJwxWEN3wiNOHPJZwtpd7Rj6VP7mE8ETIm+g6OZZr0POei/8wECMxgYYss2YuwahXTUxdbUCxwGWaArNz4hveTYGQI1J9YR3AU2TaMIRAHN4njrIBjnd3F5EEOiSBsXzdkomBzznSI68dIDVRZIkHhCbMZPFieeCGGkMzqnkDgZWaIgVxs6hwEH/EIl6HvaXI6vngX+ITK8EpjuFTPlLfPfAaQBgmgJT3ZADwT6mLFAQOWUSTEikaxLPc0zaJ0CepuB0sNvmJwCnKeokNqBpDM0TWOtmpST+OC4oSWX2cfQMTkFV2OKR2NFjISIgz1dWjiBkcTFsgXADQcIiWONbCIbDA9sgMGxg2IvFsFV+ppqsWzfoHCcD742s3SU+NiKZeiqbJ2A5F+s2TFNVl4swMe2EMlQqD9fh4AVsFlrARkdnDoIOmErMQrpjnDWuQ8J4gwnjl9mt5HKKLL6HKO8+AM/8FKB9kBE0AVcTuKaZKqBaSXonDB07OJWYY7lPd85OYE/nNayL6I4oabX2HGTmqMKi01eZ5RkRRPJce/wQcfQGUByBokrhPszsJDeQfYwYHJyZFsIHYsHzQ7jOcIoQ33FtAHgau5TkDgJcUNvNgNoSRHvAPYraGJA1i6yQEGEbM6xjIvc23j5BbhvbZnQhgDWRyd1B3ZBmE1lvcjO7538KM6A1AxRbWOIqWLF1emCdYDtwcOrmgpwPAG1NNuI4g7xOCXdV/DlgOw8Wl8SU1MTc0vY8e7uABD+K3gFTNSOBFeF8N3oHOM/dks9dAksegpqDSe3iUHOX5JLPew4mtZv3fNF2ySc+F5RaTv84jXLJCWE0PLWjhpyNri5ivzSmyqGBT2AKliuI5JtXcT5HhIEVDSu62BIqt7htyOBsHNMdzCxKfA4ZSeeXWRaK/XVhmecYn9qoexu3CVChaVRT3nvAtBKmHj3lYiMHUp0KrECr87sLjbY51El6B53RDJZw44p5eR3wnAv2yIh8m8Mdo8h+LRNybE4nKI/doIOput+toXKh3lz1iXUa+Ee+R2T6yQLYiwpbXkCJPPtWspzvMY46KNvgSrG1uRj2S2BLbUo5t7KBiyAfmdr6ijIjrc1JrKmcGYD7oOE+6JlIhXAf9DT1F7gP+kqImPAuuCPm2Fqh8hfuq4uQd92WV5sEhiwcE5xzMQZpeLV7mO9yzqyPgXvsBa4tjzXBuk7dzvfA46RFrPB+85qsbuzKtB6wto0B2sEcCW0JpQ5NZSloR34EIM/tClfzU5NVDrdAzS/xPfIZpnwfs8CR//Q9l9Um+fV0biXaWGLBUYiMCYtRnHoMHB6DlLpTWNXgHMgh1Nr5BNIDrOcFBmAqrSSHZSz+caqJLKaArZ4mS6lH72PGUBtspPOxLWZCFnKofTZhC95p6aADRV9XEHGQAS2ZvH42eFHcLg1YSxoRrx6uxFVzs0HrDPGj6+ulAZPFNn6uV0yvNb1SrjeTwJtHvoxCUJdDgbYMEoGetpwiIVCZ50llzp2cpdebc1CpofKcM0rQoIGnT6JBp0gH1GhQo+uuRueTPOjSYzeavfDGLRV7U6OrKkFrMIYm3FNpcLc2flcgaFuGJO7HmA5DXfa986XXs1J41E7DSo1Pd+9j+Ekg0MTzdVrpIqERbKvjHocUuQypmYGMuprAoiOPQHoEg3D63IfLc82h2aKApjE0bQvANAamxQBMY2ASF9A0hyZnsNAnELAvJd0Vs/NxqaBEbspcEasRMBV38SQgNCeQolqTuWLIdFvM26obZtvI28AMN5HH1g03kcvsDLeRywKmssY0b9vJanBLbp7IIFI7A0VmhHNtnjdhuJtsrZ4h54p5tdXhU9yQpmrZCh1hdQsMgwACc1hmAwjOMvQDuXb08VWu3QS+8fV5J3wL2obZnaQ644wmBpxASURq7QZSI6zLHtmZTsgS+JsNEd5+wBhB7p7HeBSDUzsxjV/mZ5fMRPyciSCxuLIDzdKIyqQVT6VS8AlIFHO2yNWptS2XnWMKCx0WeuFCj9PJiMWe+QwW/Jzu6pq5D2ulR2RhkajU78bc3GGC1W36f/PJ1mVe/suElnIhxBj9rg2bZDk0I4Y6RvyFnXCeRV/YDYvEuLrviKmhwq5okAXBFglbZJib/K7MVm08ffFygegqVgUgVgHRxhZhpm40XkLc4jdbSK4Yf67JFJcIRUsuYGxvcSC/suTHEXESKtZO9Gj5cJud8sQJRHhGXoT6xXU6MrM+u40c5FpYqkJ1cwcdh0NLnkFVo44N2kCmMbDVGCXJzOQse4h5Hio1ZUepUdbmng+42tAontNw9C+VucvBbeTccTnh3Qeog8F0WEXJUwdkMI141+zPcC6y2ud7jHBAcraSWSaxXcbqo53RbpAETS+l3UbpjHbXS5d4v3SJD0qX+IfSJX5WusSHpUuszyTzX57rHByzKUhqJ+4PrHlLPtO/wl11KcIeInQJwtBqo8+ZyZ+4RJJdFDS1k5PE85B04FSnJqwUtwnjmKZ9TIDrXBk2wyCSpd/tMiE1tdvy4vt5XTa7U9wF93U5yJRCBaA1Znt5+JNAsA1sg1Y5uO0+CUj99I3UAOvCgPGFkPBc5OxCuhljmM7JUdG6JK/spK/IgysFqijLAfU9hh+2BowMlr0hZMWmMQ1Alzdt7Vl0q2sisVT8plfAdT6E4X/CFsjCMVk4gUcNReHE+Op5yA0u1JorG2dIanvUszBjcBM0iGMTCLo5R+nz6Aiug57vdQ5x2iMIt3bCRdFAQcoABfEKjZfrkCt7JO+B+7Yr0S3ctz1vskgmLHkE1cOV25Nu49HJT9DWgCXqa2uXRASq2typaunJAT0ti0pdlbTkKEFDA7YOGtoVYArhJSbRhPCSachxB2YOsQCkAsAWxgDnFRi4LsUZsG5VYgEQYTY1owNEmBkFFCLMFsgUm+HPs7HD2hS1eGlTrI9dW92oW7IcEwM/OkdESPnto2Pknh4h36eeuiC4ZFU2thzijrjGt6hgOF3ycO0MLMk72CFnmHYPMD0jw1yCYFGGzXIMnnaScFL5XguICizM82NhHjNJS25pHolODVO6jBhtfXaAdD70xKDBylfFZsodyER41cw9EvisE9xB8umNpuJkchbYsMUb6ll0lfPAKGtZ2Odh2tbo5G3OIg9L+ogOZ9k7xXm3MeRBmilsqdbi628r1os9RJHYdOI1RaOJeh4+Hz+YQqG1oEPp+zG3Y081OtPGVwxrgbeiYt9njGLgM0z5Q2qri26vBkhPtRYfxMPoSeUBTI4mVZtuvBOPVE0plUy/S/cI454QSZCz8OhmhzInOMsETtKEgu96dBYoq7ZzsB48NzaoangPazQAOsOu/RhT0hLkIKXtOeK+hWVzb32bqGTkBFTTkONoiZ5PUhP1zhOEksBQvTMyGbqEEr83S3NHi3cktGteFW2Mnd+Ko6iy+kbUp9NDwVb4PYwE2R2Qtiuqo9gwypV6MjlShIXIYNsUCRL3TAzvERMDkro9Ii6mV0WBNGxn2G7FeoaIGpqrfGhKkbX6dtLxlaGhYZ3TIuhigbrqbDHD01QExyxXlWCJObmnF2HX96N0kvFte/DIIBBVNodRFVbvY/wm56WfsCQYhiYtt9JKBoHBTWNXOF8sNWHM1AiqgDzi3jWd3kX76lWzr7yzINmMi0YGVAXskUkp9HonE6vMB9qplD4mhjNXWEs2lyapGakE+V0xgFZOrIRm/1Lutxkile1JFaDGB5eM7masp75HeTxmyUG85dHOlk/2ojfDLoYFbM8K5CwpHfdW71/7X/VeNHqvey/6T3tve39p7JGOmMfG1t5uo/eX3o/9bxviix/7z3p/7X0nn7ztfyW+fN77Xnz9Kupass4cl07YtOcPInyyHooEkLk9/U/R7E/9L1XTL/tP+183ei97b/q/Fz36UvT/ef9fxONvGr23jd6r3o/i3Qvx9qkc3PfRoMb1NmyWuH7AGxHa4Xzl+YWa2ZJewMcUjWZ3WDbq0RCYlFM6F7Xh3j0esv8ScySmrv/HCLBw6sR/Put/lUTpuTY2Gc/OhMBkvSqVUIl7FsYD828CmO8uKampfr4R/xxNWj9J4vpBlHsl/i///VobtjxXzoTI5TpRDICXdhxMF8aXihjlm5eCPl8IJvNKfP3aLL5Fbp1SSBc6VCpinnUdTMAEUwAL6J6HQCoeLtD+tv9N/09igX8jHn87MR033onzhmZYpZyPkN/3v363wkwUO34mnocRbpZKs5C2yetsQ4J0vxcE/GXvVf+ZNkpFnpoJESr0LVTdarIW8arbzk8KqxdClHguSFkS899iEPZeVtmJir0ak+9KI3wAlcDMMcGPh/I/hnJZJIwp2nt7iZZazOLdT5Kh9l6+p34LwP8sNvfnAmWxgEMR6ODh1t41tdj/T06PquNV74U22iN8GxNiPconUQnpPAO6DtRiN5MLvf8nyVhDzvlKkqqE/nu1gT1rqL1LkvMPKhrzZx9cE6i+DSXP3t9EMfH2vaEk/SycvqjGiPYlL/lOPHza/3MjXCDDCe1/3f9Ge4ZGuVgmnKKRrojKqyFrPb+KBRGfsuxcVVoPxd6REktihE+hKjMfdy/lRNy8UOv6SSKssPyr+PcfGpKkG7J4/4+Ktnv/rSZCTEo0SwkVpMLeOdrrMTnnH2XgNwh93A5tDH6pnAu81Tp4GsKvEJar44WalDeC+b/sfytF7PETI1nTa7VMpHDY+1/x9b8bmp88R4fWHOXa882o1gNTtsb0SH1Icpo3kucklWzBpaqr2WmfQ1l1O2Pxr4RYyrI9Hq//ESj8IGW/EC0pj4RbokLrTe+5pOWIk1RgCQUuhAnBKrLXV4QqbpeeTNF4HUkeU8Yqz/4/MVS51vbK7HLkTVcTah2RDPBWSG1x8OQ6NAjfKJN+Cb5WaIqvCmXadK2HZKTxii1DEmQoB+dahStqcEUm/8mRLDTVTwBk9GZgVI8OC4TNHRN1lDZraL9NojO2MU9IoRn+hjxoNayL8a4jWYNvidIUuUy2fHP1hHN/s9kMTzCwtYuOI0utebTdlP/RlO+LTlqVNbWr/lx+Ln9uqZNZN1cD6sZH8mtR8d8lKor3v1kwwykqDYfv2d1GIM+uYVeePrJXVTeZjyyc2+xqQ30oIDsIWxyH0ODzZqpfzfyOxQlqHjuc7tloVlDSc1CSAi5rgelfqOkvdpGUpIBERUAEC0gEo109WuSQqRIIY8EIY5w/qjRZ5FQIRLFQRDHaPVaSIDKVATEsmvg4zhtYXpTMqRHIYqHIYqxfsyRR5NUHJLFQJDHeAVuSJnIrBKJYOD4xzuOrwSpyqgTCWDS5YqxjurxgkVclEMYCE0ax27wScSSqBQJZTIN2vr9e17A9rA3IYaHIYWQwQkliSNcFpLBgpFAcbFGaEhJVASEsnAwxOpZEQ3rIHgoGolgwohgdFVOeJjL1AUnMHUlEb6Lgm0RwDwunLRvcE83n6q3Umdv887arjajyTBjQIEhoGIOksEO2TTFjDccLLeBDLPyo5BryydrgxzXx4xrjyLXXfF/8z8ZnazRonl0/+jAEKjSTDVCKBTPFsqBGI721cqNp4xZxiWyX3fp/7cSnsA==';

    /** @var string API токер */
    protected $_token;

    /** @var string Содержимое WSDL */
    protected $_wsdl;

    /** @var PimPayApi_SoapClient SOAP клиент */
    protected $_soapClient;

    /**
     * @param  string                   $token            API токен
     * @param  IPimPayApi_CryptoHandler $cryptoHandler    Крипто-обработчик (OpenSSL/GnuPG)
     * @throws PimPayApi_Exception
     */
    public function __construct($token, IPimPayApi_CryptoHandler $cryptoHandler)
    {
        $this->_token         = $token;
        $this->_cryptoHandler = $cryptoHandler;

        foreach (array('zlib', 'dom', 'soap') as $extension)
        {
            if (!extension_loaded($extension))
            {
                throw new PimPayApi_Exception("$extension extension is not loaded.");
            }
        }

        $this->_initSoapClient();
    }

    /**
     * @return IPimPayApi_CryptoHandler
     */
    public function getCryptoHandler()
    {
        return $this->_cryptoHandler;
    }

    /**
     * @return string
     */
    public function getToken()
    {
        return $this->_token;
    }

    /**
     * Добавить клиента
     *
     * @param  PimPayApi_AcceptClientParams $params  Параметры клиента
     * @return PimPayApi_ClientInfo                  Возвратная информация по клиенту
     */
    public function acceptClient(PimPayApi_AcceptClientParams $params)
    {
        return $this->_getSoapClient()->acceptClient($this->_token, $params);
    }

    /**
     * Получить информацию по клиенту
     *
     * @param  string                $tin  ИНН клиента
     * @return PimPayApi_ClientInfo        Возвратная информация по клиенту
     */
    public function getClient($tin)
    {
        return $this->_getSoapClient()->getClient($this->_token, $tin);
    }

    /**
     * Добавить/обновить заказы
     *
     * @param  PimPayApi_Order[] $orders   Карточка заказа
     * @return $response                   Информация о добавленных заказах
     */
    public function upsertOrders($orders)
    {
        return $this->_getSoapClient()->upsertOrders($this->_token, $orders);
    }

    /**
     * Добавить/обновить исторические заказы
     *
     * @param  PimPayApi_Order[] $orders   Карточка заказа
     * @return $response                   Информация о добавленных заказах
     */
    public function upsertHistoricalOrders($orders)
    {
        return $this->_getSoapClient()->upsertHistoricalOrders($this->_token, $orders);
    }

    /**
     * Обновить статусы заказов
     *
     * @param  PimPayApi_OrderState[] $ordersStates Статусы заказов
     * @return int                                  Кол-во заказов
     */
    public function updateStateForOrders($ordersStates)
    {
        return $this->_getSoapClient()->updateStateForOrders($this->_token, $ordersStates);
    }

    /**
     * Проверить работоспособность подписи
     *
     * @return bool
     */
    public function testHeaderSignature()
    {
        return $this->_getSoapClient()->testHeaderSignature($this->_token);
    }

    /**
     * Отправить сверку
     *
     * @param string                      $tin          ИНН клиента
     * @param string                      $id           Идентификатор сверки в вашей системе
     * @param PimPayApi_PaymentOrder      $paymentOrder Платежное поручение
     * @param PimPayApi_VerificationRow[] $rows         Строки сверки
     * @return mixed
     */
    public function sendVerification($tin, $id, PimPayApi_PaymentOrder $paymentOrder, array $rows)
    {
        $requestRows = [];
        foreach ($rows as $row)
        {
            $itemXml = '<item';
            foreach (array('oid' => $row->orderId, 'ptp' => $row->paymentToPimPay, 'pfr' => $row->paymentFromRecipient, 'dc' => $row->deliveryCost, 'cs' => $row->cashService, 'ins' => $row->insurance) as $attr => $val)
            {
                if (!empty($val))
                {
                    $itemXml .= " $attr=\"$val\"";
                }
            }

            if (is_array($row->customTransactions) && $row->customTransactions)
            {
                $itemXml .= '><ns1:txs>';

                foreach ($row->customTransactions as $ctx)
                {
                    $itemXml .= '<item val="' . $ctx->value . '" comment="' . $ctx->comment . '"/>';
                }

                $itemXml .= '</ns1:txs></item>';
            }
            else
            {
                $itemXml .= '/>';
            }

            $requestRows[] = new SoapVar($itemXml, XSD_ANYXML);
        }

        return $this->_getSoapClient()->sendVerification($this->_token, $tin, $id, $paymentOrder, $requestRows);
    }

    /**
     * Получить статус отправленной сверки
     *
     * @param string $id Идентификатор сверки в вашей системе
     * @return PimPayApi_VerificationStatusResponse
     */
    public function getVerificationStatus($id)
    {
        return $this->_getSoapClient()->getVerificationStatus($this->_token, $id);
    }

    /**
     * @param $token
     * @param $tin
     * @param array $postIds
     * @return RussianPostPaymentsResponse
     */
    public function getRussianPostPayments($tin, array $postIds)
    {
        return $this->_getSoapClient()->getRussianPostPayments($this->_token, $tin, $postIds);
    }

    /**
     * @param $token
     * @param $tin
     * @param array $postIds
     * @return RussianPostClaimAnswersResponse
     */
    public function getRussianPostClaimAnswers($tin, array $postIds)
    {
        return $this->_getSoapClient()->getRussianPostClaimAnswers($this->_token, $tin, $postIds);
    }

    /**
     * Получить информацию по балансам клиентов
     *
     * @param  array                                 $tins  Список ИНН клиентов
     * @return PimPayApi_ClientsBalanceInfoResponse         Информация по балансам
     */
    public function getClientsBalance($tins)
    {
        return $this->_getSoapClient()->getClientsBalance($this->_token, $tins);
    }

    /**
     * Создать запросы на оплату
     *
     * @param  PimPayApi_RequestedPayment[]           $payments  Информация о платежах
     * @return PimPayApi_PaymentProcessResultResponse $response  Информация о созданых платежах
     */
    public function requestPayments($payments)
    {
        return $this->_getSoapClient()->requestPayments($this->_token, $payments);
    }

    /**
     * Отклонить запросы на оплату
     *
     * @param  PimPayApi_RejectedPayment[]            $payments  Информация о платежах
     * @return PimPayApi_PaymentProcessResultResponse $response  Информация об отклоненных платежах
     */
    public function rejectPayments($payments)
    {
        return $this->_getSoapClient()->rejectPayments($this->_token, $payments);
    }

    /**
     * Получить список запросов на оплату
     *
     * @return PimPayApi_PaymentInfoResultResponse $response  Информация о платежах
     */
    public function getRequestedPayments()
    {
        return $this->_getSoapClient()->getRequestedPayments($this->_token);
    }

    /**
     * Получить список служб доставки
     *
     * @return PimPayApi_DeliveryServiceInfoResultResponse $response  Информация о службе доставки
     */
    public function getDeliveryServices()
    {
        return $this->_getSoapClient()->getDeliveryServices($this->_token);
    }

    /**
     * @param  mixed $data
     * @return string
     */
    public function sign($data)
    {
        return $this->_cryptoHandler->sign($data);
    }

    /**
     * @param     $request
     * @param     $location
     * @param     $action
     * @param     $version
     * @param int $one_way
     */
    public function beforeSoapClientRequest($request, $location, $action, $version, $one_way = 0)
    {
        // You do stuff here like logging, reporting, etc...
    }

    /**
     * @param mixed $response
     */
    public function afterSoapClientRequest($response)
    {
        // You do stuff here like logging, reporting, etc...
    }

    /**
     * Инициализация SOAP клиента
     */
    protected function _initSoapClient()
    {
        $this->_setWdsl();

        $this->_soapClient = new PimPayApi_SoapClient($this, 'data://text/xml;base64,' . base64_encode($this->_wsdl), array(
                'classmap' => array(
                    'AcceptClientParams'           => PimPayApi_AcceptClientParams::class,
                    'ClientInfo'                   => PimPayApi_ClientInfo::class,
                    'Order'                        => PimPayApi_Order::class,
                    'OrderBase'                    => PimPayApi_OrderBase::class,
                    'OrderParams'                  => PimPayApi_OrderParams::class,
                    'DeliveryStatusHistoryItem'    => PimPayApi_DeliveryStatusHistoryItem::class,
                    'OrderState'                   => PimPayApi_OrderState::class,
                    'OrderItem'                    => PimPayApi_OrderItem::class,
                    'Address'                      => PimPayApi_Address::class,
                    'Recipient'                    => PimPayApi_Recipient::class,
                    'F103'                         => PimPayApi_F103::class,
                    'PaymentOrder'                 => PimPayApi_PaymentOrder::class,
                    'VerificationRow'              => PimPayApi_VerificationRow::class,
                    'CustomTransaction'            => PimPayApi_CustomTransaction::class,
                    'VerificationStatusResponse'   => PimPayApi_VerificationStatusResponse::class,
                    'VerificationError'            => PimPayApi_VerificationError::class,
                    'UpsertResultResponse'         => PimPayApi_UpsertResultResponse::class,
                    'UpsertResultItem'             => PimPayApi_UpsertResultItem::class,
                    'RussianPostPaymentsResponse'  => PimPayApi_RussianPostPaymentsResponse::class,
                    'RussianPostPaymentInfo'       => PimPayApi_RussianPostPaymentInfo::class,
                    'RussianPostPayment'           => PimPayApi_RussianPostPayment::class,
                    'ClientsBalanceInfoResponse'   => PimPayApi_ClientsBalanceInfoResponse::class,
                    'ClientBalanceInfoItem'        => PimPayApi_ClientBalanceInfoItem::class,
                    'RequestedPayment'             => PimPayApi_RequestedPayment::class,
                    'RejectedPayment'              => PimPayApi_RejectedPayment::class,
                    'PaymentProcessResultResponse' => PimPayApi_PaymentProcessResultResponse::class,
                    'PaymentProcessResultItem'     => PimPayApi_PaymentProcessResultItem::class,
                    'PaymentInfoResultResponse'    => PimPayApi_PaymentInfoResultResponse::class,
                    'PaymentInfoResultItem'        => PimPayApi_PaymentInfoResultItem::class,
                    'DeliveryServiceInfoResultResponse' => PimPayApi_DeliveryServiceInfoResultResponse::class,
                    'DeliveryServiceInfoResultItem'     => PimPayApi_DeliveryServiceInfoResultItem::class,
                ),
            )
        );
    }

    abstract protected function _setWdsl();

    /**
     * @return PimPayApi_SoapClient
     */
    protected function _getSoapClient()
    {
        return $this->_soapClient;
    }
}

class PimPayApi extends AbstractPimpayApi
{
    protected function _setWdsl()
    {
        $this->_wsdl = gzuncompress(base64_decode($this->_wsdlEncoded));
    }
}

class PimpayApiDev extends AbstractPimpayApi
{
    protected function _setWdsl()
    {
        $this->_wsdl = gzuncompress(base64_decode($this->_wsdlDevEncoded));
    }
}

class PimPayApi_AcceptClientParams {
    public $legalEntityName;
    public $tin;
    public $shopName;
    public $email;
    public $mobile;
    public $extra;
}

class PimPayApi_ClientInfo {
    public $tin;
    public $status;
    public $isMoneyTransferPossible;
    public $paymentOrderPurpose;
    public $ordersCount;
    public $isActive;
}

class PimPayApi_Order {
    public $id;
    public $tin;
    public $shopExternalId;
    public $base;
    public $createdAt;
    public $items;
    public $destinationAddress;
    public $recipient;
    public $f103;
    public $moneyRecipient;
}

class PimPayApi_OrderState {
    public $id;
    public $cost;
    public $uniformPimpayDeliveryStatus;
    public $customDeliveryStatus;
    public $deliveryServiceDeliveryStatus;
    public $time;
}

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

class PimPayApi_OrderParams {
    public $currency;
    public $paymentFromRecipient;
    public $declaredOrderCost;
    public $estimatedDeliveryCost;
    public $deliveryService;
    public $deliveryServiceExternalId;
    public $uniformPimpayDeliveryStatus;
    public $customDeliveryStatus;
    public $deliveryServiceDeliveryStatus;
    public $history;
    public $returnUtilization;
}

class PimPayApi_DeliveryStatusHistoryItem {
    public $time;
    public $uniformPimpayDeliveryStatus;
    public $customDeliveryStatus;
    public $deliveryServiceDeliveryStatus;
}

class PimPayApi_OrderItem {
    public $id;
    public $name;
    public $sku;
    public $value;
    public $cost;
    public $count;
    public $weight;
    public $length;
    public $width;
    public $height;
    public $vatValue;
    public $category;
}

class PimPayApi_Address {
    public $full;
    public $zipcode;
    public $city;
    public $cityId;
    public $kladr;
    public $fias;
    public $region;
}

class PimPayApi_Recipient {
    public $fio;
    public $phone;
    public $email;
}

class PimPayApi_F103
{
    public $number;
    public $date;
}

class PimPayApi_PaymentOrder {
    public $num;
    public $date;
    public $sum;

    public function __construct($num, $date, $sum)
    {
        $this->num  = $num;
        $this->date = $date;
        $this->sum  = $sum;
    }
}

class PimPayApi_VerificationRow {
    public $orderId;
    public $paymentFromRecipient;
    public $paymentToPimPay;
    public $deliveryCost;
    public $cashService;
    public $insurance;
    /** @var PimPayApi_CustomTransaction[] $customTransactions */
    public $customTransactions = array();
}

class PimPayApi_CustomTransaction {
    public $value;
    public $comment;

    public  function __construct($value, $comment)
    {
        $this->value = $value;
        $this->comment = $comment;
    }
}

class PimPayApi_VerificationStatusResponse {
    public $id;
    public $status;
    public $errors = [];
}

class PimPayApi_VerificationError {
    public $message;
}

class PimPayApi_UpsertResultResponse {
    public $count;
    public $orders;
}

class PimPayApi_UpsertResultItem {
    public $id;
    public $status;
    public $errorMessage;
}

class PimPayApi_RussianPostPaymentsResponse {
    public $tin;
    /**
     * @var PimPayApi_RussianPostPaymentInfo[]
     */
    public $russianPostPaymentsInfo;
}

class PimPayApi_RussianPostPaymentInfo {
    public $id;
    public $postId;
    /**
     * @var PimPayApi_RussianPostPayment[]
     */
    public $payments = [];
}

class PimPayApi_RussianPostPayment
{
    public $sum;
    public $paymentDate;
    public $registeredAt;
}

class PimPayApi_ClientsBalanceInfoResponse {
    /**
     * @var PimPayApi_ClientBalanceInfoItem[]
     */
    public $balances;
}

class PimPayApi_ClientBalanceInfoItem
{
    public $status;
    public $tin;
    public $legalEntityName;
    public $customerBalance;
    public $depositBalance;
}

class PimPayApi_PaymentProcessResultResponse
{
    public $count;
    /** @var PimPayApi_PaymentProcessResultItem[] */
    public $payments;
}

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

class PimPayApi_PaymentInfoResultResponse
{
    public $count;
    /** @var PimPayApi_PaymentInfoResultItem[] */
    public $payments;
}

class PimPayApi_PaymentInfoResultItem
{
    public $id;
    public $externalId;
    public $tin;
    public $amount;
    public $paymentSum;
    public $feeSum;
    public $status;
    public $purposeOfPayment;
    public $comment;
    public $vatValue;
}

class PimPayApi_RequestedPayment
{
    public $externalId;
    public $tin;
    public $amount;
    public $purposeOfPayment;
    public $comment;
    public $vatValue;
}

class PimPayApi_RejectedPayment
{
    public $id;
}

class PimPayApi_DeliveryServiceInfoResultResponse
{
    public $count;
    /** @var PimPayApi_DeliveryServiceInfoResultItem[] */
    public $deliveryServices;
}

class PimPayApi_DeliveryServiceInfoResultItem
{
    public $code;
    public $title;
}

class PimPayApi_Exception extends Exception {}

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
    public function __construct(PimPayApi $api, $wsdl, array $options = null)
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
