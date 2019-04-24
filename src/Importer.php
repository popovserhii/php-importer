<?php
/**
 * Import service
 *
 * @category Popov
 * @package Popov_Importer
 * @author Serhii Popov <popow.serhii@gmail.com>
 */
namespace Popov\Importer;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Popov\Importer\Driver\DriverInterface;
use Popov\Variably\ConfigHandler;
use Popov\Variably\Preprocessor;
use Popov\Db\Db;

class Importer
{
    const MODE_SAVE = 'save';

    const MODE_UPDATE = 'update';

    /**
     * @var Db
     */
    protected $db;

    /**
     * @var Preprocessor
     */
    protected $preprocessor;

    /**
     * @var ConfigHandler
     */
    protected $configHandler;

    /**
     * @var DriverCreator
     */
    protected $driverCreator;

    /**
     * @var ObservableInterface
     */
    protected $observable;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var array
     */
    protected $fieldsMap;

    protected $tableOrders;

    protected $fieldOrders;

    protected $codenamedOrders;

    protected $saved = [];

    /**
     * Current real row from database
     *
     * @var array
     */
    protected $currentRealRows = [];

    /**
     * Unhandled set of row data
     *
     * @var array
     */
    protected $currentRawRow = [];

    /**
     * Messages of import process.
     * Log level can be "info", "error", "success" etc.
     */
    protected $messages = [];

    protected $timeExecution;

    protected $preparedFields = [];

    public function __construct(
        Db $db,
        ConfigHandler $configHandler,
        Preprocessor $preprocessor,
        DriverCreator $driverCreator = null,
        ObservableInterface $observable = null,
        LoggerInterface $logger = null,
        array $config = null
    )
    {
        $this->db = $db;
        $this->configHandler = $configHandler;
        $this->preprocessor = $preprocessor;
        #$this->configHandler = $preprocessor->getConfigHandler();
        $this->driverCreator = $driverCreator ?? new DriverCreator(null, []);
        $this->observable = $observable;
        $this->logger = $logger;

        $this->driverCreator->setConfig($config['importer'] ?? $config);
        $this->configHandler->setConfig($config['importer'] ?? $config)
            ->getVariably()->set('importer', $this);

        $this->config = $config['importer'] ?? $config;
    }

    public function getDb()
    {
        return $this->db;
    }

    public function getPdo()
    {
        return $this->db->getPdo();
    }

    public function setPreprocessor(Preprocessor $preprocessor)
    {
        $this->preprocessor = $preprocessor;

        return $this;
    }

    public function setDriverCreator(DriverCreator $driverCreator)
    {
        $this->driverCreator = $driverCreator;

        return $this;
    }

    public function setObservable(Observable $observable)
    {
        $this->observable = $observable;

        return $this;
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;

        return $this;
    }

    public function import($task, $source)
    {
        $this->profiling();
        try {
            $this->runImport($task, $source);
        } catch (\Throwable $e) {
            $this->log('error', $e);
        } catch (\Exception $e) {
            $this->log('error', $e);
        }

        // execution time of the script
        $this->log('info', sprintf('Total Execution Time: %s Mins', $this->profiling(false)));
        #$this->messages['info'][] = sprintf('Total Execution Time: %s Mins', $this->profiling(false));

        ($hasErrors = $this->hasErrors())
            ? $this->log('error', 'During the import the errors occurred!')
            : $this->log('info', 'Data has been imported successfully!');

        return !$hasErrors;
    }

