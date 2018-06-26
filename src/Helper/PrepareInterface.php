<?php
/**
 * Prepare Interface
 *
 * @category Popov
 * @package Popov_Importer
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 13.05.2016 13:38
 */
namespace Popov\Importer\Helper;

interface PrepareInterface
{
    /**
     * Prepare value
     *
     * @param $value
     * @return mixed
     */
    public function prepare($value);
}
