<?php
/**
 * Filter float number
 *
 * @category Agere
 * @package Agere_Importer
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 27.04.2016 20:08
 */
namespace Agere\Importer\Helper;

class FilterFloat implements FilterInterface {

	/**
	 * @param $num
	 * @return string
	 */
	public function filter($num) {
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
	}

}