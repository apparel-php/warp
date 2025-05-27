<?php

namespace Warp;

use Woof\Web\Controller;

/**
 * 解決された Controller を評価し、新しい Target (宛先) へ変換するためのインタフェースです。
 *
 * ControllerMapper が Controller の装飾に特化しているのに対し、
 * TargetMapper は条件に応じて Target::empty() を返すことで宛先を破棄 (フィルタリング)
 * するなど、より広範なルーティングの制御を行う際に使用されます。
 */
interface TargetMapper
{
    /**
     * 渡された Controller を評価し、新しい Target を返します。
     *
     * @param Controller $controller 変換元となる Controller
     * @return Target 変換結果の Target オブジェクト
     */
    public function map(Controller $controller): Target;
}
