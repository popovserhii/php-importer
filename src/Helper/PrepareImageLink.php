<?php
/**
 * Temporary import service
 *
 * @category Agere
 * @package Agere_Spare
 * @author Vlad Kozak <vk@agere.com.ua>
 * @datetime: 18.03.2016 21:51
 */
namespace Agere\Spare\Service\Import\Helper;

use Agere\Spare\Service\Import\ImportService;

class PrepareImageLink {

	/** @var ImportService */
	protected $import;

	protected $__fieldsImage = ['link' => '', 'code' => '', 'pathToImage' => '/uploads/images/'];

	public function __construct(ImportService $import) {
		$this->import = $import;
	}

	public function getImport() {
		return $this->import;
	}
    
	public function prepare($value) {
		if (!$value) {
			return false;
		}

		$import = $this->getImport();
		$fieldsMap = $import->getFieldsMap();
		$preparedFields = $import->getPreparedFields();

		$productTable = $fieldsMap[$import->getTableOrderByCodename('product')]['__table'];

		//\Zend\Debug\Debug::dump([$import->getTableOrderByCodename('product'), $productTable]); die(__METHOD__);
		
		$this->__fieldsImage['link'] = $value;
		$this->__fieldsImage['code'] = $preparedFields[$productTable]['code'];

		$sql = sprintf('SELECT %s FROM `%s` WHERE %s = ?', 'image', $productTable, 'code');

		$db = $import->getDb();
		$stmt = $db->getPdo()->prepare($sql);
		$stmt->execute([$this->__fieldsImage['code']]);
		$result = $stmt->fetch();

		if ($result['image']) {
			return $result['image'];
		} else {
			return $this->downloadImage();
		}
	}

	protected function generateUrl() {
		return $this->__fieldsImage['pathToImage'] . $this->__fieldsImage['code'] . '.jpg';
	}

	protected function downloadImage() {
		$path = $this->generateUrl();

		try {
			if (!file_exists('public' . $path)) {
				if ($this->grabImage($this->__fieldsImage['link'], 'public' . $path)) {
					return $path;
				} else {
					return false;
				}
			}
			return $path;
		} catch (\Exception $e) {
			$import = $this->getImport();
			$errors = $import->getErrors();
			$errors['image'] = 'Error image download: ' . $this->__fieldsImage['link'];
			$import->setMessages('error', $errors);
		}
	}

	protected function grabImage($url, $saveTo) {
	    $ch = curl_init ($url);

	    curl_setopt($ch, CURLOPT_HEADER, 0);
	    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	    curl_setopt($ch, CURLOPT_BINARYTRANSFER,1);

	    $raw = curl_exec($ch);
		$info = curl_getinfo($ch);
	    curl_close ($ch);

	    if ($info['http_code'] != 200) {
	    	return false;
	    }

	    if(file_exists($saveTo)){
	        unlink($saveTo);
	    }
	    
	    $fp = fopen($saveTo, 'x');
	    fwrite($fp, $raw);
	    fclose($fp);
	    return true;
	}
}