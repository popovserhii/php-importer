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

/**
 * Convert any numeric string to integer value.
 * Method is very general and is not optimized.
 * For custom value better create personal filter.
 *
 * @link http://php.net/manual/en/function.intval.php#111582
 */
class FilterInt implements FilterInterface {

	/**
	 * @param $num
	 * @return int
	 */
	public function filter($num) 
	{
		return (int) preg_replace('/[^\-\d]*(\-?\d*).*/', '$1', $num);
	}
}
