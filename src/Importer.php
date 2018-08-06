<?php
/**
 * Import service
 *
 * @category Popov
 * @package Popov_Spare
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 16.12.2015 17:36
 */
namespace Popov\Importer;

use Zend\Stdlib\Exception;
use Popov\Importer\Driver\DriverInterface;
use Popov\Variably\ConfigHandler;
use Popov\Db\Db;

class Importer
{
    const MODE_SAVE = 'save';

    const MODE_UPDATE = 'update';

    /**
     * @var DriverCreator
     */
    protected $driverCreator;

    /**
     * @var ConfigHandler
     */
    protected $configHandler;

    /**
     * @var Db
     */
    protected $db;

    /**
     * @var array
     */
    protected $config;

    protected $fieldsMap = [];

    protected $tableOrders;

    protected $fieldOrders;

    protected $codenamedOrders;

    protected $saved = [];

    /**
     * Messages of import process.
     * Can be "info", "error", "success"
     */
    protected $messages = [];

    protected $timeExecution;

    protected $preparedFields = [];

    public function __construct(
        Db $db,
        ConfigHandler $configHandler,
        DriverCreator $driverCreator = null,
        //HelperCreator $helperCreator = null
        array $config = null
    )
    {
        $this->db = $db;
        $this->configHandler = $configHandler;
        $this->driverCreator = $driverCreator ?? new DriverCreator([]);
        //$this->helperCreator = $helperCreator ?? new HelperCreator([]);

        $this->driverCreator->setConfig($config['importer']);
        $this->configHandler->setConfig($config['importer'])
            ->getVariably()->set('importer', $this);

        $this->config = $config;
    }

