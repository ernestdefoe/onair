<?php

namespace Ernestdefoe\OnAir\Event;

use Ernestdefoe\OnAir\Model\Stream;

class StreamStarted
{
    public function __construct(public Stream $stream) {}
}
