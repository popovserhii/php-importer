<?php
/**
 * Import service
 *
 * @category Agere
 * @package Agere_Spare
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 16.12.2015 17:36
 */
namespace Agere\Importer;

use Zend\Stdlib\Exception;
use Agere\Importer\Factory\DriverFactory;
use Agere\Importer\Driver\DriverInterface;
use Agere\Db\Db;

class Importer
{
    /** @var DriverFactory */
    protected $driverFactory;

    /** @var Db */
    protected $db;

    /**
     * Table fields which is now in preparation
     *
     * @var string
     */
    protected $processingTable;

    protected $fieldsMap = [];

    protected $errors = [];

    protected $tableOrders;

    protected $codenamedOrders;

    protected $saved = [];

    /** Messages of import process. Can be "info", "error", "success" */
    protected $messages = [];

    protected $timeExecution;

    protected $preparedFields = [];

    protected $helpers = [
        'filter' => [
            'int' => Helper\FilterInt::class,
            'float' => Helper\FilterFloat::class,
        ],
        'prepare' => [],
    ];

    public function __construct(DriverFactory $driverFactory, Db $db)
    {
        $this->driverFactory = $driverFactory;
        $this->db = $db;
    }

    public function import($task, $filename)
    {
        $driver = $this->getDriver($task, $filename);
        $this->profiling();
		$this->fieldsMap = $this->fieldsMap ?: $driver->config()['fields_map'];

		
        $tables = [];
        for ($col = $driver->firstColumn(); $col < $driver->lastColumn(); $col++) {
            $title = $driver->read($driver->firstRow(), $col);
            foreach ($this->fieldsMap as $i => $table) {
				//\Zend\Debug\Debug::dump($table, '$table');
				//\Zend\Debug\Debug::dump(isset($table[$title]), 'isset($table[' . $title . '])');
                if (isset($table[$title])) {
                    $tables[$this->getTableOrder($table['__table'])][] = ['index' => $col, 'name' => $title];
                } elseif (isset($table['__dynamic'])) {
                    if (!isset($indexStartAfter)
                        && isset($table['__options']['startAfter'])
                        && ($table['__options']['startAfter'] == $title)
                    ) {
                        $indexStartAfter = $col; // find 'startAfter' column index
                    } elseif (isset($indexStartAfter) && $indexStartAfter < $col && ($title = trim($title))) {
                        $tableOrder = $this->getTableOrder($table['__table']);
                        $tables[$tableOrder][] = ['index' => $col, 'name' => $title];
                        $this->fieldsMap[$tableOrder][$title] = $table['__dynamic'];
                    }
                }
            }
        }
        ksort($tables);
		
		//\Zend\Debug\Debug::dump([$tables]); die(__METHOD__.__LINE__);
        // skip head row
        /** @link http://www.libxl.com/spreadsheet.html#lastRow */
        for ($row = ($driver->firstRow() + 1); $row < $driver->lastRow(); $row++) {
            $this->preparedFields = [];
            foreach ($tables as $tableOrder => $table) {
                $fields = $this->fieldsMap[$tableOrder];
                $tableName = $fields['__table'];
                $this->saved[$tableName] = null;
                $item = [];
                foreach ($table as $column) {
                    $field = $fields[$column['name']];
                    // prepare row for save
                    $this->prepareField(
                        $driver->read($row, $column['index']),
                        $field,
                        $item
                    );
                    $this->preparedFields[$tableName] = $item;
                }

                if (!$item) {
                    continue;
                }
				//\Zend\Debug\Debug::dump($item); die(__METHOD__.__LINE__);
                // save row
                if (!isset($fields['__exclude']) || !$fields['__exclude']) {
                    if (isset($fields['__foreign'])) {
                        foreach ($fields['__foreign'] as $referenceTable => $foreignField) {
                            $item[$foreignField] = $this->saved[$referenceTable];
                        }
                    }
                    $this->saved[$tableName] = $this->save($item, $tableName);
                }
            }
            $this->saved = [];
        }

        // execution time of the script
        $this->messages['info'][] = sprintf('Total Execution Time: %s Mins', $this->profiling(false));
        if (!$hasErrors = $this->hasErrors()) {
            $this->messages['success'][] = 'File has been imported successfully!';
        }

        return !$hasErrors;
    }

