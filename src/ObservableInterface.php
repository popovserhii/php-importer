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

interface ObservableInterface
{
    /**
     * Create and trigger an event.
     * Use this method when you do not want to create an EventInterface
     * instance prior to triggering. You will be required to pass:
     * - the event name
     * - the event target (can be null)
     * - any event parameters you want to provide (empty array by default)
     * It will create the Event instance for you and then trigger all listeners
     * related to the event.
     *
     * @param  string $eventName
     * @param  null|object|string $target
     * @param  array|object $params
     * @return void
     */
    public function trigger($eventName, $target = null, $params = []);

    /**
     * Attach a listener to an event
     *
     * The first argument is the event, and the next argument is a
     * callable that will respond to that event.
     *
     * @param  string $eventName Event to which to listen.
     * @param  callable $listener
     * @return callable
     */
    public function attach($eventName, callable $listener);

    /**
     * Detach a listener.
     *
     * @param callable $listener
     * @param null|string $eventName
     *     indicate all events.
     * @return void
     */
    public function detach(callable $listener, $eventName);

}