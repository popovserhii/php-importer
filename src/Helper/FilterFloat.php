<?php
/**
 * Filter Nomenclature Code
 *
 * @category Agere
 * @package Agere_Spare
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 27.04.2016 20:08
 */
namespace Agere\Spare\Service\Import\Helper;

use Agere\Spare\Service\Import\ImportService;

class FilterFloat {

	/** @var ImportService */
	/*protected $import;

	public function __construct(ImportService $import) {
		$this->import = $import;
	}

	public function getImport() {
		return $this->import;
	}*/

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