    public function save(array $row, $table)
    {
        if (!count($row)) {
            return false;
        }
        $id = $this->getIds($row, $table); // important place here
        try {
            $options = $this->getTableOptions($table);
            $mode = isset($options['mode']) ? $options['mode'] : 'save';
            $modeMethod = method_exists($this, $method = $mode . 'Mode') ? $method : 'saveMode';
            $this->{$modeMethod}($row, $table);
        } catch (\Exception $e) {
            $this->messages['error'][] = $e->getMessage();
        }

        $isDeep = $this->isDeep($row);
        $isDeepCond = $isDeep && (count($id) !== count($row));
        // Double check if we have multiple array. We don't know which data will be inserted or updated
        if ($isDeepCond) {
            $id = $this->getIds($row, $table, false);
        } else {
            $db = $this->getDb();
            $id = $id ?: [$db->lastInsertId()];
        }
        $id = (!isset($id[1]))
            ? ($id ? current($id) : false) // if __identifier set to false then return false
            : $id;

        return $id;
    }

    protected function saveMode(array $row, $table)
    {
        $isDeep = $this->isDeep($row);
        $db = $this->getDb();
        if ($isDeep) {
            $db->multipleSave($table, $row);
        } else {
            $db->save($table, $row);
        }
    }

    protected function updateMode(array $row, $table)
    {
        if (isset($row['id']) && $row['id']) {
            $this->saveMode($row, $table);
        }
    }

    public function getIds(array & $row, $table, $apply = true, $identifierCustom = false)
    {
        $identifierField = ($identifierField = $this->getTableFieldsMap($table, '__identifier'))
            ? $identifierField
            : $identifierCustom;
        if (!$identifierField) {
            return false;
        }
        $isDeep = $this->isDeep($row);
        $identifiers = [];
        if ($isDeep) {
            foreach ($row as $sub) {
                $identifiers[] = $sub[$identifierField];
            }
        } else {
            $identifiers = [$row[$identifierField]];
        }

        $sql = sprintf('SELECT `id`, `%s` FROM `%s` WHERE `%s` IN (%s)',
            $identifierField,
            $table,
            $identifierField,
            rtrim(str_repeat('?,', count($identifiers)), ',')
        );

        $pdo = $this->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($identifiers);
        $ids = $stmt->fetchAll();
        if ($apply) {
            $findId = function ($row) use ($ids, $identifierField) {
                foreach ($ids as $id) {
                    // Foton = FOTON
                    if (mb_strtolower($id[$identifierField]) === mb_strtolower($row[$identifierField])) {
                        return $id['id'];
                    }
                }

                return 0;
            };
            if ($isDeep) {
                foreach ($row as $i => & $r) {
                    $r['id'] = $findId($r);
                }
            } else {
                $row['id'] = $findId($row);
            }
        }
        $ids = array_map(function ($id) {
            return $id['id'];
        }, $ids);

        return $ids;
    }

    protected function prepareField($cellValue, $params, & $row)
    {
        $cellValue = trim($cellValue);
        // filter field
        if (isset($params['__filter'])) {
            foreach ($params['__filter'] as $filter) {
                $cellValue = $this->getHelper($filter, 'filter')->filter($cellValue);
            }
        }
		
        // prepared filed
        if (isset($params['__prepare'])) {
            foreach ($params['__prepare'] as $prepare) {
                $cellValue = $this->getHelper($prepare, 'prepare')->prepare($cellValue);
            }
        }
		
        if (is_string($params)) { 
			// if field not has any preparations
            $row[$params] = ($cellValue !== null) ? $cellValue : '';
		} elseif (isset($params['name']) && is_array($cellValue)) {
			// if field contains values for different fields of one table
			$row = array_merge($row, $cellValue);
        } elseif (isset($params['name'])) {
			// if field has preparation
            $row[$params['name']] = ($cellValue !== null) ? $cellValue : '';
        } else {
			// if field contains values for multi-dimensional save
            $row = $cellValue;
        }

        return $row;
    }

    public function getHelper($name, $pool)
    {
        static $helpers = [];

        $key = $pool . $name;
        if (isset($helpers[$key])) {
            return $helpers[$key];
        }

        $config = $this->getDriverFactory()->getConfig();
        if (isset($this->helpers[$pool][$name])) {
            $helperClass = $this->helpers[$pool][$name];
        } elseif (isset($config['helpers'][$pool][$name])) {
            $helperClass = $config['helpers'][$pool][$name];
        } else {
            throw new Exception\RuntimeException(sprintf('Import helper [%s:%s] not exists', $pool, $name));
        }

        return $helpers[$key] = new $helperClass($this);
    }

