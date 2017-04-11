<?php
namespace ChatFlow\Interfaces;

abstract class Confirm extends Dialog
{
	public function getName(): string
	{
		return 'confirm';
	}
}
