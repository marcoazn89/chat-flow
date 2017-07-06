<?php
declare(strict_types=1);

namespace ChatFlow;

use InvalidArgumentException;
use RuntimeException;

class State
{
    // Actions constants
    public const CONFIRM = 'confirm';
    public const CONTINUE = 'continue';
    public const INTRO = 'intro';
    public const MESSAGE = 'message';
    public const SUCCESS = 'success';
    public const PROMPT = 'prompt';
    public const FAIL = 'fail';

    // resolvers
    public const RESOLVER_CONFIRM = 'confirm';
    public const RESOLVER_STATE = 'state';
    public const RESOLVER_PROMPT = 'prompt';
    public const RESOLVER_CHATTY = 'chatty';

    // Default number of max attempts a state will resolve for
    protected $maxAttempts = 1;

    // Actions that the state manager will triggered if defined
    protected $actions = [
        self::CONFIRM => null,
        self::CONTINUE => null,
        self::INTRO => null,
        self::MESSAGE => null,
        self::SUCCESS => null,
        self::PROMPT => null,
        self::FAIL => null
    ];

    // State resolvers
    protected $resolvers = [
        self::RESOLVER_CONFIRM => null,
        self::RESOLVER_STATE => null,
        self::RESOLVER_PROMPT => null,
        self::RESOLVER_CHATTY => null
    ];

    // Name of the state
    protected $name;

    // Current resolver
    protected $currentResolver = '';

    public function __construct(array $config)
    {
        $this->mergeConfig($config);
    }

    /**
     * Merge $configs
     *
     * @param  array  $config Configuration for the state
     * @throws InvalidArgumentException
     * @return void
     */
    public function mergeConfig(array $config): void
    {
        // Merge config settings
        if (!empty($config['max_attempts'])) {
            if ($config['maxAttempts'] < 1) {
                throw new InvalidArgumentException("'max_attempts' can only be greater or equal to 1");
            } else {
                $this->maxAttempts = $config['maxAttempts'];
            }
        }

        // Merge actions
        if (!empty($config['actions'])) {
            $this->actions = array_merge($this->actions, $config['actions']);
        }

        // Merge resolvers
        if (!empty($config['resolvers'])) {
            $this->resolvers = array_merge($this->resolvers, $config['resolvers']);
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName($name): void
    {
        $this->name = $name;
    }

    public function getMaxAttempts(): int
    {
        return $this->config[self::MAX_ATTEMPTS];
    }

    public function setMaxAttempts($attempts): void
    {
        $this->maxAttempts = $attempts;
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
        $action = substr($action, 0, strlen($action) - 6);

        if (isset($this->actions[$action])) {
            $this->actions[$action]($args[0]);
        }
    }

    public function hasResolver(string $resolver): bool
    {
        return !empty($this->resolvers[$resolver]);
    }

    public function resolve($input, string $resolver = ''): bool
    {
        $resolver = $resolver !== '' ? $resolver : $this->currentResolver;

        if (empty($this->resolvers[$resolver]) && $resolver === self::RESOLVER_STATE) {
            return true;
        } else {
            return $this->resolvers[$resolver]($input);
        }
    }

    public function hasAction(string $action): bool
    {
        return !empty($this->actions[$action]);
    }

    public function setResolver(string $resolver): void
    {
        $this->currentResolver = $resolver;
    }

    public function getResolver(): string
    {
        return $this->currentResolver;
    }

    public function isAttemptAllowed(int $attempt)
    {
        return $attempt < $this->maxAttempts;
    }
}
