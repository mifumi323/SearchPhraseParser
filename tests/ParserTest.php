<?php

namespace MifuminLib\SearchPhraseParser;

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
}
