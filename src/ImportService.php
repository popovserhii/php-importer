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

class ImportService extends AbstractImportService {

    protected $fieldsMap = [
        [
            'Бренд' => 'name',
            //'__entity' => 'Agere\Spare\Model\Group',
            '__table' => 'brand',
            '__codename' => 'brand', // code name of position
            '__identifier' => 'name',
        ],
        [
            'КодГруппы' => 'code',
            'Группа' => 'name',
            'Номер группы' => 'number',
            'Идентификатор группы' => 'identifier',
            '__entity' => 'Agere\Spare\Model\Group',
            '__table' => 'ra_spare_group',
            '__codename' => 'group', // code name of position
            '__identifier' => 'code',
        ],
        [
            'КодНоменклатуры' => ['name' => 'code', '__filter' => ['nomenclatureCode']],
            'Артикул' => 'sku',
            'Применяемость' => 'applicability',
            'Полное наименование' => 'name',
            'Описание' => 'description',
            'Количество 1С' => 'quantity',
            'Единица измерения' => 'unitMeasure',
            'Минимальный объем заказа' => 'orderMinimum',
            'Цена с НДС' => ['name' => 'priceWithTax', '__filter' => ['float']],
            'Цена без НДС' => ['name' => 'priceWithoutTax', '__filter' => ['float']],
            'Валюта' => 'currency',
            'ссылка на изображение' => ['name' => 'image', '__prepare' => ['imageLink']],
            'ссылка на схему в каталоге' => 'imageCatalogSchema',
            'Комментарий' => 'comment',
            'Наименование 1С' => 'name1c',
            'ИндексМодели' => 'modelIndex',
            'КодМодели' => 'modelCode',
            'НомерНоменклатурный' => 'nomenclatureNumber',
            'ЦифровойИндекс' => 'numericIndex',
            'ЕКНГАЗ' => 'EKNGAZ',
            'ЕКНЗМЗ' => 'EKNZMZ',
            'ЕКНУАЗ' => 'EKNUAZ',
            'СобствПроизв' => 'ownManufacture',
            '__entity' => 'Agere\Spare\Model\Product',
            '__moduleMnemo' => 'spare',
            '__table' => 'ra_spare', // russian auto spare
            '__codename' => 'product', // code name of position
            '__identifier' => 'code',
            '__foreign' => ['ra_spare_group' => 'groupId', 'brand' => 'brandId'], // ra_spare_group.id -> ra_spare.groupId
            //'__order' => 20,
        ],
        [
            'Аналоги' => ['__prepare' => ['analog']],
            '__table' => 'ra_spare_analog',
            '__codename' => 'analog', // code name of position
            '__exclude' => false, // Prepare differently, exclude from general save.
            '__identifier' => false,
            '__options' => ['delimeter' => ';'],
            //'__order' => 30,
        ],
        [
            'Ключевые слова' => ['__prepare' => ['tags']],
            '__table' => 'tag_item',
            '__codename' => 'tag', // code name of position
            '__identifier' => false, // improve performance if id will be never use
            '__exclude' => false,
            '__options' => ['delimeter' => ';'],
            //'__order' => 25,
        ],
    ];

}