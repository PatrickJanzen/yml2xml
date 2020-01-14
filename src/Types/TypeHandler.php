<?php


namespace App\Types;


use ReflectionClass;
use XMLWriter;

abstract class TypeHandler
{
    /**
     * @var string
     */
    private $type;
    /**
     * @var XMLWriter
     */
    protected $xmlwriter;
    /**
     * @var array
     */
    private $messages;
    /**
     * @var array
     */
    protected $yaml;

    /**
     *
     * @throws \ReflectionException
     */
    public function __construct()
    {
        $this->type = strtolower((new ReflectionClass($this))->getShortName());
    }

    public function canHandleType(string $type): bool
    {
        return $type === $this->getType();
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function handle(XMLWriter $XMLWriter, array $yaml): array
    {
        $this->xmlwriter = $XMLWriter;
        $this->yaml = $yaml;
        $this->messages = [];
        $this->convert();
        return $this->messages;
    }

    abstract protected function convert();

    protected function addMessage(string $message, $type = 'text')
    {
        $this->messages[] = [$type, $message];
    }

    protected function addWarning(string $message)
    {
        $this->addMessage($message, 'warning');
    }
}