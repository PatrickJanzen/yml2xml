<?php

namespace App\Types;

use XMLWriter;

class Resource extends TypeHandler
{
    /**
     */
    protected function convert(): void
    {
        $this->xmlwriter->startElement('resources');
        $this->xmlwriter->writeAttribute('xmlns', 'https://api-platform.com/schema/metadata');
        $this->xmlwriter->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $this->xmlwriter->writeAttribute('xsi:schemaLocation', 'https://api-platform.com/schema/metadata https://api-platform.com/schema/metadata/metadata-2.0.xsd');

        if (array_key_exists('resources', $this->yaml)) {
            foreach ($this->yaml['resources'] as $name => $resource) {
                $this->xmlwriter->startElement('resource');
                $this->xmlwriter->writeAttribute('class', $name);
                foreach (['iri', 'description', 'shortName'] as $key) {
                    if (array_key_exists($key, $resource)) {
                        $this->xmlwriter->writeAttribute($key, $resource[$key]);
                        unset($resource[$key]);
                    }
                }
                if (array_key_exists('attributes', $resource)) {
                    foreach ($resource['attributes'] as $attrName => $attribute) {
                        $this->addAttribute($attrName, $attribute);
                    }
                    unset($resource['attributes']);
                }
                foreach (['item', 'collection', 'subresource'] as $operation) {
                    if (array_key_exists($operation . 'Operations', $resource)) {
                        $this->createOperations($operation, $resource[$operation . 'Operations']);
                        unset($resource[$operation . 'Operations']);
                    }
                }
                $this->xmlwriter->endElement();
                if (count($resource) !== 0) {
                    $this->addMessage('Unprocessed items in resource ' . $name);
                }
            }
        } else {
            $this->addWarning('no resources in yaml found!');
        }
        $this->xmlwriter->endElement();

    }

    private function addAttribute(string $name, $attributes)
    {
        $this->xmlwriter->startElement('attribute');
        $this->xmlwriter->writeAttribute('name', $name);
        if (is_array($attributes)) {
            foreach ($attributes as $attrName => $attribute) {
                $this->addAttribute($attrName, $attribute);
            }
        } else {
            if (is_bool($attributes)) {
                $attributes = $attributes ? 'true' : 'false';
            }
            $this->xmlwriter->text($attributes);
        }
        $this->xmlwriter->endElement();
    }

    private function createOperations($type, $element)
    {
        $this->xmlwriter->startElement($type . 'Operations');
        foreach ($element as $name => $operation) {
            if (is_numeric($name)) {
                $name = $operation;
                $operation = null;
            }
            $this->createOperation($type, $name, $operation);
        }
        $this->xmlwriter->endElement();
    }

    private function createOperation($type, $name, $operation)
    {
        $this->xmlwriter->startElement($type . 'Operation');
        $this->xmlwriter->writeAttribute('name', $name);
        if (is_array($operation)) {
            foreach ($operation as $opname => $op) {
                $this->addAttribute($opname, $op);
            }

        }
        $this->xmlwriter->endElement();
    }

}
