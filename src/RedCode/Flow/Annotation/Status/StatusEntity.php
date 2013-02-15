<?php

/**
 * @author maZahaca
 */
namespace RedCode\Flow\Annotation\Status;

/**
 * @Annotation
 * @Target({"PROPERTY","ANNOTATION"})
 */
class StatusEntity
{
    public static function className()
    {
        return get_called_class();
    }
}
