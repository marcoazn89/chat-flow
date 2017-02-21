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

error_log('running bg job');

sleep(20);

die('poo');

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

$stateManager->run('cancel');

