<?php
namespace ChatFlow;

use Interfaces\StateInterface;
use ChatFlow\Interfaces\ActionInterface;

class PromptState implements StateInterface, ActionInterface
{
    protected $action;

    protected $confirmVals;

    public function getName()
    {
        return 'prompt';
    }

    public function setActionValues($values)
    {
        $this->confirmVals = $values;
    }

    public function resolve($input)
    {
        $action = $this->action;
        $choices = $action();

        if (in_array($input, $choices)) {
            return $input;
        }

        return false;
    }
}
