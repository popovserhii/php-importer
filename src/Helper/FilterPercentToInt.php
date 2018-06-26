<?php
/**
 * Filter percent value (3%, 0.03) to integer value
 *
 * @category Popov
 * @package Popov_Importer
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 01.06.2016 13:34
 */
namespace Popov\Importer\Helper;

class FilterPercentToInt implements FilterInterface {

	/**
	 * @param $num
	 * @return int
	 */
	public function filter($num) 
	{
		if ($this->endsWith($num, '%')) { // 3%
			$num = rtrim($num, '%');
		} elseif ($num >= 0 && $num < 1) { // 0.03
			$num = $num * 100;
		}
		
		return (int) $num;
	}
	
	/**
	 * Search forward starting from end minus needle length characters
	 * @link http://stackoverflow.com/a/10473026
	 */
	protected function endsWith($haystack, $needle)
	{
		$length = strlen($needle);
		if ($length == 0) {
			return true;
		}

		return (substr($haystack, -$length) === $needle);
	}
}
