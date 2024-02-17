<?php declare(strict_types=1);

namespace Lkrms\LkUtil\Command\Generate;

use Lkrms\Cli\Catalog\CliOptionType;
use Lkrms\Cli\CliOption;
use Lkrms\LkUtil\Catalog\EnvVar;
use Lkrms\LkUtil\Command\Generate\Concept\GenerateCommand;
use PHPUnit\Framework\TestCase;
use Salient\Core\Utility\Reflect;
use Salient\Core\Utility\Str;

/**
 * Generates PHPUnit tests
 */
class GenerateTests extends GenerateCommand
{
    /**
     * Methods that don't generally need to be tested explicitly
     */
    private const SKIP = [
        '__construct',
        'id',
        'description',
        'getLongDescription',
        'getHelpSections',
    ];

    private string $ClassFqcn = '';

    private ?string $TestClassFqcn = null;

    /**
     * @var string[]
     */
    private array $Skip = [];

    public function description(): string
    {
        return 'Generate PHPUnit tests for the public methods of a class';
    }

    protected function getOptionList(): array
    {
        return [
            CliOption::build()
                ->long('class')
                ->valueName('class')
                ->description('The class to generate tests for')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->required()
                ->bindTo($this->ClassFqcn),
            CliOption::build()
                ->long('generate')
                ->valueName('test_class')
                ->description('The class to generate')
                ->optionType(CliOptionType::VALUE_POSITIONAL)
                ->bindTo($this->TestClassFqcn),
            CliOption::build()
                ->long('skip')
                ->short('k')
                ->description('Exclude a method from the tests')
                ->optionType(CliOptionType::VALUE)
                ->multipleAllowed()
                ->bindTo($this->Skip),
            ...$this->getOutputOptionList('tests', false),
        ];
    }

    protected function run(string ...$args)
    {
        $this->reset();

        $this->Skip = array_merge($this->Skip, self::SKIP);

        $classFqcn = $this->getRequiredFqcnOptionValue(
            'class',
            $this->ClassFqcn,
            null,
            $classClass
        );

        $this->getRequiredFqcnOptionValue(
            'class',
            $this->TestClassFqcn ?: $classFqcn . 'Test',
            EnvVar::NS_TESTS,
            $testClass,
            $testNamespace
        );

        $this->OutputClass = $testClass;
        $this->OutputNamespace = $testNamespace;

        $this->loadInputClass($classFqcn);

        $classPrefix = $this->getClassPrefix();

        // Search for the most appropriate `TestCase` to extend, e.g. for
        // `Lkrms\Tests\Sync\Command\GetSyncEntitiesTest`, try:
        //
        // - `Lkrms\Tests\Sync\Command\CommandTestCase` (generality: 0)
        // - `Lkrms\Tests\Sync\Command\TestCase` (generality: 1)
        // - `Lkrms\Tests\Sync\CommandTestCase` (generality: 0)
        // - `Lkrms\Tests\Sync\SyncTestCase` (generality: 1)
        // - `Lkrms\Tests\Sync\TestCase` (generality: 2)
        // - `Lkrms\Tests\CommandTestCase` (generality: 0)
        // - `Lkrms\Tests\SyncTestCase` (generality: 1)
        // - `Lkrms\Tests\TestsTestCase` (generality: 2)
        // - `Lkrms\Tests\TestCase` (generality: 3)
        // - `Lkrms\CommandTestCase `(generality: 0) <==
        // - `Lkrms\SyncTestCase` (generality: 1)
        // - `Lkrms\TestsTestCase` (generality: 2)
        // - `Lkrms\LkrmsTestCase` (generality: 3)
        // - `Lkrms\TestCase` (generality: 4)
        //
        // Preference is given to the `TestCase` with the lowest generality, so
        // in this example, `Lkrms\CommandTestCase` is extended instead of
        // `Lkrms\Tests\Sync\SyncTestCase`. If multiple classes have the same
        // generality, preference is given to the first encountered.

        $extends = null;
        $extendsGenerality = null;
        $namesIn = explode('\\', $testNamespace);
        $namesOut = [];
        while ($namesIn) {
            $namespace = implode('\\', $namesIn);
            $namesOut[] = array_pop($namesIn);
            foreach ([...$namesOut, ''] as $generality => $name) {
                $testCase = "{$namespace}\\{$name}TestCase";
                if (class_exists($testCase)) {
                    if (
                        $extendsGenerality === null ||
                        $extendsGenerality > $generality
                    ) {
                        $extends = $testCase;
                        $extendsGenerality = $generality;
                    }
                }
            }
        }

        $this->Extends[] = $classPrefix . (
            $extends === null
                ? TestCase::class
                : $extends
        );

        $this->Modifiers[] = 'final';

        foreach ($this->InputClass->getMethods() as $_method) {
            if (!$_method->isPublic()) {
                continue;
            }

            $method = $_method->getName();

            if (
                $_method->isFinal() &&
                $_method->getDeclaringClass()->getName() !== $this->InputClassName &&
                $method !== '__invoke'
            ) {
                continue;
            }

            if (in_array($method, $this->Skip)) {
                continue;
            }

            $testMethod = Str::toCamelCase("test_{$method}");
            $providerMethod = Str::toCamelCase("{$method}_provider");

            $_parameters = $_method->getParameters();
            if ($_method->hasReturnType()) {
                $returnType = Reflect::getTypeDeclaration(
                    $_method->getReturnType(),
                    $classPrefix,
                    fn(string $type): ?string =>
                        $this->getTypeAlias($type, $_method->getFileName(), false)
                );
                array_unshift(
                    $_parameters,
                    "$returnType \$expected"
                );
            } else {
                array_unshift(
                    $_parameters,
                    '$expected'
                );
            }

            if ($_method->isStatic()) {
                $arguments = [];
                foreach ($_method->getParameters() as $_parameter) {
                    $arguments[] =
                        ($_parameter->isVariadic()
                            ? '...'
                            : '') . '$' . $_parameter->getName();
                }

                $code = sprintf(
                    '$this->assertSame($expected, %s::%s(%s));',
                    $this->getFqcnAlias($classFqcn, $classClass),
                    $method,
                    implode(', ', $arguments)
                );
            } else {
                $code = '$this->assertTrue(true);';
            }

            $this->addMethod($testMethod, $code, $_parameters, 'void', "@dataProvider $providerMethod", false);
            $this->addMethod($providerMethod, 'return [];', [], 'array', '', true);
        }

        $this->handleOutput($this->generate());
    }
}
