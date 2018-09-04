<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2018 Serhii Popov
 * This source file is subject to The MIT License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @category Popov
 * @package Popov_Importer
 * @author Serhii Popov <popow.serhii@gmail.com>
 * @license https://opensource.org/licenses/MIT The MIT License (MIT)
 */

namespace Popov\Importer;

class Observable implements ObservableInterface
{
    protected $events = [];

    public function trigger($eventName, $target = null, $params = [])
    {
        foreach ($this->events[$eventName] as $listener) {
            $listener($target, $params);
        }
    }

    /**
     * @inheritDoc
     */
    public function attach($eventName, callable $listener)
    {
        $this->events[$eventName][] = $listener;

        return $listener;
    }

    /**
     * @inheritDoc
     */
    public function detach(callable $listener, $eventName)
    {
        foreach ($this->events[$eventName] as $i => $callback) {
            if ($callback === $listener) {
                unset($this->events[$eventName][$i]);
            }
        }
    }
}