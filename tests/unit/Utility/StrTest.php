<?php declare(strict_types=1);

namespace Lkrms\Tests\Utility;

use Lkrms\Tests\TestCase;
use Lkrms\Utility\Get;
use Lkrms\Utility\Str;

final class StrTest extends TestCase
{
    /**
     * @dataProvider coalesceProvider
     */
    public function testCoalesce(?string $expected, ?string $string, ?string ...$strings): void
    {
        $this->assertSame($expected, Str::coalesce($string, ...$strings));
    }

    /**
     * @return array<array<string|null>>
     */
    public static function coalesceProvider(): array
    {
        return [
            [null, null],
            ['', ''],
            [null, '', null],
            ['', null, ''],
            ['a', '', null, 'a', null],
            ['0', '0', '1', null],
        ];
    }

    /**
     * @dataProvider setEolProvider
     */
    public function testSetEol(string $expected, string $string, string $eol = "\n"): void
    {
        $this->assertSame($expected, Str::setEol($string, $eol));
    }

    /**
     * @return array<array{string,string,2?:string}>
     */
    public static function setEolProvider(): array
    {
        return [
            [
                '',
                '',
            ],
            [
                "\n",
                "\n",
            ],
            [
                "\n",
                "\r\n",
            ],
            [
                "\r",
                "\r\n",
                "\r",
            ],
            [
                "\r\r",
                "\n\r",
                "\r",
            ],
            [
                "line1\nline2\n",
                "line1\nline2\n",
            ],
            [
                "line1\nline2\n",
                "line1\r\nline2\r\n",
            ],
            [
                "line1\nline2\n",
                "line1\rline2\r",
            ],
            [
                "line1\nline2\nline3\nline4\n\n",
                "line1\r\nline2\nline3\rline4\n\r",
            ],
            [
                "line1\rline2\rline3\rline4\r\r",
                "line1\r\nline2\nline3\rline4\n\r",
                "\r",
            ],
            [
                "line1\r\nline2\r\nline3\r\nline4\r\n\r\n",
                "line1\r\nline2\nline3\rline4\n\r",
                "\r\n",
            ],
            [
                "line1<br />\nline2<br />\nline3<br />\nline4<br />\n<br />\n",
                "line1\r\nline2\nline3\rline4\n\r",
                "<br />\n",
            ],
        ];
    }

    /**
     * @dataProvider eolToNativeProvider
     */
    public function testEolToNative(?string $expected, ?string $string): void
    {
        $this->assertSame($expected, Str::eolToNative($string));
    }

    /**
     * @return array<array{string,string}>
     */
    public static function eolToNativeProvider(): array
    {
        return [
            [
                null,
                null,
            ],
            [
                '',
                '',
            ],
            [
                <<<'EOF'


                EOF,
                "\n",
            ],
            [
                <<<'EOF'
                line1
                line2


                EOF,
                "line1\nline2\n\n",
            ],
        ];
    }

    /**
     * @dataProvider eolFromNativeProvider
     */
    public function testEolFromNative(?string $expected, ?string $string): void
    {
        $this->assertSame($expected, Str::eolFromNative($string));
    }

    /**
     * @return array<array{string,string}>
     */
    public static function eolFromNativeProvider(): array
    {
        return [
            [
                null,
                null,
            ],
            [
                '',
                '',
            ],
            [
                "\n",
                <<<'EOF'


                EOF,
            ],
            [
                "line1\nline2\n\n",
                <<<'EOF'
                line1
                line2


                EOF,
            ],
        ];
    }

    /**
     * @dataProvider caseConversionProvider
     */
    public function testCaseConversion(
        string $expectedLower,
        string $expectedUpper,
        string $expectedUpperFirst,
        string $string
    ): void {
        $this->assertSame($expectedLower, Str::lower($string));
        $this->assertSame($expectedUpper, Str::upper($string));
        $this->assertSame($expectedUpperFirst, Str::upperFirst($string));
    }

    /**
     * @return array<array{string,string,string,string}>
     */
    public static function caseConversionProvider(): array
    {
        return [
            ['', '', '', ''],
            ['foobar', 'FOOBAR', 'FoObAr', 'foObAr'],
            ['foo bar', 'FOO BAR', 'FoO bAr', 'foO bAr'],
            ['123 foo', '123 FOO', '123 foo', '123 foo'],
            ['äëïöüÿ', 'äëïöüÿ', 'äëïöüÿ', 'äëïöüÿ'],
        ];
    }

    /**
     * @dataProvider matchCaseProvider
     */
    public function testMatchCase(string $expected, string $string, string $match): void
    {
        $this->assertSame($expected, Str::matchCase($string, $match));
    }

    /**
     * @return array<string,array{string,string,string}>
     */
    public static function matchCaseProvider(): array
    {
        return [
            'uppercase' => ['HELLO', 'hElLo', 'WORLD'],
            'lowercase' => ['hello', 'hElLo', 'world'],
            'title case' => ['Hello', 'hElLo', 'World'],
            'uppercase char' => ['Hello', 'hElLo', 'A'],
            'lowercase char' => ['hello', 'hElLo', 'a'],
            'uppercase + space' => ['HELLO', 'hElLo', ' WORLD '],
            'lowercase + space' => ['hello', 'hElLo', ' world '],
            'title case + space' => ['Hello', 'hElLo', ' World '],
            'uppercase char + space' => ['Hello', 'hElLo', ' A '],
            'lowercase char + space' => ['hello', 'hElLo', ' a '],
            'empty' => ['hElLo', 'hElLo', ''],
            'mixed case' => ['hElLo', 'hElLo', 'wORLD'],
            'numeric' => ['hElLo', 'hElLo', '12345'],
        ];
    }

