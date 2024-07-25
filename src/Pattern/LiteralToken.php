<?php

namespace Warp\Pattern;

/**
 * URL パターン内の固定文字列 (リテラル) を表すトークンです。
 */
class LiteralToken implements Token
{
    /**
     * トークンが保持する固定文字列です。
     *
     * @var string
     */
    private $text;

    /**
     * 指定された固定文字列を持つ LiteralToken オブジェクトを構築します。
     *
     * @param string $text 固定文字列
     */
    public function __construct(string $text)
    {
        $this->text = $text;
    }

    /**
     * トークンが保持する固定文字列を取得します。
     *
     * @return string 固定文字列
     */
    public function getText(): string
    {
        return $this->text;
    }

    /**
     * 与えられたパス文字列の先頭部分が、このリテラル文字列と一致するか検証します。
     * * 一致した場合は、このリテラルの文字数をオフセットとして持つ成功状態の TokenMatch を返します。
     * 一致しなかった場合は、失敗状態の TokenMatch を返します。
     *
     * @param string $path 検証対象となる残りのパス文字列
     * @param Token $nextToken 次のトークン (このクラスでは使用しません)
     * @return TokenMatch マッチ結果を保持するオブジェクト
     */
    public function match(string $path, Token $nextToken = null): TokenMatch
    {
        if (strpos($path, $this->text) === 0) {
            return TokenMatch::matched(strlen($this->text));
        }

        return TokenMatch::failed();
    }
}
