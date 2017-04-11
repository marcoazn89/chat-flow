<?php
require '../vendor/autoload.php';

use \ChatFlow\StateManager;
use \ChatFlow\Interfaces\StateInterface;
use \ChatFlow\Interfaces\MessageInterface;
use \ChatFlow\Interfaces\ConfirmInterface;
use \ChatFlow\Interfaces\LastPromptInterface;
use \ChatFlow\Interfaces\IntroInterface;
use \ChatFlow\Interfaces\StateRepositoryInterface;
use \ChatFlow\Interfaces\BackgroundInterface;
use \ChatFlow\Interfaces\PromptContinueInterface;
use \ChatFlow\Interfaces\DecisionPointInterface;

class GreetingState implements StateInterface, MessageInterface, ConfirmInterface, IntroInterface, DecisionPointInterface
{
    protected $decision;

    public function getName()
    {
        return 'greeting';
    }

    public function introMessage()
    {
        echo "I'm here to help you with anything. My name is Roadbot<br>";
    }

    public function message($attempt)
    {
        $messages = ['Hey what do you want to do?', "Umm, I'm not sure what that means..", 'One more time?'];

        echo "{$messages[$attempt]}<br>";
    }

    public function cancelMessage()
    {
        echo "Okay, let me know if you need me<br>";
    }

    public function resolvedMessage()
    {
        echo "Cool!<br>";
    }

    public function confirmMessage()
    {
        echo "Hey, are you sure you wanna try this?";
    }

    public function getConfirmValues()
    {
        return ['yes', 'yeah', 'yep', 'yup', 'of course'];
    }

    public function setDecision($value)
    {
        $this->decision = $value;
    }

    public function getDecision()
    {
        return $this->decision;
    }

    public function resolve($input)
    {
        switch ((string)$input) {
            case 'park':
            case 'submit':
            case 'leaving':
                $this->setDecision($input);
                return true;
            default:
                return false;
        }
    }
}

class ParkState implements StateInterface, MessageInterface, ConfirmInterface, IntroInterface, LastPromptInterface
{
    public function getName()
    {
        return 'park';
    }

    public function introMessage()
    {
        echo "I need few things from you first<br>";
    }

    public function message($attempt)
    {
        $messages = ['parking attempt 1', 'parking attempt 2', 'parking attempt 3'];

        echo $messages[$attempt];
    }

    public function cancelMessage()
    {
        echo "Hit me up if you need help parking<br>";
    }

    public function resolvedMessage()
    {
        echo "Thank you for your cooperation!<br>";
    }

    public function promptMessage()
    {
        echo "I'm guessing you don't want to park..is that correct?";
    }

    public function getPromptValues()
    {
        return ['yes', 'yeah', 'yep', 'yup', 'of course'];
    }

    public function confirmMessage()
    {
        echo "Ready to park?";
    }

    public function getConfirmValues()
    {
        return ['yes', 'yeah', 'yep', 'yup', 'of course'];
    }

    public function resolve($input)
    {
        return true;
    }
}

class LocationState implements StateInterface, MessageInterface, ConfirmInterface, IntroInterface, LastPromptInterface
{
    public function getName()
    {
        return 'location';
    }

    public function introMessage()
    {
        echo "Your location will help a lot of people<br>";
    }

    public function message($attempt)
    {
        $messages = ['parking attempt 1', 'parking attempt 2', 'parking attempt 3'];

        echo $messages[$attempt];
    }

    public function cancelMessage()
    {
        echo "Hit me up if you need help parking<br>";
    }

    public function resolvedMessage()
    {
        echo "Awesome you got a ton of brownie points for that one!<br>";
    }

    public function promptMessage()
    {
        echo "I'm guessing you don't want to park..is that correct?";
    }

    public function getPromptValues()
    {
        return ['yes', 'yeah', 'yep', 'yup', 'of course'];
    }

    public function confirmMessage()
    {
        echo "Ready to park?";
    }

    public function getConfirmValues()
    {
        return ['yes', 'yeah', 'yep', 'yup', 'of course'];
    }

    public function resolve($input)
    {
        return true;
    }
}

class PointAState implements StateInterface, MessageInterface, IntroInterface, LastPromptInterface
{
    public function getName()
    {
        return 'pointA';
    }

    public function introMessage()
    {
        echo "Lets get you started with the first point<br>";
    }

    public function message($attempt)
    {
        $messages = ['Please share the starting point of the parking sign', 'Press the location button and place it on the map', 'Yeah that did not work, can you try sharing it again? Make sure your GPS is on', "Maybe you want to read this link http://fb.com to see how you may share your location"];

        echo $messages[$attempt];
    }

    public function promptMessage()
    {
        echo "I'm guessing you don't want to park..?";
    }

    public function getPromptValues()
    {
        return ['I want to park', 'let me try again'];
    }

    public function cancelMessage()
    {
        echo "Hit me up if you need help parking<br>";
    }

    public function resolvedMessage()
    {
        echo "Thanks :)<br>";
    }

    public function chattyAction($input)
    {
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

    public function resolve($input)
    {
        if ($input === "location") {
            return true;
        }

        return false;
    }
}

class PointBState extends PointAState implements StateInterface, MessageInterface, IntroInterface, LastPromptInterface {
    public function getName()
    {
        return 'pointB';
    }
}

class PicState implements StateInterface, MessageInterface, ConfirmInterface, IntroInterface, LastPromptInterface
{
    public function getName()
    {
        return 'pic';
    }

    public function introMessage()
    {
        echo "There must be a parking sign around there also..<br>";
    }

