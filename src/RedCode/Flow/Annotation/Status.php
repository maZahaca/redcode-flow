<?php

/**
 * @author maZahaca
 */

namespace RedCode\Flow\Annotation;

/**
 * @Annotation
 * @Target({"PROPERTY","ANNOTATION"})
 */
class Status
{
    public static function className()
    {
        return get_called_class();
    }
}
