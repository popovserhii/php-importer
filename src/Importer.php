<?php
/**
 * Temporary import service
 *
 * @category Agere
 * @package Agere_Spare
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 16.12.2015 17:36
 */
namespace Agere\Spare\Service\Import;

use Zend\Stdlib\Exception;
use Agere\Importer\DriverInterface;

abstract class Importer {

    private $username = "Stanislav Kharchenko";
    private $password = "linux-ecd71d7698a2a61e0f0f233b43pcf4se";

    /** @var DriverInterface */
    protected $driver;

    /** @var \Doctrine\DBAL\Connection */
    protected $db;

    /**
     * Table which fields is now in preparation
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

    protected $namespace = 'Agere\\Spare\\Service\\Import\\Helper';


    public function __construct(DriverInterface $driver, $db) {
        $this->driver = $driver;
        $this->db = $db;
    }

    public function import($filename) {
        //die('Uncomment this string for run import!');
        //ini_set('display_errors', 'on');
        //error_reporting(-1);

        $xlBook = $this->getXlBook($filename);
        $xlSheet = $xlBook->getSheet();

        $this->profiling();

        $tables = [];
        for($col = $xlSheet->firstCol(); $col < $xlSheet->lastCol(); $col++) {
            $title = $xlSheet->read($xlSheet->firstRow(), $col);
            foreach ($this->fieldsMap as $i => $table) {
                if (isset($table[$title])) {
                    $tables[$this->getTableOrder($table['__table'])][] = ['index' => $col, 'name' => $title];
                } elseif (isset($table['__dynamic'])) {
                    if (!isset($indexStartAfter)
                        && isset($table['__options']['startAfter'])
                        && ($table['__options']['startAfter'] == $title)
                    ) {
                        $indexStartAfter = $col; //  find 'startAfter' column index
                    } elseif (isset($indexStartAfter) && $indexStartAfter < $col && ($title = trim($title))) {
                        $tableOrder = $this->getTableOrder($table['__table']);
                        $tables[$tableOrder][] = ['index' => $col, 'name' => $title];
                        $this->fieldsMap[$tableOrder][$title] = $table['__dynamic'];
                    }
                }
            }
        }

        ksort($tables);
        //\Zend\Debug\Debug::dump([$xlSheet->read(0, 0), $xlSheet->firstRow(), $xlSheet->lastRow()]); die(__METHOD__);
        /* skip head row */
        /** @link http://www.libxl.com/spreadsheet.html#lastRow */
        for ($row = ($xlSheet->firstRow() + 1); $row < $xlSheet->lastRow(); $row++) {
            $this->preparedFields = [];
            foreach ($tables as $tableOrder => $table) {
                $fields = $this->fieldsMap[$tableOrder];
                $tableName = $fields['__table'];
                $this->saved[$tableName] = null;

                $item = [];
                foreach ($table as $column) {
                    //\Zend\Debug\Debug::dump($column);
                    $field = $fields[$column['name']];
                    // prepare row for save
                    $this->prepareField(
                        $xlSheet->read($row, $column['index']),
                        $field,
                        $item
                    );
                    $this->preparedFields[$tableName] = $item;
                }
                //\Zend\Debug\Debug::dump([$item, __METHOD__ . __LINE__]); //die(__METHOD__);
                if (!$item) {
                    continue;
                }

                // save excel row
                if (!isset($fields['__exclude']) || !$fields['__exclude']) {
                    if (isset($fields['__foreign'])) {
						foreach ($fields['__foreign'] as $referenceTable => $foreignField) {
							$item[$foreignField] = $this->saved[$referenceTable];
						}
                    }
                    $this->saved[$tableName] = $this->save($item, $tableName);
                    //\Zend\Debug\Debug::dump([$this->saved[$tableName], $item]); //die(__METHOD__);
                }
            }
            //\Zend\Debug\Debug::dump([$this->saved, $this->errors]); die(__METHOD__);
            $this->saved = [];
        }

        //execution time of the script
        $this->messages['info'][] = sprintf('Total Execution Time: %s Mins', $this->profiling(false));

        if (!$hasErrors = $this->hasErrors()) {
            $this->messages['success'][] = 'File has been imported successfully!';
        }
        //\Zend\Debug\Debug::dump($this->getMessages()); die(__METHOD__);
        //die('<br />' . __METHOD__);

