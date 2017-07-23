<?php
declare(strict_types=1);

namespace ChatFlow;

use ChatFlow\StateRepositoryInterface;
use Ds\Deque;
use Closure;
use DateTime;
use DateTimeZone;
use DateInterval;
use InvalidArgumentException;
use RuntimeException;

class StateManager
{
    protected $repo;
    protected $stack;
    protected $statesConfig = [];
    protected $stateData = [
        'resolved_attempts' => 0,
        'prompted' => false,
        'bg_running' => false,
        'current_resolver' => '',
        'current_state' => null,
        'data' => []
    ];
    protected $registeredStates = [];
    protected $state;
    protected $config = [];
    protected $userId;
    protected $parentState;
    protected $decision;
    protected $isSetup = false;

    public function __construct(StateRepositoryInterface $repo)
    {
        $this->repo = $repo;
    }

    /**
     * Set up the state manager
     *
     * @param int $userId
     * @param string $defaultState
     * @throws RuntimeException
     * @return void
     */
    public function setUp(int $userId, string $defaultState): void
    {
        $this->userId = $userId;
        $this->defaultState = $defaultState;
        $stateData = $this->repo->getStateData($this->userId);
        $this->resetState();

        // Check if there are any state records for the user
        if (empty($stateData) || (!empty($stateData['current_state']) && $this->isStateExpired($stateData['current_state'], $stateData['timestamp']))) {
            $this->deque = new Deque;

            // Build a fresh stack
            $this->buildDeque($this->deque, $defaultState);
        } else {

            // Get the serialized stack string
            $this->deque = unserialize($stateData['stack']);

            // No need to copy the serialized stack
            unset($stateData['stack']);

            $this->stateData = array_merge($this->stateData, $stateData);
        }
    }

