<?php

namespace AwtTech\Framework;

class AppConfig
{
    /**
     * @var array Config Values
     */
    private static $config;
    
    /**
     * Method to load Config Values from a .ini file
     * @param string|array $configIniFile Path(s) to .ini config file
     */
    public static function load($configIniFile)
    {
        if (!empty(static::$config)) {
            throw new \Exception('Config has already been loaded');
        }
        
        if (is_array($configIniFile)) {
            static::$config = static::loadIniFiles($configIniFile);
            
        } else {
            static::$config = static::loadIniFile($configIniFile);
        }
    }
    
    /**
     * Method to get a Config Value
     * @param string $section Config Key Section
     * @param string $name Name of Config Key
     * @return mixed Config Value
     */
    public static function get($section, $name, $default=NULL)
    {
        if (isset(static::$config->$section->$name)) {
            return static::$config->$section->$name;
        } else {
            return $default;
        }
    }
    
    /**
     * Method to get a Config Section as an array
     * @param string $section Config Key Section
     * @return array Config Value
     */
    public static function getSection($section)
    {
        if (isset(static::$config->$section)) {
            $sectionValues = static::$config->$section;
            return (array)$sectionValues;
        } else {
            throw new \Exception('Config Section not found');
        }
    }
    
    /**
     * Method to check if Config has been loaded
     */
    public static function configLoaded()
    {
        return !empty(static::$config);
    }
    
    protected static function loadIniFile($configIniFile)
    {
        $values = parse_ini_file($configIniFile, TRUE);
        
        if ($values == FALSE) {
            throw new \Exception('Unable to parse config file');
        } else {
            return json_decode(json_encode($values), FALSE);
        }
    }
    
    protected static function loadIniFiles($configIniFiles)
    {
        $config = [];
        
        foreach ($configIniFiles as $file)
        {
            $values = parse_ini_file($file, TRUE);
            
            if ($values == FALSE) {
                throw new \Exception('Unable to parse config file');
            }
            
            $config = array_replace_recursive($config, $values);
        }
        
        return json_decode(json_encode($config), FALSE);
    }
    
}