    /**
     * @dataProvider toWordsProvider
     */
    public function testToWords(
        string $expected,
        string $string,
        string $separator = ' ',
        ?string $preserve = null,
        ?callable $callback = null
    ): void {
        $this->assertSame($expected, Str::toWords($string, $separator, $preserve, $callback));
    }

    /**
     * @return array<array{string,string,2?:string,3?:string|null,4?:callable|null}>
     */
    public static function toWordsProvider(): array
    {
        return [
            ['foo bar', 'foo bar'],
            ['FOO BAR', 'FOO_BAR'],
            ['foo Bar', 'fooBar'],
            ['this foo Bar', '$this = fooBar'],
            ['this = foo Bar', '$this = fooBar', ' ', '='],
            ['this=foo Bar', '$this=fooBar', ' ', '='],
            ['PHP Doc', 'PHPDoc'],
            ['PHP8 Doc', 'PHP8Doc'],
            ['PHP8.3_0 Doc', 'PHP8.3_0Doc', ' ', '._'],
        ];
    }

    /**
     * @dataProvider toCaseProvider
     */
    public function testToCase(
        string $string,
        ?string $preserve,
        string $expectedSnakeCase,
        string $expectedKebabCase,
        string $expectedCamelCase,
        string $expectedPascalCase
    ): void {
        $name = Get::code([$string, $preserve]);
        $this->assertSame($expectedSnakeCase, Str::toSnakeCase($string, $preserve), "snake_case{$name}");
        $this->assertSame($expectedKebabCase, Str::toKebabCase($string, $preserve), "kebab-case{$name}");
        $this->assertSame($expectedCamelCase, Str::toCamelCase($string, $preserve), "camelCase{$name}");
        $this->assertSame($expectedPascalCase, Str::toPascalCase($string, $preserve), "PascalCase{$name}");
    }

    /**
     * @return array<array{string,string|null,string,string,string,string}>
     */
    public static function toCaseProvider(): array
    {
        return [
            ['**two words**', null, 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['**Two_Words**', null, 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['**TWO-WORDS**', null, 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['**two.Words**', null, 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['12two_words', null, '12two_words', '12two-words', '12twoWords', '12twoWords'],
            ['12two_Words', null, '12two_words', '12two-words', '12twoWords', '12twoWords'],
            ['12Two_Words', null, '12_two_words', '12-two-words', '12TwoWords', '12TwoWords'],
            ['12TWO_WORDS', null, '12_two_words', '12-two-words', '12TwoWords', '12TwoWords'],
            ['12twoWords', null, '12two_words', '12two-words', '12twoWords', '12twoWords'],
            ['12TwoWords', null, '12_two_words', '12-two-words', '12TwoWords', '12TwoWords'],
            ['12TWOWords', null, '12_two_words', '12-two-words', '12TwoWords', '12TwoWords'],
            ['field-name=value-name', '=', 'field_name=value_name', 'field-name=value-name', 'fieldName=valueName', 'FieldName=ValueName'],
            ['Field-name=Value-name', '=', 'field_name=value_name', 'field-name=value-name', 'fieldName=valueName', 'FieldName=ValueName'],
            ['Field-Name=Value-Name', '=', 'field_name=value_name', 'field-name=value-name', 'fieldName=valueName', 'FieldName=ValueName'],
            ['FIELD-NAME=VALUE-NAME', '=', 'field_name=value_name', 'field-name=value-name', 'fieldName=valueName', 'FieldName=ValueName'],
            ['two words', null, 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['two_12words', null, 'two_12words', 'two-12words', 'two12words', 'Two12words'],
            ['two_12Words', null, 'two_12_words', 'two-12-words', 'two12Words', 'Two12Words'],
            ['Two_12Words', null, 'two_12_words', 'two-12-words', 'two12Words', 'Two12Words'],
            ['TWO_12WORDS', null, 'two_12_words', 'two-12-words', 'two12Words', 'Two12Words'],
            ['Two_Words', null, 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['two_words12', null, 'two_words12', 'two-words12', 'twoWords12', 'TwoWords12'],
            ['two_Words12', null, 'two_words12', 'two-words12', 'twoWords12', 'TwoWords12'],
            ['Two_Words12', null, 'two_words12', 'two-words12', 'twoWords12', 'TwoWords12'],
            ['TWO_WORDS12', null, 'two_words12', 'two-words12', 'twoWords12', 'TwoWords12'],
            ['TWO-WORDS', null, 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['two.Words', null, 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['two12_words', null, 'two12_words', 'two12-words', 'two12Words', 'Two12Words'],
            ['two12_Words', null, 'two12_words', 'two12-words', 'two12Words', 'Two12Words'],
            ['Two12_Words', null, 'two12_words', 'two12-words', 'two12Words', 'Two12Words'],
            ['TWO12_WORDS', null, 'two12_words', 'two12-words', 'two12Words', 'Two12Words'],
            ['two12words', null, 'two12words', 'two12words', 'two12words', 'Two12words'],
            ['two12Words', null, 'two12_words', 'two12-words', 'two12Words', 'Two12Words'],
            ['Two12Words', null, 'two12_words', 'two12-words', 'two12Words', 'Two12Words'],
            ['TWO12WORDS', null, 'two12_words', 'two12-words', 'two12Words', 'Two12Words'],
            ['twoWords', null, 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['TwoWords', null, 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['TWOWords', null, 'two_words', 'two-words', 'twoWords', 'TwoWords'],
            ['twoWords12', null, 'two_words12', 'two-words12', 'twoWords12', 'TwoWords12'],
            ['TwoWords12', null, 'two_words12', 'two-words12', 'twoWords12', 'TwoWords12'],
            ['TWOWords12', null, 'two_words12', 'two-words12', 'twoWords12', 'TwoWords12'],
        ];
    }
}
