<?php
namespace ChatFlow;

use Interfaces\FlowRepositoryInterface;

class Bot
{
    protected $repository;
    protected $flows;
    protected $resolvers = [];
    protected $currentState;

    public function __construct(FlowRepositoryInterface $repository, array $flows)
    {
        $this->repository = $repository;
        $this->flows = $flows;
    }

    public function registerResolver($name, \Closure $resolver)
    {
        $this->resolvers[$name] = $resolver;
    }

    /**
     * Given a user input formulate a reply
     *
     * @param  mixed $input A message sent by the user
     * @return void
     */
    public function reply($input)
    {
        $currentState = $this->getCurrentState();

        // If there is no active state
        if (is_null($currentState)) {

        } else {
            $this->updateState($this->resolvers[$currentState]($input));
            /*// The state resolved successfully
            if ($this->resolvers[$currentState]($input)) {
                $this->updateState(true);
            } else {
                // The state didn't resolve
            }*/
        }
    }

    protected function updateState($shouldForward)
    {
        if ($shouldForward) {

        } else {

        }
    }

    protected function pushState($state)
    {

    }

    protected function popState()
    {

    }

    protected function clearStates()
    {

    }

    protected function resolve($name, $input)
    {
        $result = $this->resolvers[$name]($input);

        if (!is_bool($result)) {
            throw new \InvalidArgumentException("Incorrect output for resolver {$name}");
        }

        return $result;
    }

    public function setCurrentState($state)
    {
        $this->currentState = $state;
    }

    public function getCurrentState()
    {

    }
}
