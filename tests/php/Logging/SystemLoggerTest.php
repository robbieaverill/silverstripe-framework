<?php

namespace SilverStripe\Logging\Tests;

use Psr\Log\LoggerInterface;
use Monolog\Handler\NullHandler;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;

/**
 * Tests for modules and user code that want to use the "system logger" to log information without
 * having it coupled to the "core logger".
 */
class SystemLoggerTest extends SapphireTest
{
    public function testSystemLoggerIsDifferentToCore()
    {
        $coreLogger = Injector::inst()->get(LoggerInterface::class . '.core');
        $systemLogger = Injector::inst()->get(LoggerInterface::class);
        $this->assertInstanceOf(LoggerInterface::class, $systemLogger);
        $this->assertNotSame($systemLogger, $coreLogger);
    }

    public function testCoreLoggerDoesNotHandleSystemLogs()
    {
        $coreLoggerHandler = $this->getMockBuilder(NullHandler::class)
            ->setMethods(['handle'])
            ->getMock();

        $coreLoggerHandler->expects($this->never())->method('handle');

        Injector::inst()->get(LoggerInterface::class . '.core')
            ->setHandlers([$coreLoggerHandler]);

        $systemLogger = Injector::inst()->get(LoggerInterface::class);

        $systemLogger->debug('debug message');
        $systemLogger->info('info message');
        $systemLogger->notice('notice message');
        $systemLogger->warning('warning message');
        $systemLogger->error('error message');
        $systemLogger->critical('critical message');
        $systemLogger->alert('alert message');
        $systemLogger->emergency('emergency message');
    }
}
