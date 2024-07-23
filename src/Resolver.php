<?php

namespace Warp;

/**
 * ルーティング機能の実際の処理を担うリゾルバのインタフェースです。
 * パスセグメントや HTTP メソッドをはじめとするコンテキスト全体の情報を評価し、適切な Controller を解決します。
 */
interface Resolver
{
    /**
     * 渡されたルーティングコンテキストを評価し、解決結果を返します。
     *
     * @param RoutingContext $context 現在のルーティングコンテキスト
     * @return Target 解決結果をあらわす Target オブジェクト
     */
    public function resolve(RoutingContext $context): Target;
}