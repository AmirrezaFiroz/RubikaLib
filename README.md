# rubika

A library for working with rubika API from PHP source.
**use this client to make bots, games and ...**

# usage

```bash
composer require rubikalib/rubikalib
```

1. create a new php file in current directory

2. require vendor and **Main** class in file
```php
require_once __DIR__ . '/vendor/autoload.php';

use RubikaLib\Main;
```

3. now you can send messages
```php
$bot = new Bot(9123456789);
$bot->sendMessage('u0FFeu...', 'سلام');
```

# get message updates

for getting updates, you must create new class with a name and call it
```php
require_once __DIR__ . '/vendor/autoload.php';

use RubikaLib\enums\chatActivities;
use RubikaLib\interfaces\runner;
use RubikaLib\{
    Logger,
    Main
};

try {
    $app = new Main(9123456789);

    $app->getChatsUpdates();

    $app->proccess(
        new class implements runner
        {
            public function onStart(array $mySelf): void # when this class declared as update geeter on Main, this method get called
            {
            }

            public function onMessage(array $update, Main $obj): void # All updates will pass to this method (not action updates)
            {
            }

            public function onAction(chatActivities $activitie, string $guid, string $from, Main $obj): void # All action updates (Typing, Recording, uploading) will pass to this method
            {
            }
        }
    );

    $app->run();
} catch (Logger $e) {
    echo $e->getMessage() . "\n";
}
```

# an example

here as base of one bot you can run

```php
declare(strict_types=1);
require_once 'vendor/autoload.php';

use RubikaLib\enums\chatActivities;
use RubikaLib\interfaces\runner;
use RubikaLib\{
    Logger,
    Main
};

try {
    $app = new Main(9123456789);

    $app->proccess(
        new class implements runner
        {
            private ?array $me;
            private array $userData = [];

            public function onStart(array $mySelf): void
            {
                $this->me = $mySelf;
                echo "bot is running...\n";
            }

            public function onMessage(array $update, Main $obj): void
            {
                if (isset($update['message_updates'])) {
                    foreach ($update['message_updates'] as $update) {
                        if ($update['action'] == 'New') {
                            $guid = $update['object_guid'];
                            $message_id = $update['message_id'];
                            $action = $update['action'];
                            $from = $update['message']['author_object_guid'];
                            $author_type = $update['message']['author_type'];
                            $text = $update['message']['text'];

                            echo "new message: $from => $text\n";

                            if ($text == 'شروع' && $author_type == 'User' && $from != $this->me['user_guid']) {
                                $this->setUp($from);
                                $obj->sendMessage($guid, "به منوی اصلی خوش آمدید 😎\n\nگزینه مورد نظر را ارسال کنید:\n\nراهنما 📚(5) |  پشتیبانی 🆘(6)", $message_id);
                            }
                        }
                    }
                }
            }

            public function onAction(chatActivities $activitie, string $guid, string $from, Main $obj): void
            {
            }

            private function setUp(string $guid)
            {
                $this->userData = [
                    'step' => 'none'
                ];

                file_put_contents("users/$guid.json", json_encode($this->userData));
            }
        }
    );

    $app->run();
} catch (Logger $e) {
    echo $e->getMessage() . "\n";
}
```
