<?php declare(strict_types=1);

if (\PHP_VERSION_ID < 80000) {
    return [
        'parameters' => [
            'excludePaths' => [
                'analyseAndScan' => [
                    'tests/fixtures/Util/Utility/Reflect/MyClassWithDnfTypes.php',
                    'tests/fixtures/Util/Utility/Reflect/MyClassWithUnionsAndIntersections.php',
                ],
            ],
            'ignoreErrors' => [],
        ]
    ];
}

return [
    'parameters' => [
        'ignoreErrors' => [
            [
                'message' => '#^Strict comparison using \=\=\= between array and false will always evaluate to false\.$#',
                'count' => 1,
                'path' => '../src/Util/Support/ArrayMapper.php',
            ],
        ],
    ]
];
