<?php

namespace Warp\Pattern;

/**
 * URL パターン内の "{id}" などのプレースホルダを表すトークンです。
 */
class PlaceholderToken implements Token
{
    /**
     * プレースホルダの名前 (例: "id", "name") です。
     *
     * @var string
     */
    private $name;

    /**
     * 指定された名前を持つ PlaceholderToken オブジェクトを構築します。
     *
     * @param string $name プレースホルダの名前
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    /**
     * プレースホルダの名前を取得します。
     *
     * @return string プレースホルダの名前
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * 与えられたパス文字列の先頭部分から、このプレースホルダに該当する値を抽出します。
     *
     * 次のトークン (リテラル) までの文字列またはパスの末尾までの文字列を値として抽出します。
     * ただし、抽出された値の中にセパレーター ("/") が含まれる場合はマッチ失敗とみなします。
     *
     * @param string $path 検証対象となる残りのパス文字列
     * @param Token $nextToken 次のトークン (抽出範囲の終端を決定するために使用します)
     * @return TokenMatch マッチ結果と抽出されたパラメータを保持するオブジェクト
     */
    public function match(string $path, $nextToken = null): TokenMatch
    {
        if ($nextToken === null) {
            return ($path === "" || strpos($path, "/") !== false) ? TokenMatch::failed() : TokenMatch::matched(strlen($path), [$this->name => $path]);
        }

        if ($nextToken instanceof LiteralToken) {
            $nextText = $nextToken->getText();
            $nextPos  = strpos($path, $nextText);
            if ($nextPos === false || $nextPos === 0) {
                return TokenMatch::failed();
            }

            $value = substr($path, 0, $nextPos);
            return (strpos($value, "/") !== false) ? TokenMatch::failed() : TokenMatch::matched(strlen($value), [$this->name => $value]);
        }

        // 次のトークンがプレースホルダである場合 (連続するプレースホルダ) はサポート対象外とします
        return TokenMatch::failed();
    }
}
