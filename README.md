# ChatFlow
State manager for chat bots

Requirements
------------
* php 7.1+

When cloning the repo, run these at the root level
-------------------------------------------------------------
	composer install

Test with Docker
-----------------
	cd docker
    docker build -t "chatflow:chatflow" .
	docker run -tid -p 80:80 -v <full path to root of the project>:/var/www/html --name chatflow chatflow:chatflow
	Load this url http://localhost/test/test.php

Get started:
------------
1) Implement the `StateRepositoryInterface`
```php
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
```

2) Create and register states (states of the conversation):
```php
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
```

3) Provider a user, default state, and run it
```php
$stateManager->setUp(1, 'greeting');

$stateManager->run($_GET['input']);
```

Why chat-bots are hard to program?
----------------------------------
Chat-bots can be easy to program when a question is always followed by an answer such as

Bot: What's your name?
User: My name is Foo

However, it can be difficult when your user decides to reply with multiple messages:
```
Bot: What's your name?
User: My
User: name
User: is
User: Foo
```

Harder when a question has multiple sub-questions:
```
Bot: Let's get your address
Bot: Start with your street
User: Bar 123
Bot: Ok, now your City
User: Lalaland
Bot: Finally, zip code please
User: 1010101
Bot: Thank you for providing your address! <- Hard
```

You might think that the bot should always send "Thank you for providing your address!" after the obtaining the zip code, but what if you want to add more sub-questions to the address? Then you will have to move around every time you change the order of questions. If at this point you are thinking you can use some data structure, we are heading in the right direction!

What about when your conversations can have multiple paths? Yes, a lot harder!
```
User: What's the weather?
User: Please find me the address to FooBar
```

Why use this instead of the drag and drop frameworks out there?
-----------------------------------------------------------------
Those are great and you should totally try them. This allows you to have full control over your conversations and create custom actions. Also, this framework is platform agnostic.
