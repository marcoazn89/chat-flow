<?php
declare(strict_types=1);

namespace ChatFlow;

use ChatFlow\StateRepositoryInterface;
use Ds\Stack;
use Closure;

class StateManager
{
    protected $repo;
    protected $stack;
    protected $statesConfig = [];
    protected $stateData = [
        'resolved_attempts' => 0,
        'prompted' => false,
        'bg_running' => false,
        'current_resolver' => ''
    ];
    protected $registeredStates = [];
    protected $state;
    protected $config = [];
    protected $userId;
    protected $parentState;
    protected $decision;

    public function __construct(StateRepositoryInterface $repo)
    {
        $this->repo = $repo;
        $this->stack = new \SplStack();
    }

    /**
     * Set up the state manager
     *
     * @throws RuntimeException
     * @return void
     */
    protected function setup(): void
    {
        $stateData = $this->repo->getStateData($this->userId);

        // Check if there are any state records for the user
        if (empty($stateData)) {
            //echo "Has no state data<br>";
            if (empty($this->defaultState)) {
                throw new \RuntimeException('A default state must be provided');
            }

            // Build a fresh stack
            $this->buildStack($this->defaultState);
        } else {

            // Get the serialized stack string
            $this->stack->unserialize($stateData['stack']);

            // No need to copy the serialized stack
            unset($stateData['stack']);

            $this->stateData = array_merge($this->stateData, $stateData);
        }
    }

    /**
     * Build the stack recursively which contains all states
     *
     * @param  sting $state State starting point
     * @throws InvalidArgumentException
     * @return void
     */
    protected function buildStack(string $state): void
    {
        // Fail state isn't found
        if (!isset($this->config[$state])) {
            throw new \InvalidArgumentException("State {$state} not found");
        }

        // Next state is defined & is not array
        if (!empty($this->config[$state]['next_state']) &&
            !is_array($this->config[$state]['next_state'])) {
            // recursively build every state
            $this->buildStack($this->config[$state]['next_state']);
        }

        // Push state
        $this->stack->push($state);

        // If state has children
        if (!empty($this->config[$state]['children'])) {
            // Add every child to the stack
            for ($i = count($this->config[$state]['children']) - 1; $i >=0; $i--) {
                $childName = $this->config[$state]['children'][$i];
                $this->buildStack($childName);
            }
        }

    }

    /**
     * Set the default state
     *
     * @throws InvalidArgumentException
     * @param string $name
     */
    public function setDefaultState(string $name): void
    {
        if (isset($this->config[$name])) {
            $this->defaultState = $name;
        } else {
            throw new \InvalidArgumentException(
                "Unable to register state {$name} because it wasn't found"
            );
        }
    }

    /**
     * Set the user whose state data will be used
     *
     * @param void
     */
    public function setUser($userId): void
    {
        $this->userId = $userId;
    }

    /**
     * Add data that can be used later for actions and resolvers
     *
     * @param string $key
     * @param mixed $value
     */
    public function addData(string $key, $value): void
    {
        $this->stateData[$key] = $value;
    }

    /**
     * Register state
     *
     * @param  string  $name      Name of the state
     * @param  Closure $stateCall A function that returns an instance of State
     * @param  array   $config       State configuration
     * @return void
     */
    public function registerState(string $name, Closure $stateCall, array $config = []): void
    {
        $this->registeredStates[$name] = $stateCall;
        $this->config[$name] = $config;
    }

