<?php

namespace Mifumi323\SearchPhraseParser;

use PHPUnit\Framework\Assert;

class ParserTest extends \PHPUnit\Framework\TestCase
{
    public function testParseToArray()
    {
        $actual = Parser::ParseToArray('aaa NOT bbb OR (ccc "ddd eee")');
        $expected = [
            'type' => 'AND',
            'value' => [
                [
                    'type' => 'VALUE',
                    'value' => 'aaa',
                ],
                [
                    'type' => 'OR',
                    'value' => [
                        [
                            'type' => 'NOT',
                            'value' => [
                                [
                                    'type' => 'VALUE',
                                    'value' => 'bbb',
                                ],
                            ],
                        ],
                        [
                            'type' => 'AND',
                            'value' => [
                                [
                                    'type' => 'VALUE',
                                    'value' => 'ccc',
                                ],
                                [
                                    'type' => 'VALUE',
                                    'value' => 'ddd eee',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        Assert::assertSame($expected, $actual);
    }

    public function testParseToArrayOpenBranket()
    {
        $actual = Parser::ParseToArray('(');
        $expected = [];
        Assert::assertSame($expected, $actual);
    }

    public function testParseToArrayCloseBranket()
    {
        $actual = Parser::ParseToArray(')');
        $expected = [];
        Assert::assertSame($expected, $actual);
    }

    public function testParseToArrayInvalidBranket()
    {
        $actual = Parser::ParseToArray('fff )(');
        $expected = [
            'type' => 'VALUE',
            'value' => 'fff',
        ];
        Assert::assertSame($expected, $actual);
    }

    public function testParseToArrayQuotedQuote()
    {
        $actual = Parser::ParseToArray('"aaa""bbb"');
        $expected = [
            'type' => 'VALUE',
            'value' => 'aaa"bbb',
        ];
        Assert::assertSame($expected, $actual);
    }

    public function testParseToArrayEmpty()
    {
        $actual = Parser::ParseToArray('');
        $expected = [];
        Assert::assertSame($expected, $actual);
    }

    public function testParseToArrayOrWithoutLeftValue()
    {
        // 実際に来たSQLインジェクション試行のリクエスト
        // これを防ぐのは当ライブラリの責任範囲ではないため、普通にパースを試みる(初手ORなのでどのみち失敗に終わる)
        $actual = Parser::ParseToArray(' or (1,2)=(select*from(select name_const(CHAR(108,79,100,111,120,85,77,85,114,116),1),name_const(CHAR(108,79,100,111,120,85,77,85,114,116),1))a) -- and 1=1');
        $expected = [];
        Assert::assertSame($expected, $actual);
    }

    public function testParseToArrayContinuousAndOr()
    {
        $actual = Parser::ParseToArray('a and or b');
        $expected = [
            'type' => 'VALUE',
            'value' => 'a',
        ];
        Assert::assertSame($expected, $actual);
    }

    public function testParseToArrayOpenBranketAndCharacter()
    {
        $actual = Parser::ParseToArray('t0b@5d@(y ');
        $expected = [
            'type' => 'VALUE',
            'value' => 't0b@5d@',
        ];
        Assert::assertSame($expected, $actual);
    }
}
