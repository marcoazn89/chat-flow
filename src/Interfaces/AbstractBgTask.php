<?php
namespace ChatFlow

interface AbstractBgTask
{
    abstract public function task();

    abstract public function getFrequency();

    public function run()
    {

    }
}
