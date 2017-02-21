<?php
namespace ChatFlow\Interfaces;

interface StateInterface
{
    public function getName();

    /**
     * This methods only processes the input and is not
     * intended for sending messages
     *
     * @param  mixed $input
     * @return boolean
     */
    public function resolve($input);
}
