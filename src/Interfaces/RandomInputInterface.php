<?php
namespace ChatFlow\Interfaces;

interface RandomInputInterface
{
    /**
     * Returns the user defined type so that the state manager
     * can invoke a state or enter a decision state point
     *
     * @return string A string that must match inputType key(s)
     */
    public function getType();
}
