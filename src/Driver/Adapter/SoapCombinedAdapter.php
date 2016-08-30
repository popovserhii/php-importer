<?php
/**
 * Adapter for merge result from two or more SOAP services
 *
 * @category Agere
 * @package Agere_Importer
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 29.08.2016 11:26
 */
namespace Agere\Importer\Driver\Adapter;

use Zend\Soap\Client as SoapClient;

class SoapCombinedAdapter
{
    /**
     * Set of Soap Clients data from will be merged
     *
     * @var SoapClient[]
     */
    protected $soapClients = [];

    public function __construct($soapClients)
    {
        if (!is_array($soapClients)) {
            $soapClients = [$soapClients];
        }
        $this->soapClients = $soapClients;
    }

    /**
     * Overloading all Soap methods and merge data from different Soap Servers in one array
     *
     * @param string $method
     * @param array $args
     * @return array
     */
    public function __call($method, $args)
    {
        $merged = [];
        foreach ($this->soapClients as $soap) {
            $result = json_decode(json_encode(call_user_func_array([$soap, $method], $args)), true);
            $merged = array_merge_recursive($merged, $result);
        }

        return $merged;
    }
}