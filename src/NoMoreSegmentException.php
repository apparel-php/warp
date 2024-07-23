<?php

namespace Warp;

use RuntimeException;

/**
 * 未処理のパスセグメントが存在しない状態でコンテキストを進めようとした際にスローされる例外です。
 */
class NoMoreSegmentException extends RuntimeException
{
}
