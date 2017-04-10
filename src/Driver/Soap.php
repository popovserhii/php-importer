<?php
/**
 * Soap client driver
 *
 * @category Agere
 * @package Agere_Importer
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 13.05.2016 13:35
 * @link https://github.com/iliaal/php_excel
 */
namespace Agere\Importer\Driver;

use Agere\Importer\Driver\Factory\SoapCombinedAdapterFactory;
use Zend\Soap\Client as SoapClient;
use Agere\Importer\Driver\Adapter\SoapCombinedAdapter;

class Soap implements DriverInterface
{
    protected $source;

    protected $config = [];

    protected $sheet = [];

    protected $soapAdapter;

    public function __construct(array $config = [], SoapCombinedAdapter $soapAdapter = null)
    {
        $this->config = $config;
        $this->soapAdapter = $soapAdapter ?: (new SoapCombinedAdapterFactory($config))();
    }

    /**
     * {@inheritDoc}
     */
    public function source($source = null)
    {
        if ($source) {
            $this->source = $source;

            return $this;
        }

        return $this->source;
    }

    protected function columns()
    {
        if (!isset($this->sheet[0])) {
            $sheet = $this->sheet();
            $row = next($sheet);
            foreach ($row as $name => $value) {
                $this->sheet[0][] = $name;
            }
        }

        return $this->sheet[0];
    }

    protected function sheet()
    {
        if (!$this->sheet) {
            //die(__METHOD__);
            //$params = new \stdClass();
            //$params->DateBalance = '2016-08-09';
            $params = json_decode(json_encode($this->config()['params']), false);;

            $soap = $this->soapAdapter;

            // @todo Фіча яка робить масив макимально наближеним до логіки циклу в Importer.
            // Після реалізації Traversable все стане простіше і логічніше
            $sourceMethod = ucfirst($this->source());
            $this->sheet = [0 => []] + $this->deeper($soap->{$sourceMethod}($params));
            //var_dump($soap->createInvoice($invoice)) . "<br />"; // 50
            //\Zend\Debug\Debug::dump($this->sheet); die(__METHOD__);
        }

        return $this->sheet;
    }

    /**
     * Depth iterate over array to find data set and skip web service wrapper
     *
     * @param $array
     * @return array
     */
    protected function deeper($array)
    {
        $i = 0;
        foreach ($array as $sub) {
            if ($i > 1) {
                return $array;
            }
            $i++;
        }

        return $this->deeper(current($array));
    }

    /**
     * {@inheritDoc}
     */
    public function firstColumn()
    {
        static $first;

        if (!$first) {
            $columns = $this->columns();
            reset($columns);
            $first = key($columns);
            //\Zend\Debug\Debug::dump(get_class_methods($sheet)); die(__METHOD__);
        }
        return $first;
    }

    /**
     * {@inheritDoc}
     */
    public function lastColumn()
    {
        static $last;

        if (!$last) {
            $columns = $this->columns();
            end($columns);
            $last = key($columns) + 1;
        }

        return $last;
    }

    /**
     * {@inheritDoc}
     * @link http://www.libxl.com/spreadsheet.html#lastRow
     */
    public function firstRow()
    {
        static $first;

        if (!$first) {
            $sheet = $this->sheet();
            $first = key($sheet);
        }

        return $first;
    }

    /**
     * {@inheritDoc}
     */
    public function lastRow()
    {
        static $last;

        if (!$last) {
            $sheet = $this->sheet();
            end($sheet);
            $last = key($sheet) + 1;
        }

        return $last;
    }

    /**
     * {@inheritDoc}
     */
    public function read($row, $column)
    {
        if ($row !== $this->firstRow()) {
            $column = $this->columns()[$column];
        }

        //\Zend\Debug\Debug::dump([$row, $column, $this->sheet()[$row][$column]]);
        return $this->sheet()[$row][$column];
    }

    /**
     * {@inheritDoc}
     */
    public function config()
    {
        return $this->config;
    }
}
