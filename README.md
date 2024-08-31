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

use RubikaLib\enums\ChatActivities;
use RubikaLib\interfaces\Runner;
use RubikaLib\{
    Failure,
    Main
};

try {
    $app = new Main(9123456789);

    $app->proccess(
        new class implements Runner
        {
            # when this class declared as update getter on Main, this method get called
            public function onStart(array $mySelf): void
            {
            }

            # All updates will pass to this method (not action updates)
            public function onMessage(array $updates, Main $class): void
            {
            }

            # All action updates (Typing, Recording, uploading) will pass to this method
            public function onAction(ChatActivities $activitie, string $guid, string $from, Main $class): void
            {
            }
        }
    );

    $app->RunAndLoop();
} catch (Failure $e) {
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

|                                                                                                              method                                                                                                              |                                                        describtion                                                        |                              example of return data                               |
| :------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------: | :-----------------------------------------------------------------------------------------------------------------------: | :-------------------------------------------------------------------------------: |
|                                                                                                              logout                                                                                                              |                                               logout and terminate session                                                |                        [logout.json](examples/logout.json)                        |
|                                                                                                          getMySessions                                                                                                           |                                                   get account sessions                                                    |                 [getMySessions.json](examples/getMySessions.json)                 |
|                                                                                              TerminateSession(string $session_key)                                                                                               |                          terminate sessions which are got by getMySessions() ---> session['key']                          |              [terminateSession.json](examples/terminateSession.json)              |
|                                                                                                            getMySelf                                                                                                             |                                                  get account's self info                                                  |                     [getMySelf.json](examples/getMySelf.json)                     |
|                                                                                            ChangeUsername(string $newUserName): array                                                                                            |                                                     set new username                                                      |                [ChangeUsername.json](examples/ChangeUsername.json)                |
|                                                                              sendMessage(string $guid, string $text, int $reply_to_message_id = 0)                                                                               |                                                 send text message to guid                                                 |                   [sendMessage.json](examples/sendMessage.json)                   |
|                                                                                  EditMessage(string $guid, string $NewText, string $message_id)                                                                                  |                                                 edit message in guid chat                                                 |                   [EditMessage.json](examples/EditMessage.json)                   |
|                                                                      forwardMessages(string $from_object_guid, array $message_ids, string $to_object_guid)                                                                       |                   forward message to guid ----> array of message-ids: ['11916516161', '85626232', ...]                    |               [forwardMessages.json](examples/forwardMessages.json)               |
|                                                                  deleteMessages(string $object_guid, array $message_ids, DeleteType $type = DeleteType::Local)                                                                   |                                delete message in guid ---->  DeleteType = {Global, Local}                                 |                [deleteMessages.json](examples/deleteMessages.json)                |
|                                                                                     sendChatActivity(string $guid, ChatActivities $activity)                                                                                     |                  send an activitie on top of chat ---->  ChatActivities = {Typing, Uploading,Recording}                   |              [sendChatActivity.json](examples/sendChatActivity.json)              |
|                                                                                                   getChats(int $start_id = 0)                                                                                                    |                                                     get list of chats                                                     |                                not researched yet                                 |
|                                                                                                    joinChat(string $enterKey)                                                                                                    |                                     join to channel or group using guid or join link                                      |                      [joinChat.json](examples/joinChat.json)                      |
|                                                                                                     leaveChat(string $guid)                                                                                                      |                                             leave channel or group using guid                                             |                     [leaveChat.json](examples/leaveChat.json)                     |
|                                                                                                 deleteGroup(string $group_guid)                                                                                                  |                                                delete group for all users                                                 |                                not researched yet                                 |
|                                                                                                         getMyStickerSets                                                                                                         |                                                     get stickers list                                                     |              [getMyStickerSets.json](examples/getMyStickerSets.json)              |
|                                                                                                            getFolders                                                                                                            |                                                     get folders list                                                      |                                not researched yet                                 |
|                                                                                                 getChatsUpdates(int $state = 0)                                                                                                  |                                       get all chat updates from $state time to now                                        |               [getChatsUpdates.json](examples/getChatsUpdates.json)               |
|                                                                                    getMessagesInterval(string $guid, int $middle_message_id)                                                                                     |                                                    not researched yet                                                     |                                not researched yet                                 |
|                                                                                             getGroupOnlineCount(string $group_guid)                                                                                              |                                               get group online users count                                                |           [getGroupOnlineCount.json](examples/getGroupOnlineCount.json)           |
|                                                                                         seenChats(string $guid, string $last_message_id)                                                                                         |                                                    seen chat messages                                                     |                     [seenChats.json](examples/seenChats.json)                     |
|                                                                                      seenChatsArray(array $guids, array $last_message_ids)                                                                                       |                seen chats -> seenChatsArray(['u0UBF88...', 'g0UKLD66...'], ['91729830180', '9798103900']);                |                     [seenChats.json](examples/seenChats.json)                     |
|                                                                                                           getContacts                                                                                                            |                                                     get contact list                                                      |                   [getContacts.json](examples/getContacts.json)                   |
|                                                                            addContact(int $phone_number, string $first_name, string $last_name = '')                                                                             |                                                      add new contact                                                      |                    [addContact.json](examples/addContact.json)                    |
|                                                                                                 deleteContact(string $user_guid)                                                                                                 |                                                  remove contact by guid                                                   |                 [deleteContact.json](examples/deleteContact.json)                 |
|                                      sendContact(string $guid, string $first_name, int $phone_number, string $contact_guid = '', string $last_name = '', string $reply_to_message_id = '0')                                      |                                                 send contsct to some one                                                  |                   [sendContact.json](examples/sendContact.json)                   |
|                                                                                                    getChatInfo(string $guid)                                                                                                     |                                                       get chat info                                                       |                   [getChatInfo.json](examples/getChatInfo.json)                   |
|                                                                                             getChatInfoByUsername(string $username)                                                                                              |                                     get chat info by username ---> exmample: @someone                                     |         [getChatInfoByUsername.json](examples/getChatInfoByUsername.json)         |
|                                                                          EditProfile(string $first_name = '', string $last_name = '', string $bio = '')                                                                          |                                                 change account parameters                                                 |                   [EditProfile.json](examples/EditProfile.json)                   |
|                                                                                                       RequestDeleteAccount                                                                                                       |                                            send request to delete this account                                            |                                not researched yet                                 |
|                                                                                                 getAvatars(string $object_guid)                                                                                                  |                                                     get guid avatars                                                      |                    [getAvatars.json](examples/getAvatars.json)                    |
|                                                                        getGroupAllMembers(string $group_guid, string $search_for = '', int $start_id = 0)                                                                        |                                                  get group members list                                                   |            [getGroupAllMembers.json](examples/getGroupAllMembers.json)            |
|                                                                          DownloadFile(string $access_hash_rec, string $file_id, string $path, int $DC)                                                                           |                                                      download a file                                                      |                 `true` or `false` (depended on API file finding)                  |
|                                                                                            UploadNewProfileAvatar(string $file_path)                                                                                             |                                            upload new account profile picture                                             |        [UploadNewProfileAvatar.json](examples/UploadNewProfileAvatar.json)        |
|                                                                                   uploadNewGroupAvatar(string $group_guid, string $file_path)                                                                                    |                                            upload new account profile picture                                             |          [uploadNewGroupAvatar.json](examples/uploadNewGroupAvatar.json)          |
|                                                                                                DeleteMyAvatar(string $avatar_id)                                                                                                 |                                              delete account profile picture                                               |                [DeleteMyAvatar.json](examples/DeleteMyAvatar.json)                |
|                                                                                            createGroup(string $title, array $members)                                                                                            |                                                     create new group                                                      |                   [createGroup.json](examples/createGroup.json)                   |
|                                                                                       addGroupMembers(string $group_guid, array $members)                                                                                        |                                                     add group members                                                     |               [addGroupMembers.json](examples/addGroupMembers.json)               |
|                                                                                     deleteGroupAvatar(string $group_guid, string $avatar_id)                                                                                     |                                               delete group profile picture                                                |             [deleteGroupAvatar.json](examples/deleteGroupAvatar.json)             |
|                                                            sendPhoto(string $guid, string $path, bool $isLink = false, string $caption = '', strXing $thumbnail = '')                                                            |                                                    send photo to guid                                                     |                     [sendPhoto.json](examples/sendPhoto.json)                     |
|                                                                       sendDocument(string $guid, string $path, bool $isLink = false, string $caption = '')                                                                       |                                                     send file to guid                                                     |                  [sendDocument.json](examples/sendDocument.json)                  |
|                                                                addMessageReaction(string $guid, string $message_id, ReactionsEmoji or ReactionsString $reaction)                                                                 |                                                  add reaction to message                                                  |            [addMessageReaction.json](examples/addMessageReaction.json)            |
|                                                                                     removeMessageReaction(string $guid, string $message_id)                                                                                      |                                               remove reaction from message                                                |         [removeMessageReaction.json](examples/removeMessageReaction.json)         |
|                                                               setGroupDefaultAccess(string $group_guid, GroupDefaultAccesses $settings = new GroupDefaultAccesses)                                                               |                                            set group members default accesses                                             |         [setGroupDefaultAccess.json](examples/setGroupDefaultAccess.json)         |
|                                                                                                 getGroupLink(string $group_guid)                                                                                                 |                                                    get group join link                                                    |                  [getGroupLink.json](examples/getGroupLink.json)                  |
|                                                                                               getNewGroupLink(string $group_guid)                                                                                                |                                             reset and get group new join link                                             |               [getNewGroupLink.json](examples/getNewGroupLink.json)               |
|                                                                                             getGroupAdminMembers(string $group_guid)                                                                                             |                                                   get group admins list                                                   |          [getGroupAdminMembers.json](examples/getGroupAdminMembers.json)          |
|                                                              editGroupHistoryForNewMembers(string $group_guid, HistoryForNewMembers $chat_history_for_new_members)                                                               |                               show group chat gistory for new members --> Visible or Hidden                               | [editGroupHistoryForNewMembers.json](examples/editGroupHistoryForNewMembers.json) |
|                                                                                  setGroupEventMessages(string $group_guid, bool $EventMssages)                                                                                   |                                                show event messages in chat                                                |         [setGroupEventMessages.json](examples/setGroupEventMessages.json)         |
|                                                                        editGroupProfile(string $group_guid, string $title = '', string $description = '')                                                                        |                                                  edit group profile info                                                  |              [editGroupProfile.json](examples/editGroupProfile.json)              |
|                                                                                     banGroupMember(string $group_guid, string $member_guid)                                                                                      |                                                     ban group member                                                      |                [banGroupMember.json](examples/banGroupMember.json)                |
|                                                                                    unBanGroupMember(string $group_guid, string $member_guid)                                                                                     |                               unban group member (delete from group block list to joining)                                |              [unBanGroupMember.json](examples/unBanGroupMember.json)              |
|                                                                            setGroupAdmin(string $group_guid, string $member_guid, array $access_list)                                                                            | set member as group admin : example -> setGroupAdmin('g0UBD989...', 'u0YUB78...', [GroupAdminAccessList::BanMember, ...]) |                 [setGroupAdmin.json](examples/setGroupAdmin.json)                 |
|                                                                                    removeGroupAdmin(string $group_guid, string $member_guid)                                                                                     |                                          remove group admin (set as just member)                                          |              [removeGroupAdmin.json](examples/removeGroupAdmin.json)              |
|                                                                                 getGroupAdminAccessList(string $group_guid, string $admin_guid)                                                                                  |                                                get group admin access list                                                |       [getGroupAdminAccessList.json](examples/getGroupAdminAccessList.json)       |
|                                                                                       setGroupSlowModeTime(string $group_guid, int $time)                                                                                        |                    set group slow time --->(in seconds). just allowed -> 0, 10, 30, 60, 300, 900, 3600                    |          [setGroupSlowModeTime.json](examples/setGroupSlowModeTime.json)          |
|                                                                                          AcceptRequestObjectOwning(string $object_guid)                                                                                          |                                                   accept owning a chat                                                    |     [AcceptRequestObjectOwning.json](examples/AcceptRequestObjectOwning.json)     |
|                                                                                          RejectRequestObjectOwning(string $object_guid)                                                                                          |                                                   reject owning a chat                                                    |     [RejectRequestObjectOwning.json](examples/RejectRequestObjectOwning.json)     |
|                                                                                            getBannedGroupMembers(string $group_guid)                                                                                             |                                             get group block list for joining                                              |         [getBannedGroupMembers.json](examples/getBannedGroupMembers.json)         |
|                                                                       SetGroupReactions(string $group_guid, SetGroupReactions $mode, array $selects = [])                                                                        |                                           set group's which reactions can used                                            |             [SetGroupReactions.json](examples/SetGroupReactions.json)             |
|                                                                            requestChangeObjectOwner(string $group_guid, string $new_owner_user_guid)                                                                             |                                             set another admin to group owner                                              |      [requestChangeObjectOwner.json](examples/requestChangeObjectOwner.json)      |
|                                           sendVideo(string $guid, string $path, bool $isLink = false, string $caption = '', string $thumbnail = '', string $reply_to_message_id = '')                                            |                                                        send video                                                         |                     [sendVideo.json](examples/sendVideo.json)                     |
|                                            sendGif(string $guid, string $path, bool $isLink = false, string $caption = '', string $thumbnail = '', string $reply_to_message_id = '')                                             |                                                         send gif                                                          |                       [sendGif.json](examples/sendGif.json)                       |
|                                           sendMusic(string $guid, string $path, bool $isLink = false, string $caption = '', string $thumbnail = '', string $reply_to_message_id = '')                                            |                                                        send music                                                         |                     [sendMusic.json](examples/sendMusic.json)                     |
| createPoll(string $guid,string $question,array $options,pollType $type,bool $is_anonymous = false,string $explanation = '',int $correct_option_index = 0,bool $allows_multiple_answers = false,string $reply_to_message_id = '') |                                                         send poll                                                         |                    [createPoll.json](examples/createPoll.json)                    |
|                                                                                                  getPollStatus(string $poll_id)                                                                                                  |                                                       get poll info                                                       |                 [getPollStatus.json](examples/getPollStatus.json)                 |
|                                                                                    getPollOptionVoters(string $poll_id, int $selection_index)                                                                                    |                                                  get poll option voters                                                   |           [getPollOptionVoters.json](examples/getPollOptionVoters.json)           |
|                                                                                  sendLocation(string $guid, float $latitude, float $longitude)                                                                                   |                                           send location  get poll option voters                                           |                  [sendLocation.json](examples/sendLocation.json)                  |
|                                                                                                           getMyGifSet                                                                                                            |                                                       get gifs list                                                       |                   [getMyGifSet.json](examples/getMyGifSet.json)                   |
|                                                                                           getStickersBySetIDs(string $sticker_set_ids)                                                                                           |                                                  get stickers by set id                                                   |           [getStickersBySetIDs.json](examples/getStickersBySetIDs.json)           |
|                                                                                       votePoll(string $poll_id, int ...$selection_indexs)                                                                                        |                                                        vote a poll                                                        |                      [votePoll.json](examples/votePoll.json)                      |

# Example

here as base of one bot you can run

```php
declare(strict_types=1);
require_once 'vendor/autoload.php';

use RubikaLib\enums\ChatActivities;
use RubikaLib\interfaces\Runner;
use RubikaLib\{
    Failure,
    Main
};

$app = new Main(9123456789);

$app->proccess(
    new class implements Runner
    {
        private ?array $me;
        private array $userData = [];

        public function onStart(array $mySelf): void
        {
            $this->me = $mySelf;
            echo "bot is running...\n";
        }

        public function onMessage(array $updates, Main $class): void
        {
            if (isset($updates['message_updates'])) {
                foreach ($updates['message_updates'] as $update) {
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

        public function onAction(ChatActivities $activitie, string $guid, string $from, Main $class): void
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

$app->RunAndLoop();
```

see more about methods result: [here](examples)

# Error Handling

we wrote an Exceptions class called **Failure** that specialy used for library errors.
here is an example of some futures:

```php
try {
    $app = new Main(9123456789);

    // ...

    $app->RunAndLoop();
} catch (Failure $error) {
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
$session_data = json_decode(Cryption::Decode(file_get_contents("lib/$hash.rub"), $hash), true);
var_dump($session_data);

```

or you can find phone-hash by library:

```php
use RubikaLib\Session;
use RubikaLib\Cryption;

$hash = Session::GeneratePhoneHash(989123456789); # 98 must be entered before phone number
$session_data = json_decode(Cryption::Decode(file_get_contents("lib/$hash.rub"), $hash), true);
var_dump($session_data);

```

# Some Hints

**!** `don't forget to update user info` use this code to get newest info abount account profile. (this problem will solve on next updates)
```php
$this->getChatInfo($this->getMySelf()['user_guid'])['user']
```

# Join Us

[rubika ✅](https://rubika.ir/RubikaLibPHP)

[telegram ✅](https://t.me/rubika_lib)
