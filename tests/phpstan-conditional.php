<?php declare(strict_types=1);

if (PHP_VERSION_ID < 80000) {
    return [
        'parameters' => [
            'excludePaths' => [
                'analyseAndScan' => [
                    'tests/Utility/Reflection/MyClassWithUnionsAndIntersections.php',
                ],
            ],
            'ignoreErrors' => [
                [
                    'message' => '#^Property Lkrms\\\\Curler\\\\Curler\:\:\$Handle has unknown class CurlHandle as its type\.$#',
                    'count' => 1,
                    'path' => '../src/Curler/Curler.php',
                ],
                [
                    'message' => '#^Property Lkrms\\\\Curler\\\\Curler\:\:\$MultiHandle has unknown class CurlMultiHandle as its type\.$#',
                    'count' => 1,
                    'path' => '../src/Curler/Curler.php',
                ],
                [
                    'message' => '#^Strict comparison using \=\=\= between string and false will always evaluate to false\.$#',
                    'count' => 1,
                    'path' => '../tests/TestCase.php',
                ],
            ],
        ]
    ];
}

return [
    'parameters' => [
        'ignoreErrors' => [
            [
                'message' => '#^Strict comparison using \=\=\= between array\<array\<int, int\|string\>\|int\|string, mixed\> and false will always evaluate to false\.$#',
                'count' => 1,
                'path' => '../src/Support/ArrayMapper.php',
            ],
            [
                'message' => '#^Call to an undefined method ReflectionType\:\:isBuiltin\(\)\.$#',
                'count' => 1,
                'path' => '../src/Utility/Reflection.php',
            ],
        ],
    ]
];
