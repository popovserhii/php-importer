<?php
/**
 * Filter int number
 *
 * @category Agere
 * @package Agere_Importer
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 27.04.2016 20:08
 */
namespace Agere\Importer\Helper;

class FilterInt implements FilterInterface {

	/**
	 * @param $num
	 * @return string
	 */
	public function filter($num) {
		return (int) $num;
	}

}