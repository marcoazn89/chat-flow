<?php
namespace ChatFlow\Interfaces;

interface ConfirmInterface extends Resolvable
{
    public function resolve(): bool;
}
