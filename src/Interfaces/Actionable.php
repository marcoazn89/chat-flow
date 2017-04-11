<?php
namespace ChatFlow\Interfaces;

/**
 * Actions to be executed at different points in a conversation such as:
 * - introduction
 * - message
 * - cancel
 * - confirm
 * - prompt

 */
interface Actionable
{
    public function action(array $stateData = []): void;
}
