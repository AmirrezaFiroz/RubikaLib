<?php

declare(strict_types=1);

namespace RubikaLib;

use RubikaLib\enums\{
    HistoryForNewMembers,
    GroupAdminAccessList,
    SetGroupReactions,
    ReactionsString,
    ReactionsEmoji
};
use RubikaLib\Interfaces\GroupDefaultAccesses;
use RubikaLib\Utils\Tools;

final class chats
{
    public function __construct(
        private Session $session,
        private Requests $req
    ) {}

    /**
     * join to channel or group
     *
     * @param string $enterKey guid or join link
     * @return array API result
     */
    public function joinChat(string $enterKey): array
    {
        if (filter_var($enterKey, FILTER_VALIDATE_URL)) {
            if (str_contains($enterKey, 'rubika.ir/joing')) {
                $method = 'joinGroup';
            } elseif (str_contains($enterKey, 'rubika.ir/joinc')) {
                $method = 'joinChannelByLink';
            } else {
                exit;
            }
        } else {
            $method = 'joinChannelAction';
        }
        $t = explode('/', $enterKey);
        return $this->req->SendRequest($method, filter_var($enterKey, FILTER_VALIDATE_URL) ? [
            'hash_link' => $t[count($t) - 1]
        ] : [
            'channel_guid' => $enterKey,
            'action' => 'Join'
        ], $this->session)['data'];
    }

    /**
     * leave channel or group
     *
     * @param string $guid
     * @return array API result
     */
    public function leaveChat(string $guid): array
    {
        $chatType = strtolower((string)Tools::ChatTypeByGuid($guid)->value);
        $d = [
            "{$chatType}_guid" => $guid
        ];
        if ($chatType == 'Channel') {
            $d['action'] = 'Leave';
        }
        return $this->req->SendRequest($chatType == 'group' ? 'leaveGroup' : 'joinChannelAction', $d, $this->session)['data'];
    }

    /**
     * delete group for all users
     *
     * @param string $group_guid
     * @return array API result
     */
    public function deleteGroup(string $group_guid): array
    {
        return $this->req->SendRequest('removeGroup', [
            'group_guid' => $group_guid
        ], $this->session)['data'];
    }

    /**
     * create new group
     *
     * @param string $title
     * @param array $members example: ["u0HMRZ...", "u08UBju..."]
     * @return array API result
     */
    public function createGroup(string $title, array $members): array
    {
        return $this->req->SendRequest('addGroup', [
            'title' => $title,
            'member_guids' => $members
        ], $this->session)['data'];
    }




    // ======================================================= group methods ===================================================================



    /**
     * create new group
     *
     * @param string $group_guid
     * @param array $members example: ["u0HMRZ...", "u08UBju..."]
     * @return array API result
     */
    public function addGroupMembers(string $group_guid, array $members): array
    {
        return $this->req->SendRequest('addGroupMembers', [
            'group_guid' => $group_guid,
            'member_guids' => $members
        ], $this->session)['data'];
    }

    /**
     * get groups onlines count
     *
     * @param string $group_guid
     * @return array API result
     */
    public function getGroupOnlineCount(string $group_guid): array
    {
        return $this->req->SendRequest('getGroupOnlineCount', [
            'group_guid' => $group_guid
        ], $this->session)['data'];
    }
    /**
     * get group members list
     *
     * @param string $group_guid
     * @param string $search_for searh for name
     * @param integer $start_id section
     * @return array API result
     */
    public function getGroupAllMembers(string $group_guid, string $search_for = '', int $start_id = 0): array
    {
        $d = [
            'group_guid' => $group_guid
        ];
        if ($search_for != '') {
            $d['search_text'] = $search_for;
        }
        if ($start_id != 0) {
            $d['start$start_id'] = $start_id;
        }
        return $this->req->SendRequest('getGroupAllMembers', $d, $this->session)['data'];
    }

    /**
     * upload new group avatar picture
     *
     * @param string $file_path must be picture
     * @param string $group_guid
     * @return array API result
     */
    public function uploadNewGroupAvatar(string $group_guid, string $file_path): array
    {
        list($file_id, $dc_id, $access_hash_rec) = $this->sendFileToAPI($file_path);
        return $this->req->SendRequest('uploadAvatar', [
            'object_guid' => $group_guid,
            'thumbnail_file_id' => $file_id,
            'main_file_id' => $file_id
        ], $this->session)['data'];
    }

