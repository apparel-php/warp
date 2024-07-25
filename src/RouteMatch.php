<?php

namespace Warp;

/**
 * アプリケーションパスのパターンマッチングの結果を保持する、イミュータブルなデータクラスです。
 */
class RouteMatch
{
    /**
     * マッチしたかどうかをあらわします。
     *
     * @var bool
     */
    private $matched;

    /**
     * 抽出されたプレースホルダのパラメータ群です。
     *
     * @var array
     */
    private $parameters;

    /**
     * マッチの有無および抽出されたパラメータを指定して RouteMatch インスタンスを生成します。
     *
     * このクラスは matched() および failed() から生成されるため、
     * 直接インスタンス化される機会はありません。
     *
     * @param bool $matched マッチした場合は true
     * @param array $parameters プレースホルダから抽出されたパラメータの連想配列
     */
    private function __construct(bool $matched, array $parameters = [])
    {
        $this->matched    = $matched;
        $this->parameters = $parameters;
    }

    /**
     * パターンにマッチしたことを表す RouteMatch インスタンスを生成します。
     *
     * @param array $parameters 抽出されたパラメータ
     * @return self 成功状態のオブジェクト
     */
    public static function matched(array $parameters): self
    {
        return new self(true, $parameters);
    }

    /**
     * パターンにマッチしなかったことを表す RouteMatch インスタンスを取得します。
     *
     * @return self 失敗状態のオブジェクト
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
     * パターンにマッチしたかどうかを調べます。
     *
     * @return bool マッチした場合は true
     */
    public function isMatched(): bool
    {
        return $this->matched;
    }

    /**
     * プレースホルダから抽出されたパラメータの値を取得します。
     *
     * @param string $name プレースホルダ名 (例: "id")
     * @param string $defaultValue 存在しない場合の代替値 (デフォルトは空文字列)
     * @return string 指定されたパラメータの値または代替値
     */
    public function get(string $name, string $defaultValue = ""): string
    {
        return array_key_exists($name, $this->parameters) ? (string) $this->parameters[$name] : $defaultValue;
    }
}
