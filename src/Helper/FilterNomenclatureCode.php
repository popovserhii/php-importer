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

class FilterNomenclatureCode {

	/** @var ImportService */
	/*protected $import;

	public function __construct(ImportService $import) {
		$this->import = $import;
	}

	public function getImport() {
		return $this->import;
	}*/

	/**
	 * @param $value
	 * @return string
	 */
	public function filter($value) {
		return sprintf("%'.011s", $value);
	}

}