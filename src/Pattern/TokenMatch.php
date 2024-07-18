<?php

namespace Warp\Pattern;

/**
 * トークン単位でのパスのマッチング結果を保持するデータクラスです。
 */
class TokenMatch
{
    /**
     * パターンにマッチしたかどうかを示すフラグです。
     *
     * @var bool
     */
    private $matched;

    /**
     * このトークンによって消費された文字列の長さです。
     * マッチング処理において、次のトークンが検証を開始する相対位置 (オフセット) として使用されます。
     *
     * @var int
     */
    private $offset;

    /**
     * プレースホルダから抽出されたパラメータの連想配列です。
     *
     * @var array
     */
    private $parameters;

    /**
     * マッチング結果・オフセット・抽出されたパラメータを指定して TokenMatch インスタンスを初期化します。
     *
     * このクラスは matched() または failed() を使ってインスタンス化されるため、
     * コンストラクタが直接実行される機会はありません。
     *
     * @param bool $matched マッチした場合は true
     * @param int $offset 消費した文字列の長さ (次のトークンへのオフセット)
     * @param array $parameters 抽出されたパラメータの連想配列
     */
    private function __construct(bool $matched, int $offset = 0, array $parameters = [])
    {
        $this->matched    = $matched;
        $this->offset     = $offset;
        $this->parameters = $parameters;
    }

    /**
     * トークンがパス文字列にマッチしたことを表す TokenMatch インスタンスを生成します。
     *
     * @param int $offset 消費した文字列の長さ (次のトークンへのオフセット)
     * @param array $parameters 抽出されたパラメータの連想配列 (デフォルトは空配列)
     * @return self 成功状態のマッチ結果
     */
    public static function matched(int $offset, array $parameters = []): self
    {
        return new self(true, $offset, $parameters);
    }

    /**
     * トークンがパス文字列にマッチしなかったことを表す TokenMatch インスタンスを取得します。
     *
     * @return self 失敗状態のマッチ結果
     */
    public static function failed(): self
    {
        // @codeCoverageIgnoreStart
        static $instance = null;
        if ($instance === null) {
            $instance = new self(false);
        }
        // @codeCoverageIgnoreEnd

        return $instance;
    }

    /**
     * トークンがマッチしたかどうかを調べます。
     *
     * @return bool マッチした場合は true
     */
    public function isMatched(): bool
    {
        return $this->matched;
    }

    /**
     * このトークンが消費したパス文字列の長さを取得します。
     *
     * @return int 消費した文字列の長さ (オフセット)
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * このトークンから抽出されたパラメータの連想配列を取得します。
     *
     * @return array 抽出されたパラメータ
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }
}
