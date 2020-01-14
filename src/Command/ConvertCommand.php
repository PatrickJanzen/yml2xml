<?php

namespace App\Command;

use App\Types\TypeHandler;
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

    /**
     * @var TypeHandler[]
     */
    private $typeHandlers;

    /**
     * @var string[]
     */
    private $supportedTypes;

    public function __construct($typeHandlers, string $name = null)
    {
        $this->typeHandlers = $typeHandlers;
        $this->supportedTypes = [];
        foreach ($typeHandlers as $handler) {
            $this->supportedTypes[] = $handler->getType();
        }
        parent::__construct($name);
    }

    protected function configure()
    {
        $this
            ->setDescription('converts given yaml file to xml')
            ->addArgument('yamlFile', InputArgument::REQUIRED, 'file to process')
            ->addArgument('type', InputArgument::OPTIONAL, 'type of file [' . implode(', ', $this->supportedTypes) . ']', 'resource');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->path = $input->getArgument('yamlFile');
        if (!file_exists($this->path)) {
            $this->io->error(sprintf('File not found: %s', $this->path));
            return 1;
        }

        $type = strtolower($input->getArgument('type'));
        $handled = false;
        foreach ($this->typeHandlers as $handler) {
            if ($handler->canHandleType($type)) {
                $this->startXML();
                $this->io->text('processing ' . $this->path . ' as <info>' . $type . '-file</info>');
                $messages = $handler->handle($this->xmlwriter, $this->yaml);
                foreach ($messages as list($messageType, $message)) {
                    if ($messageType === 'warning') {
                        $this->io->warning($message);
                    } else {
                        $this->io->text($message);
                    }
                }
                $this->xmlwriter->flush();
                $this->io->success('Done');
                $handled = true;
                break;
            }
        }

        if (!$handled) {
            $this->io->error(sprintf('unsupported type: %s', $type));
            $this->io->error(sprintf('supported types are: [%s]', implode(', ', $this->supportedTypes)));
            return 1;
        }

        //$this->io->text(count($this->yaml));

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

}
