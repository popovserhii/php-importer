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

class PrepareTags {

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
        $tableOrder = $import->getTableOrders();

        $tagTable = 'tag';

        $productTable = $fieldsMap[$import->getTableOrderByCodename('product')]['__table'];
        $tagItemTable = $fieldsMap[$import->getTableOrderByCodename('tag')]['__table'];
        $moduleMnemo = $fieldsMap[$import->getTableOrderByCodename('product')]['__moduleMnemo'];
        $options = $import->getTableOptions($tagItemTable);

        $sql = sprintf('DELETE FROM `%s` WHERE `moduleMnemo`=? AND `itemId`=?', $tagItemTable);
        $db = $import->getDb();
        $stmt = $db->getPdo()->prepare($sql);
        $stmt->execute([$moduleMnemo, $import->getSaved($productTable)]);

        if (!$value) {
            return false;
        }

        $tags = explode($options['delimeter'], $value);
        $tagItems = [];

        foreach ($tags as $tag) {
            $tagItems[] = ['name' => $tag];
        }

        $countTable = count($tableOrder);
        $import->setTableOrder($tagTable, $countTable);
        $import->setFieldsMap($countTable, [
            '__table' => $tagTable,
            '__identifier' => 'name',
            '__exclude' => true
        ]);

        $existIds = $import->getIds($tagItems, $tagTable);

        $insertItems = [];
        foreach ($tagItems as $tag) {
            if (!$tag['id']) { // not exist
                $insertItems[] = $tag;
            }
        }

        $ids = [];
        if ($existIds) {
            $ids = array_merge($ids, $existIds); // if has exist tags merge them
        }

        if ($newIds = $import->save($insertItems, $tagTable)) {
            $ids = array_merge($ids, $newIds); // if has new tags merge them
        }

        $import->unsetTableOrder($tagTable);
        $import->unsetFieldsMap($countTable);

        $items = [];
        foreach ($ids as $id) {
            $items[] = ['tagId' => $id, 'moduleMnemo' => $moduleMnemo, 'itemId' => $import->getSaved($productTable)];
        }

        return $items;
    }
}