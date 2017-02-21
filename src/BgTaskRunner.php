<?php
namespace Chatflow;

class BgTaskRunner
{
    protected $tasks = [];

    public function registerTask($name, $when, \Closure $task)
    {
        if (isset($this->tasks[$name])) {
            printf('WARNING: Task %s already exists and it will be overriden\n', $name);
        }

        $this->validateWhen($when);

        $this->tasks[$name] = [
            'when' => $when,
            'task' => $task
        ];

        if (!isset($this->schedule[$when])) {
            $this->schedule[$when] = [];
        }

        $this->schedule[$when][] =
    }

    abstract public function getName();

    public function done()
    {

    }
}


// user x says they want to find someone leaving
// write into db: event: match, user: x, points: 10, active: 1
// cron runs all events registered
//
// name
// when
// function