    public function getDriverFactory()
    {
        return $this->driverFactory;
    }

    /**
     * Get driver
     *
     * @param $configTask
     * @param $filename
     * @return DriverInterface
     */
    public function getDriver($configTask, $filename) {
        $driverFactory = $this->getDriverFactory();
        /** @var DriverInterface $driver */
        $driver = $driverFactory->create($configTask);
        $driver->filename($filename);

        return $driver;
    }

    public function getTableFieldsMap($table, $field = false)
    {
        if (!$table
            || (false === ($tableOrder = $this->getTableOrder($table)))
            || (false === isset($this->fieldsMap[$tableOrder]))
        ) {
            return false;
        }

        $fieldsMap = $this->fieldsMap[$this->getTableOrder($table)];
        if (false === $field) {
            return $fieldsMap;
        }
        if (isset($fieldsMap[$field])) {
            return $fieldsMap[$field];
        }

        return false;
    }

    public function getTableOrderByCodename($codename)
    {
        if (is_null($this->codenamedOrders)) {
            $this->prepareOrders();
        }

        return isset($this->codenamedOrders[$codename]) ? $this->codenamedOrders[$codename] : false;
    }

    public function getTableOrders()
    {
        return $this->tableOrders;
    }

    public function setTableOrder($tableName, $order)
    {
        $this->tableOrders[$tableName] = $order;

        return $this;
    }

    public function unsetTableOrder($tableName)
    {
        unset($this->tableOrders[$tableName]);

        return $this;
    }

    public function getTableOrder($tableName)
    {
        if (is_null($this->tableOrders)) {
            $this->prepareOrders();
        }

        return isset($this->tableOrders[$tableName]) ? $this->tableOrders[$tableName] : false;
    }

    protected function prepareOrders()
    {
        $this->tableOrders = [];
        $this->codenamedOrders = [];
        foreach ($this->fieldsMap as $i => $fields) {
            if (!isset($fields['__table'])) {
                continue;
            }
            $this->tableOrders[$fields['__table']] = $i;
            $this->codenamedOrders[$fields['__codename']] = $i;
        }
    }

    public function getTableOptions($table)
    {
        return isset($this->fieldsMap[$this->getTableOrder($table)]['__options'])
            ? $this->fieldsMap[$this->getTableOrder($table)]['__options']
            : false;
    }

    /**
     * Execution time of the script
     *
     * @var bool $signal true - start, false - stop
     * @return float $timeExecution Total Execution Time in Mins
     */
    protected function profiling($signal = true)
    {
        static $timeStart;
        if ($signal) {
            $timeStart = microtime(true);
        }
        if (!$signal) {
            $this->timeExecution = (microtime(true) - $timeStart) / 60;

            return $this->timeExecution;
        }
    }

    public function getTimeExecution()
    {
        return $this->timeExecution;
    }

    /**
     * If will be use more ArrayEx functions then inject relative object
     *
     * @param $array
     * @return bool
     */
    protected function isDeep($array)
    {
        foreach ($array as $elm) {
            if (is_array($elm)) {
                return true;
            }
        }

        return false;
    }

    public function addMessage($message, $namespace = 'info')
    {
        $this->messages[$namespace][] = $message;
    }

    public function setMessages(array $messages, $namespace)
    {
        $this->messages[$namespace] = $messages;
    }

    public function getMessages()
    {
        return $this->messages;
    }

    public function hasErrors()
    {
        return isset($this->messages['error']);
    }

    public function getErrors()
    {
        if ($this->hasErrors()) {
            return $this->messages['error'];
        }

        return false;
    }

    public function getDb()
    {
        return $this->db;
    }

    public function getPdo()
    {
        return $this->db->getPdo();
    }

    public function getPreparedFields()
    {
        return $this->preparedFields;
    }

    public function setFieldsMap($orderTable, $options)
    {
        $this->fieldsMap[$orderTable] = $options;

        return $this;
    }

    public function unsetFieldsMap($orderTable)
    {
        unset($this->fieldsMap[$orderTable]);

        return $this;
    }

    public function getFieldsMap($orderTable = null)
    {
        if ($orderTable === null) {
            return $this->fieldsMap;
        }
        if (isset($this->fieldsMap[$orderTable])) {
            return $this->fieldsMap[$orderTable];
        }

        return false;
    }

    public function getSaved($productTable)
    {
        return $this->saved[$productTable];
    }
}
