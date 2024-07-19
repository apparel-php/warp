<?php

namespace Warp\Pattern;

/**
 * テンプレート文字列を構成する各要素 (リテラルやプレースホルダ) のインターフェースです。
 */
interface Token
{
    /**
     * 与えられたパス文字列の先頭部分が、このトークンにマッチするか検証します。
     *
     * @param string $path 検証対象となる残りのパス文字列
     * @param Token $nextToken 次のトークン (プレースホルダの終端判定などに使用します)
     * @return TokenMatch マッチ結果を保持するオブジェクト
     */
    public function match(string $path, Token $nextToken = null): TokenMatch;
}