    public function setGroupDefaultAccess(string $group_guid, GroupDefaultAccesses $settings = new GroupDefaultAccesses): array
    {
        $d = [
            'group_guid' => $group_guid
        ];
        if ($settings->ViewAdmins) {
            $d[] = 'ViewAdmins';
        }
        if ($settings->SendMessages) {
            $d[] = 'SendMessages';
        }
        if ($settings->ViewMembers) {
            $d[] = 'ViewMembers';
        }
        if ($settings->AddMember) {
            $d[] = 'AddMember';
        }
        return $this->req->SendRequest('setGroupDefaultAccess', $d, $this->session)['data'];
    }

    /**
     * delete group profile picture
     *
     * @param string $group_guid
     * @param string $avatar_id
     * @return array API result
     */
    public function deleteGroupAvatar(string $group_guid, string $avatar_id): array
    {
        return $this->req->SendRequest('deleteAvatar', [
            'object_guid' => $group_guid,
            'avatar_id' => $avatar_id
        ], $this->session)['data'];
    }

    /**
     * get group join link
     *
     * @param string $group_guid
     * @return array API result
     */
    public function getGroupLink(string $group_guid): array
    {
        return $this->req->SendRequest('getGroupLink', [
            'object_guid' => $group_guid,
        ], $this->session)['data'];
    }

    /**
     * get new group join link
     *
     * @param string $group_guid
     * @return array API result
     */
    public function getNewGroupLink(string $group_guid): array
    {
        return $this->req->SendRequest('setGroupLink', [
            'object_guid' => $group_guid,
        ], $this->session)['data'];
    }

    /**
     * get ngroup admins list
     *
     * @param string $group_guid
     * @return array API result
     */
    public function getGroupAdminMembers(string $group_guid): array
    {
        return $this->req->SendRequest('getGroupAdminMembers', [
            'object_guid' => $group_guid,
        ], $this->session)['data'];
    }

    /**
     * get chat history for new members
     *
     * @param string $group_guid
     * @param HistoryForNewMembers $chat_history_for_new_members Hidden or Visible
     * @return array API result
     */
    public function editGroupHistoryForNewMembers(string $group_guid, HistoryForNewMembers $chat_history_for_new_members): array
    {
        return $this->req->SendRequest('setGroupLink', [
            'object_guid' => $group_guid,
            'chat_history_for_new_members' => $chat_history_for_new_members->value,
            'updated_parameters' => ['chat_history_for_new_members']
        ], $this->session)['data'];
    }

    /**
     * get chat event messages for members
     *
     * @param string $group_guid
     * @param bool $EventMssages 
     * @return array API result
     */
    public function setGroupEventMessages(string $group_guid, bool $EventMssages): array
    {
        return $this->req->SendRequest('setGroupLink', [
            'object_guid' => $group_guid,
            'event_messages' => $EventMssages,
            'updated_parameters' => ['event_messages']
        ], $this->session)['data'];
    }

    /**
     * edit group account info
     *
     * @param string $group_guid
     * @param string $title
     * @param string $description bio
     * @return array API result
     */
    public function editGroupProfile(string $group_guid, string $title = '', string $description = ''): array
    {
        $d = [
            'group_guid' => $group_guid
        ];
        if ($title != '') {
            $d['title'] = $title;
        }
        if ($description != '') {
            $d['description'] = $description;
        }
        $d['updated_parameters'] = [
            "title",
            "description"
        ];

        $d = $this->req->SendRequest('editGroupInfo', $d, $this->session)['data'];

        return $d;
    }

    /**
     * ban group member
     *
     * @param string $group_guid
     * @param string $member_guid 
     * @return array API result
     */
    public function banGroupMember(string $group_guid, string $member_guid): array
    {
        return $this->req->SendRequest('banGroupMember', [
            'group_guid' => $group_guid,
            'member_guid' => $member_guid,
            'action' => 'Set'
        ], $this->session)['data'];
    }

