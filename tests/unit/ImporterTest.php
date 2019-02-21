<?php
/**
 * The MIT License (MIT)
 * Copyright (c) 2019 Serhii Popov
 * This source file is subject to The MIT License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/MIT
 *
 * @category PopovTest
 * @package PopovTest_Importer
 * @author Serhii Popov <popow.serhii@gmail.com>
 * @license https://opensource.org/licenses/MIT The MIT License (MIT)
 */

namespace PopovTest\Importer;

use Mockery;
use Popov\Db\Db;
use Popov\Importer\DriverCreator;
use Popov\Importer\Importer;
use Popov\Variably\ConfigHandler;
use Popov\Variably\Preprocessor;
use Popov\Variably\Variably;
use Psr\Log\LoggerInterface;
use Zend\Stdlib\Exception;
use PHPUnit\Framework\TestCase;

class ImporterTest extends TestCase
{
    /**
     * @var Db|Mockery\MockInterface
     */
    protected $dbMock;

    /**
     * @var ConfigHandler|Mockery\MockInterface
     */
    protected $configHandlerMock;

    /**
     * @var Preprocessor|Mockery\MockInterface
     */
    protected $preprocessorMock;

    protected function setUp()
    {
        $this->dbMock = Mockery::mock(Db::class);

        $this->configHandlerMock = Mockery::mock(ConfigHandler::class);
        $this->configHandlerMock->shouldReceive('setConfig')->andReturn(Mockery::self());
        $this->configHandlerMock->shouldReceive('getVariably')
            ->andReturn(Mockery::mock(Variably::class)->shouldReceive('set')->withAnyArgs());

        $this->preprocessorMock = Mockery::mock(Preprocessor::class);
    }

    protected function tearDown()
    {
        Mockery::close();
    }

    public function testLogMessageToLoggerAndInMemory()
    {
        $loggerMock = Mockery::mock(LoggerInterface::class);
        $loggerMock->shouldReceive('log')
            ->with('info', $message = 'Write message to log', [])
            ->once();

        $importer = new Importer($this->dbMock, $this->configHandlerMock, $this->preprocessorMock);
        $importer->setLogger($loggerMock);

        $importer->log('info', $message);

        $this->assertEquals(1, count($messages = $importer->getMessages()));
        $this->assertContains($message, $messages['info']);
    }

    public function testLogShouldGroupIdentical()
    {
        $loggerMock = Mockery::mock(LoggerInterface::class);
        $loggerMock->shouldReceive('log')
            ->withAnyArgs()
            /*->once()*/;

        $importer = new Importer($this->dbMock, $this->configHandlerMock, $this->preprocessorMock);
        $importer->setLogger($loggerMock);

        $importer->log('notice', $message = 'Hello real world!');
        $importer->log('notice', $message = 'Hello real world!');

        $this->assertEquals(1, count($messages = $importer->getMessages()));
        $this->assertContains('(2) Hello real world!', $messages['notice']);
    }

    public function testLogShouldHaveDifferentLevelForMessages()
    {
        $loggerMock = Mockery::mock(LoggerInterface::class);
        $loggerMock->shouldReceive('log')
            ->withAnyArgs()
            /*->once()*/;

        $importer = new Importer($this->dbMock, $this->configHandlerMock, $this->preprocessorMock);
        $importer->setLogger($loggerMock);

        $importer->log('info', $message = 'Hello real world!');
        $importer->log('notice', $message = 'Value does not have default value');
        $importer->log('error', $message = 'Some error occurred');
        $importer->log('critical', $message = 'Some critical crash occurred');

        $this->assertEquals(4, count($messages = $importer->getMessages()));
        $this->assertEquals(['info', 'notice', 'error', 'critical'], array_keys($messages));
    }
}