    /**
     * Check if state is a decision point
     * A state becomes a decision point when the next state is an array
     * with more than one element
     *
     * @param  string  $state
     * @return boolean
     */
    protected function isDecisionPoint(string $state): bool
    {
        // Next state is defined & is not array
        if (empty($this->config[$state]['next_state']) || !is_array($this->config[$state]['next_state'])) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Invoke state
     * This will trigger the function that was register for the state
     *
     * @param  string $state
     * @throws RuntimeException
     * @return State
     */
    public function invoke(string $state): State
    {
        if (!isset($this->registeredStates[$state])) {
            throw new \RuntimeException("State {$state} is not registered");
        }

        if ($this->registeredStates[$state] instanceof State) {
            return $this->registeredStates[$state];
        } else {
            $this->registeredStates[$state] = $this->registeredStates[$state]();
            $this->registeredStates[$state]->setName($state);
            $this->registeredStates[$state]->setMaxAttempts($this->config[$state]['max_attempts']);
            $this->setResolver($this->registeredStates[$state], $this->stateData['current_resolver']);

            return $this->registeredStates[$state];
        }
    }

    /**
     * Set the resolver to be used by the state
     *
     * @param State  $state
     * @param string $resolver
     */
    protected function setResolver(State $state, string $resolver = ''): void
    {
        if (empty($resolver)) {
            if ($state->hasResolver($state::RESOLVER_CONFIRM)) {
                $this->stateData['current_resolver'] = $state::RESOLVER_CONFIRM;
            } else {
                $this->stateData['current_resolver'] = $state::RESOLVER_STATE;
            }
        } else {
            $this->stateData['current_resolver'] = $resolver;
        }

        $state->setResolver($this->stateData['current_resolver']);
    }

    /**
     * Resolve the state
     *
     * @param  mixed $input
     * @return void
     */
    public function resolve($input = null): void
    {
        // Get the current state
        $current = $this->stack->top();

        // Invoking current state
        $state = $this->invoke($current);

        // If resolved
        if ($result = $state->resolve($input)) {
            switch ($state->getResolver()) {
                case $state::RESOLVER_CONFIRM:
                case $state::RESOLVER_PROMPT:
                    $state->continueAction($this->stateData);
                    $this->setResolver($state, $state::RESOLVER_STATE);
                    $this->stateData['resolved_attempts'] = 0;
                    break;
                case $state::RESOLVER_STATE:
                    $state->successAction($this->stateData);
                    $this->stack->pop();
                    $this->resetState();

                    if ($this->isDecisionPoint($state->getName())) {
                        $this->buildStack($this->getDecision());
                    } elseif ($this->stack->isEmpty()) {
                        $this->stack = new \SplStack;
                        $this->buildStack($this->defaultState);
                        return;
                    }
                    break;
            }

            $this->resolve($result);
            return;
        } else {
            $justFail = false;

            // Has chatty state
            // Chatty never increases resolved attempts
            if ($state->hasResolver($state::RESOLVER_CHATTY)
                && $state->resolve($input, $state::RESOLVER_CHATTY)) {
                return;
            } elseif ($state->getResolver() === $state::RESOLVER_CONFIRM && $this->stateData['resolved_attempts'] === 1) {
                $justFail = true;
            }

            $this->stateData['resolved_attempts']++;
            if ($state->isAttemptAllowed($this->stateData['resolved_attempts'] - 1) && !$justFail) {
                if ($state->getResolver() === $state::RESOLVER_CONFIRM) {
                    $state->confirmAction($this->stateData);
                    return;
                } else {
                    if ($this->stateData['resolved_attempts'] === 1) {
                        $state->introAction($this->stateData);
                    }
                    $state->messageAction($this->stateData);

                    $state->backgroundAction($this->stateData);

                    $this->setResolver($state, $state::RESOLVER_STATE);
                    return;
                }
            } elseif ($state->getResolver() === $state::RESOLVER_STATE) {
                if ($state->hasResolver($state::RESOLVER_PROMPT)) {
                    $this->setResolver($state, $state::PROMPT);
                    $state->promptAction($this->stateData);
                    return;
                }
            }

            $state->failAction($this->stateData);

            $this->stack = new \SplStack;
            $this->buildStack($this->defaultState);
            $this->resetState();
            return;
        }
    }

    /**
     * Reset the state
     *
     * @return void
     */
    protected function resetState(): void
    {
        $this->stateData['resolved_attempts'] = 0;
        $this->stateData['prompted'] = 0;
        $this->stateData['bg_running'] = false;
        $this->stateData['current_resolver'] = '';
    }

    /**
     * Save state data in the repository provided
     *
     * @return void
     */
    protected function sync(): void
    {
        $this->stateData['stack'] = $this->stack->serialize();
        $this->repo->saveStateData($this->stateData);
    }

    /**
     * Set what state is next when a state is a decision point
     *
     * @param string $next
     * @return void
     */
    public function setDecision(string $next): void
    {
        $this->decision = $next;
    }

    /**
     * Get the next state that was decided by the resolver
     *
     * @return string
     */
    public function getDecision(): string
    {
        return $this->decision;
    }

    /**
     * Run the state manager
     *
     * @param  mixed $input  Input to resolve
     * @return void
     */
    public function run($input = null): void
    {
        $this->setup();

        $this->resolve($input);

        $this->sync();
    }
}
