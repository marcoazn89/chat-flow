<?php
namespace ChatFlow\Interfaces;

interface Dialog extends BasicState
{
	public function ask(array $stateData = []): void;
}
