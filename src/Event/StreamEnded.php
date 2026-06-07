<?php

namespace Ernestdefoe\OnAir\Event;

use Ernestdefoe\OnAir\Model\Stream;

class StreamEnded
{
    public function __construct(public Stream $stream) {}
}
