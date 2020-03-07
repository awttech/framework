<?php

namespace AwtTech\Framework;

/**
 * Template Interface
 */
interface TemplateInterface
{
    public function __construct($writableFolder);
    
    public function persistTemplateVar($name, $value);
    
    public function loadTemplate($template, array $vars=[], $cacheId='');
    
    public function templateExists($template);
    
    public function setTemplateDir($template);
}
