<?php

namespace MifuminLib\SearchPhraseParser;

/**
 * \MifuminLib\SearchPhraseParser\Parser
 *
 * OR検索や括弧などを含む検索ワードを分割します。
 */
class Parser
{
    /**
     * 配列形式に解析します。
     *  例：$ret = Parser::ParseToArray('aaa bbb OR (ccc "ddd eee")');
     *
     * エラーがあった場合、エラーが起きない部分までを解析します。
     *
     * @param  string $string
     * @param  bool   $ignorecase
     * @return array  解析結果のツリー状の配列
     */
    public static function ParseToArray($string, $ignorecase = true): array
    {
        $symbols = self::Analyze($string, $ignorecase);
        $tree = self::EXP0($symbols, 0);

        return self::OptimizeTree($tree);
    }

    // 字句解析
    //  直接呼び出して使うわけじゃないけど、見ておくと構文の理解に役立つよ！
    private static function Analyze($str, $ignorecase)
    {
        $lex_pattern = [
            [
                'symbol' => 'AND',
                'pattern' => 'AND\b|&',
            ],
            [
                'symbol' => 'OR',
                'pattern' => 'OR\b|\|',
            ],
            [
                'symbol' => 'NOT',
                'pattern' => 'NOT\b|-',
            ],
            [
                'symbol' => 'BRST',
                'pattern' => '\(',
            ],
            [
                'symbol' => 'BREN',
                'pattern' => '\)',
            ],
            [
                'symbol' => 'PHRASE',
                'pattern' => '"(([^"]*("")?)*)"',
                'exec' => [self::class, 'QuotedPhrase'],
            ],
            [
                'symbol' => 'PHRASE',
                'pattern' => '[^\s\(\)]+',
            ],
            [
                'symbol' => '',
                'pattern' => '\s+',
            ],
        ];
        $lex = [];
        do {
            $matched = false;
            foreach ($lex_pattern as $val) {
                $pattern = '/^(?:'.$val['pattern'].')/'.($ignorecase ? 'iu' : 'u');
                if (preg_match($pattern, $str, $matches)) {
                    if ($val['symbol'] !== '') {
                        $exec = $val['exec'] ?? false;
                        $lex[] = [
                            'symbol' => $val['symbol'],
                            'value' => $exec ? call_user_func($exec, $matches) : $matches[0],
                        ];
                    }
                    $str = mb_substr($str, mb_strlen($matches[0]));
                    $matched = true;
                    break;
                }
            }
        } while ($matched);

        return $lex;
    }

    // クォーテーションで囲まれた文字列の中身をちょっといじる
    public static function QuotedPhrase($matches)
    {
        return str_replace('""', '"', $matches[1]);
    }

    // 以下、構文解析

    // EXP0 = EXP1 ( EXP1 )*
    private static function EXP0($symbols, $next)
    {
        $values = [];
        while (is_array($exp1 = self::EXP1($symbols, $next))) {
            $values[] = $exp1;
            $next = $exp1['next'];
        }
        if (count($values) === 0) {
            return null;
        }

        return [
            'symbol' => 'EXP0',
            'type' => 'AND',
            'next' => $next,
            'value' => $values,
        ];
    }

    // EXP1 = EXP2 ( OR EXP2 )*
    private static function EXP1($symbols, $next)
    {
        $values = [];
        while (is_array($exp2 = self::EXP2($symbols, $next))) {
            $values[] = $exp2;
            $next = $exp2['next'];
            if (!isset($symbols[$next]) || $symbols[$next]['symbol'] !== 'OR') {
                break;
            }
            ++$next;
        }
        if ($symbols[$next - 1]['symbol'] === 'OR') {
            --$next;
        }
        if (count($values) === 0) {
            return null;
        }

        return [
            'symbol' => 'EXP1',
            'type' => 'OR',
            'next' => $next,
            'value' => $values,
        ];
    }

    // EXP2 = EXP3 ( AND EXP3 )*
    private static function EXP2($symbols, $next)
    {
        $values = [];
        while (is_array($exp3 = self::EXP3($symbols, $next))) {
            $values[] = $exp3;
            $next = $exp3['next'];
            if (!isset($symbols[$next]) || $symbols[$next]['symbol'] !== 'AND') {
                break;
            }
            ++$next;
        }
        if ($symbols[$next - 1]['symbol'] === 'AND') {
            --$next;
        }
        if (count($values) === 0) {
            return null;
        }

        return [
            'symbol' => 'EXP2',
            'type' => 'AND',
            'next' => $next,
            'value' => $values,
        ];
    }

    // EXP3 = ( NOT )? EXP4
    private static function EXP3($symbols, $next)
    {
        $values = [];
        if (isset($symbols[$next]) && $symbols[$next]['symbol'] === 'NOT') {
            ++$next;
            $exp4 = self::EXP4($symbols, $next);
            if (!is_array($exp4)) {
                return null;
            }
            $values[] = $exp4;
            $next = $exp4['next'];

            return [
                'symbol' => 'EXP3',
                'type' => 'NOT',
                'next' => $next,
                'value' => $values,
            ];
        } else {
            $exp4 = self::EXP4($symbols, $next);
            if (!is_array($exp4)) {
                return null;
            }
            $values[] = $exp4;
            $next = $exp4['next'];

            return [
                'symbol' => 'EXP3',
                'type' => 'EXP',
                'next' => $next,
                'value' => $values,
            ];
        }
    }

    // EXP4 = PHRASE | BRST EXP0 BREN
    private static function EXP4($symbols, $next)
    {
        $values = [];
        if (isset($symbols[$next]) && $symbols[$next]['symbol'] === 'PHRASE') {
            $values[] = $symbols[$next];
            ++$next;

            return [
                'symbol' => 'EXP4',
                'type' => 'TERM',
                'next' => $next,
                'value' => $values,
            ];
        } elseif (isset($symbols[$next]) && $symbols[$next]['symbol'] === 'BRST') {
            ++$next;
            $exp0 = self::EXP0($symbols, $next);
            if (!is_array($exp0)) {
                return null;
            }
            $values[] = $exp0;
            $next = $exp0['next'];
            if ($symbols[$next]['symbol'] !== 'BREN') {
                return null;
            }
            ++$next;

            return [
                'symbol' => 'EXP4',
                'type' => 'EXP',
                'next' => $next,
                'value' => $values,
            ];
        } else {
            return null;
        }
    }

    // ツリー構造の最適化
    private static function OptimizeTree($tree)
    {
        if (!is_array($tree)) {
            return null;
        }
        if ($tree['type'] === 'TERM') {
            return [
                'type' => 'VALUE',
                'value' => $tree['value'][0]['value'],
            ];
        }
        $values = [];
        foreach ($tree['value'] as $v) {
            $opt = self::OptimizeTree($v);
            if (isset($opt)) {
                $values[] = $opt;
            }
        }
        if (count($values) === 0) {
            return null;
        }
        if ($tree['type'] !== 'NOT' && count($values) === 1) {
            return $values[0];
        }
        $ret = [
            'type' => $tree['type'],
            'value' => $values,
        ];

        return $ret;
    }
}
