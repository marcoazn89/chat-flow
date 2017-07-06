<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
require '../vendor/autoload.php';

use ChatFlow\{StateManager, State, StateRepositoryInterface};


class Repo implements StateRepositoryInterface
{
    public function getStateData(int $userId): ?array
    {
        $data = file_get_contents('db.json');

        if (empty($data)) {
            return null;
        }

        $state = json_decode($data, true);

        return $state;
    }

    public function saveStateData(array $data): void
    {
        file_put_contents('db.json', json_encode($data));
    }
}

$stateManager = new StateManager(new Repo());

$stateManager->registerState('greeting', function () use ($stateManager) {
    return new State([
        'actions' => [
            State::CONFIRM => function ($data) {
                echo "Hey, are you sure you wanna try this?";
            },
            State::INTRO => function ($data) {
                echo "I'm here to help you with anything. My name is Roadbot<br>";
            },
            State::MESSAGE => function ($data) {
                $messages = ['Hey what do you want to do?', "Umm, I'm not sure what that means..", 'One more time?'];

                echo "{$messages[$data['resolved_attempts'] - 1]}<br>";
            },
            State::SUCCESS => function ($data) {
                echo "Cool!<br>";
            },
            State::FAIL => function ($data) {
                echo "Okay, let me know if you need me<br>";
            },
            State::CONTINUE => function ($data) {
                echo "Okay lets do this!<br>";
            }
        ],
        'resolvers' => [
            State::RESOLVER_CONFIRM => function ($input) {
                if (in_array($input, ['yes', 'yeah', 'yep', 'yup', 'of course'])) {
                    return true;
                } else {
                    return false;
                }
            },
            State::RESOLVER_STATE => function ($input) use ($stateManager) {
                switch ((string)$input) {
                    case 'park_intro':
                    case 'submit':
                    case 'leaving':
                        $stateManager->setDecision($input);
                        return true;
                    default:
                        return false;
                }
            }
        ]
    ]);
}, ['max_attempts' => 3, 'next_state' => ['park_intro', 'submit']]);

$stateManager->registerState('park_intro', function () use ($stateManager) {
    return new State([
        'actions' => [
            State::CONFIRM => function ($data) {
                echo "Ready to park?";
            },
            State::INTRO => function ($data) {
                echo "I need few things from you first<br>";
            }
        ],
        'resolvers' => [
            State::RESOLVER_CONFIRM => function ($input) {
                if (in_array($input, ['yes', 'yeah', 'yep', 'yup', 'of course'])) {
                    return true;
                } else {
                    return false;
                }
            }
        ]
    ]);
}, ['max_attempts' => 4, 'expiration' => 'PT30S', 'next_state' => 'park_outro', 'children' => ['location_intro', 'pic']]);

$stateManager->registerState('park_outro', function () use ($stateManager) {
    return new State([
        'actions' => [
            State::SUCCESS => function ($data) {
                echo "Thank you for your cooperation! (park_outro)<br>";
            }
        ],
        'resolvers' => [
        ]
    ]);
}, ['max_attempts' => 4]);

$stateManager->registerState('submit', function () use ($stateManager) {
    return new State([
        'actions' => [
            State::CONFIRM => function ($data) {
                echo "Let's submit!?";
            },
            State::MESSAGE => function ($data) {
                $messages = ['submit1','submit2'];

                echo "{$messages[$data['resolved_attempts'] - 1]}<br>";
            },
            State::SUCCESS => function ($data) {
                echo "Yay thanks!";
            },
            State::FAIL => function ($data) {
                echo "Okay nvm<br>";
            }
        ],
        'resolvers' => [
            State::RESOLVER_CONFIRM => function ($input) {
                if (in_array($input, ['yes', 'yeah', 'yep', 'yup', 'of course'], true)) {
                    return true;
                } else {
                    return false;
                }
            },
            State::RESOLVER_STATE => function ($input) use ($stateManager) {
                return $input === 'submit';
            }
        ]
    ]);
}, ['max_attempts' => 2]);