    public function message($attempt)
    {
        $messages = ['Can you share it with me by taking a pic?', 'Um, that did not work. Please point your camera at it and take a pic', 'Yeah maybe its blurry..try again?'];

        echo $messages[$attempt];
    }

    public function cancelMessage()
    {
        echo "Boy that pic was a challenge. Nevermind<br>";
    }

    public function resolvedMessage()
    {
        echo "Woohooo this is all I needed!<br>";
    }

    public function promptMessage()
    {
        echo "you sure you wanna give up?";
    }

    public function getPromptValues()
    {
        return ['no', 'try agian'];
    }

    public function confirmMessage()
    {
        echo "You need to be able to take a picture with your phone, can you do that?";
    }

    public function getConfirmValues()
    {
        return ['yes', 'yeah', 'yep', 'yup', 'of course'];
    }

    public function resolve($input)
    {
        if ($input === "pic") {
            return true;
        }

        return false;
    }
}

class FindMatchState implements StateInterface, MessageInterface, ConfirmInterface, IntroInterface, LastPromptInterface, BackgroundInterface
{
    public function getName()
    {
        return 'find_match';
    }

    public function introMessage()
    {
        echo "I'm also great at finding spots<br>";
    }

    public function message($attempt)
    {
        $messages = ['Okay I will look for a driver', "Hey I'm still looking don't think I forgot :)"];

        echo $messages[$attempt];
    }

    public function cancelMessage()
    {
        echo "Hit me up if you need help parking<br>";
    }

    public function resolvedMessage()
    {
        echo "Awesome lmore points for you!<br>";
    }

    public function backgroundAction()
    {
        return shell_exec("php bg.php > /dev/null 2>/dev/null &");
    }

    public function promptContinueMessage()
    {
        echo "Hang on..I'll find sombody...<br>";
    }

    public function confirmMessage()
    {
        echo "Do you want me to help you find a driver leaving their spot right now?";
    }

    public function promptMessage()
    {
        echo "I can stop looking for a spot if you want to, do you want me to stop?";
    }

    public function getPromptValues()
    {
        return ['no', 'try again'];
    }

    public function getConfirmValues()
    {
        return ['yes', 'yeah', 'yep', 'yup', 'of course'];
    }

    public function chattyAction($input)
    {
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

    public function resolve($input)
    {
        if ($input === 'cancel') {
            return true;
        }

        return false;
    }
}

class Repo implements StateRepositoryInterface
{
    public function getStateData($userId)
    {
        $data = file_get_contents('db.json');

        if (empty($data)) {
            return null;
        }

        $state = json_decode($data, true);

        return $state;
    }

    public function saveStateData(array $data)
    {
        file_put_contents('db.json', json_encode($data));
    }
}


$states = [
    'greeting' => [
        'next_state' => ['park', 'submit'],
        'max_attempts' => 3,
        'prompt_before_cancel' => false,
        'has_cancel_message' => true,
        'has_intro' => true,
        'confirm_before_run' => true,
        'has_resolved_message' => true,
        'background' => false,
        'decision_point' => true,
        'children' => []
    ],
    'park' => [
        'next_state' => 'find_match',
        'max_attempts' => 4,
        'prompt_before_cancel' => true,
        'has_intro' => true,
        'has_cancel_message' => true,
        'confirm_before_run' => true,
        'has_resolved_message' => true,
        'background' => false,
        'children' => ['location', 'pic']
    ],
    'location' => [
        'max_attempts' => 4,
        'prompt_before_cancel' => true,
        'has_intro' => true,
        'has_cancel_message' => true,
        'confirm_before_run' => false,
        'has_resolved_message' => true,
        'background' => false,
        'children' => ['pointA', 'pointB']
    ],
    'pointA' => [
        'max_attempts' => 4,
        'prompt_before_cancel' => true,
        'has_intro' => false,
        'has_cancel_message' => true,
        'confirm_before_run' => false,
        'has_resolved_message' => true,
        'background' => false,
        'chatty' => true,
        'children' => []
    ],
    'pointB' => [
        'max_attempts' => 4,
        'prompt_before_cancel' => true,
        'has_intro' => false,
        'has_cancel_message' => true,
        'confirm_before_run' => false,
        'has_resolved_message' => true,
        'background' => false,
        'children' => []
    ],
    'pic' => [
        'max_attempts' => 3,
        'prompt_before_cancel' => true,
        'has_intro' => true,
        'has_cancel_message' => true,
        'confirm_before_run' => true,
        'has_resolved_message' => true,
        'background' => false,
        'children' => []
    ],
    'find_match' => [
        'max_attempts' => 1,
        'prompt_before_cancel' => true,
        'prompt_continue_message' => true,
        'has_intro' => false,
        'has_cancel_message' => false,
        'confirm_before_run' => true,
        'has_resolved_message' => false,
        'background' => true,        'enable_random' => true,
        'chatty' => true,
        'children' => []
    ]
];

$stateManager = new StateManager(new Repo, $states);

$stateManager->setUser(1);

$stateManager->registerState('greeting', function () {
    return new GreetingState;
});

$stateManager->registerState('park', function () {
    return new ParkState;
});

$stateManager->registerState('location', function () {
    return new LocationState;
});

$stateManager->registerState('pointA', function () {
    return new PointAState;
});

$stateManager->registerState('pointB', function () {
    return new PointAState;
});

$stateManager->registerState('pic', function () {
    return new PicState;
});

$stateManager->registerState('find_match', function () {
    return new FindMatchState;
});

$stateManager->setDefaultState('greeting');

$stateManager->run($_GET['input']);
