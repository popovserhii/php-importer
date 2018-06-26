<?php
/**
 * LibXl driver
 *
 * @category Popov
 * @package Popov_Importer
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 13.05.2016 13:35
 * @link https://github.com/iliaal/php_excel
 */
namespace Popov\Importer\Driver;

use ExcelBook;

class LibXl implements DriverInterface
{
    protected $filename;

    protected $config = [
        'username' => '',
        'password' => '',
        'locale' => 'UTF-8',
        'sheet' => 0,
    ];

    public function __construct(array $config = [])
    {
		//\Zend\Debug\Debug::dump($config); die(__METHOD__);
        $this->config = array_merge($this->config, $config);
    }

    /**
     * {@inheritDoc}
     */
    public function source($filename = null)
    {
        if ($filename) {
            $this->filename = $filename;

            return $this;
        }

        return $this->filename;
    }

    /**
     * {@inheritDoc}
     */
    public function firstColumn()
    {
		//\Zend\Debug\Debug::dump($this->filename); die(__METHOD__);
		//$xlBook = $this->xlBook();
		
		//$xlSheet = $this->config['sheet'] ? $xlBook->getSheet($this->config['sheet']) : $xlBook->getSheet();
		
		//$xlSheet = $this->xlBook();
		//\Zend\Debug\Debug::dump($xlSheet->name());die(__METHOD__);
		//\Zend\Debug\Debug::dump($xlSheet->name());
		//\Zend\Debug\Debug::dump(get_class_methods($xlSheet)); die(__METHOD__);
		
        return $this->xlSheet()->firstCol();
    }

    /**
     * {@inheritDoc}
     */
    public function lastColumn()
    {
        return $this->xlSheet()->lastCol();
    }

    /**
     * {@inheritDoc}
     * @link http://www.libxl.com/spreadsheet.html#lastRow
     */
    public function firstRow()
    {
        return $this->xlSheet()->firstRow();
    }

    /**
     * {@inheritDoc}
     */
    public function lastRow()
    {
        return $this->xlSheet()->lastRow();
    }

    /**
     * {@inheritDoc}
     */
    public function read($row, $column = null)
    {
        return $this->xlSheet()->read($row, $column);
    }

    /**
     * {@inheritDoc}
     */
    public function config()
    {
        return $this->config;
    }

    protected function xlBook()
    {
        static $xlBook;

        if (!$xlBook) {
            $splFile = new \SplFileInfo($this->filename);

            $useXlsxFormat = false;
            if ($splFile->getExtension() === 'xlsx') {
                $useXlsxFormat = true;
            }
			
            $xlBook = new \ExcelBook($this->config['username'], $this->config['password'], $useXlsxFormat);
            $xlBook->setLocale($this->config['locale']);
            $xlBook->loadFile($splFile->getPathname());
        }
		
        return $xlBook;
    }
	
	protected function xlSheet() 
	{
		static $xlSheet;
		
		if (!$xlSheet) {
			$xlBook = $this->xlBook();
			$xlSheet = $this->config['sheet'] ? $xlBook->getSheet($this->config['sheet']) : $xlBook->getSheet();
		}
		
		return $xlSheet;
	}
}
