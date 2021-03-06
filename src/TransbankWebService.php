<?php
namespace Freshwork\Transbank;


use Freshwork\Transbank\Exceptions\InvalidCertificateException;
use Freshwork\Transbank\WebpayOneClick\WebpayOneClickWebService;
use SoapValidation;

/**
 * Class TransbankWebService
 * @package Freshwork\Transbank
 */
class TransbankWebService
{
    /**
     * @var TransbankSoap
     */
    protected $soapClient;

    /**
     * @var CertificationBag
     */
    protected $certificationBag;

    /**
     * @var
     */
    protected static $classmap = [];

    /**
     * WebpayOneClick constructor.
     * @param CertificationBag $certificationBag
     * @param string $url
     */
    function __construct(CertificationBag $certificationBag, $url = null)
    {
        $url = $this->getWsdlUrl($certificationBag, $url);

        $this->certificationBag = $certificationBag;

        $this->soapClient = new TransbankSoap($url, [
            "classmap" => static::$classmap,
            "trace" => true,
            "exceptions" => true
        ]);

        $this->soapClient->setCertificate($this->certificationBag->getClientCertificate());
        $this->soapClient->setPrivateKey($this->certificationBag->getClientPrivateKey());
    }

    /**
     * @return CertificationBag
     */
    public function getCertificationBag()
    {
        return $this->certificationBag;
    }

    /**
     * @param CertificationBag $certificationBag
     */
    public function setCertificationBag(CertificationBag $certificationBag)
    {
        $this->certificationBag = $certificationBag;
    }

    /**
     * @return TransbankSoap
     */
    public function getSoapClient()
    {
        return $this->soapClient;
    }

    /**
     * @throws InvalidCertificateException
     */
    public function validateResponseCertificate()
    {
        $xmlResponse = $this->getLastRawResponse();

        $soapValidation = new SoapValidation($xmlResponse, $this->certificationBag->getServerCertificate());
        $validation =  $soapValidation->getValidationResult(); //Esto valida si el mensaje está firmado por Transbank

        if ($validation !== true)
        {
            throw new InvalidCertificateException('The Transbank response fails on the certificate signature validation.');
        }
    }

    /**
     * @param $method
     * @return mixed
     * @throws InvalidCertificateException
     */
    protected function callSoapMethod($method)
    {
        //Get arguments, and remove the first one ($method) so the $args array will just have the additional paramenters
        $args = func_get_args();
        array_shift($args);

        //Call $this->getSoapClient()->$method($args[0], $arg[1]...)
        $response = call_user_func_array([$this->getSoapClient(), $method], $args);

        //Validate the signature of the response
        $this->validateResponseCertificate();
        return $response;
    }

    /**
     * This method allow you to call any method on
     * @param $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, array $arguments)
    {
        array_unshift($arguments, $name);
        return call_user_func_array([$this, 'callSoapMethod'], $arguments);
    }

    /**
     * @return string
     */
    protected function getLastRawResponse()
    {
        $xmlResponse = $this->getSoapClient()->__getLastResponse();
        return $xmlResponse;
    }

    /**
     * @param CertificationBag $certificationBag
     * @param $url
     * @return string
     */
    public function getWsdlUrl(CertificationBag $certificationBag, $url = null)
    {
        if ($url) return $url;

        if ($certificationBag->getEnvironment() == CertificationBag::PRODUCTION) {
            return static::PRODUCTION_WSDL;
        }

        return static::INTEGRATION_WSDL;
    }
}