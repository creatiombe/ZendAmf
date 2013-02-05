<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @link      http://github.com/zendframework/zf2 for the canonical source repository
 * @copyright Copyright (c) 2005-2012 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   http://framework.zend.com/license/new-bsd New BSD License
 * @package   Zend_Amf
 */

namespace ZendAmfTest\TestAsset;

use Zend\Loader\PluginClassLocator;

class TestResourceLoader implements PluginClassLocator
{
    protected $suffix;
    protected $namespace = 'ZendAmfTest\\TestAsset\\';

    public function __construct($suffix)
    {
        $this->suffix = $suffix;
    }

    public function registerPlugin($shortName, $className)
    {
    }

    public function unregisterPlugin($shortName)
    {
    }

    public function getRegisteredPlugins()
    {
    }

    public function isLoaded($name)
    {
    }

    public function getClassName($name)
    {
    }

    public function getIterator()
    {
    }

    public function load($name)
    {
        return $this->namespace . $name . $this->suffix;
    }
}
