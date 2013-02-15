<?php

/**
 * @author maZahaca
 */
namespace RedCode\Flow\Annotation\Status;

/**
 * @Annotation
 * @Target({"PROPERTY","ANNOTATION"})
 */
class StatusValue
{
    public static function className()
    {
        return get_called_class();
    }
}
