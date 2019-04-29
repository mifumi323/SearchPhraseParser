<?php
// SearchPhraseParser v1.1.0
//  OR検索や括弧などを含む検索ワードを分割
//  PHP5以降に対応
//  http://tgws.fromc.jp/dl/searchphraseparser/
//
// 1.0.1 例とインデント修正。動作は一切変更なし。
// 1.1.0 大文字小文字を区別するかどうかを選択制に。

class SearchPhraseParser {
    // このメソッドを呼び出せばいいよ！
    //  例：$ret = SearchPhraseParser::Parse('aaa bbb OR (ccc "ddd eee")');
    //  戻り値はツリー状の配列だから再帰処理で頑張ればなんとかなると思うよ！
    //  エラーがあったらエラーが起きないところまで解析するよ！
    public static function Parse($string, $ignorecase = true)
    {
        $symbols = self::Analyze($string, $ignorecase);
        $tree = self::EXP0($symbols, 0);
        return self::OptimizeTree($tree);
    }

    // 字句解析
    //  直接呼び出して使うわけじゃないけど、見ておくと構文の理解に役立つよ！
    private static function Analyze($str, $ignorecase)
    {
        $lex_pattern = array(
            array(
                'symbol' => 'AND',
                'pattern' => 'AND\b|&',
            ),
            array(
                'symbol' => 'OR',
                'pattern' => 'OR\b|\|',
            ),
            array(
                'symbol' => 'NOT',
                'pattern' => 'NOT\b|-',
            ),
            array(
                'symbol' => 'BRST',
                'pattern' => '\(',
            ),
            array(
                'symbol' => 'BREN',
                'pattern' => '\)',
            ),
            array(
                'symbol' => 'PHRASE',
                'pattern' => '"(([^"]*("")?)*)"',
                'exec' => array('SearchPhraseParser', 'QuotedPhrase'),
            ),
            array(
                'symbol' => 'PHRASE',
                'pattern' => '[^\s\(\)]+',
            ),
            array(
                'symbol' => '',
                'pattern' => '\s+'
            ),
        );
        $lex = array();
        do {
            $matched = false;
            foreach ($lex_pattern as $val) {
                $pattern = '/^(?:'.$val['pattern'].')/'.($ignorecase?'iu':'u');
                if (preg_match($pattern, $str, $matches)) {
                    if ($val['symbol']!=='') {
                        $lex[] = array(
                            'symbol' => $val['symbol'],
                            'value' => $val['exec']?call_user_func($val['exec'], $matches):$matches[0],
                        );
                    }
                    $str = mb_substr($str, mb_strlen($matches[0]));
                    $matched = true;
                    break;
                }
            }
        }while ($matched);
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
        $values = array();
        while (is_array($exp1 = self::EXP1($symbols, $next))) {
            $values[] = $exp1;
            $next = $exp1['next'];
        }
        if (count($values)===0) return NULL;
        return array(
            'symbol' => 'EXP0',
            'type' => 'AND',
            'next' => $next,
            'value' => $values
        );
    }

    // EXP1 = EXP2 ( OR EXP2 )*
    private static function EXP1($symbols, $next)
    {
        $values = array();
        while (is_array($exp2 = self::EXP2($symbols, $next))) {
            $values[] = $exp2;
            $next = $exp2['next'];
            if ($symbols[$next]['symbol']!=='OR') break;
            $next++;
        }
        if ($symbols[$next-1]['symbol']==='OR') {
            $next--;
        }
        if (count($values)===0) return NULL;
        return array(
            'symbol' => 'EXP1',
            'type' => 'OR',
            'next' => $next,
            'value' => $values
        );
    }

    // EXP2 = EXP3 ( AND EXP3 )*
    private static function EXP2($symbols, $next)
    {
        $values = array();
        while (is_array($exp3 = self::EXP3($symbols, $next))) {
            $values[] = $exp3;
            $next = $exp3['next'];
            if ($symbols[$next]['symbol']!=='AND') break;
            $next++;
        }
        if ($symbols[$next-1]['symbol']==='AND') {
            $next--;
        }
        if (count($values)===0) return NULL;
        return array(
            'symbol' => 'EXP2',
            'type' => 'AND',
            'next' => $next,
            'value' => $values
        );
    }

    // EXP3 = ( NOT )? EXP4
    private static function EXP3($symbols, $next)
    {
        $values = array();
        if ($symbols[$next]['symbol']==='NOT') {
            $next++;
            $exp4 = self::EXP4($symbols, $next);
            if (!is_array($exp4)) return NULL;
            $values[] = $exp4;
            $next = $exp4['next'];
            return array(
                'symbol' => 'EXP3',
                'type' => 'NOT',
                'next' => $next,
                'value' => $values
            );
        } else {
            $exp4 = self::EXP4($symbols, $next);
            if (!is_array($exp4)) return NULL;
            $values[] = $exp4;
            $next = $exp4['next'];
            return array(
                'symbol' => 'EXP3',
                'type' => 'EXP',
                'next' => $next,
                'value' => $values
            );
        }
    }

    // EXP4 = PHRASE | BRST EXP0 BREN
    private static function EXP4($symbols, $next)
    {
        $values = array();
        if ($symbols[$next]['symbol']==='PHRASE') {
            $values[] = $symbols[$next];
            $next++;
            return array(
                'symbol' => 'EXP4',
                'type' => 'TERM',
                'next' => $next,
                'value' => $values
            );
        } else if ($symbols[$next]['symbol']==='BRST') {
            $next++;
            $exp0 = self::EXP0($symbols, $next);
            if (!is_array($exp0)) return NULL;
            $values[] = $exp0;
            $next = $exp0['next'];
            if ($symbols[$next]['symbol']!=='BREN') return NULL;
            $next++;
            return array(
                'symbol' => 'EXP4',
                'type' => 'EXP',
                'next' => $next,
                'value' => $values
            );
        } else {
            return NULL;
        }
    }

    // ツリー構造の最適化
    private static function OptimizeTree($tree)
    {
        if (!is_array($tree)) return NULL;
        if ($tree['type']==='TERM') {
            return array(
                'type' => 'VALUE',
                'value' => $tree['value'][0]['value']
            );
        }
        $values = array();
        foreach ($tree['value'] as $v) {
            $opt = self::OptimizeTree($v);
            if (isset($opt)) $values[] = $opt;
        }
        if (count($values)===0) return NULL;
        if ($tree['type']!=='NOT' && count($values)===1) return $values[0];
        $ret = array(
            'type' => $tree['type'],
            'value' => $values,
        );
        return $ret;
    }
}