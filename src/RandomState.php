<?php
namespace ChatFlow;

use ChatFlow\Interfaces\StateInterface;
use ChatFlow\Interfaces\ActionInterface;

class RandomState implements StateInterface
{
    public function getName()
    {
        return 'random';
    }

    public function resolve($input)
    {
        switch ((string)$input) {
            case 'joke':
                echo 'I am not funny at all sorry';
                break;
            case 'fuck you':
                echo 'Woah woah so rude';
                break;
        }

        return true;
    }
}
