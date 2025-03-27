<?php

namespace App\Http\Controllers;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\Request;

class SlackController extends Controller
{
    protected $client;
    protected $slackToken;
    protected $url = 'https://slack.com/api/';

    public function __construct()
    {
        $this->client = new Client();
        $this->slackToken = env('SLACK_API_TOKEN'); // Add your Slack token here
    }

    function isValidSlackRequest(Request $request)
    {
        // Tu signing secret de Slack
        $signingSecret = env('SLACK_SIGNING_SECRET');

        // Encabezados de Slack
        $slackSignature = $request->header('X-Slack-Signature');
        $slackTimestamp = $request->header('X-Slack-Request-Timestamp');

        // Verificar que el timestamp no sea muy antiguo (previene ataques de repetición)
        if (abs(time() - $slackTimestamp) > 300) {
            return false; // Solicitud expirada
        }

        // Construir la base string
        $baseString = "v0:$slackTimestamp:" . $request->getContent();

        // Generar la firma
        $hash = 'v0=' . hash_hmac('sha256', $baseString, $signingSecret);

        // Comparar firmas usando hash_equals para evitar ataques de tiempo
        return hash_equals($hash, $slackSignature);
    }

    public function admin_users_set_owner(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.users.setOwner', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'team_id' => $request->input('team_id'),
                'user_id' => $request->input('user_id')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_users_session_reset(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.users.session.reset', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'user_id' => $request->input('user_id'),
                'mobile_only' => $request->input('mobile_only'),
                'web_only' => $request->input('web_only')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_apps_approve(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.apps.approve', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'app_id' => $request->input('app_id'),
                'request_id' => $request->input('request_id'),
                'team_id' => $request->input('team_id')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_apps_approved_list(Request $request)
    {
        $response = $this->client->get($this->url . '/admin.apps.approved.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_apps_requests_list(Request $request)
    {
        $response = $this->client->get($this->url . '/admin.apps.requests.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_apps_restricted_list(Request $request)
    {
        $response = $this->client->get($this->url . '/admin.apps.restricted.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_conversations_archive(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.conversations.archive', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel_id' => $request->input('channel_id')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_apps_restrict(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.apps.restrict', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'app_id' => $request->input('app_id'),
                'request_id' => $request->input('request_id'),
                'team_id' => $request->input('team_id')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_conversations_convert_to_private(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.conversations.convertToPrivate', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel_id' => $request->input('channel_id')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_conversations_create(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.conversations.create', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'name' => $request->input('name'),
                'is_private' => $request->input('is_private'),
                'description' => $request->input('description'),
                'org_wide' => $request->input('org_wide'),
                'team_id' => $request->input('team_id')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_conversations_delete(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.conversations.delete', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel_id' => $request->input('channel_id')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_conversations_disconnect_shared(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.conversations.disconnectShared', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel_id' => $request->input('channel_id'),
                'leaving_team_ids' => $request->input('leaving_team_ids')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_conversations_ekm_list_original_connected_channel_info(Request $request)
    {
        $response = $this->client->get($this->url . '/admin.conversations.ekm.listOriginalConnectedChannelInfo', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_conversations_get_teams(Request $request)
    {
        $response = $this->client->get($this->url . '/admin.conversations.getTeams', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_conversations_get_conversation_prefs(Request $request)
    {
        $response = $this->client->get($this->url . '/admin.conversations.getConversationPrefs', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_conversations_rename(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.conversations.rename', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel_id' => $request->input('channel_id'),
                'name' => $request->input('name')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_conversations_invite(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.conversations.invite', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'user_ids' => $request->input('user_ids'),
                'channel_id' => $request->input('channel_id')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_conversations_restrict_access_remove_group(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.conversations.restrictAccess.removeGroup', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'token' => $request->input('token'),
                'team_id' => $request->input('team_id'),
                'group_id' => $request->input('group_id'),
                'channel_id' => $request->input('channel_id')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_conversations_restrict_access_list_groups(Request $request)
    {
        $response = $this->client->get($this->url . '/admin.conversations.restrictAccess.listGroups', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_conversations_restrict_access_add_group(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.conversations.restrictAccess.addGroup', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'token' => $request->input('token'),
                'group_id' => $request->input('group_id'),
                'channel_id' => $request->input('channel_id'),
                'team_id' => $request->input('team_id')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_conversations_search(Request $request)
    {
        $response = $this->client->get($this->url . '/admin.conversations.search', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_conversations_set_conversation_prefs(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.conversations.setConversationPrefs', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel_id' => $request->input('channel_id'),
                'prefs' => $request->input('prefs')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_conversations_set_teams(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.conversations.setTeams', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel_id' => $request->input('channel_id'),
                'team_id' => $request->input('team_id'),
                'target_team_ids' => $request->input('target_team_ids'),
                'org_channel' => $request->input('org_channel')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_emoji_add(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.emoji.add', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'token' => $request->input('token'),
                'name' => $request->input('name'),
                'url' => $request->input('url')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_emoji_add_alias(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.emoji.addAlias', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'token' => $request->input('token'),
                'name' => $request->input('name'),
                'alias_for' => $request->input('alias_for')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_emoji_list(Request $request)
    {
        $response = $this->client->get($this->url . '/admin.emoji.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_conversations_unarchive(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.conversations.unarchive', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel_id' => $request->input('channel_id')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_emoji_rename(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.emoji.rename', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'token' => $request->input('token'),
                'name' => $request->input('name'),
                'new_name' => $request->input('new_name')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_emoji_remove(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.emoji.remove', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'token' => $request->input('token'),
                'name' => $request->input('name')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_invite_requests_approve(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.inviteRequests.approve', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'invite_request_id' => $request->input('invite_request_id'),
                'team_id' => $request->input('team_id')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_invite_requests_approved_list(Request $request)
    {
        $response = $this->client->get($this->url . '/admin.inviteRequests.approved.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_invite_requests_deny(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.inviteRequests.deny', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'invite_request_id' => $request->input('invite_request_id'),
                'team_id' => $request->input('team_id')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_invite_requests_denied_list(Request $request)
    {
        $response = $this->client->get($this->url . '/admin.inviteRequests.denied.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_invite_requests_list(Request $request)
    {
        $response = $this->client->get($this->url . '/admin.inviteRequests.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_teams_admins_list(Request $request)
    {
        $response = $this->client->get($this->url . '/admin.teams.admins.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_teams_create(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.teams.create', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'team_domain' => $request->input('team_domain'),
                'team_name' => $request->input('team_name'),
                'team_description' => $request->input('team_description'),
                'team_discoverability' => $request->input('team_discoverability')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_teams_list(Request $request)
    {
        $response = $this->client->get($this->url . '/admin.teams.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_teams_owners_list(Request $request)
    {
        $response = $this->client->get($this->url . '/admin.teams.owners.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_teams_settings_info(Request $request)
    {
        $response = $this->client->get($this->url . '/admin.teams.settings.info', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_teams_settings_set_description(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.teams.settings.setDescription', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'team_id' => $request->input('team_id'),
                'description' => $request->input('description')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_teams_settings_set_discoverability(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.teams.settings.setDiscoverability', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'team_id' => $request->input('team_id'),
                'discoverability' => $request->input('discoverability')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_teams_settings_set_icon(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.teams.settings.setIcon', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'token' => $request->input('token'),
                'image_url' => $request->input('image_url'),
                'team_id' => $request->input('team_id')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_teams_settings_set_name(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.teams.settings.setName', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'team_id' => $request->input('team_id'),
                'name' => $request->input('name')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_teams_settings_set_default_channels(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.teams.settings.setDefaultChannels', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'token' => $request->input('token'),
                'team_id' => $request->input('team_id'),
                'channel_ids' => $request->input('channel_ids')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_usergroups_add_channels(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.usergroups.addChannels', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'usergroup_id' => $request->input('usergroup_id'),
                'channel_ids' => $request->input('channel_ids'),
                'team_id' => $request->input('team_id')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_usergroups_list_channels(Request $request)
    {
        $response = $this->client->get($this->url . '/admin.usergroups.listChannels', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_usergroups_add_teams(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.usergroups.addTeams', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'usergroup_id' => $request->input('usergroup_id'),
                'team_ids' => $request->input('team_ids'),
                'auto_provision' => $request->input('auto_provision')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_users_invite(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.users.invite', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'team_id' => $request->input('team_id'),
                'email' => $request->input('email'),
                'channel_ids' => $request->input('channel_ids'),
                'custom_message' => $request->input('custom_message'),
                'real_name' => $request->input('real_name'),
                'resend' => $request->input('resend'),
                'is_restricted' => $request->input('is_restricted'),
                'is_ultra_restricted' => $request->input('is_ultra_restricted'),
                'guest_expiration_ts' => $request->input('guest_expiration_ts')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_usergroups_remove_channels(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.usergroups.removeChannels', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'usergroup_id' => $request->input('usergroup_id'),
                'channel_ids' => $request->input('channel_ids')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_users_list(Request $request)
    {
        $response = $this->client->get($this->url . '/admin.users.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_users_session_invalidate(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.users.session.invalidate', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'team_id' => $request->input('team_id'),
                'session_id' => $request->input('session_id')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_users_assign(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.users.assign', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'team_id' => $request->input('team_id'),
                'user_id' => $request->input('user_id'),
                'is_restricted' => $request->input('is_restricted'),
                'is_ultra_restricted' => $request->input('is_ultra_restricted'),
                'channel_ids' => $request->input('channel_ids')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_users_remove(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.users.remove', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'team_id' => $request->input('team_id'),
                'user_id' => $request->input('user_id')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_users_set_regular(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.users.setRegular', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'team_id' => $request->input('team_id'),
                'user_id' => $request->input('user_id')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_users_set_admin(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.users.setAdmin', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'team_id' => $request->input('team_id'),
                'user_id' => $request->input('user_id')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function admin_users_set_expiration(Request $request)
    {
        $response = $this->client->post($this->url . '/admin.users.setExpiration', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'team_id' => $request->input('team_id'),
                'user_id' => $request->input('user_id'),
                'expiration_ts' => $request->input('expiration_ts')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function api_test(Request $request)
    {
        $response = $this->client->get($this->url . '/api.test', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function apps_permissions_info(Request $request)
    {
        $response = $this->client->get($this->url . '/apps.permissions.info', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function apps_event_authorizations_list(Request $request)
    {
        $response = $this->client->get($this->url . '/apps.event.authorizations.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function apps_permissions_request(Request $request)
    {
        $response = $this->client->get($this->url . '/apps.permissions.request', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function apps_permissions_resources_list(Request $request)
    {
        $response = $this->client->get($this->url . '/apps.permissions.resources.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function apps_permissions_scopes_list(Request $request)
    {
        $response = $this->client->get($this->url . '/apps.permissions.scopes.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function apps_uninstall(Request $request)
    {
        $response = $this->client->get($this->url . '/apps.uninstall', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function apps_permissions_users_list(Request $request)
    {
        $response = $this->client->get($this->url . '/apps.permissions.users.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function apps_permissions_users_request(Request $request)
    {
        $response = $this->client->get($this->url . '/apps.permissions.users.request', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function bots_info(Request $request)
    {
        $response = $this->client->get($this->url . '/bots.info', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function auth_revoke(Request $request)
    {
        $response = $this->client->get($this->url . '/auth.revoke', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function auth_test(Request $request)
    {
        $response = $this->client->get($this->url . '/auth.test', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function calls_add(Request $request)
    {
        $response = $this->client->post($this->url . '/calls.add', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'external_unique_id' => $request->input('external_unique_id'),
                'join_url' => $request->input('join_url'),
                'external_display_id' => $request->input('external_display_id'),
                'desktop_app_join_url' => $request->input('desktop_app_join_url'),
                'date_start' => $request->input('date_start'),
                'title' => $request->input('title'),
                'created_by' => $request->input('created_by'),
                'users' => $request->input('users')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function calls_end(Request $request)
    {
        $response = $this->client->post($this->url . '/calls.end', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'id' => $request->input('id'),
                'duration' => $request->input('duration')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function calls_participants_add(Request $request)
    {
        $response = $this->client->post($this->url . '/calls.participants.add', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'id' => $request->input('id'),
                'users' => $request->input('users')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function calls_participants_remove(Request $request)
    {
        $response = $this->client->post($this->url . '/calls.participants.remove', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'id' => $request->input('id'),
                'users' => $request->input('users')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function calls_info(Request $request)
    {
        $response = $this->client->get($this->url . '/calls.info', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function calls_update(Request $request)
    {
        $response = $this->client->post($this->url . '/calls.update', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'id' => $request->input('id'),
                'title' => $request->input('title'),
                'join_url' => $request->input('join_url'),
                'desktop_app_join_url' => $request->input('desktop_app_join_url')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function chat_delete(Request $request)
    {
        $response = $this->client->post($this->url . '/chat.delete', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'ts' => $request->input('ts'),
                'channel' => $request->input('channel'),
                'as_user' => $request->input('as_user')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function chat_delete_scheduled_message(Request $request)
    {
        $response = $this->client->post($this->url . '/chat.deleteScheduledMessage', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel'),
                'scheduled_message_id' => $request->input('scheduled_message_id'),
                'as_user' => $request->input('as_user')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function chat_get_permalink(Request $request)
    {
        $response = $this->client->get($this->url . '/chat.getPermalink', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function chat_post_ephemeral(Request $request)
    {
        $response = $this->client->post($this->url . '/chat.postEphemeral', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel'),
                'user' => $request->input('user'),
                'as_user' => $request->input('as_user'),
                'attachments' => $request->input('attachments'),
                'blocks' => $request->input('blocks'),
                'icon_emoji' => $request->input('icon_emoji'),
                'icon_url' => $request->input('icon_url'),
                'link_names' => $request->input('link_names'),
                'parse' => $request->input('parse'),
                'text' => $request->input('text'),
                'thread_ts' => $request->input('thread_ts'),
                'username' => $request->input('username')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function chat_post_message_api(Request $request)
    {
        $response = $this->client->post($this->url . '/chat.postMessage', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel'),
                'as_user' => $request->input('as_user'),
                'attachments' => $request->input('attachments'),
                'blocks' => $request->input('blocks'),
                'icon_emoji' => $request->input('icon_emoji'),
                'icon_url' => $request->input('icon_url'),
                'link_names' => $request->input('link_names'),
                'mrkdwn' => $request->input('mrkdwn'),
                'parse' => $request->input('parse'),
                'reply_broadcast' => $request->input('reply_broadcast'),
                'text' => $request->input('text'),
                'thread_ts' => $request->input('thread_ts'),
                'unfurl_links' => $request->input('unfurl_links'),
                'unfurl_media' => $request->input('unfurl_media'),
                'username' => $request->input('username')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function chat_post_message(string $channel, string $message)
    {
        $response = $this->client->post($this->url . '/chat.postMessage', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'json' => [
                'channel' => $channel,
                /*'as_user' => $request->input('as_user'),
                'attachments' => $request->input('attachments'),
                'blocks' => $request->input('blocks'),
                'icon_emoji' => $request->input('icon_emoji'),
                'icon_url' => $request->input('icon_url'),*/
                'link_names' => 1,
                /*'mrkdwn' => $request->input('mrkdwn'),
                'parse' => $request->input('parse'),
                'reply_broadcast' => $request->input('reply_broadcast'),*/
                'text' => $message/*,
                'thread_ts' => $request->input('thread_ts'),
                'unfurl_links' => $request->input('unfurl_links'),
                'unfurl_media' => $request->input('unfurl_media'),
                'username' => $request->input('username')*/
            ]
        ]);

        return $response->getStatusCode();
    }

    public function chat_me_message(Request $request)
    {
        $response = $this->client->post($this->url . '/chat.meMessage', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel'),
                'text' => $request->input('text')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function chat_schedule_message(Request $request)
    {
        $response = $this->client->post($this->url . '/chat.scheduleMessage', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel'),
                'text' => $request->input('text'),
                'post_at' => $request->input('post_at'),
                'parse' => $request->input('parse'),
                'as_user' => $request->input('as_user'),
                'link_names' => $request->input('link_names'),
                'attachments' => $request->input('attachments'),
                'blocks' => $request->input('blocks'),
                'unfurl_links' => $request->input('unfurl_links'),
                'unfurl_media' => $request->input('unfurl_media'),
                'thread_ts' => $request->input('thread_ts'),
                'reply_broadcast' => $request->input('reply_broadcast')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function chat_scheduled_messages_list(Request $request)
    {
        $response = $this->client->get($this->url . '/chat.scheduledMessages.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function chat_unfurl(Request $request)
    {
        $response = $this->client->post($this->url . '/chat.unfurl', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel'),
                'ts' => $request->input('ts'),
                'unfurls' => $request->input('unfurls'),
                'user_auth_message' => $request->input('user_auth_message'),
                'user_auth_required' => $request->input('user_auth_required'),
                'user_auth_url' => $request->input('user_auth_url')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function chat_update(Request $request)
    {
        $response = $this->client->post($this->url . '/chat.update', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel'),
                'ts' => $request->input('ts'),
                'as_user' => $request->input('as_user'),
                'attachments' => $request->input('attachments'),
                'blocks' => $request->input('blocks'),
                'link_names' => $request->input('link_names'),
                'parse' => $request->input('parse'),
                'text' => $request->input('text')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    /**
     * @throws GuzzleException
     */
    public function conversations_info(Request $request)
    {
        $response = $this->client->get($this->url . '/conversations.info', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function conversations_archive(Request $request)
    {
        $response = $this->client->post($this->url . '/conversations.archive', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function conversations_close(Request $request)
    {
        $response = $this->client->post($this->url . '/conversations.close', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function conversations_create(Request $request)
    {
        $response = $this->client->post($this->url . '/conversations.create', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'name' => $request->input('name'),
                'is_private' => $request->input('is_private')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function conversations_invite(Request $request)
    {
        $response = $this->client->post($this->url . '/conversations.invite', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel'),
                'users' => $request->input('users')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function conversations_join(Request $request)
    {
        $response = $this->client->post($this->url . '/conversations.join', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function conversations_history(Request $request)
    {
        $response = $this->client->get($this->url . '/conversations.history', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function conversations_open(Request $request)
    {
        $response = $this->client->post($this->url . '/conversations.open', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel'),
                'users' => $request->input('users'),
                'return_im' => $request->input('return_im')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function conversations_kick(Request $request)
    {
        $response = $this->client->post($this->url . '/conversations.kick', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel'),
                'user' => $request->input('user')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function conversations_leave(Request $request)
    {
        $response = $this->client->post($this->url . '/conversations.leave', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function get_conversations_list(Request $request)
    {
        $response = $this->client->get($this->url . '/conversations.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function conversations_list($params)
    {
        $response = $this->client->get($this->url . '/conversations.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $params // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function conversations_mark(Request $request)
    {
        $response = $this->client->post($this->url . '/conversations.mark', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel'),
                'ts' => $request->input('ts')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function conversations_set_topic(Request $request)
    {
        $response = $this->client->post($this->url . '/conversations.setTopic', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel'),
                'topic' => $request->input('topic')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function conversations_unarchive(Request $request)
    {
        $response = $this->client->post($this->url . '/conversations.unarchive', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function dialog_open(Request $request)
    {
        $response = $this->client->get($this->url . '/dialog.open', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function conversations_members(Request $request)
    {
        $response = $this->client->get($this->url . '/conversations.members', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function conversations_rename(Request $request)
    {
        $response = $this->client->post($this->url . '/conversations.rename', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel'),
                'name' => $request->input('name')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function conversations_replies(Request $request)
    {
        $response = $this->client->get($this->url . '/conversations.replies', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function conversations_set_purpose(Request $request)
    {
        $response = $this->client->post($this->url . '/conversations.setPurpose', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel'),
                'purpose' => $request->input('purpose')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function dnd_end_dnd(Request $request)
    {
        $response = $this->client->post($this->url . '/dnd.endDnd', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [

            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function dnd_end_snooze(Request $request)
    {
        $response = $this->client->post($this->url . '/dnd.endSnooze', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [

            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function dnd_info(Request $request)
    {
        $response = $this->client->get($this->url . '/dnd.info', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function dnd_set_snooze(Request $request)
    {
        $response = $this->client->post($this->url . '/dnd.setSnooze', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'token' => $request->input('token'),
                'num_minutes' => $request->input('num_minutes')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function dnd_team_info(Request $request)
    {
        $response = $this->client->get($this->url . '/dnd.teamInfo', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function emoji_list(Request $request)
    {
        $response = $this->client->get($this->url . '/emoji.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function files_delete(Request $request)
    {
        $response = $this->client->post($this->url . '/files.delete', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'file' => $request->input('file')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function files_comments_delete(Request $request)
    {
        $response = $this->client->post($this->url . '/files.comments.delete', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'file' => $request->input('file'),
                'id' => $request->input('id')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function files_list(Request $request)
    {
        $response = $this->client->get($this->url . '/files.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function files_remote_update(Request $request)
    {
        $response = $this->client->post($this->url . '/files.remote.update', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'token' => $request->input('token'),
                'file' => $request->input('file'),
                'external_id' => $request->input('external_id'),
                'title' => $request->input('title'),
                'filetype' => $request->input('filetype'),
                'external_url' => $request->input('external_url'),
                'preview_image' => $request->input('preview_image'),
                'indexable_file_contents' => $request->input('indexable_file_contents')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function files_info(Request $request)
    {
        $response = $this->client->get($this->url . '/files.info', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function files_upload(Request $request)
    {
        $response = $this->client->post($this->url . '/files.upload', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'token' => $request->input('token'),
                'file' => $request->input('file'),
                'content' => $request->input('content'),
                'filetype' => $request->input('filetype'),
                'filename' => $request->input('filename'),
                'title' => $request->input('title'),
                'initial_comment' => $request->input('initial_comment'),
                'channels' => $request->input('channels'),
                'thread_ts' => $request->input('thread_ts')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function files_remote_add(Request $request)
    {
        $response = $this->client->post($this->url . '/files.remote.add', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'token' => $request->input('token'),
                'external_id' => $request->input('external_id'),
                'title' => $request->input('title'),
                'filetype' => $request->input('filetype'),
                'external_url' => $request->input('external_url'),
                'preview_image' => $request->input('preview_image'),
                'indexable_file_contents' => $request->input('indexable_file_contents')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function files_remote_info(Request $request)
    {
        $response = $this->client->get($this->url . '/files.remote.info', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function migration_exchange(Request $request)
    {
        $response = $this->client->get($this->url . '/migration.exchange', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function files_remote_list(Request $request)
    {
        $response = $this->client->get($this->url . '/files.remote.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function files_remote_remove(Request $request)
    {
        $response = $this->client->post($this->url . '/files.remote.remove', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'token' => $request->input('token'),
                'file' => $request->input('file'),
                'external_id' => $request->input('external_id')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function files_remote_share(Request $request)
    {
        $response = $this->client->get($this->url . '/files.remote.share', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function files_revoke_public_url(Request $request)
    {
        $response = $this->client->post($this->url . '/files.revokePublicURL', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'file' => $request->input('file')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function files_shared_public_url(Request $request)
    {
        $response = $this->client->post($this->url . '/files.sharedPublicURL', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'file' => $request->input('file')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function pins_list(Request $request)
    {
        $response = $this->client->get($this->url . '/pins.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function reactions_list(Request $request)
    {
        $response = $this->client->get($this->url . '/reactions.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function oauth_token(Request $request)
    {
        $response = $this->client->get($this->url . '/oauth.token', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function reactions_add(Request $request)
    {
        $response = $this->client->post($this->url . '/reactions.add', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel'),
                'name' => $request->input('name'),
                'timestamp' => $request->input('timestamp')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function reactions_remove(Request $request)
    {
        $response = $this->client->post($this->url . '/reactions.remove', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'name' => $request->input('name'),
                'file' => $request->input('file'),
                'file_comment' => $request->input('file_comment'),
                'channel' => $request->input('channel'),
                'timestamp' => $request->input('timestamp')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function oauth_v2_access(Request $request)
    {
        $response = $this->client->get($this->url . '/oauth.v2.access', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function pins_add(Request $request)
    {
        $response = $this->client->post($this->url . '/pins.add', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel'),
                'timestamp' => $request->input('timestamp')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function oauth_access(Request $request)
    {
        $response = $this->client->get($this->url . '/oauth.access', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function reactions_get(Request $request)
    {
        $response = $this->client->get($this->url . '/reactions.get', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function pins_remove(Request $request)
    {
        $response = $this->client->post($this->url . '/pins.remove', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel'),
                'timestamp' => $request->input('timestamp')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function reminders_complete(Request $request)
    {
        $response = $this->client->post($this->url . '/reminders.complete', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'reminder' => $request->input('reminder')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function reminders_add(Request $request)
    {
        $response = $this->client->post($this->url . '/reminders.add', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'text' => $request->input('text'),
                'time' => $request->input('time'),
                'user' => $request->input('user')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function reminders_delete(Request $request)
    {
        $response = $this->client->post($this->url . '/reminders.delete', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'reminder' => $request->input('reminder')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function reminders_info(Request $request)
    {
        $response = $this->client->get($this->url . '/reminders.info', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function reminders_list(Request $request)
    {
        $response = $this->client->get($this->url . '/reminders.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function rtm_connect(Request $request)
    {
        $response = $this->client->get($this->url . '/rtm.connect', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function search_messages(Request $request)
    {
        $response = $this->client->get($this->url . '/search.messages', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function stars_add(Request $request)
    {
        $response = $this->client->post($this->url . '/stars.add', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel'),
                'file' => $request->input('file'),
                'file_comment' => $request->input('file_comment'),
                'timestamp' => $request->input('timestamp')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function stars_list(Request $request)
    {
        $response = $this->client->get($this->url . '/stars.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function stars_remove(Request $request)
    {
        $response = $this->client->post($this->url . '/stars.remove', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'channel' => $request->input('channel'),
                'file' => $request->input('file'),
                'file_comment' => $request->input('file_comment'),
                'timestamp' => $request->input('timestamp')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function team_access_logs(Request $request)
    {
        $response = $this->client->get($this->url . '/team.accessLogs', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function team_billable_info(Request $request)
    {
        $response = $this->client->get($this->url . '/team.billableInfo', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function team_info(Request $request)
    {
        $response = $this->client->get($this->url . '/team.info', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function usergroups_enable(Request $request)
    {
        $response = $this->client->post($this->url . '/usergroups.enable', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'usergroup' => $request->input('usergroup'),
                'include_count' => $request->input('include_count')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function team_integration_logs(Request $request)
    {
        $response = $this->client->get($this->url . '/team.integrationLogs', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function team_profile_get(Request $request)
    {
        $response = $this->client->get($this->url . '/team.profile.get', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function usergroups_create(Request $request)
    {
        $response = $this->client->post($this->url . '/usergroups.create', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'name' => $request->input('name'),
                'channels' => $request->input('channels'),
                'description' => $request->input('description'),
                'handle' => $request->input('handle'),
                'include_count' => $request->input('include_count')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function usergroups_disable(Request $request)
    {
        $response = $this->client->post($this->url . '/usergroups.disable', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'usergroup' => $request->input('usergroup'),
                'include_count' => $request->input('include_count')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function usergroups_list(Request $request)
    {
        $response = $this->client->get($this->url . '/usergroups.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function usergroups_users_list(Request $request)
    {
        $response = $this->client->get($this->url . '/usergroups.users.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function usergroups_update(Request $request)
    {
        $response = $this->client->post($this->url . '/usergroups.update', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'usergroup' => $request->input('usergroup'),
                'handle' => $request->input('handle'),
                'description' => $request->input('description'),
                'channels' => $request->input('channels'),
                'include_count' => $request->input('include_count'),
                'name' => $request->input('name')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function usergroups_users_update(Request $request)
    {
        $response = $this->client->post($this->url . '/usergroups.users.update', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'usergroup' => $request->input('usergroup'),
                'users' => $request->input('users'),
                'include_count' => $request->input('include_count')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function users_delete_photo(Request $request)
    {
        $response = $this->client->post($this->url . '/users.deletePhoto', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'token' => $request->input('token')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function users_get_presence(Request $request)
    {
        $response = $this->client->get($this->url . '/users.getPresence', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function users_conversations(Request $request)
    {
        $response = $this->client->get($this->url . '/users.conversations', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function users_info(Request $request)
    {
        $response = $this->client->get($this->url . '/users.info', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function views_open(Request $request)
    {
        $response = $this->client->get($this->url . '/views.open', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function users_identity(Request $request)
    {
        $response = $this->client->get($this->url . '/users.identity', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }


    public function users_list($cursor = '', $limit = 10, $include_locale = true)
    {


        $response = $this->client->get($this->url . '/users.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => [
                'cursor' => $cursor,
                'limit' => $limit,
                'include_locale' => $include_locale
            ] // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }


    public function users_list_request(Request $request)
    {
        $response = $this->client->get($this->url . '/users.list', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function users_lookup_by_email(Request $request)
    {
        $response = $this->client->get($this->url . '/users.lookupByEmail', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function users_profile_set(Request $request)
    {
        $response = $this->client->post($this->url . '/users.profile.set', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'name' => $request->input('name'),
                'profile' => $request->input('profile'),
                'user' => $request->input('user'),
                'value' => $request->input('value')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function users_profile_get(Request $request)
    {
        $response = $this->client->get($this->url . '/users.profile.get', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function users_set_active(Request $request)
    {
        $response = $this->client->post($this->url . '/users.setActive', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [

            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function users_set_photo(Request $request)
    {
        $response = $this->client->post($this->url . '/users.setPhoto', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'token' => $request->input('token'),
                'crop_w' => $request->input('crop_w'),
                'crop_x' => $request->input('crop_x'),
                'crop_y' => $request->input('crop_y'),
                'image' => $request->input('image')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function users_set_presence(Request $request)
    {
        $response = $this->client->post($this->url . '/users.setPresence', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'form_params' => [
                'presence' => $request->input('presence')
            ]
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function views_publish(Request $request)
    {
        $response = $this->client->get($this->url . '/views.publish', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function views_push(Request $request)
    {
        $response = $this->client->get($this->url . '/views.push', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function views_update(Request $request)
    {
        $response = $this->client->get($this->url . '/views.update', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function workflows_step_completed(Request $request)
    {
        $response = $this->client->get($this->url . '/workflows.stepCompleted', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function workflows_step_failed(Request $request)
    {
        $response = $this->client->get($this->url . '/workflows.stepFailed', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    public function workflows_update_step(Request $request)
    {
        $response = $this->client->get($this->url . '/workflows.updateStep', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->slackToken
            ],
            'query' => $request->all() // Handle query parameters here
        ]);

        return response()->json(json_decode($response->getBody(), true));
    }

    /**
     * Find channel id by client id
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function findChannelIdByClientId($client_id)
    {

        $allChannels = $this->conversations_list('exclude_archived=true&types=private_channel')->getData();
        $channel_id = null;
        foreach ($allChannels->channels as $channel) {
            $channel_id = null;
            preg_match('/(Subelementos de )?(\d*)_.*/', $channel->name, $matches);
            if (count($matches) === 3 && $matches[2] == $client_id) {
                $channel_id = $channel->id;
                break;
            }
        }
        return $channel_id;
    }


    //Get summary of a board on monday and send it to slack
    public function getTimeTrackingMondayBoardSummaryRequest(Request $request)
    {
        $slackController = new SlackController();
        if (!$slackController->isValidSlackRequest($request)) {
            return response('Unauthorized', 401);
        }
        $mondayController = new MondayController();
        $channel_id = $request->input('channel_id');
        $channel_name = $request->input('channel_name');
        $board_name = $request->input('text');

        $slackController->chat_post_message($channel_id, "Getting summary for board: " . $board_name);
        $board_id = $mondayController->findBoardIdByName($board_name)['id'];
        if (!$board_id) {
            $slackController->chat_post_message($channel_id, "Board not found: " . $board_name);
            return response()->json(['message' => 'Board not found']);
        }
        $mondaySummary = $mondayController->getTimeTrakingMondayBoardSummary($board_id);
        $report = $mondayController->generateTimeTrackingReport($mondaySummary);
        $response = $slackController->chat_post_message($channel_id, $report);
        return response()->json($response);
    }

    //Get summary of a board on monday and send it to slack
    public function getTimeTrackingMondayBoardSummary($board_id, $channel_id)
    {
        $slackController = new SlackController();
        $mondayController = new MondayController();
        $mondaySummary = $mondayController->getTimeTrakingMondayBoardSummary($board_id);
        $report = $mondayController->generateTimeTrackingReport($mondaySummary);
        $response = $slackController->chat_post_message($channel_id, $report);
        return response()->json($response);
    }


    //Get summary of a board on monday and send it to slack
    public function timeTrackingMondayBoardSummaryWithBoardIds($board_ids, $from = null, $to = null): bool
    {
        $slackController = new SlackController();
        $mondayController = new MondayController();
        $boards = $mondayController->getBoardsByIds($board_ids)->getData();
        $client_ids = [];

        foreach ($boards as $board) {
            $board_name = $board->name;
            // regexp to get client id from board name
            preg_match('/(Subelementos de )?(\d*)_.*/', $board_name, $matches);
//            $client_id = explode('_', $board_name)[0];
//            $client_id = explode('Subelementos de ', $board_name);
            $client_id = $matches[2] ?? null;

            if ($client_id !== null && !in_array($client_id, $client_ids)) {
                $client_ids[] = $client_id;
                $channel_id = $this->findChannelIdByClientId($client_id);

                if ($channel_id) {
                    $slackController->chat_post_message($channel_id, "Getting summary for board: " . $board_name);
                    $mondaySummary = $mondayController->getTimeTrakingMondayBoardSummary($board->id);
                    $report = $mondayController->generateTimeTrackingReport($mondaySummary);
                    $slackController->chat_post_message($channel_id, $report);
                }
                $channel_id = null;
            }

        }
        return true;


        /*if (!$board_id) {
            $slackController->chat_post_message($channel_id, "Board not found: " . $board_name);
            return response()->json(['message' => 'Board not found']);
        }
        $mondaySummary = $mondayController->getTimeTrakingMondayBoardSummary($board_id);
        $report = $mondayController->generateTimeTrackingReport($mondaySummary);
        $response = $slackController->chat_post_message('C07PF06HF46', $report);
        return response()->json($response);*/
    }

    public function formatDisplayUser($user)
    {
        if (is_null($user)) {
            $userDisplayName = 'Sin especificar';
        } else {
            $userDisplayName = $user['slack_user_id'] ? "<@{$user['slack_user_id']}>" : $user['name'];
        }

        return $userDisplayName;

    }

    /**
     * Get user info from Holded and display it in Slack
     * @param Request $request
     * @return \Illuminate\Contracts\Foundation\Application|\Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function getUserInfoRequest(Request $request)
    {
        /*if (!$this->isValidSlackRequest($request)) {
            return response('Unauthorized', 401);
        }*/

        // Get channel name and extract client ID
        $channel_id = $request->input('channel_id');
        $channel_name = $request->input('channel_name');

        // Extract client ID from channel name (format: 123_clientname)
        preg_match('/^(\d+)_/', $channel_name, $matches);
        if (empty($matches) || !isset($matches[1])) {
            return response()->json([
                'response_type' => 'ephemeral',
                'text' => 'No se pudo determinar el ID del cliente desde el nombre del canal.'
            ]);
        }

        $client_internal_id = number_format($matches[1]);

        // Find ALL clients in database with this internal ID
        $clients = \App\Models\Client::where('internal_id', $client_internal_id)->get();

        if ($clients->isEmpty()) {
            return response()->json([
                'response_type' => 'ephemeral',
                'text' => 'No se encontró el cliente con ID interno: ' . $client_internal_id
            ]);
        }

        // Filter out clients without Holded ID
        $clientsWithHoldedId = $clients->filter(function ($client) {
            return !empty($client->holded_id);
        });

        if ($clientsWithHoldedId->isEmpty()) {
            return response()->json([
                'response_type' => 'ephemeral',
                'text' => 'No se encontraron clientes con ID de Holded para el ID interno: ' . $client_internal_id
            ]);
        }

        $documentsController = new \App\Http\Controllers\Holded\DocumentsHoldedController();
        $combinedResponse = '';
        $foundServices = false;

        // Process each client and combine their information
        foreach ($clientsWithHoldedId as $client) {
            $clientInfo = $documentsController->getClientServicesInfo($client->holded_id);

            if ($clientInfo['success'] && !empty($clientInfo['services'])) {
                $foundServices = true;

                // Add a separator between multiple clients
                if (!empty($combinedResponse)) {
                    $combinedResponse .= "\n\n-------------------------------------------\n\n";
                }

                $combinedResponse .= $clientInfo['formatted_text'];
            }
        }

        // If no services were found for any client, show a generic message
        if (!$foundServices) {
            return response()->json([
                'response_type' => 'ephemeral',
                'text' => 'No se encontraron servicios contratados para ninguno de los clientes con ID interno: ' . $client_internal_id
            ]);
        }

        // Send the combined formatted response back to Slack
        return response()->json([
            'response_type' => 'ephemeral',
            'text' => $combinedResponse
        ]);
    }
}
