<?php
namespace ChatFlow\Interfaces;

interface MessageInterface
{
    public function message($attempt);

    public function cancelMessage();

    public function resolvedMessage();
}
