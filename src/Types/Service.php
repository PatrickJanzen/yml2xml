<?php

namespace App\Types;

class Service extends TypeHandler
{

    protected function convert()
    {
        $this->xmlwriter->startElement('container');
        $this->xmlwriter->writeAttribute('xmlns', 'http://symfony.com/schema/dic/services');
        $this->xmlwriter->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $this->xmlwriter->writeAttribute('xsi:schemaLocation', 'http://symfony.com/schema/dic/services https://symfony.com/schema/dic/services/services-1.0.xsd');

        $handledParameters = false;
        $handledServices = false;

        if (array_key_exists('parameters', $this->yaml)) {
            $this->createParameters($this->yaml['parameters']);
            $handledParameters = true;
        }

        if (array_key_exists('services', $this->yaml)) {
            $this->createServices($this->yaml['services']);
            $handledServices = true;
        }

        if (!$handledParameters && !$handledServices) {
            $this->addWarning('nothing from yaml was converted');
        }

        $this->xmlwriter->endElement();
    }

    private function createParameters($parameters)
    {
        if (!is_array($parameters)) {
            return;
        }
        $this->xmlwriter->startElement('parameters');
        foreach ($parameters as $key => $parameter) {
            $this->xmlwriter->startElement('parameter');
            $this->xmlwriter->writeAttribute('key', $key);
            if ('' !== $parameter) {
                $this->xmlwriter->text($parameter);
            }
            $this->xmlwriter->endElement();
        }
        $this->xmlwriter->endElement();
    }

    private function createServices($services)
    {
        $this->xmlwriter->startElement('services');
        foreach ($services as $key => $parameter) {
            if ($key === 'imports') {
                $this->addMessage('please take care of imports manually!');
            }
            if ($key === '_defaults') {
                $this->xmlwriter->startElement('defaults');
                foreach ($parameter as $defaultKey => $value) {
                    $this->xmlwriter->writeAttribute($defaultKey, $value ? 'true' : 'false');
                }
                $this->xmlwriter->endElement();
                continue;
            }

            if ($key[strlen($key) - 1] === '\\') {
                $this->xmlwriter->startElement('prototype');
                $this->xmlwriter->writeAttribute('namespace', $key);
                foreach (['resource', 'exclude'] as $attrKey) {
                    if (array_key_exists($attrKey, $parameter)) {
                        $this->xmlwriter->writeAttribute($attrKey, $parameter[$attrKey]);
                    }
                }
                if (array_key_exists('tags', $parameter)) {
                    $this->addTags($parameter['tags']);
                }
            } else {
                $this->xmlwriter->startElement('service');
                $this->xmlwriter->writeAttribute('id', $key);
                if (is_array($parameter)) {
                    foreach (['class', 'decorates'] as $attrKey) {
                        if (array_key_exists($attrKey, $parameter)) {
                            $this->xmlwriter->writeAttribute($attrKey, $parameter[$attrKey]);
                            unset($parameter[$attrKey]);
                        }
                    }
                    if (array_key_exists('tags', $parameter)) {
                        $this->addTags($parameter['tags']);
                        unset($parameter['tags']);
                    }
                    if (array_key_exists('calls', $parameter)) {
                        $this->addCalls($parameter['calls']);
                        unset($parameter['calls']);
                    }
                    if (array_key_exists('arguments', $parameter)) {
                        $this->addArguments($parameter['arguments']);
                        unset($parameter['arguments']);
                    }
                    if (count($parameter) > 0) {
                        $this->addMessage('unprocessed parameters: ' . implode(',', array_keys($parameter)));
                    }
                }
            }
            $this->xmlwriter->endElement();
        }
        $this->xmlwriter->endElement();
    }

    private function addTags($tags)
    {
        foreach ($tags as $tag) {
            $this->xmlwriter->startElement('tag');
            if (is_array($tag)) {
                foreach ($tag as $key => $value) {
                    $this->xmlwriter->writeAttribute($key, $value);
                }
            } else {
                $this->xmlwriter->writeAttribute('name', $tag);
            }
            $this->xmlwriter->endElement();
        }
    }

    private function addCalls($calls)
    {
        foreach ($calls as $call) {
            $this->xmlwriter->startElement('call');
            if (is_array($call)) {
                $arguments = null;
                foreach ($call as $key => $value) {
                    if ($key === 'arguments') {
                        $arguments = $value;
                    } else {
                        $this->xmlwriter->writeAttribute($key, $value);
                    }
                }
                if (null !== $arguments) {
                    $this->addArguments($arguments);
                }
            }
            $this->xmlwriter->endElement();
        }
    }

    private function addArguments($arguments)
    {
        foreach ($arguments as $argument) {
            $this->xmlwriter->startElement('argument');
            if (is_array($argument)) {
                foreach ($argument as $key => $value) {
                    $this->xmlwriter->writeAttribute($key, $value);
                }
            } else {
                if ($argument[0] === '@' && $argument[1] !== '@') {
                    $this->xmlwriter->writeAttribute('type', 'service');
                    $this->xmlwriter->writeAttribute('id', substr($argument, 1));
                } else {
                    $this->xmlwriter->writeAttribute('type', 'string');
                    $this->xmlwriter->text($argument);
                }
            }
            $this->xmlwriter->endElement();
        }
    }
}
