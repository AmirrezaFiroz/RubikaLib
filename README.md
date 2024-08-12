# Rubika Library

A library for working with rubika API from PHP source.
**use this client to make bots, games and ...**

# Usage

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
$bot = new Main(9123456789);
$bot->sendMessage('u0FFeu...', 'سلام');
```

# Get Updates From API

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

    $app->proccess(
        new class implements runner
        {
            # when this class declared as update getter on Main, this method get called
            public function onStart(array $mySelf): void
            {
            }

            # All updates will pass to this method (not action updates)
            public function onMessage(array $update, Main $class): void
            {
            }

            # All action updates (Typing, Recording, uploading) will pass to this method
            public function onAction(chatActivities $activitie, string $guid, string $from, Main $class): void
            {
            }
        }
    );

    $app->run();
} catch (Logger $e) {
    echo $e->getMessage() . "\n";
}
```

`update example:`
```json
{
    "chat_updates": [
        {
            "object_guid": "u0HMRZI03...",
            "action": "Edit",
            "chat": {
                "time_string": "172329480300001076130340791385",
                "last_message": {
                    "message_id": "1076130340791385",
                    "type": "Text",
                    "text": "hello dear",
                    "author_object_guid": "u0HMRZI03...",
                    "is_mine": true,
                    "author_title": "\u0634\u0645\u0627",
                    "author_type": "User"
                },
                "last_seen_my_mid": "1076130340791385",
                "last_seen_peer_mid": "0",
                "status": "Active",
                "time": 1723294803,
                "last_message_id": "1076130340791385"
            },
            "updated_parameters": [
                "last_message_id",
                "last_message",
                "status",
                "time_string",
                "last_seen_my_mid",
                "last_seen_peer_mid",
                "time"
            ],
            "timestamp": "1723294804",
            "type": "User"
        }
    ],
    "message_updates": [
        {
            "message_id": "1076130340791385",
            "action": "New",
            "message": {
                "message_id": "1076130340791385",
                "text": "hello dear",
                "time": "1723294803",
                "is_edited": false,
                "type": "Text",
                "author_type": "User",
                "author_object_guid": "u0HMRZI03...",
                "allow_transcription": false
            },
            "updated_parameters": [],
            "timestamp": "1723294804",
            "prev_message_id": "1076130340663385",
            "object_guid": "u0HMRZI03...",
            "type": "User",
            "state": "1723294744"
        }
    ],
    "user_guid": "u0HMRZI03..."
}
```

# [Methods](https://github.com/AmirrezaFiroz/RubikaLib?tab=readme-ov-file#methods)

|                                                                         method                                                                         |                                      describtion                                       |                      example of return data                       |
| :----------------------------------------------------------------------------------------------------------------------------------------------------: | :------------------------------------------------------------------------------------: | :---------------------------------------------------------------: |
|                                                                         logout                                                                         |                              logout and terminate session                              |                [logout.json](examples/logout.json)                |
|                                                                     getMySessions                                                                      |                                  get account sessions                                  |         [getMySessions.json](examples/getMySessions.json)         |
|                                                         terminateSession(string $session_key)                                                          |        terminate sessions which are got by getMySessions() ---> session['key']         |      [terminateSession.json](examples/terminateSession.json)      |
|                                                                       getMySelf                                                                        |                                get account's self info                                 |             [getMySelf.json](examples/getMySelf.json)             |
|                                       sendMessage(string $guid, string $text, string $reply_to_message_id = '')                                        |                               send text message to guid                                |           [sendMessage.json](examples/sendMessage.json)           |
|                                              editMessage(string $guid, string $text, string $message_id)                                               |                               edit message in guid chat                                |           [editMessage.json](examples/editMessage.json)           |
|                                 forwardMessages(string $from_object_guid, array $message_ids, string $to_object_guid)                                  |  forward message to guid ----> array of message-ids: ['11916516161', '85626232', ...]  |       [forwardMessages.json](examples/forwardMessages.json)       |
|                             deleteMessages(string $object_guid, array $message_ids, deleteType $type = deleteType::Local)                              |               delete message in guid ---->  deleteType = {Global, Local}               |        [deleteMessages.json](examples/deleteMessages.json)        |
|                                                sendChatActivity(string $guid, chatActivities $activity)                                                | send an activitie on top of chat ---->  chatActivities = {Typing, Uploading,Recording} |      [sendChatActivity.json](examples/sendChatActivity.json)      |
|                                                              getChats(int $start_id = 0)                                                               |                                   get list of chats                                    |                        not researched yet                         |
|                                                               joinChat(string $enterKey)                                                               |                    join to channel or group using guid or join link                    |              [joinChat.json](examples/joinChat.json)              |
|                                                                leaveChat(string $guid)                                                                 |                           leave channel or group using guid                            |             [leaveChat.json](examples/leaveChat.json)             |
|                                                            deleteGroup(string $group_guid)                                                             |                               delete group for all users                               |                        not researched yet                         |
|                                                                    getMyStickerSets                                                                    |                                   get stickers list                                    |      [getMyStickerSets.json](examples/getMyStickerSets.json)      |
|                                                                       getFolders                                                                       |                                    get folders list                                    |                        not researched yet                         |
|                                                            getChatsUpdates(int $state = 0)                                                             |                      get all chat updates from $state time to now                      |       [getChatsUpdates.json](examples/getChatsUpdates.json)       |
|                                               getMessagesInterval(string $guid, int $middle_message_id)                                                |                                   not researched yet                                   |                        not researched yet                         |
|                                                        getGroupOnlineCount(string $group_guid)                                                         |                              get group online users count                              |   [getGroupOnlineCount.json](examples/getGroupOnlineCount.json)   |
|                                                    seenChats(string $guid, string $last_message_id)                                                    |                                   seen chat messages                                   |             [seenChats.json](examples/seenChats.json)             |
|                                                                      getContacts                                                                       |                                    get contact list                                    |           [getContacts.json](examples/getContacts.json)           |
|                                       addContact(int $phone_number, string $first_name, string $last_name = '')                                        |                                    add new contact                                     |            [addContact.json](examples/addContact.json)            |
|                                                            deleteContact(string $user_guid)                                                            |                                 remove contact by guid                                 |         [deleteContact.json](examples/deleteContact.json)         |
| sendContact(string $guid, string $first_name, int $phone_number, string $contact_guid = '', string $last_name = '', string $reply_to_message_id = '0') |                                send contsct to some one                                |           [sendContact.json](examples/sendContact.json)           |
|                                                               getChatInfo(string $guid)                                                                |                                     get chat info                                      |           [getChatInfo.json](examples/getChatInfo.json)           |
|                                                        getChatInfoByUsername(string $username)                                                         |                   get chat info by username ---> exmample: @someone                    | [getChatInfoByUsername.json](examples/getChatInfoByUsername.json) |
|                                                            changeUsername(string $username)                                                            |                                    change username                                     |        [changeUsername.json](examples/changeUsername.json)        |
|                                     editAccount(string $first_name = '', string $last_name = '', string $bio = '')                                     |                               change account parameters                                |           [editAccount.json](examples/editAccount.json)           |
|                                                                  requestDeleteAccount                                                                  |                          send request to delete this account                           |                        not researched yet                         |
|                                                            getAvatars(string $object_guid)                                                             |                                    get guid avatars                                    |            [getAvatars.json](examples/getAvatars.json)            |
|                                   getGroupAllMembers(string $group_guid, string $search_for = '', int $start_id = 0)                                   |                                 get group members list                                 |    [getGroupAllMembers.json](examples/getGroupAllMembers.json)    |

