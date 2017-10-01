<?php

namespace DataTransmissionCommand;

use Assert\Assertion;

class DataTransitionCommand implements \Countable, \ArrayAccess, \IteratorAggregate
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
     * @var array $syntaxErrorBuild
     */
    private $syntaxErrorBuild = [];
    /**
     * @var array $result
     */
    private $result = [];
    /**
     * DataTransitionCommand constructor.
     * @param array $transitions
     * @param array $data
     */
    public function __construct(array $transitions, array $data)
    {
        if (empty($transitions)) {
            throw new \RuntimeException('DataTransitionCommand: No transitions. You passed an empty value');
        }

        if (empty($data)) {
            throw new \RuntimeException('DataTransitionCommand: No data. You passed an empty value');
        }

        $this->recursiveDataIterator = new \RecursiveIteratorIterator(
            new \RecursiveArrayIterator($data),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $this->parseTransitions($transitions);
        $this->createTransitionObject();

        if (!empty($this->syntaxErrorBuild)) {
            $this->handleSyntaxErrors();
        }
    }
    /**
     * @param $name
     * @return mixed
     */
    public function __get($name)
    {
        if (!array_key_exists($name, $this->result)) {
            throw new \RuntimeException(
                sprintf('Property \'%s\' not found', $name)
            );
        }

        return $this->result[$name];
    }
    /**
     * @void
     */
    private function createTransitionObject()
    {
        $result = $this->buildFoundTree();

        if (empty($result)) {
            throw new \RuntimeException(
                sprintf('DataTransitionCommand: Something went wrong. Results are empty and not parsed. This is an internal error')
            );
        }

        $this->result = $result;
    }
    /**
     * @return array
     */
    private function buildFoundTree()
    {
        $searchingFor = 0;
        $found = [];
        $previousDepth = null;
        $inSearchMode = false;
        foreach ($this->tokens as $token) {
            foreach ($this->recursiveDataIterator as $key => $value) {
                $tree = $token['tree'];
                $currentDepth = $this->recursiveDataIterator->getDepth();

                if (is_null($previousDepth)) {
                    $previousDepth = $currentDepth;
                }

                if ($inSearchMode === true) {
                    if ($previousDepth !== ($currentDepth - 1)) {
                        continue;
                    }
                }

                if (
                    $key === $tree[$searchingFor] and
                    $currentDepth === $searchingFor
                ) {
                    if ((count($tree) - 1) === $currentDepth) {
                        $this->assert($value, $token);


                        if (is_string($token['alias'])) {
                            $found[$token['alias']] = $value;
                        } else {
                            $found[$tree[count($tree) - 1]] = $value;
                        }

                        $previousDepth = null;
                        $searchingFor = 0;
                        $inSearchMode = false;

                        break;
                    } else {
                        $previousDepth = $currentDepth;
                        ++$searchingFor;
                        $inSearchMode = true;
                    }
                }
            }
        }

        return $found;
    }
    /**
     * @param array $transitions
     * @void
     */
    private function parseTransitions(array $transitions)
    {
        foreach ($transitions as $transition) {
            $token = $this->parseSingleTransition($transition);

            if (is_null($token)) {
                continue;
            }

            $this->tokens[] = $token;
        }
    }
    /**
     * @param string $transition
     * @return array
     */
    private function parseSingleTransition($transition)
    {
        $this->validateTransition($transition);

        $parsedToken = $this->parseToken($transition);

        $this->tokens[] = $parsedToken;
    }
    /**
     * @param string $transition
     * @return array
     */
    private function parseToken($transition)
    {
        $parsedToken = [];

        $mainParsedData = $this->parseTree($transition);

        $parsedToken['tree'] = $mainParsedData['tree'];
        $parsedToken['searchItem'] = $mainParsedData['searchItem'];
        $parsedToken['assertions'] = $this->parseAssertions($transition);
        $parsedToken['alias'] = $this->parseAlias($transition);

        return $parsedToken;
    }
    /**
     * @param string $transition
     * @return array
     */
    private function parseTree($transition)
    {
        $parsedToken = [];
        $parseTree = null;
        $unparsed = null;

        if (preg_match('#>#', $transition) === 1) {
            $unparsed = preg_split('#>#', $transition);

            $tree = [];
            foreach ($unparsed as $index => $item) {
                if (preg_match('#\##', $item) === 0 and preg_match('#\|#', $item) === 0) {
                    $tree[] = $item;
                }
            }

            $parseTree = $tree;

            $last = $unparsed[count($unparsed) - 1];

            if (preg_match('#\w+\##', $last)) {
                $searchItem = preg_split('#\##', $last)[0];
            } else {
                $searchItem = $tree[count($tree) - 1];
            }

            $parseTree[] = $searchItem;

            $parsedToken = [
                'tree' => $parseTree,
                'searchItem' => $searchItem,
            ];
        } else if (preg_match('#\##', $transition) === 1) {
            $unparsed = preg_split('#\##', $transition);

            $tree = [$unparsed[0]];
            $searchItem = $unparsed[0];

            unset($unparsed[0]);

            $parsedToken = [
                'tree' => $tree,
                'searchItem' => $searchItem
            ];
        } else if (preg_match('#\|#', $transition) === 1) {
            $pipeSplit = preg_split('#\|#', $transition);

            $parsedToken = [
                'tree' => [$pipeSplit[0]],
                'searchItem' => $pipeSplit[0]
            ];
        }

        return $parsedToken;
    }
    /**
     * @param string $transition
     * @return null|string
     */
    private function parseAlias($transition)
    {
        if (preg_match('#\|#', $transition) === 1) {
            $alias = preg_split('#\|#', $transition);

            if (count($alias) > 2) {
                $this->syntaxErrorBuild[] = sprintf('DataTransitionCommand invalid transition. A transition can only have one alias. More that one given for transition %s', $transition);

                $alias = null;
            }

            return $alias[1];
        }

        return null;
    }
    /**
     * @param string $transition
     * @return array
     */
    private function parseAssertions($transition)
    {
        if (preg_match('#\##', $transition) === 1) {
            $assertions = preg_split('#\##', $transition);

            // remove part that does not concern assertions, the rest of the array does
            unset($assertions[0]);
            $assertions = array_values($assertions);

            $lastIndex = (count($assertions) > 1) ? (count($assertions) - 1) : 0;

            if (preg_match('#\|#', $assertions[$lastIndex]) === 1) {
                $pipedEntry = preg_split('#\|#', $assertions[$lastIndex]);

                unset($assertions[$lastIndex]);

                $assertions[] = $pipedEntry[0];
            }
            sort($assertions);

            return $assertions;
        }

        return [];
    }
    /**
     * @param mixed $found
     * @param array $token
     */
    private function assert($found, array $token)
    {
        $assertions = $token['assertions'];

        foreach ($assertions as $assertion) {
            Assertion::{$assertion}(
                $found,
                sprintf('Failed assertion \'%s\' for search item \'%s\'', $assertion, $token['searchItem'])
            );
        }
    }
    /**
     * @throws \InvalidArgumentException
     */
    private function handleSyntaxErrors()
    {
        $mainMessage = "Following syntax errors were encountered:\n";

        foreach ($this->syntaxErrorBuild as $syntaxError) {
            $mainMessage.="\t".$syntaxError."\n";
        }

        throw new \InvalidArgumentException($mainMessage);
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
    /**
     * @return int
     */
    public function count()
    {
        return count($this->result);
    }
    /**
     * @param int|string $offset
     * @return bool
     */
    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->result);
    }

    /**
     * @param int|string $offset
     * @param mixed $value
     */
    public function offsetSet($offset, $value)
    {
        if (!$this->offsetExists($offset)) {
            throw new \OutOfBoundsException(sprintf('Property \'%s\' does not exist', $offset));
        }

        $this->result[$offset] = $value;
    }
    /**
     * @param int|string $offset
     * @return mixed
     */
    public function offsetGet($offset)
    {
        if ($this->offsetExists($offset)) {
            return $this->result[$offset];
        }

        throw new \OutOfBoundsException(sprintf('Property \'%s\' does not exist', $offset));
    }
    /**
     * @param int|string $offset
     */
    public function offsetUnset($offset)
    {
        if ($this->offsetExists($offset)) {
            unset($this->result[$offset]);
        }
    }
    /**
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->result);
    }
    /**
     * @return \Generator
     */
    public function getGenerator()
    {
        foreach ($this->result as $key => $item) {
            yield $item;
        }
    }

}