<?php
/**
 * Quantity Import
 *
 * @category Agere
 * @package Agere_Spare
 * @author Sergiy Popov <popov@agere.com.ua>
 * @datetime: 07.03.2016 02:48
 */
namespace Agere\Spare\Service\Import;

class QuantityImportService extends AbstractImportService
{
    protected $fieldsMap = [
        [
            'КодНоменклатуры' => ['name' => 'code', '__filter' => ['nomenclatureCode']],
            '1С_ИТОГО' => 'quantity',
            '__table' => 'ra_spare',
            '__codename' => 'product', // code name of position
            '__identifier' => 'code',
            '__exclude' => false,
            '__options' => ['mode' => 'update'],
        ],
        [
            '__dynamic' => ['__prepare' => ['dynamic']],
            '__table' => 'ra_spare_city',
            '__codename' => 'productCity', // code name of position
            '__identifier' => false, // improve performance if id will be never use
            '__exclude' => false,
            '__options' => ['startAfter' => 'ВИРТ_ИТОГО'], // dynamic columns put in end of document,
                                                           // 'startAfter' mean after which field begin dynamic columns
        ],
    ];
}