    /**
     * unban group member
     *
     * @param string $group_guid
     * @param string $member_guid 
     * @return array API result
     */
    public function unBanGroupMember(string $group_guid, string $member_guid): array
    {
        return $this->req->SendRequest('banGroupMember', [
            'group_guid' => $group_guid,
            'member_guid' => $member_guid,
            'action' => 'Unset'
        ], $this->session)['data'];
    }

    /**
     * set admin or change admin accesses
     *
     * @param string $group_guid
     * @param string $member_guid
     * @param array $access_list
     * @example . setGroupAdmin('g0UBD989...', 'u0YUB78...', [GroupAdminAccessList::BanMember, ...])
     * @return array API result
     */
    public function setGroupAdmin(string $group_guid, string $member_guid, array $access_list): array
    {
        $d = [
            'group_guid' => $group_guid,
            'member_guid' => $member_guid,
            'action' => 'SetAdmin'
        ];
        foreach ($access_list as $access) {
            if ($access instanceof GroupAdminAccessList) {
                $d['access_list'][] = (string)$access->value;
            }
        }
        return $this->req->SendRequest('setGroupAdmin', $d, $this->session)['data'];
    }

    /**
     * remove admin
     *
     * @param string $group_guid
     * @param string $member_guid
     * @return array API result
     */
    public function removeGroupAdmin(string $group_guid, string $member_guid): array
    {
        return $this->req->SendRequest('setGroupAdmin', [
            'group_guid' => $group_guid,
            'member_guid' => $member_guid,
            'action' => 'UnsetAdmin'
        ], $this->session)['data'];
    }

    /**
     * get group admin accesses
     *
     * @param string $group_guid
     * @param string $admin_guid
     * @return array API resilt
     */
    public function getGroupAdminAccessList(string $group_guid, string $admin_guid): array
    {
        return $this->req->SendRequest('getGroupAdminAccessList', [
            'group_guid' => $group_guid,
            'member_guid' => $admin_guid,
        ], $this->session)['data'];
    }

    /**
     * set group slow mode
     *
     * @param string $group_guid
     * @param integer $time (in seconds). just allowed -> 0, 10, 30, 60, 300, 900, 3600
     * @return array API resilt
     */
    public function setGroupSlowModeTime(string $group_guid, int $time): array
    {
        return $this->req->SendRequest('editGroupInfo', [
            'group_guid' => $group_guid,
            'slow_mode' => $time,
            'updated_parameters' => ['slow_mode']
        ], $this->session)['data'];
    }

    /**
     * get group banned members
     *
     * @param string $group_guid
     * @return array API resilt
     */
    public function getBannedGroupMembers(string $group_guid): array
    {
        return $this->req->SendRequest('getBannedGroupMembers', [
            'group_guid' => $group_guid
        ], $this->session)['data'];
    }

    /**
     * set group allowed reactions
     *
     * @param string $group_guid
     * @param SetGroupReactions $mode all or diabled or selected
     * @param array $selects if mode is set to Selected
     * @example . SetGroupReactions('g0UBD989...', SetGroupReactions::Selected, [ReactionsEmoji::❤️, ReactionsEmoji::👍])
     * @return array API result
     */
    public function SetGroupReactions(string $group_guid, SetGroupReactions $mode, array $selects = []): array
    {
        $d = [
            'group_guid' => $group_guid,
            'chat_reaction_setting' => [
                'reaction_type' => $mode->value
            ],
            'updated_parameters' => ['chat_reaction_setting']
        ];
        if ($mode == SetGroupReactions::Selected) {
            foreach ($selects as $reaction) {
                if ($reaction instanceof ReactionsEmoji or $reaction instanceof ReactionsString) {
                    $d['chat_reaction_setting']['selected_reactions'][] = (string)$reaction->value;
                }
            }
        }
        return $this->req->SendRequest('editGroupInfo', $d, $this->session)['data'];
    }



    // ======================================================= chats methods ===================================================================



    /**
     * set another admin to group owner
     *
     * @param string $group_guid
     * @param string $new_owner_user_guid
     * @return array API result
     */
    public function requestChangeObjectOwner(string $group_guid, string $new_owner_user_guid): array
    {
        return $this->req->SendRequest('editGroupInfo', [
            'group_guid' => $group_guid,
            'new_owner_user_guid' => $new_owner_user_guid
        ], $this->session)['data'];
    }

