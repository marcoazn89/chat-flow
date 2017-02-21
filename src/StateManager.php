<?php
namespace ChatFlow;

use ChatFlow\Interfaces\StateRepositoryInterface;
use ChatFlow\Interfaces\RandomInputInterface;
use ChatFlow\Interfaces\StateInterface;

class StateManager
{
    const ACTION_MESSAGE = 1;
    const ACTION_MESSAGE_FAIL = 2;
    const ACTION_PROMPT = 3;
    const ACTION_CANCEL = 4;

    protected $repo;
    protected $typeFinder;
    protected $stack;
    protected $statesConfig = [];
    protected $stateData = [];
    protected $registeredStates = [];
    protected $attempts;
    protected $state;
    protected $config;
    protected $isSetup = false;
    protected $userId;
    protected $parentState;

    public function __construct(StateRepositoryInterface $repo, array $config, RandomInputInterface $typeFinder = null)
    {
        $this->repo = $repo;
        $this->config = $config;
        $this->typeFinder = $typeFinder;
        $this->stack = new \SplStack();
    }

    public function setDefaultState($name)
    {
        if (isset($this->config[$name])) {
            $this->defaultState = $name;
        } else {
            throw new \InvalidArgumentException(
                "Unable to register state {$name} because it wasn't found"
            );
        }
    }

    public function setUser($userId)
    {
        $this->userId = $userId;
    }

    public function registerState($name, \Closure $stateCall)
    {
        $this->registeredStates[$name] = $stateCall;
    }

    public function invoke($state)
    {
        if (!isset($this->registeredStates[$state])) {
            throw new \RuntimeException("State {$state} is not registered");
        }

        return $this->registeredStates[$state]();
    }

