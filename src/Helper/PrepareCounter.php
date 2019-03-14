<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2018 Serhii Popov
 * This source file is subject to The MIT License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @category Stagem
 * @package Stagem_Product
 * @author Vlad Kozak <vlad.gem.typ@gmail.com>
 * @license https://opensource.org/licenses/MIT The MIT License (MIT)
 */

namespace Popov\Importer\Helper;

use Popov\Importer\Importer;
use Popov\Variably\Helper\HelperAbstract;
use Popov\Variably\Helper\PrepareInterface;

/**
 * Class PrepareCounter
 *
 * This class is used usually in importer config file in order to write counter for every row data in the table.
 * It is universal class and you can use it in any config file,
 * because it get data about table and last counter from importer variable.
 *
 * @package Popov_Importer
 */
class PrepareCounter extends HelperAbstract implements PrepareInterface
{
    protected $counter;

    /**
     * This method check existing counter and if it isn`t exist it calculate a new one.
     *
     * @param $value
     * @return mixed
     */
    public function prepare($value)
    {
        /** @var Importer $importer */
        $importer = $this->getVariably()->get('importer');
        $table = $importer->getCurrentTable();

        if (!isset($this->counter[$table])) {
            $db = $importer->getDb();
            $counter = $db->fetchOne('SELECT MAX(DISTINCT counter) AS counter FROM ' . $table);

            $this->counter[$table] = $counter['counter'] + 1;
        }

        return $this->counter[$table];
    }
}