# Example

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

        public function onMessage(array $update, Main $class): void
        {
            if (isset($update['message_updates'])) {
                foreach ($update['message_updates'] as $update) {
                    if ($update['action'] == 'New') {
                        $guid = $update['object_guid']; # chat guid
                        $message_id = $update['message_id'];
                        $from = $update['message']['author_object_guid']; # message from who?
                        $author_type = $update['message']['author_type']; # Group, Channel, User, Bot, ...
                        $action = $update['action'];
                        $text = $update['message']['text'];

                        echo "new message: $from => $text\n";

                        if ($text == 'شروع' && $author_type == 'User' && $from != $this->me['user_guid']) {
                            $this->setUp($from);
                            $class->sendMessage($guid, "به منوی اصلی خوش آمدید 😎\n\nگزینه مورد نظر را ارسال کنید:\n\nراهنما 📚(5) |  پشتیبانی 🆘(6)", $message_id);
                        }
                    }
                }
            }
        }

        public function onAction(chatActivities $activitie, string $guid, string $from, Main $class): void
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
```

see more about methods result: [here](examples)

# Error Handling

we wrote an Exceptions class called **Logger** that specialy used for library errors.
here is an example of some futures:

```php
try {
    $app = new Main(9123456789);

    // ...

    $app->run();
} catch (Logger $error) {
    echo $error->getMessage() . "\n";

    if ($error->obj != array()) {
        var_dump($error->obj);

        echo PHP_EOL;
    }
}
```

# Library Settings

we maked an settings class that you can set allowed parameters in and pass it to Main class
here is an example:

```php
use RubikaLib\interfaces\MainSettings;

$settings = new MainSettings();
$settings->userAgent = ...;
$settings->auth = ...;

$app = new Main(9123456789, $settings);
```

**Note !** : you can chain setting parameters by this pattern:

```php
// ...

(new MainSettings())->
    setUserAgent('Chrome ...')->
    setAuth('a829skm32knk...');

// ...
```

`parameters:`
| parameter |                                         describtion                                          |
| :-------: | :------------------------------------------------------------------------------------------: |
| userAgent | default useragent for library (it just used in login and will save in session for next uses) |
|   auth    |   default auth for library (it just used in login and will save in session for next uses)    |

# Sessions

our library saves account info like auth-code, private-key and etc on a file with `.rub` format which is encoded. if you need to read this file you must decode it with its phone-hash. the phone-hash is the name file.

here is example for reading session data:

```php
use RubikaLib\Cryption;

$file_path = "lib/f4f863d***.rub";
$hash = str_replace(['lib/', '.rub'], '', $file_path);
$session_data = json_decode(Cryption::decode(file_get_contents("lib/$hash.rub"), $hash), true);
var_dump($session_data);

```

or you can find phone-hash by library:

```php
use RubikaLib\Session;
use RubikaLib\Cryption;

$hash = Session::generatePhoneHash(989123456789); # 98 must be entered before phone number
$session_data = json_decode(Cryption::decode(file_get_contents("lib/$hash.rub"), $hash), true);
var_dump($session_data);

```
