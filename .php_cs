<?php
// .php_cs
namespace PhpCsFixer;

return Config::create()
    ->setRules([
        '@Symfony' => true,
        'array_syntax' => [
            'syntax' => 'short',
        ],
        'phpdoc_summary' => false,
        'phpdoc_separation' => false,
        'yoda_style' => null,
    ])
;
