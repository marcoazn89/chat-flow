<?php
namespace ChatFlow\Interfaces;

interface BasicState
{
	public function resolve(array $data): bool;
}
