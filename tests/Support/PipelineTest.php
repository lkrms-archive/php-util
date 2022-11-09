<?php

declare(strict_types=1);

namespace Lkrms\Tests\Support;

use Closure;
use Lkrms\Exception\PipelineException;
use Lkrms\Support\ArrayKeyConformity;
use Lkrms\Support\ArrayMapperFlag;
use Lkrms\Support\Pipeline;
use UnexpectedValueException;

final class PipelineTest extends \Lkrms\Tests\TestCase
{
    public function testStream()
    {
        $in  = [12, 23, 34, 45, 56, 67, 78, 89, 90];
        $out = [];
        foreach ((new Pipeline())
            ->stream($in)
            ->through(
                fn($payload, Closure $next) => $next($payload * 3),
                fn($payload, Closure $next) => $next($payload / 23),
                fn($payload, Closure $next) => $next(round($payload, 3)),
            )->start() as $_out)
        {
            $out[] = $_out;
        }

        $this->assertSame(
            [1.565, 3.0, 4.435, 5.87, 7.304, 8.739, 10.174, 11.609, 11.739],
            $out
        );
    }

    public function testUnless()
    {
        $in  = [12, 23, 34, 45, 56, 67, 78, 89, 90];
        $out = [];
        foreach ((new Pipeline())
            ->stream($in)
            ->through(
                fn($payload, Closure $next) => $payload % 2 ? null : $next($payload * 3),
                fn($payload, Closure $next) => $next($payload / 23),
                fn($payload, Closure $next) => $payload < 11 ? $next(round($payload, 3)) : null,
            )->unless(fn($result) => !is_null($result))
            ->start() as $_out)
        {
            $out[] = $_out;
        }

        $this->assertSame(
            [1.565, 4.435, 7.304, 10.174],
            $out
        );

        $this->expectException(PipelineException::class);
        (new Pipeline())
            ->send(23)
            ->through(
                fn($payload, Closure $next) => $payload % 2 ? null : $next($payload * 3),
                fn($payload, Closure $next) => $next($payload / 23),
                fn($payload, Closure $next) => $payload < 11 ? $next(round($payload, 3)) : null,
            )->unless(fn($result) => !is_null($result))
            ->go();
    }

    public function testMap()
    {
        $in = [
            [
                "USER_ID"   => 32,
                "FULL_NAME" => "Greta",
                "MAIL"      => "greta@domain.test",
            ],
            [
                "FULL_NAME" => "Amir",
                "MAIL"      => "amir@domain.test",
                "URI"       => "https://domain.test/~amir",
            ],
            [
                "USER_ID"   => 71,
                "FULL_NAME" => "Terry",
                "MAIL"      => null,
            ],
        ];
        $map = [
            "USER_ID"   => "Id",
            "FULL_NAME" => "Name",
            "MAIL"      => "Email",
        ];
        $out = [];

        $pipeline = Pipeline::create()->withConformity(ArrayKeyConformity::NONE)->throughKeyMap($map, 0);
        foreach ($in as $_in)
        {
            $out[] = $pipeline->send($_in)->run();
        }

        $pipeline = Pipeline::create()->withConformity(ArrayKeyConformity::NONE)->throughKeyMap($map, ArrayMapperFlag::ADD_MISSING);
        foreach ($in as $_in)
        {
            $out[] = $pipeline->send($_in)->run();
        }

        $pipeline = Pipeline::create()->withConformity(ArrayKeyConformity::NONE)->throughKeyMap($map, ArrayMapperFlag::ADD_UNMAPPED);
        foreach ($in as $_in)
        {
            $out[] = $pipeline->send($_in)->run();
        }

        $pipeline = Pipeline::create()->withConformity(ArrayKeyConformity::NONE)->throughKeyMap($map, ArrayMapperFlag::REMOVE_NULL);
        foreach ($in as $_in)
        {
            $out[] = $pipeline->send($_in)->run();
        }

        $pipeline = Pipeline::create()->withConformity(ArrayKeyConformity::NONE)->throughKeyMap($map, ArrayMapperFlag::ADD_MISSING | ArrayMapperFlag::ADD_UNMAPPED | ArrayMapperFlag::REMOVE_NULL);
        foreach ($in as $_in)
        {
            $out[] = $pipeline->send($_in)->run();
        }

        $mapToMultiple = [
            "USER_ID"   => "Id",
            "FULL_NAME" => "Name",
            "MAIL"      => ["Email", "UPN"],
        ];
        $pipeline = Pipeline::create()->withConformity(ArrayKeyConformity::NONE)->throughKeyMap($mapToMultiple, ArrayMapperFlag::ADD_MISSING);
        foreach ($in as $_in)
        {
            $out[] = $pipeline->send($_in)->run();
        }

        $compliantIn = [
            [
                "USER_ID"   => 32,
                "FULL_NAME" => "Greta",
                "MAIL"      => "greta@domain.test",
            ],
            [
                "USER_ID"   => 53,
                "FULL_NAME" => "Amir",
                "MAIL"      => "amir@domain.test",
            ],
            [
                "USER_ID"   => 71,
                "FULL_NAME" => "Terry",
                "MAIL"      => null,
            ],
        ];
        $pipeline = Pipeline::create()->withConformity(ArrayKeyConformity::COMPLETE)->throughKeyMap($map, 0);
        foreach ($compliantIn as $_in)
        {
            $out[] = $pipeline->send($_in)->run();
        }

        $this->assertSame([
            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Name' => 'Amir', 'Email' => 'amir@domain.test'],
            ['Id' => 71, 'Name' => 'Terry', 'Email' => null],

            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Id' => null, 'Name' => 'Amir', 'Email' => 'amir@domain.test'],
            ['Id' => 71, 'Name' => 'Terry', 'Email' => null],

            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Name' => 'Amir', 'Email' => 'amir@domain.test', 'URI' => 'https://domain.test/~amir'],
            ['Id' => 71, 'Name' => 'Terry', 'Email' => null],

            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Name' => 'Amir', 'Email' => 'amir@domain.test'],
            ['Id' => 71, 'Name' => 'Terry'],

            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Name' => 'Amir', 'Email' => 'amir@domain.test', 'URI' => 'https://domain.test/~amir'],
            ['Id' => 71, 'Name' => 'Terry'],

            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test', 'UPN' => 'greta@domain.test'],
            ['Id' => null, 'Name' => 'Amir', 'Email' => 'amir@domain.test', 'UPN' => 'amir@domain.test'],
            ['Id' => 71, 'Name' => 'Terry', 'Email' => null, 'UPN' => null],

            ['Id' => 32, 'Name' => 'Greta', 'Email' => 'greta@domain.test'],
            ['Id' => 53, 'Name' => 'Amir', 'Email' => 'amir@domain.test'],
            ['Id' => 71, 'Name' => 'Terry', 'Email' => null],
        ], $out);

        $pipeline = Pipeline::create()->withConformity(ArrayKeyConformity::NONE)->throughKeyMap($map, ArrayMapperFlag::REQUIRE_MAPPED);
        $this->expectException(UnexpectedValueException::class);
        foreach ($in as $_in)
        {
            $pipeline->send($_in)->run();
        }
    }
}