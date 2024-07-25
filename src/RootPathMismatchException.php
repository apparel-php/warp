<?php

namespace Warp;

use RuntimeException;

/**
 * リクエストパスがアプリケーションのルートパス (app.root-path) と一致しない場合にスローされる例外です。
 * 主に Web サーバーの Rewrite 設定とアプリケーションの設定の不整合によって発生します。
 */
class RootPathMismatchException extends RuntimeException
{
}
