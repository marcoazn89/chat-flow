<?php
namespace ChatFlow;

use ChatFlow\Interfaces\StateRepositoryInterface;

class BackgroundTask
{
    protected $repo;

    public function __construct(StateRepositoryInterface $repo)
    {
        $this=>repo = $repo;
    }

    public function updateStack()
    {

    }
}
