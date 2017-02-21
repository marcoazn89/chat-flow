<?php
namespace ChatFlow;

use ChatFlow\Interfaces\StateInterface;
use ChatFlow\Interfaces\ActionInterface;

class ConfirmState implements StateInterface, ActionInterface
{
    protected $action;

    protected $confirmVals;

    protected $name;

    public function getName()
    {
        return $this->name;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function setActionValues($values)
    {
        $this->confirmVals = $values;
    }

    public function resolve($input)
    {
        if (in_array($input, $this->confirmVals, true)) {
            return true;
        }

        return false;
    }
}
