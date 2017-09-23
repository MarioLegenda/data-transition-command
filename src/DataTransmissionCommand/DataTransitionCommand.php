<?php

namespace DataTransmissionCommand;

class DataTransitionCommand implements \Countable, \ArrayAccess
{
    /**
     * @var \RecursiveIteratorIterator $recursiveDataIterator
     */
    private $recursiveDataIterator;
    /**
     * @var array $tokens
     */
    private $tokens = [];
    /**
     * DataTransitionCommand constructor.
     * @param array $transitions
     * @param array $data
     */
    public function __construct(array $transitions, array $data)
    {
        $this->recursiveDataIterator = new \RecursiveIteratorIterator(new \RecursiveArrayIterator($data));

        $this->parseTransitions($transitions);
    }

    public function count()
    {
    }

    public function offsetExists($offset)
    {
    }

    public function offsetSet($offset, $value)
    {
    }

    public function offsetGet($offset)
    {
    }

    public function offsetUnset($offset)
    {
    }

    private function parseTransitions(array $transitions)
    {
        foreach ($transitions as $transition) {
            $this->tokens[] = $this->parseSingleTransition($transition);
        }
    }
    /**
     * @param string $transition
     * @return array
     */
    private function parseSingleTransition($transition)
    {
        $this->validateTransition($transition);


    }
    /**
     * @param string $transition
     * @throws \InvalidArgumentException
     */
    private function validateTransition($transition)
    {
        if (!is_string($transition)) {
            if (is_scalar($transition) and !is_null($transition)) {
                $message = sprintf('DataTransitionCommand invalid transition for value %s. Every transition should be a string', (string) $transition);
            } else if (is_null($transition)) {
                $message = sprintf('DataTransitionCommand invalid transition. Every transition should be a string. Null given');
            } else {
                $message = sprintf('DataTransitionCommand invalid transition. Every transition should be a string');
            }

            throw new \InvalidArgumentException($message);
        }
    }
}