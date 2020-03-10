<?php

namespace AwtTech\Framework;

/**
 * Controller Class
 *
 * Basically a way of offsetting code from AppRun into controllers
 */
abstract class Controller extends Runner
{
    
    /**
     * Constructor to Setup Controller
     */
    final public function __construct(&$frameworkRunner)
    {
        foreach ($frameworkRunner->getObjectsForController() as $type => $obj)
        {
            $this->$type = $obj;
        }
        
        $this->init();
    }
    
    /**
     *
     */
    public function init() {}
}
