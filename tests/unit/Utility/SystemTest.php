<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Utility\System;
use LogicException;

final class SystemTest extends \Lkrms\Tests\TestCase
{
    public function testStartTimer()
    {
        $system = new System();

        $time0 = hrtime(true) / 1000000;
        $system->startTimer('primary');
        $system->startTimer('secondary');
        usleep(100000);
        $time1 = hrtime(true) / 1000000;

        $system->startTimer('other', 'special');
        $secondary = $system->stopTimer('secondary');
        usleep(200000);
        $time2 = hrtime(true) / 1000000;

        $system->startTimer('secondary');
        usleep(75000);
        $time3 = hrtime(true) / 1000000;

        $system->stopTimer('secondary');
        $system->stopTimer('other', 'special');
        usleep(100000);
        $time4 = hrtime(true) / 1000000;

        $timers = $system->getTimers();
        $generalTimers = $system->getTimers(true, 'general');
        $specialTimers = $system->getTimers(true, 'special');
        $stoppedTimers = $system->getTimers(false);
        // Allow timing to be off by up to 16ms because of [Windows] timer
        // granularity
        $this->assertEqualsWithDelta($time1 - $time0, $secondary, 16);
        $this->assertEqualsWithDelta($time4 - $time0, $timers['general']['primary'][0], 16);
        $this->assertEqualsWithDelta(($time1 - $time0) + ($time3 - $time2), $timers['general']['secondary'][0], 16);
        $this->assertEqualsWithDelta($time3 - $time1, $timers['special']['other'][0], 16);
        $this->assertSame(1, $timers['general']['primary'][1]);
        $this->assertSame(2, $timers['general']['secondary'][1]);
        $this->assertSame(1, $timers['special']['other'][1]);
        $this->assertArrayHasSignature(['general'], $generalTimers);
        $this->assertArrayHasSignature(['primary', 'secondary'], $generalTimers['general']);
        $this->assertArrayHasSignature(['special'], $specialTimers);
        $this->assertArrayHasSignature(['other'], $specialTimers['special']);
        $this->assertArrayHasSignature(['secondary'], $stoppedTimers['general']);
        $this->assertArrayHasSignature(['other'], $stoppedTimers['special']);
        $this->expectException(LogicException::class);
        $system->startTimer('primary');
    }

    public function testStopTimer()
    {
        $this->expectException(LogicException::class);
        $system = new System();
        $system->stopTimer('primary');
    }

    public function testGetWorkingDirectory()
    {
        $system = new System();
        $cwd = $system->getCwd();
        $this->assertSame(fileinode($cwd), fileinode(getcwd()));
    }
}
