<?php
namespace ChatFlow\Interfaces;

interface LastPromptInterface
{
    public function promptMessage();

    public function getPromptValues();
}
