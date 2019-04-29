<?php

namespace MifuminLib\SearchPhraseParser;

use PHPUnit\Framework\Assert;

class ParserTest extends \PHPUnit\Framework\TestCase
{
    public function testParseToArray()
    {
        error_reporting(E_ALL & ~E_NOTICE);
        $actual = Parser::ParseToArray('aaa bbb OR (ccc "ddd eee")');
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
                            'type' => 'VALUE',
                            'value' => 'bbb',
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
}
