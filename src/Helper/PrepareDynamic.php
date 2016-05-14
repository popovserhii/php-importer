<?php
/**
 * Prepare Dynamic fields
 *
 * @category Agere
 * @package Agere_Spare
 * @author Popov Sergiy <popov@agere.com.ua>
 * @datetime: 26.04.2016 0:09
 */
namespace Agere\Spare\Service\Import\Helper;

use Agere\Spare\Service\Import\QuantityImportService;

class PrepareDynamic {

	/** @var QuantityImportService */
	protected $import;

	public function __construct(QuantityImportService $import) {
		$this->import = $import;
	}

	public function getImport() {
		return $this->import;
	}

	/**
	 * @param $value
	 * @return array
	 */
	public function prepare($value) {
		static $i = 0, $values = [], $cities = [], $citiesQuantity = 0, $dynamicFields = [], $dynamicFieldsQuantity = 0;

		$import = $this->getImport();
		$fieldsMap = $import->getFieldsMap();

		$cityTable = 'city';
		$productTable = $fieldsMap[$import->getTableOrderByCodename('product')]['__table'];
		$productCityTable = $fieldsMap[$import->getTableOrderByCodename('productCity')]['__table'];

		#$productTable = 'ra_spare';
		#$productCityTable = 'ra_spare_city';
		#$cityTable = 'city';

		if (!$dynamicFields) {
			foreach ($import->getTableFieldsMap($productCityTable) as $field => $params) {
				if (substr($field, 0, 2) !== '__') { // skip optional fields
					$dynamicFields[$field] = $dynamicFieldsQuantity;
					$dynamicFieldsQuantity++;
				}
			}

			$sql = sprintf('SELECT `id`, `abbreviation` as `code` FROM `%s` WHERE `abbreviation` IN (%s)',
				$cityTable,
				rtrim(str_repeat('?,', ($dynamicFieldsQuantity)), ',')
			);
			//\Zend\Debug\Debug::dump([$sql]); die(__METHOD__);

			$pdo = $import->getPdo();
			$stmt = $pdo->prepare($sql);
			$stmt->execute(array_keys($dynamicFields));
			$result = $stmt->fetchAll();

			$cities = [];
			foreach ($result as $city) {
				$cities[$city['code']] = $city['id'];
				$citiesQuantity++;
			}
			//\Zend\Debug\Debug::dump([array_keys($dynamicFields), $cities/*, $dynamicFieldsQuantity, $dynamicFields, $this->fieldsMap, $value*/]); die(__METHOD__);
		}

		$i++;
		$values[] = $value;
		if ($i === $dynamicFieldsQuantity) {
			$items = [];
			if ($productId = $import->getSaved($productTable)) {
				// id 	productId 	cityId
				$sql = sprintf('SELECT * FROM `%s` WHERE `productId` = ? AND `cityId` IN (%s)',
					$productCityTable,
					rtrim(str_repeat('?,', $citiesQuantity), ',')
				);

				//\Zend\Debug\Debug::dump([$productId] + $cities); die(__METHOD__);
				//\Zend\Debug\Debug::dump([$sql]); //die(__METHOD__);

				//\Zend\Debug\Debug::dump([$cityProducts, $productId, array_values($cities), array_merge([$productId], array_values($cities))/*, $dynamicFieldsQuantity, $dynamicFields, $this->fieldsMap, $value*/]); die(__METHOD__);

				$mergedValues = array_merge([$productId], array_values($cities));
				$pdo = $import->getPdo();
				$stmt = $pdo->prepare($sql);
				$stmt->execute($mergedValues);
				$result = $stmt->fetchAll();

				$cityProducts = [];
				foreach ($result as $cityProduct) {
					$cityProducts[$cityProduct['cityId']] = $cityProduct;
				}

				foreach ($cities as $code => $id) {
					$items[] = [
						'id' => isset($cityProducts[$id]) ? $cityProducts[$id]['id'] : 0,
						'productId' => $productId,
						'cityId' => $id,
						'quantity' => (int) $values[$dynamicFields[$code]] // get value by index
					];
				}
				//\Zend\Debug\Debug::dump([$items, $productId/*, $dynamicFieldsQuantity, $dynamicFields, $this->fieldsMap, $value*/]); //die(__METHOD__);
			}

			$i = 0;
			$values = []; // unset for nex row

			return $items;
		}
	}

}