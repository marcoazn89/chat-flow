<?php
declare(strict_types=1);

namespace ChatFlow\Interfaces;

use InvalidArgumentException;
use RuntimeException;
use Actionable;

class State extends BasicState
{
    // Config constants
    public const NEXT_STATES = 'NEXT_STATES';
    public const CHILDREN = 'children';
    public const MAX_ATTEMPTS = 'max_attempts';

    // Actions constants
    public const CONFIRM = 'confirm';
    public const CONFIRM_CONTINUE = 'confirm_continue';
    public const INTRO = 'intro';
    public const MESSAGE = 'message';
    public const SUCCESS = 'success';
    public const PROMPT = 'prompt';
    public const PROMPT_CONTINUE = 'prompt_continue';
    public const FAIL = 'fail';

    // Substates
    public const SUBSTATE_CONFIRM = 'substate_confirm';
    public const SUBSTATE_PROMPT = 'substate_prompt';

    public const BACKGROUND = 'background';

    protected $config = [
        self::NEXT_STATES => null,
        self::CHILDREN => [],
        self::MAX_ATTEMPTS => 0,
    ];

    protected $actions = [
        self::CONFIRM => null,
        self::CONFIRM_CONTINUE => null,
        self::INTRO => null,
        self::MESSAGE => null,
        self::SUCCESS => null,
        self::POMPT => null,
        self::PROMPT_CONTINUE => null,
        self::FAIL => null
    ];

    protected $subStates = [
        self::SUBSTATE_CONFIRM => null,
        self::SUBSTATE_PROMPT => null
    ];

    public function __construct(array $config)
    {
        $this->mergeConfig($config);
    }

    /**
     * Merge $config with $config
     *
     * @param  array  $config Configuration for the state
     * @return void
     */
    public function mergeConfig(array $config): void
    {
        // Merge config settings
        if (empty($config['config'])) {
            throw new RuntimeException('Config settings are missing');
        } else {
            $this->config = array_merge($this->config, $config['config']);
        }

        // Merge actions
        if (!empty($config['actions'])) {
            $this->actions = array_merge($this->actions, $config['actions']);
        }

        if (!empty($config['sub_states'])) {
            $this->subStates = array_merge($this->subStates, $config['sub_states']);
        }
    }

    public function getNextStates(): array
    {
        return $this->config[self::NEXT_STATES];
    }

    public function getChildren(): array
    {
        return $this->config[self::NEXT_STATES];
    }

    public function getMaxAttempts(): int
    {
        return $this->config[self::MAX_ATTEMPTS];
    }

    /**
     * Dynamically call the action method of one of the defined settings
     *
     * @param  string $action
     * @param  array  $args
     * @return void
     */
    public function __call(string $action, array $args): void
    {
        if (!isset($this->actions[$class])) {
            throw new RuntimeException("Unknown method {$action}Action()");
        }

        $this->actions[$action]($args[0]);
    }

    public function getConfirm(): Closure
    {
        return $this->subStates['confirm'];
    }

    public function getPrompt(): Closure
    {
        return $this->subStates['prompt'];
    }
}
