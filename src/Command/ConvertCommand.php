<?php

namespace App\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Yaml\Yaml;
use XMLWriter;

class ConvertCommand extends Command
{
    protected static $defaultName = 'y2x:convert';
    /**
     * @var SymfonyStyle
     */
    private $io;
    /**
     * @var string|null
     */
    private $path;
    /**
     * @var XMLWriter
     */
    private $xmlwriter;
    /**
     * @var array
     */
    private $yaml;

    protected function configure()
    {
        $this
            ->setDescription('converts given yaml file to xml')
            ->addArgument('yamlFile', InputArgument::REQUIRED, 'file to process')
            ->addArgument('type', InputArgument::OPTIONAL, 'type of file [resource, services]', 'resource');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);
        $this->path = $input->getArgument('yamlFile');
        $type = strtolower($input->getArgument('type'));

        if (!file_exists($this->path)) {
            $this->io->error(sprintf('File not found: %s', $this->path));
            return 1;
        }
        if (!in_array($type, ['resource', 'service'])) {
            $this->io->error(sprintf('unsupported type: %s', $type));
            return 1;
        }

        $this->io->text('processing ' . $this->path . ' as <info>' . $type . 's-file</info>');

        $this->startXML();
        switch ($type) {
            case 'resource' :
                $this->convertResource();
                break;
            case 'service' :
                $this->convertService();
                break;
            default:
                $this->io->error('unsupported file type: ' . $type);
                break;
        }

        $this->xmlwriter->flush();

        $this->io->success('Done');

        return 0;
    }

    /**
     *
     */
    private function startXML()
    {
        $pathInfo = pathinfo($this->path);

        $this->yaml = Yaml::parseFile($this->path);
        $outPath = $pathInfo['dirname'] . DIRECTORY_SEPARATOR . $pathInfo['filename'] . '.xml';

        $i = 0;
        while (file_exists($outPath)) {
            $i++;
            $outPath = $pathInfo['dirname'] . DIRECTORY_SEPARATOR . $pathInfo['filename'] . '_' . $i . '.xml';
        }

        if ($i > 0) {
            $this->io->warning($pathInfo['dirname'] . DIRECTORY_SEPARATOR . $pathInfo['filename'] . '.xml ' . 'already exists, using ' . $outPath);
        }

        $this->xmlwriter = new XMLWriter();
        $this->xmlwriter->openUri($outPath);
        $this->xmlwriter->setIndent(true);
        $this->xmlwriter->startComment();
        $this->xmlwriter->text('Converted from ' . $pathInfo['basename']);
        $this->xmlwriter->endComment();
        $this->xmlwriter->setIndentString('    ');

    }

    /**
     */
    private function convertResource(): void
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
                    $this->io->warning('Unprocessed items in resource ' . $name);
                }
            }
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

    private function convertService()
    {
        $this->xmlwriter->startElement('container');
        $this->xmlwriter->writeAttribute('xmlns', 'http://symfony.com/schema/dic/services');
        $this->xmlwriter->writeAttribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance');
        $this->xmlwriter->writeAttribute('xsi:schemaLocation', 'http://symfony.com/schema/dic/services https://symfony.com/schema/dic/services/services-1.0.xsd');

        if (array_key_exists('parameters', $this->yaml)) {
            $this->createParameters($this->yaml['parameters']);
        }

        if (array_key_exists('services', $this->yaml)) {
            $this->createServices($this->yaml['services']);
        }

        $this->xmlwriter->endElement();
    }

    private function createParameters($parameters)
    {
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
                $this->io->caution('please take care of imports manually!');
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
                        $this->io->caution('unprocessed parameters: ' . implode(',', array_keys($parameter)));
                    }
                }
            }
            /*
                        $this->xmlwriter->writeAttribute('key', $key);
                        $this->xmlwriter->writeAttribute('parameter', print_r($parameter, 1));*/
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