    protected function buildStack($state, array $temp = [])
    {
        // Fail state isn't found
        if (!isset($this->config[$state])) {
            throw new \InvalidArgumentException("State {$state} not found");
        }

        // Next state is defined & is not array
        if (!empty($this->config[$state]['next_state']) && !is_array($this->config[$state]['next_state'])) {
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

        // Push confirm sub-state when there's a confirmation
        if (!empty($this->config[$state]['confirm_before_run'])) {
            $this->stack->push("{$state}.confirm");
        }

        /*if (!empty($this->config[$state]['has_intro'])) {
            $this->stack->push("{$state}.intro");
        }*/
    }

    protected function setup()
    {
        if (!$this->isSetup) {
            $this->stateData = $this->repo->getStateData($this->userId);
            //die(json_encode($this->stateData));
            // Check if there are any state records for the user
            if (empty($this->stateData)) {
                if (empty($this->defaultState)) {
                    throw new \RuntimeException('A default state must be provided');
                }

                $this->resetState();

                // Build a fresh stack
                $this->buildStack($this->defaultState);
            } else {
                // Get the serialized stack string
                $stackStr = $this->stateData['stack'];
                $this->stack->unserialize($stackStr);
            }

            $this->registerState('confirm', function () {
                return new ConfirmState;
            });

            $this->registerState('prompt', function () {
                return new PromptState;
            });

            $this->registerState('random', function () {
                return new RandomState;
            });

            $this->isSetup = true;
        }
    }

    protected function stateAction($action)
    {
        switch ($action) {
            case self::ACTION_MESSAGE:
                $this->repo->saveStateData([
                    'resolved_attempts' => 0,
                    'prompted'         => 0,
                    'stack'            => $this->stack->serialize()
                ]);

                $this->state->message(0);
                break;
            case self::ACTION_MESSAGE_FAIL:
                $this->repo->saveStateData([
                    'resolved_attempts' => $this->attempts,
                    'prompted'         => 0,
                    'stack'            => $this->stack->serialize()
                ]);

                $this->state->message($this->attempts);
                break;
            case self::ACTION_PROMPT;
                $this->statesConfig['last_prompt_values'] = $this->state->getPromptValues();

                $this->repo->saveStateData([
                    'resolved_attempts' => $attempts,
                    'prompted'         => 1,
                    'stack'            => $this->stack->serialize()
                ]);

                $this->state->promptMessage();
                break;
            case self::ACTION_CANCEL;
                $this->repo->saveStateData([
                    'resolved_attempts' => 0,
                    'prompted'         => 0,
                    'stack'            => null
                ]);

                $this->state->cancelMessage();
                break;
        }
    }

    protected function setState($current)
    {
        ## This block invokes the state!

        // If state is not defined, must be a substate
        if (empty($this->config[$current])) {
            echo "State doesn't exist, must be a confirm/prompt<br>";
            $parts = explode('.', $current);

            // Fail because it misses definitions
            if (count($parts) !== 2) {
                throw new \RuntimeException('State found but has no parts');
            }

            // Invoke state
            echo "Found state, invoking parent state: {$parts[0]}<br>";
            $this->parentState = $this->invoke($parts[0]);

            echo "Invoking confirm/prompt (they are the same)<br>";
            $this->state = $this->invoke('confirm');

            switch ($parts[1]) {
                case 'confirm':
                    echo "Setting confirm<br>";
                    $this->state->setName('confirm');
                    $this->state->setActionValues($this->parentState->getConfirmValues());
                    break;
                case 'prompt':
                    echo "Setting prompt<br>";
                    $this->state->setName('prompt');
                    $this->state->setActionValues($this->parentState->getPromptValues());
                    break;
            }
        // Invoke current state
        } else {
            echo "State is defined, invoking<br>";
            $this->state = $this->invoke($current);
        }
    }

    public function resolve($input = null)
    {
        echo print_r($this->stack) . "<br><br>";
        // Get the current state
        $current = $this->stack->top();

        echo "current: {$current}<br>";

        echo "Resolved Attempts: {$this->stateData['resolved_attempts']}<br>";

        $this->parentState = null;

        ## This block invokes the state!
        $this->setState($current);
        // If state is not defined, must be a substate
        /*if (empty($this->config[$current])) {
            echo "State doesn't exist, must be a confirm/prompt<br>";
            $parts = explode('.', $current);

            // Fail because it misses definitions
            if (count($parts) !== 2) {
                throw new \RuntimeException('State found but has no parts');
            }

            // Invoke state
            echo "Found state, invoking parent state: {$parts[0]}<br>";
            $this->parentState = $this->invoke($parts[0]);

            echo "Invoking confirm/prompt (they are the same)<br>";
            $this->state = $this->invoke('confirm');

            switch ($parts[1]) {
                case 'confirm':
                    echo "Setting confirm<br>";
                    $this->state->setName('confirm');
                    $this->state->setActionValues($this->parentState->getConfirmValues());
                    break;
                case 'prompt':
                    echo "Setting prompt<br>";
                    $this->state->setName('prompt');
                    $this->state->setActionValues($this->parentState->getPromptValues());
                    break;
            }
        // Invoke current state
        } else {
            echo "State is defined, invoking<br>";
            $this->state = $this->invoke($current);
        }*/

        echo "Resolving: {$this->state->getName()}<br>";

        # This block resolves the state

        // Resolve state given current input
        $result = $this->state->resolve($input);

        $name = $this->state->getName();

        // If failed to resolve
        if (!$result) {
            echo "Failed to resolve<br>";

            // Has chatty state
            // Chatty never increases resolved attempts
            if (!empty($this->config[$name]['chatty'])
                && $this->state->chattyAction($input) !== false) {
                echo "End: Chatty<br>";
                return;
            }

            $this->stateData['resolved_attempts']++;
            echo "Resolved attempts is now {$this->stateData['resolved_attempts']}<br>";

            // Is in confirm/prompt state
            /*if ($name === 'confirm' || $name === 'prompt') {
                if ($this->stateData['resolved_attempts'] === 1) {
                    echo "End: confirm prompt<br>";
                    $this->parentState->confirmMessage();
                    return;
                } else {
                    echo "End: cancel message<br>";
                    $this->parentState->cancelMessage();
                    $this->stack = new \SplStack;
                    $this->buildStack($this->defaultState);
                    $this->resetState();
                    return;
                }
            }*/

            // Resolved Attempts surpassed max attempts

            /*if ($name === 'confirm') {
                echo "Poping state {$current}<br>";
                $this->stack->pop();
                $name = $this->parentState->getName();
                $this->state = $this->parentState;
                echo "Override with {$name}<br>";
            }*/

            if ($name === 'prompt' || ($name === 'confirm' && $this->stateData['resolved_attempts'] === 2)) {
                echo "Poping state {$current}<br>";
                if ($name === 'prompt') {
                    $prevName = $name;
                }

                $this->stack->pop();
                $name = $this->parentState->getName();
                $this->state = $this->parentState;
                $this->stateData['resolved_attempts'] = $this->config[$name]['max_attempts'] + 1;
                echo "Override with {$name}<br>";
            }

            if (!empty($this->config[$name]) && $this->stateData['resolved_attempts'] > $this->config[$name]['max_attempts']) {
                echo "Resolved attempts surpassed max attempts {$this->stateData['resolved_attempts']}/{$this->config[$name]['max_attempts']}<br>";
                // Prompt before cancel
                if (empty($prevName) && $this->config[$name]['prompt_before_cancel']) {// && !$this->stateData['prompted']) {
                    /*echo "I dunno why this is happening<br>";
                    if ($this->stateData['resolved_attempts'] < ($this->config[$name]['max_attempts'] + 2)) {
                        echo "End: prompting<br>";
                        $this->state->promptMessage();
                        $this->stack->push("{$name}.prompt");
                        return;
                    } else {
                        echo "Call top of the stack<br>";
                        $this->stack->pop();
                        $this->state = $this->invoke($this->stack->top());
                    }*/
                    echo "End: prompting<br>";
                    $this->state->promptMessage();
                    $this->stack->push("{$name}.prompt");
                    return;
                }

                if ($this->config[$name]['has_cancel_message']) {
                    echo "End: sending cancel message<br>";
                    $this->state->cancelMessage();
                }

                echo "Failed so resetting everything<br>";
                $this->stack = new \SplStack;
                $this->buildStack($this->defaultState);
                $this->resetState();
                return;
            }

            // Is in confirm/prompt state
            if ($name === 'confirm' || $name === 'prompt') {
                if ($this->stateData['resolved_attempts'] === 1) {
                    echo "End: confirm prompt<br>";
                    $this->parentState->confirmMessage();
                    return;
                }
            }

            // Send intro message when defined
            if ($this->config[$name]['has_intro'] && $this->stateData['resolved_attempts'] === 1) {
                echo "Sending intro message<br>";
                $this->state->introMessage();
            }

            // Send message
            echo "Sending state message<br>";
            $this->state->message($this->stateData['resolved_attempts'] - 1);


            if ($this->config[$name]['background'] && !$this->stateData['bg_running']) {
                echo "Bg action<br>";
                $this->state->backgroundAction();
                $this->stateData['bg_running'] = 1;
            }

            return;
        }

        $name = $this->state->getName();
        echo "State name: {$name}<br>";
        if (($name !== 'confirm' && $name !== 'prompt') && $this->config[$name]['has_resolved_message']) {
            echo "Sending resolved message<br>";
            $this->state->resolvedMessage();
        } else if ($name === 'prompt' && $result && !empty($this->config[$this->parentState->getName()]['prompt_continue_message'])) {
            echo "Continue message<br>";
            $this->parentState->promptContinueMessage();

            if ($this->stateData['bg_running']) {
                echo "End: Background job running";
                $this->stateData['resolved_attempts'] = 1;
                $this->stack->pop();
                return;
            }
        }

        echo "Poping state {$current}<br>";
        $this->stack->pop();

        // No more things to do
        if ($this->stack->isEmpty()) {
            echo "End: Stack is empty<br>";
            $this->stack = new \SplStack;
            $this->buildStack($this->defaultState);
            $this->resetState();
            return;
        }

        echo "Recursion<br>";

        echo "Reset state<br>";
        $this->resetState();

        return $this->resolve($result);
    }

    protected function resetState()
    {
        $this->stateData['resolved_attempts'] = 0;
        $this->stateData['prompted'] = 0;
        $this->stateData['bg_running'] = false;
    }

    protected function sync()
    {
        $this->stateData['stack'] = $this->stack->serialize();
        $this->repo->saveStateData($this->stateData);
    }

    public function run($input = null)
    {
        $this->setup();

        /*$this->stack->rewind();

        while ($this->stack->valid()) {
            echo $this->stack->current() . "<br>";
            $this->stack->next();
        }

        die;*/

        $this->resolve($input);

        $this->sync();
    }
}