    public function import($task, $source)
    {
        $driver = $this->getDriver($task, $source);
        $this->profiling();
		$this->fieldsMap = $this->fieldsMap ?: $driver->config()['fields'];

        $tables = [];
        for ($col = $driver->firstColumn(); $col < $driver->lastColumn(); $col++) {
            $tableOrder = null;
            $title = $driver->read($driver->firstRow(), $col);
            foreach ($this->fieldsMap as $table) {
                if (isset($table[$title])) {
                    $tableOrder = $this->getTableOrder($table['__table']);
                    $fieldOrder = $this->getFieldOrder($title);
                    $tables[$tableOrder][$fieldOrder] = ['index' => $col, 'name' => $title];
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
            if (isset($tables[$tableOrder])) {
                ksort($tables[$tableOrder]);
            }
        }
        ksort($tables);
		
        
        // skip head row
        for ($row = ($driver->firstRow() + 1); $row < $driver->lastRow(); $row++) {
            $this->preparedFields = [];
            foreach ($tables as $tableOrder => $table) {
                $fields = $this->fieldsMap[$tableOrder];
                $tableName = $fields['__table'];
                $this->saved[$tableName] = null;
                $item = [];
                foreach ($table as $column) {
                    $field = $fields[$column['name']];
                    if (false === ($value = $driver->read($row, $column['index']))) {
                        // Skip wrong value address
                        continue;
                    }

                    // prepare row for save
                    $this->prepareField($value, $field, $item);
                    $this->preparedFields[$tableName] = $item;
                }

                if (!$item) {
                    continue;
                }

                // save row
                if (!isset($fields['__exclude']) || !$fields['__exclude']) {
                    if (isset($fields['__foreign'])) {
                        foreach ($fields['__foreign'] as $referenceTable => $foreignField) {
                            if (isset($this->saved[$referenceTable])) {
                                $item[$foreignField] = $this->saved[$referenceTable];
                            }
                        }
                    }
                    if (isset($fields['__ignore'])) {
                        foreach ($fields['__ignore'] as $ignoredField) {
                            unset($item[$ignoredField]);
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
            $this->messages['success'][] = 'Data has been imported successfully!';
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
            $modeMethod = $this->getModeMethod($table);
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

    protected function getModeMethod($table)
    {
        $options = $this->getTableOptions($table);
        $mode = isset($options['mode']) ? $options['mode'] : self::MODE_SAVE;
        $modeMethod = method_exists($this, $method = $mode . 'Mode') ? $method : self::MODE_SAVE . 'Mode';

        return $modeMethod;
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
            static $sqlMode;
            if (!$sqlMode) {
                // @todo Винести це в конігурацію.
                // INSERT/UPDATE statement in STRICT mode
                // throw error SQLSTATE[HY000]: General error: 1364 Field 'fieldName' doesn't have a default value
                $this->getDb()->exec('SET SESSION sql_mode = "NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION"');
                $sqlMode = true;
            }
            $this->saveMode($row, $table);
        }
    }

    public function getIds(array & $row, $table, $apply = true, $identifierCustom = false)
    {
        $identifierField = $this->getIdentifierField($table, $identifierCustom);

        if (!$identifierField) {
            return false;
        }

        $realRow = $this->getRealRow($row, $table);

        if ($apply) {
            $this->applyId($row, $realRow, $identifierField);
        }

        $ids = array_map(function ($id) {
            return $id['id'];
        }, $realRow);

        return $ids;
    }

    public function getRealRow(array $row, $table)
    {
        $identifierField = $this->getIdentifierField($table);

        if (!$identifierField) {
            return false;
        }

        // Identifier can be singular (id) or combined (code, country).
        // Here we reduce all values to array.
        $identifierField = is_array($identifierField) ? $identifierField : [$identifierField];

        $identifiers = $this->prepareIdentifiers($row, $identifierField);

        $sqlPart = [];
        foreach ($identifiers as $field => $identifier) {
            //$sqlPart['column'][] = sprintf('`%s`', $field);
            $sqlPart['where'][] = sprintf('%s IN (%s)', $field, str_repeat('?,', count($identifier) - 1) . '?');
        }

        //$sql = sprintf('SELECT `id`, %s FROM `%s` WHERE %s',
        $sql = sprintf('SELECT * FROM `%s` WHERE %s',
            //implode(', ', $sqlPart['column']),
            $table,
            implode(' AND ', $sqlPart['where'])
        );

        $realRow = $this->db->fetchAll($sql, $this->flatten($identifiers));

        return $realRow;
    }

    protected function getIdentifierField($table, $identifierCustom = false)
    {
        $identifierField = ($identifierField = $this->getTableFieldsMap($table, '__identifier'))
            ? $identifierField
            : $identifierCustom;

        return $identifierField;
    }

    protected function prepareIdentifiers($row, $identifierField)
    {
        $isDeep = $this->isDeep($row);
        $identifiers = [];
        foreach ($identifierField as $field) {
            if ($isDeep) {
                foreach ($row as $sub) {
                    $identifiers[$field][] = $sub[$field];
                }
            } else {
                $identifiers[$field] = [$row[$field]];
            }
        }

        return $identifiers;
    }

    protected function flatten($array = null)
    {
        $result = [];
        if (!is_array($array)) {
            $array = func_get_args();
        }
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result = array_merge($result, $this->flatten($value));
            } else {
                $result = array_merge($result, [$key => $value]);
            }
        }

        return $result;
    }

    /**
     * Apply inner DB id to row
     *
     * @param $row
     * @param $ids
     * @param $identifierField
     */
    protected function applyId(& $row, $ids, $identifierField)
    {
        $findId = function ($row) use ($ids, $identifierField) {
            foreach ($ids as $id) {
                // Foton == FOTON
                $bool = true;
                $identifierField = $this->flatten($identifierField);
                foreach ($identifierField as $identifier) {
                    $bool = $bool && (mb_strtolower($id[$identifier]) === mb_strtolower($row[$identifier]));
                }
                if ($bool) {
                    return $id['id'];
                }
            }
            return 0;
        };

        $isDeep = $this->isDeep($row);

        if ($isDeep) {
            foreach ($row as $i => & $r) {
                $r['id'] = $findId($r);
            }
        } else {
            $row['id'] = $findId($row);
        }
    }

    protected function prepareField($value, $params, & $row)
    {
        $this->configHandler->getVariably()->set('fields', $row);
        $value = $this->configHandler->process($value, $params);

        if (is_string($params)) {
			// if field not has any preparations
            $row[$params] = ($value !== null) ? $value : '';
		} elseif (isset($params['name']) && is_array($value)) {
			// if field contains values for different fields of one table
			$row = array_merge($row, $value);
        } elseif (isset($params['name'])) {
			// if field has preparation
            $row[$params['name']] = ($value !== null) ? $value : '';
        } else {
			// if field contains values for multi-dimensional save
            $row = $value;
        }

        return $row;
    }

    public function getDriverCreator()
    {
        return $this->driverCreator;
    }

    /**
     * Get driver
     *
     * @param $configTask
     * @param $source
     * @return DriverInterface
     */
    public function getDriver($configTask, $source) {
        $driverFactory = $this->getDriverCreator();
        /** @var DriverInterface $driver */
        $driver = $driverFactory->create($configTask);
        $driver->source($source);

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

    public function getFieldOrder($filedName)
    {
        if (is_null($this->fieldOrders)) {
            $this->prepareOrders();
        }

        return isset($this->fieldOrders[$filedName]) ? $this->fieldOrders[$filedName] : false;
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
        $this->fieldOrders = [];
        $this->tableOrders = [];
        $this->codenamedOrders = [];
        foreach ($this->fieldsMap as $i => $fields) {
            if (!isset($fields['__table'])) {
                continue;
            }
            $this->tableOrders[$fields['__table']] = $i;
            $this->codenamedOrders[$fields['__codename']] = $i;

            $f = 0;
            foreach ($fields as $fromName => $toName) {
                // skip reserved config keys
                if ('__' === substr($fromName, 0, 2)) {
                    continue;
                }
                $this->fieldOrders[$fromName] = $f++;
            }
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