    protected function runImport($task, $source)
    {
        $driver = $this->getDriver($task, $source);
        $this->trigger('run', $driver);
        $this->trigger('run.' . $task, $driver);

        $this->fieldsMap = $driver->config()['fields'];

        // Reset
        $this->tableOrders = null;
        $this->fieldOrders = null;
        $this->codenamedOrders = null;
        $this->messages = [];

        $this->log('info', sprintf('%s started data processing...', $driverName = $this->getShortDriverName($driver)));

        $tables = [];
        for ($colIndex = $driver->firstColumn(); $colIndex <= $driver->lastColumn(); $colIndex++) {
            $tableOrder = null;
            $title = $driver->read($driver->firstRow(), $colIndex);
            foreach ($this->fieldsMap as $table) {
                if (isset($table[$title])) {
                    $tableOrder = $this->getTableOrder($table['__table']);
                    $fieldOrder = $this->getFieldOrder($title, $table['__table']);
                    $tables[$tableOrder][$fieldOrder] = ['index' => $colIndex, 'name' => $title];
                } elseif (isset($table['__dynamic'])) {
                    if (!isset($indexStartAfter)
                        && isset($table['__options']['startAfter'])
                        && ($table['__options']['startAfter'] == $title)
                    ) {
                        $indexStartAfter = $colIndex; // find 'startAfter' column index
                    } elseif (isset($indexStartAfter) && $indexStartAfter < $colIndex && ($title = trim($title))) {
                        $tableOrder = $this->getTableOrder($table['__table']);
                        $tables[$tableOrder][] = ['index' => $colIndex, 'name' => $title];
                        $this->fieldsMap[$tableOrder][$title] = $table['__dynamic'];
                    }
                }
            }
            if (isset($tables[$tableOrder])) {
                ksort($tables[$tableOrder]);
            }
        }
        ksort($tables);

        $successCounter = 0;
        // Skip head row
        for ($rowIndex = ($driver->firstRow() + 1); $rowIndex <= $driver->lastRow(); $rowIndex++) {
            // Reset properties on each iteration
            $this->preparedFields = [];
            $this->currentRealRows = [];

            foreach ($tables as $tableOrder => $table) {
                $fields = $this->fieldsMap[$tableOrder];
                $tableName = $fields['__table'];
                $this->saved[$tableName] = null;

                $this->currentRawRow = $driver->read($rowIndex); // Raw row - not handled yet

                // Collect data from one table in array
                $related = [];
                foreach ($table as $column) {
                    if (!isset($this->currentRawRow[$column['name']])) {
                        // Skip wrong value address
                        continue;
                    }
                    $related[$column['name']] = $this->currentRawRow[$column['name']];
                }

                if (!array_filter($related)) {
                    continue;
                }

                // Handle raw data
                $item = [];
                foreach ($related as $columnName => $value) {
                    $field = $fields[$columnName];
                    $this->handleField($value, $field, $item);
                    $this->preparedFields[$tableName] = $item;
                }

                // @todo Add filter based on @see
                if (!$item/* || $this->handleFilter()*/) {
                    continue;
                }

                // save row
                if (!isset($fields['__exclude']) || !$fields['__exclude']) {
                    if (isset($fields['__foreign'])) {
                        foreach ($fields['__foreign'] as $referenceTable => $foreignField) {
                            // Only if foreign key is set and is real value add it as reference,
                            // otherwise omit this field. It automatically applied to NULL on DB level.
                            if (isset($this->saved[$referenceTable]) && $this->saved[$referenceTable]) {
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
            $successCounter++;
            $this->currentRawRow = [];
            $this->saved = [];
        }

        $this->log('info', sprintf('Number of all rows: %s', $rowIndex - 1));
        $this->log('info', sprintf('Number of success handled rows: %s', $successCounter));
        //$this->log('info', sprintf('%s finish data processing!', $driverName));

        $this->trigger('run.post', $driver);
    }

    public function save(array $row, $table)
    {
        if (!array_filter($row)) {
            return false;
        }
        $id = $this->getIds($row, $table); // important place here
        try {
            // We run preprocessor handle directly before save for have access to $row ID.
            // For example, save default values only if there is no ID in $row.
            $row = $this->handlePreprocessor($row);

            if (!$row) {
                return false;
            }

            $this->trigger('save', $row);
            $this->trigger('save.' . $this->getCurrentFieldsMap('__codename'), $row);

            $modeMethod = $this->getModeMethod($table);
            $this->{$modeMethod}($row, $table);

            $this->trigger('save.post', $row);
            //$this->trigger('save.post.' . $this->getCurrentFieldsMap('__codename'), $row);

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

            $this->log('debug', sprintf('Data in table "%s" saved', $table), $row);
        } catch (\Throwable $e) {
            $this->log('error', $e);
        } catch (\Exception $e) {
            $this->log('error', $e);
        }

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
            // When a table has at least one unique field and this field(s) is the same as field(s) in '__identifier'
            // you can enable `unique` for performance benefit,
            // otherwise if you can not mark field as unique will be used standard SELECT-INSERT/UPDATE approach.
            if (isset($this->config['__options']['unique']) && $this->config['__options']['unique']) {
                $db->save($table, $row);
            } else {
                if (isset($row['id']) && $row['id']) {
                    $db->update($table, $row, 'id = "' . $row['id'] . '"');
                } else {
                    unset($row['id']);
                    $db->add($table, $row);
                }
            }
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
                $this->getDb()->exec('SET SESSION sql_mode = "NO_ENGINE_SUBSTITUTION"');
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

        $realRows = $this->getRealRow($row, $table);
        //$realRow = $this->getCurrentRealRow($row, $table);

        if ($apply) {
            $this->applyId($row, $realRows, $identifierField);
        }

        $ids = array_map(function ($id) {
            return $id['id'];
        }, $realRows);

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

        // $currentRealRow is reset on each iteration
        $this->currentRealRows[$table] = $realRow;

        return $realRow;
    }

    public function getIdentifierField($table, $identifierCustom = false)
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
     * @param $id
     * @param $identifierField
     */
    protected function applyId(& $row, $id, $identifierField)
    {
        $ids = (array) $id;
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

    protected function handleField($value, $params, & $row)
    {
        try {
            $codename = $this->getCurrentFieldsMap('__codename');
            $this->configHandler->getVariably()->set('fields', $row);
            #$this->configHandler->getVariably()->set('originFields', $row);
            $this->configHandler->getVariably()->set($codename, $row);

            $value = $this->configHandler->process($value, $params);
        } catch (\Exception $e) {
            $this->log('error', $e);
        }

        $this->preprocessor->correlate($row, $value, $params);

        return $row;
    }

    protected function handlePreprocessor($row)
    {
        $fieldsConfig = $this->getCurrentFieldsMap();
        if (isset($fieldsConfig['__preprocessor'])) {
            $row = $this->preprocessor->setConfig($fieldsConfig['__preprocessor'])
                ->process($row);
        }

        return $row;
    }

    public function getDriverCreator()
    {
        return $this->driverCreator;
    }

    /**
     * Get driver and replace current logger if driver has its own logger
     *
     * @param $configTask
     * @param $source
     * @return DriverInterface
     */
    public function getDriver($configTask, $source)
     {
        $driverFactory = $this->getDriverCreator();
        /** @var DriverInterface $driver */
        $driver = $driverFactory->create($configTask);
        $driver->source($source);

        if (method_exists($driver, 'getLogger') && $driver instanceof LoggerAwareInterface) {
            $this->logger = $driver->getLogger();
        }

        return $driver;
    }

    public function getCurrentTable()
    {
        end($this->preparedFields);
        $table = key($this->preparedFields);
        reset($this->preparedFields);

        return $table;
    }

    public function getCurrentFieldsMap($field = false)
    {
        $currentTable = $this->getCurrentTable();
        $fieldsMap = $this->getTableFieldsMap($currentTable, $field);

        return $fieldsMap;
    }

    /**
     * Get prepared fields grouped by tables
     *
     * @return array
     */
    public function getPreparedFields()
    {
        return $this->preparedFields;
    }

    /**
     * Get current real rows saved in database.
     *
     * This method reduce number of queries to database.
     * You should be sure identifier fields already processed and are available in $preparedFields.
     *
     * Trick: in __preprocessor all fields are available.
     *
     * @param string $table
     * @return array
     */
    public function getCurrentRealRows($table = null)
    {
        $table = $table ?: $this->getCurrentTable();
        if (!isset($this->currentRealRows[$table]) || !$this->currentRealRows[$table]) {
            $fields = $this->getPreparedFields()[$table];
            $this->currentRealRows[$table] = $this->getRealRow($fields, $table);
        }

        return $this->currentRealRows[$table];
    }

    public function getCurrentRealRow($table = null)
    {
        $realRow = $this->getCurrentRealRows($table);
        if (count($realRow) === 1) {
            $realRow = $realRow[0];
        }

        return $realRow;
    }

    /**
     * Get full set of unhandled data from driver not grouped by table
     *
     * Not recommended to use. Use very carefully.
     *
     * @return array
     */
    public function getCurrentRawRow()
    {
        return $this->currentRawRow;
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

    public function getFieldOrder($filedName, $table)
    {
        if (is_null($this->fieldOrders)) {
            $this->prepareOrders();
        }

        #$tableOrder = $this->getTableOrder($table);
        return isset($this->fieldOrders[$table][$filedName]) ? $this->fieldOrders[$table][$filedName] : false;
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
                $this->fieldOrders[$fields['__table']][$fromName] = $f++;
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
        if (!is_array($array)) {
            return false;
        }

        foreach ($array as $elm) {
            if (is_array($elm)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param $message
     * @param string $namespace
     * @deprecated Use log method instead
     */
    public function addMessage($message, $namespace = 'info')
    {
        $this->messages[$namespace][] = $message;
    }

    /**
     * @param array $messages
     * @param $namespace
     * @deprecated
     */
    public function setMessages(array $messages, $namespace)
    {
        $this->messages[$namespace] = $messages;
    }

    /**
     * @return array
     * @deprecated
     */
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

    protected function getShortDriverName($driver)
    {
        $parts = explode('\\', get_class($driver));

        return array_pop($parts);
    }

    protected function trigger($eventName, $target, $params = [])
    {
        if ($this->observable) {
            $params['context'] = $this;
            $this->observable->trigger($eventName, $target, $params);
        }
    }

    /**
     * Wrapper upon logger.
     *
     * This method write all messages in file if logger is passed and also collect them in memory
     * for following process at the end of execution.
     * All not "info" messages are grouped with number of repeats prefix.
     *
     * Each next "run" resets "messages" that is why you should process this information after each execution.
     *
     * @param string $level
     * @param string $message
     * @param array $context
     */
    public function log($level, $message, array $context = [])
    {
        static $counter = [];

        !$this->logger || $this->logger->log($level, $message, $context);

        // If message is exception convert it to string
        $message = is_object($message) ? $message->__toString() : $message;
        // Group identical messages with add suffix number
        if (isset($this->messages[$level][$hash = md5($message)])) {
            $this->messages[$level][$hash] = '(' . ++$counter[$hash] . ') ' . $message;
        } else {
            $counter[$hash] = 1;
            $this->messages[$level][$hash] = $message;
        }
    }
}