$stateManager->registerState('location_intro', function () use ($stateManager) {
    return new State([
        'actions' => [
            State::CONFIRM => function ($data) {
                echo "Ready to provide your location?";
            },
            State::INTRO => function ($data) {
                echo "Your location will help a lot of people<br>";
            },
            State::FAIL => function ($data) {
                echo "Hit me up if you need help parking<br>";
            }
        ],
        'resolvers' => [
            State::RESOLVER_CONFIRM => function ($input) {
                return in_array($input, ['yes', 'yeah', 'yep', 'yup', 'of course']);
            }
        ]
    ]);
}, ['max_attempts' => 4, 'next_state' => 'location_outro', 'children' => ['point_a', 'point_b']]);

$stateManager->registerState('location_outro', function () use ($stateManager) {
    return new State([
        'actions' => [
            State::SUCCESS => function ($data) {
                echo "Awesome you got a ton of brownie points for that one!<br>";
            }
        ],
        'resolvers' => [
        ]
    ]);
}, ['max_attempts' => 4]);

$location = function () {
    return new State([
        'actions' => [
            State::INTRO => function ($data) {
                echo "Lets get you started with the first point<br>";
            },
            State::MESSAGE => function ($data) {
                $messages = ['Please share the starting point of the parking sign', 'Press the location button and place it on the map', 'Yeah that did not work, can you try sharing it again? Make sure your GPS is on', "Maybe you want to read this link http://fb.com to see how you may share your location"];

                echo "{$messages[$data['resolved_attempts'] - 1]}<br>";
            },
            State::PROMPT => function ($data) {
                echo "I'm guessing you don't want to park..?";
            },
            State::SUCCESS => function ($data) {
                echo "Thanks :)<br>";
            },
            State::FAIL => function ($data) {
                echo "Hit me up if you need help parking<br>";
            }
        ],
        'resolvers' => [
            State::RESOLVER_PROMPT => function ($input) {
                return in_array($input, ['no', 'try again']);
            },
            State::RESOLVER_STATE => function ($input) {
                return $input === "location";
            },
            State::RESOLVER_CHATTY => function ($input) {
                switch ((string)$input) {
                    case 'thanks':
                    case 'cool':
                    case 'awesome':
                        echo ":)<br>";
                        return true;
                    default:
                        return false;
                }
            }
        ]
    ]);
};

$stateManager->registerState('point_a', $location, ['max_attempts' => 4]);

$stateManager->registerState('point_b', $location, ['max_attempts' => 4]);

$stateManager->registerState('pic', function () {
    return new State([
        'actions' => [
            State::CONFIRM => function ($data) {
                echo "You need to be able to take a picture with your phone, can you do that?";
            },
            STATE::INTRO => function ($data) {
                echo "There must be a parking sign around there also..<br>";
            },
            State::MESSAGE => function ($data) {
                $messages = ['Can you share it with me by taking a pic?', 'Um, that did not work. Please point your camera at it and take a pic', 'Yeah maybe its blurry..try again?'];

                echo "{$messages[$data['resolved_attempts'] - 1]}<br>";
            },
            State::PROMPT => function ($data) {
                echo "you sure you wanna give up?";
            },
            State::FAIL => function ($data) {
                echo "Boy that pic was a challenge. Nevermind<br>";
            }
        ],
        'resolvers' => [
            State::RESOLVER_CONFIRM => function ($input) {
                return in_array($input, ['yes', 'yeah', 'yep', 'yup', 'of course']);
            },
            State::RESOLVER_STATE => function ($input) {
                return $input === 'pic';
            }
        ]
    ]);
}, ['max_attempts' => 3]);

$stateManager->registerState('find_match', function () {
    return new State([
        'actions' => [
        ],
        'resolvers' => [
            State::RESOLVER_STATE => function ($input) {
                return $input === 'pic';
            }
        ]
    ]);
}, ['max_attempts' => 1]);

$stateManager->setUp(1, 'greeting');

$stateManager->run($_GET['input']);
