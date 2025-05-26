<?php

namespace Warp;

use LogicException;
use Woof\Web\Controller;

/**
 * Resolver によるルーティングの解決結果 (宛先) を保持するイミュータブルなデータクラスです。
 *
 * 対象のパスに対応する Controller が見つかったかどうか (成功・失敗) の状態と、
 * 見つかった場合の Controller のインスタンスをカプセル化します。
 */
class Target
{
    /**
     * 解決された宛先となる Controller です。見つからなかった場合は null となります。
     *
     * @var Controller
     */
    private $controller;

    /**
     * 解決された Controller (または null) を指定して Target インスタンスを初期化します。
     *
     * このクラスは found() または empty() を使ってインスタンス化されるため、
     * コンストラクタが直接実行される機会はありません。
     *
     * @param Controller $controller 解決された Controller (存在しない場合は null)
     */
    private function __construct($controller = null)
    {
        $this->controller = $controller;
    }

    /**
     * 宛先となる Controller が見つかったことを表す Target インスタンスを生成します。
     *
     * @param Controller $controller 見つかった Controller
     * @return self 成功状態の解決結果
     */
    public static function found(Controller $controller): self
    {
        return new self($controller);
    }

    /**
     * 宛先となる Controller が見つからなかった (空である) ことを表す Target インスタンスを取得します。
     *
     * @return self 失敗状態の解決結果
     */
    public static function empty(): self
    {
        // @codeCoverageIgnoreStart
        static $instance = null;
        if ($instance === null) {
            $instance = new self(null);
        }
        // @codeCoverageIgnoreEnd

        return $instance;
    }

    /**
     * 宛先となる Controller が見つかったかどうかを調べます。
     *
     * @return bool 見つかった場合のみ true
     */
    public function isFound(): bool
    {
        return $this->controller !== null;
    }

    /**
     * 解決された Controller を取得します。
     * このメソッドを実行する前に、isFound() で見つかっていることを確認する必要があります。
     *
     * @return Controller 解決された Controller
     * @throws LogicException 見つかっていない (失敗状態の) 場合に実行された場合
     */
    public function getController(): Controller
    {
        if ($this->controller === null) {
            throw new LogicException("Cannot get controller from an empty target.");
        }
        return $this->controller;
    }

    /**
     * 宛先が見つかっている場合、内部の Controller を指定された ControllerMapper で変換し、
     * 新しい Controller を持つ新しい Target を返します。
     * 宛先が見つかっていない場合は、状態を変更せずに自身をそのまま返します。
     *
     * @param ControllerMapper $mapper 変換処理を行うマッパーオブジェクト
     * @return self 変換後の新しい Target (見つかっていない場合は自身)
     */
    public function map(ControllerMapper $mapper): self
    {
        return ($this->controller === null) ? $this : self::found($mapper->map($this->controller));
    }

    /**
     * 宛先が見つかっていたら自身の保持する Controller を返し、
     * 見つかっていなかったら引数で渡された代替の Controller またはクロージャの実行結果を返します。
     *
     * アプリケーションのエントリーポイントにおいて
     * 404 Not Found 用の Controller にフォールバックさせるケースなどに有用です。
     * 引数なしのクロージャを渡すことで、見つからなかった場合のみ代替の Controller を生成する遅延評価が可能になります。
     *
     * @param Controller|callable $fallback 見つかっていなかった場合に使用する代替 Controller またはそれを返すクロージャ
     * @return Controller 最終的に採用される Controller
     * @throws LogicException 代替として渡された値が Controller として解決できなかった場合
     */
    public function unwrapOr($fallback): Controller
    {
        if ($this->controller !== null) {
            return $this->controller;
        }
        if (is_callable($fallback)) {
            $fallback = $fallback();
        }
        if (!$fallback instanceof Controller) {
            throw new LogicException("The fallback must be an instance of Controller or a callable returning a Controller.");
        }

        return $fallback;
    }
}
