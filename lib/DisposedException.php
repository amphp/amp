<?php

namespace Amp;

/**
 * Will be thrown from {@see PipelineSource::emit()} or the emit callable provided by {@see AsyncGenerator} if the
 * associated pipeline is destroyed.
 */
final class DisposedException extends \Exception
{
    public function __construct(\Throwable $previous = null)
    {
        parent::__construct("The pipeline has been disposed", 0, $previous);
    }
}