        return !$hasErrors;
    }

    public function save(array $row, $table) {
        if (!count($row)) {
            return false;
        }

        $id = $this->getIds($row, $table); // important place here
        try {
            $options = $this->getTableOptions($table);
            $mode = isset($options['mode']) ? $options['mode'] : 'save';
            $modeMethod = method_exists($this, $method = $mode . 'Mode') ? $method : 'saveMode';

            //\Zend\Debug\Debug::dump([$modeMethod, $row, $table]); //die(__METHOD__);

            $this->{$modeMethod}($row, $table);
        } catch (\Exception $e) {
            $this->messages['error'][] = $e->getMessage();
            //\Zend\Debug\Debug::dump([$e->getMessage()]); die(__METHOD__);
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
        //\Zend\Debug\Debug::dump($id); die(__METHOD__);

        return $id;
    }

    protected function saveMode(array $row, $table) {
        $isDeep = $this->isDeep($row);
        $db = $this->getDb();
        //\Zend\Debug\Debug::dump([$row, __METHOD__ . __LINE__]); //die(__METHOD__);
        if ($isDeep) {
            $db->multipleSave($table, $row);
            //\Zend\Debug\Debug::dump([$table, $result]); die(__METHOD__);
        } else {
            $db->save($table, $row);
        }
    }

    protected function updateMode(array $row, $table) {
        if (isset($row['id']) && $row['id']) {
            $this->saveMode($row, $table);
        }
    }

    public function getIds(array & $row, $table, $apply = true, $identifierCustom = false) {
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
				//\Zend\Debug\Debug::dump([$identifierField, $row]); die(__METHOD__);
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
        //\Zend\Debug\Debug::dump([$identifierField, $sql]); //die(__METHOD__);

        $pdo = $this->getPdo();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($identifiers);
        $ids = $stmt->fetchAll();

        if ($apply) {
            $findId = function($row) use($ids, $identifierField) {
                foreach ($ids as $id) {
                    // Foton == FOTON
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

        $ids = array_map(function($id) { return $id['id']; }, $ids);

        return $ids;
    }

    protected function prepareField($cellValue, $params, & $row) {
		$cellValue = trim($cellValue);
        // filter field
        if (isset($params['__filter'])) {
            foreach ($params['__filter'] as $filter) {
                $cellValue = $this->getHelper($filter, 'filter')->filter($cellValue);
            }
            //$cellValue = $this->filter($params['__filter'], $cellValue);
            /*foreach ($params['__filter'] as $filter) {
                if (method_exists($this, $method = 'filter' . ucfirst($filter))) {
                    $cellValue = $this->{$method}($cellValue);
                } else {
                    $this->messages['error'][$method] = sprintf('Filtered method [%s] does not exists', $method);
                }
            }*/
        }

        // prepared filed
        if (isset($params['__prepare'])) {
            foreach ($params['__prepare'] as $prepare) {
                $cellValue = $this->getHelper($prepare, 'prepare')->prepare($cellValue);
            }
            //$cellValue = $this->prepare($params['__prepare'], $cellValue);
            /*foreach ($params['__prepare'] as $prepare) {
                if (class_exists($class = $this->namespace . '\Prepare' . ucfirst($prepare))) {
                    $helper = isset($preparedHelpers[$class]) ? $preparedHelpers[$class] : $preparedHelpers[$class] = new $class($this);
                    $cellValue = $helper->prepare($cellValue);
                } else {
                    $this->messages['error'][$class] = sprintf('Preparatory class [%s] does not exists', $class);
                }
            }*/
        }

        if (is_string($params)) { // is field name
            $row[$params] = ($cellValue !== null) ? $cellValue : '';
        } elseif (isset($params['name'])) {
            $row[$params['name']] = ($cellValue !== null) ? $cellValue : '';
        } else {
            $row = $cellValue;
        }

        return $row;
    }

    /*public function prepare(array $filters, $value) {
        foreach ($filters as $filter) {
            $value = $this->getHelper($filter, 'prepare')->prepare($value);
        }

        return $value;
    }*/

   /* public function filter(array $filters, $value) {
        foreach ($filters as $filter) {
            $value = $this->getHelper($filter, 'filter')->filter($value);
        }

        return $value;
    }*/

    public function getHelper($name, $pool) {
        static $helpers = [];

        $key = $pool . $name;
        if (isset($helpers[$key])) {
            return $helpers[$key];
        }

        if (!class_exists($class = $this->namespace . '\\' . ucfirst($pool) . ucfirst($name))) {
            throw new Exception\RuntimeException(sprintf('Import helper [%s] not exists', $class));
        }

        return $helpers[$key] = new $class($this);
    }

    /*protected function filterFloat($num) {
        $dotPos = strrpos($num, '.');
        $commaPos = strrpos($num, ',');
        $sep = (($dotPos > $commaPos) && $dotPos) ? $dotPos :
            ((($commaPos > $dotPos) && $commaPos) ? $commaPos : false);

        if (!$sep) {
            return floatval(preg_replace("/[^0-9]/", '', $num));
        }

        return floatval(
            preg_replace("/[^0-9]/", '', substr($num, 0, $sep)) . '.' .
            preg_replace("/[^0-9]/", '', substr($num, $sep + 1, strlen($num)))
        );
    }*/

    public function getXlBook($filename) {
        $splFile = new \SplFileInfo($filename);

        if ($splFile->getExtension() === 'xls') {
            $xlBook = new \ExcelBook($this->username, $this->password, false);
        } elseif ($splFile->getExtension() === 'xlsx') {
            $xlBook = new \ExcelBook($this->username, $this->password, true);
        }
        $xlBook->setLocale('UTF-8');
        $xlBook->loadFile($splFile->getPathname());

        //\Zend\Debug\Debug::dump(file_exists($filename));

        return $xlBook;
    }

    public function getTableFieldsMap($table, $field = false) {
        if (!$table
            || (false === ($tableOrder = $this->getTableOrder($table)))
            || (false === isset($this->fieldsMap[$tableOrder]))
        ) {
            return false;
        }

        $fieldsMap = $this->fieldsMap[$this->getTableOrder($table)];
        if ($field === false) {
            return $fieldsMap;
        }

        if (isset($fieldsMap[$field])) {
            return $fieldsMap[$field];
        }

        return false;
    }

    public function getTableOrderByCodename($codename) {
        if (is_null($this->codenamedOrders)) {
            $this->prepareOrders();
        }

        return isset($this->codenamedOrders[$codename]) ? $this->codenamedOrders[$codename] : false;
    }

	public function getTableOrders() {
		return $this->tableOrders;
	}

	public function setTableOrder($tableName, $order) {
		$this->tableOrders[$tableName] = $order;

		return $this;
	}

	public function unsetTableOrder($tableName) {
		unset($this->tableOrders[$tableName]);

		return $this;
	}

	public function getTableOrder($tableName) {
		if (is_null($this->tableOrders)) {
			$this->prepareOrders();
		}

		return isset($this->tableOrders[$tableName]) ? $this->tableOrders[$tableName] : false;
	}

    protected function prepareOrders() {
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

	public function getTableOptions($table) {
		return isset($this->fieldsMap[$this->getTableOrder($table)]['__options']) ? $this->fieldsMap[$this->getTableOrder($table)]['__options'] : false;
	}

	/**
	 * Execution time of the script
	 *
	 * @var bool $signal true - start, false - stop
	 * @return float $timeExecution Total Execution Time in Mins
	 */
	protected function profiling($signal = true) {
		static $timeStart;

		if ($signal) {
			$timeStart = microtime(true);
		}
		if (!$signal) {
			$this->timeExecution = (microtime(true) - $timeStart) / 60;

			return $this->timeExecution;
		}
	}

	public function getTimeExecution() {
		return $this->timeExecution;
	}

    /**
     * If will be use more ArrayEx functions then inject relative object
     *
     * @param $array
     * @return bool
     */
	protected function isDeep($array) {
		foreach ($array as $elm) {
			if (is_array($elm)) {
				return true;
			}
		}

		return false;
	}

	public function addMessage($message, $namespace = 'info') {
		$this->messages[$namespace][] = $message;
	}

	public function setMessages(array $messages, $namespace) {
		$this->messages[$namespace] = $messages;
	}

	public function getMessages() {
		return $this->messages;
	}

	public function hasErrors() {
		return isset($this->messages['error']);
	}

	public function getErrors() {
		if ($this->hasErrors()) {
			return $this->messages['error'];
		}

		return false;
	}

	public function bindMessages($messenger) {
		foreach ($this->getMessages() as $namespace => $messages) {
			foreach ($messages as $message) {
				$messenger->setNamespace($namespace)->addMessage($message);
			}
		}
	}

	public function getDb() {
		return $this->db;
	}

	public function getPdo() {
		return $this->db->getPdo();
	}

	public function getPreparedFields() {
		return $this->preparedFields;
	}

	public function setFieldsMap($orderTable, $options) {
		$this->fieldsMap[$orderTable] = $options;

		return $this;
	}

	public function unsetFieldsMap($orderTable) {
		unset($this->fieldsMap[$orderTable]);

		return $this;
	}

	public function getFieldsMap($orderTable = null) {
		if ($orderTable === null) {
			return $this->fieldsMap;
		}

		if (isset($this->fieldsMap[$orderTable])) {
			return $this->fieldsMap[$orderTable];
		}

		return false;
	}

	public function getSaved($productTable) {
		return $this->saved[$productTable];
	}


}