    /**
     * accept onwing object
     *
     * @param string $object_guid
     * @return array API resilt
     */
    public function AcceptRequestObjectOwning(string $object_guid): array
    {
        return $this->req->SendRequest('editGroupInfo', [
            'object_guid' => $object_guid,
            'action' => 'Accept'
        ], $this->session)['data'];
    }

    /**
     * rejecr onwing object
     *
     * @param string $object_guid
     * @return array API resilt
     */
    public function RejectRequestObjectOwning(string $object_guid): array
    {
        return $this->req->SendRequest('editGroupInfo', [
            'object_guid' => $object_guid,
            'action' => 'Reject'
        ], $this->session)['data'];
    }

    /**
     * get chats list
     *
     * @param int $start_id
     * @return array API result
     */
    public function getChats(int $start_id = 0): array
    {
        return $this->req->SendRequest('getChats', [
            'start_id' => $start_id
        ], $this->session)['data'];
    }

    /**
     * get all chats updates
     *
     * @param int $state
     * @return array API result
     */
    public function getChatsUpdates(int $state = 0): array
    {
        return $this->req->SendRequest('getChatsUpdates', [
            'state' => $state
        ], $this->session)['data'];
    }

    /**
     * get all chat messages (recommanded use it in async mode)
     *
     * @param string $guid
     * @param integer $middle_message_id
     * @return array API result
     */
    public function getMessagesInterval(string $guid, int $middle_message_id): array
    {
        return $this->req->SendRequest('getMessagesInterval', [
            'object_guid' => $guid,
            'middle_message_id' => $middle_message_id
        ], $this->session)['data'];
    }

    /**
     * get all messages from chat (recommanded use it in async mode)
     *
     * @param string $guid
     * @param integer $message_id max_id or min_id
     * @param Sort $sort
     * @return array API result
     */
    // public function getMessages(string $guid, int $message_id, Sort $sort = Sort::FromMax): array
    // {
    //     return $this->req->SendRequest('getMessages', [
    //         'object_guid' => $guid,
    //         'sort' => $sort->value,
    //         str_replace('from', '', strtolower($sort->value)) . '_id' => $message_id
    //     ], $this->session)['data'];
    // }

    /**
     * upload file to API
     *
     * @param string $path file path or link
     * @return array [$file_id, $dc_id, $access_hash_rec]
     */
    private function sendFileToAPI(string $path): array
    {
        $fn = basename($path);
        $ex = explode('.', $fn);
        $data = $this->RequestSendFile($fn, filesize($path), $ex[count($ex) - 1]);

        return [$data['id'], $data['dc_id'], $this->req->SendFileToAPI($path, $data['id'], $data['access_hash_send'], $data['upload_url'])['data']['access_hash_rec']];
    }

    /**
     * it will use to upload file
     *
     * @param string $file_name
     * @param integer $size
     * @param string $mime
     * @return array API result
     */
    private function RequestSendFile(string $file_name, int $size, string $mime): array
    {
        return $this->req->SendRequest('requestSendFile', [
            'file_name' => $file_name,
            'size' => $size,
            'mime' => $mime
        ], $this->session)['data'];
    }

    /**
     * get chat info with guid
     *
     * @param string $guid
     * @return array API result
     */
    public function getChatInfo(string $guid): array
    {
        return $this->req->SendRequest('get' . Tools::ChatTypeByGuid($guid)->value . 'Info', [
            strtolower(Tools::ChatTypeByGuid($guid)->value) . '_guid' => $guid
        ], $this->session)['data'];
    }

    /**
     * get chat info with username
     *
     * @param string $username example: @rubika_lib
     * @return array API result
     */
    public function getChatInfoByUsername(string $username): array
    {
        return $this->req->SendRequest('getObjectInfoByUsername', [
            'username' => str_replace('@', '', $username)
        ], $this->session)['data'];
    }

    /**
     * get chat avatar with guid
     *
     * @param string $object_guid
     * @return array API result
     */
    public function getAvatars(string $object_guid): array
    {
        return $this->req->SendRequest('getAvatars', [
            'object_guid' => $object_guid
        ], $this->session)['data'];
    }
}
