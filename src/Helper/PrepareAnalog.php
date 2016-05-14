<?php
/**
 * Temporary import service
 *
 * @category Agere
 * @package Agere_Spare
 * @author Vlad Kozak <vk@agere.com.ua>
 * @datetime: 20.03.2016 01:26
 */
namespace Agere\Spare\Service\Import\Helper;

use Agere\Spare\Service\Import\ImportService;

class PrepareAnalog {

    /** @var ImportService */
    protected $import;

    public function __construct(ImportService $import) {
        $this->import = $import;
    }

    public function getImport() {
        return $this->import;
    }

    public function prepare($value) {
        $import = $this->getImport();
        $fieldsMap = $import->getFieldsMap();

        $productTable = $fieldsMap[$import->getTableOrderByCodename('product')]['__table'];
        $productTableAnalog = $fieldsMap[$import->getTableOrderByCodename('analog')]['__table'];
        $options = $import->getTableOptions($productTableAnalog);
        $productId = $import->getSaved($productTable);

        $sql = sprintf('DELETE FROM `%s` WHERE `productId`=?', $productTableAnalog);
        $db = $import->getDb();
        $stmt = $db->getPdo()->prepare($sql);
        $stmt->execute([$productId]);

        if (!$value) {
            return false;
        }

        $analogs = explode($options['delimeter'], $value);

        /*foreach ($items as $value) {
        	$analogs[] = trim($value);
        }*/

        $analogItems = [];
        foreach ($analogs as $analog) {
            $analogItems[] = ['code' => $import->getHelper('nomenclatureCode', 'filter')->filter(trim($analog))];
        }

        $existIds = $import->getIds($analogItems, $productTable);

        $insertItems = [];
        foreach ($analogItems as $analog) {
            if (!$analog['id']) {
                $insertItems[] = $analog;
            }
        }

        $ids = [];
        if ($existIds) {
            $ids = array_merge($ids, $existIds); // if has exist analogs merge them
        }

        if ($newIds = $import->save($insertItems, $productTable)) {
            $ids = array_merge($ids, $newIds); // if has new analogs merge them
        }

        $items = [];
        foreach ($ids as $id) {
            $items[] = ['productId' => $productId, 'analogProductId' => $id];
        }
        return $items;
    }

}