    /**
     * Build the stack recursively which contains all states
     *
     * @param Deque $deque Deque to be populated
     * @param string $state State starting point
     * @throws InvalidArgumentException
     * @return Deque
     */
    protected function buildDeque(Deque $deque, string $state): Deque
    {
        // Fail state isn't found
        if (!isset($this->config[$state])) {
            throw new InvalidArgumentException("State {$state} was not registered");
        }

        // Next state is defined & is not array
        if (!empty($this->config[$state]['next_state']) &&
            !is_array($this->config[$state]['next_state'])) {
            // recursively build every state
            $deque = $this->buildDeque($deque, $this->config[$state]['next_state']);
        }

        // If state has children
        if (!empty($this->config[$state]['children'])) {
            // Add every child to the stack
            for ($i = count($this->config[$state]['children']) - 1; $i >=0; $i--) {
                $childName = $this->config[$state]['children'][$i];
                $deque = $this->buildDeque($deque, $childName);
            }
        }

        $deque->push($state);

        return $deque;
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
            throw new InvalidArgumentException(
                "Unable to register state {$name} because it wasn't found"
            );
        }
    }

    /**
     * Add data that can be used later for actions and resolvers
     *
     * @param array $data
     */
    public function addData(array $data): void
    {
        $this->stateData['data'] = array_merge($this->stateData['data'], $data);
    }

    public function getData(?string $key = null): array
    {
        return $key !== null ? $this->stateData[$key] : $this->stateData;
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
        return isset($this->config[$state]['next_state']) && is_array($this->config[$state]['next_state']);
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
            throw new RuntimeException("State {$state} is not registered");
        }

        if ($this->registeredStates[$state] instanceof State) {
            return $this->registeredStates[$state];
        } else {
            $this->registeredStates[$state] = $this->registeredStates[$state]();
            $this->registeredStates[$state]->setName($state);
            $this->registeredStates[$state]->setMaxAttempts($this->config[$state]['max_attempts']);
            $this->registeredStates[$state]->setInterruptible(!empty($this->config[$state]['interruptible']));
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
    protected function setResolver(State $state, string $resolver): void
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
    public function getDecision(): ?string
    {
        return $this->decision;
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
     * Cancel move to another state
     * The default state is used if no state is specified
     *
     * @param  null|string $state
     * @return void
     */
    public function cancel(?string $state = null): void
    {
        $this->deque->clear();
        $this->buildDeque($this->deque, $state ?? $this->defaultState);
        $this->resetState();
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
        $current = $this->deque->last();

        // Invoking current state
        $state = $this->invoke($current);

        // If resolved
        if ($result = $state->resolve($input)) {
            $resolver = $state->getResolver();
            switch ($resolver) {
                case $state::RESOLVER_CONFIRM:
                case $state::RESOLVER_PROMPT:
                    $state->continueAction($this->stateData);

                    if ($resolver === $state::RESOLVER_CONFIRM) {
                        $state->introAction($this->stateData);
                    }

                    $this->setResolver($state, $state::RESOLVER_STATE);
                    $this->stateData['resolved_attempts'] = 0;
                    break;
                case $state::RESOLVER_STATE:
                    $state->successAction($this->stateData);
                    $this->deque->pop();
                    $this->resetState();

                    if ($this->isDecisionPoint($state->getName())) {
                        $decision = $this->getDecision();

                        if ($decision === null) {
                            $this->cancel();
                            return;
                        } else {
                            $this->buildDeque($this->deque, $decision);
                        }                        
                    } elseif ($this->deque->isEmpty()) {
                        $this->buildDeque($this->deque, $this->defaultState);
                        return;
                    } elseif ($this->deque->last() === $this->defaultState) {
                        return;
                    }
                    break;
            }

            $this->resolve();
            return;
        // If not resolved
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
                    if ($this->stateData['resolved_attempts'] === 1 &&
                        !$state->hasResolver($state::RESOLVER_CONFIRM)) {
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
            $this->cancel();
            return;
        }
    }

    /**
     * Save state data in the repository provided
     *
     * @return void
     */
    protected function sync(): void
    {
        $this->stateData['current_state'] = $this->deque->last();
        $this->stateData['timestamp'] = $this->getCurrentTimestamp();
        $this->stateData['stack'] = serialize($this->deque);
        $this->repo->saveStateData($this->userId, $this->stateData);
    }

    protected function getCurrentTimestamp(): int
    {
        return (new DateTime('now', new \DateTimeZone('UTC')))->getTimestamp();
    }

    /**
     * Run the state manager
     *
     * @param  mixed $input  Input to resolve
     * @return void
     */
    public function run($input = null): void
    {
        $this->resolve($input);

        $this->sync();
    }

    /**
     * Return current state
     *
     * @return string
     */
    public function getCurrentState(): string
    {
        $stateData = $this->repo->getStateData($this->userId);
        return $stateData['current_state'] ?? $this->defaultState;
    }

    /**
     * Add state to the deque.
     * A state can interrupt all the other states or wait until current
     * states are completed
     *
     * @param string $state
     * @param bool   $interrupt
     */
    public function addState(string $state, bool $interrupt): void
    {
        $stateDeque = $this->buildDeque(new Deque, $state);

        if ($interrupt) {
            $this->deque = $this->deque->merge($stateDeque->toArray());
            $this->resetState();
            $this->resolve();
        } else {
            $this->deque = $stateDeque->merge($this->deque->toArray());
        }

        $this->sync();
    }

    /**
     * Check if a state is expired
     * The date interval for the expiration comes from the config and
     * must be in ISO-8601 format
     *
     * @param  string  $state     Name of the state to check for expiration
     * @param  int     $timestamp Timestamp recorded
     * @return boolean
     */
    protected function isStateExpired(string $state, int $timestamp): bool
    {
        return !empty($this->config[$state]['expiration']) &&
        $this->hasDateExpired($timestamp, $this->config[$state]['expiration']);
    }


    /**
     * Check if a date has passed
     *
     * @param  int     $timestamp Last recorded date for the state
     * @param  string  $expTimespan ISO-8601 duration
     * @return boolean
     */
    protected function hasDateExpired(int $timestamp, string $expTimespan): bool
    {
        $now = new DateTime('now', new DateTimeZone('UTC'));
        $messageDate = new DateTime();
        $messageDate->setTimestamp($timestamp);
        $messageDate->add(new DateInterval($expTimespan));

        return $messageDate < $now;
    }
}
