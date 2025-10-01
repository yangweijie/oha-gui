<?php

declare(strict_types=1);

namespace OhaGui\GUI;

use Kingbes\Libui\Base;

/**
 * Base class for GUI components
 * Provides common functionality for all GUI components
 */
class BaseGUIComponent
{
    /**
     * Cast control to uiControl* for compatibility with libui functions
     * 
     * @param mixed $control
     * @return mixed
     */
    protected function castControl($control)
    {
        $ffi = Base::ffi();
        return $ffi->cast('uiControl*', $control);
    }
    
    /**
     * Free text allocated by libui
     * 
     * @param mixed $textPtr
     * @return void
     */
    protected function freeText($textPtr): void
    {
        $ffi = Base::ffi();
        $ffi->uiFreeText($textPtr);
    }
}