<?php

namespace Warp;

use Woof\Web\Controller;

/**
 * Controller を別の Controller に変換 (または装飾) するためのインタフェースです。
 *
 * 主に Target オブジェクトを通じて、既存の Controller をラップして別の Controller
 * を生成する際に使用されます。
 */
interface ControllerMapper
{
    /**
     * 渡された Controller を変換し、新しい Controller を返します。
     *
     * @param Controller $controller 変換元となる Controller
     * @return Controller 変換または装飾された新しい Controller
     */
    public function map(Controller $controller): Controller;
}
