<?php

namespace App\Http\Controllers;

use Pusher\Pusher;
use App\Models\User;
use Illuminate\Http\Request;

use App\Events\MakeAgoraCall;
use Illuminate\Support\Facades\Auth;
use App\Classes\AgoraDynamicKey\RtcTokenBuilder;
use App\Events\DeclineAgoraCall;
use App\Events\EndAgoraCall;

class AgoraVideoController extends Controller
{
    public function index(Request $request)
    {

        $user = $request->user();
        $others = User::where('id', '!=', $user->id)->pluck('name', 'id');


        // fetch all users apart from the authenticated user
        $users = User::where('id', '<>', Auth::id())->get();
        return view('agora-chat', ['users' => $users])->with([
            'user' => collect($request->user()->only(['id', 'name'])),
            'others' => $others
        ]);
    }

    public function auth(Request $request) {
        $user = $request->user();
        $socket_id = $request->socket_id;
        $channel_name = $request->channel_name;
        $pusher = new Pusher(
            config('broadcasting.connections.pusher.key'),
            config('broadcasting.connections.pusher.secret'),
            config('broadcasting.connections.pusher.app_id'),
            [
                'cluster' => config('broadcasting.connections.pusher.options.cluster'),
                'encrypted' => true
            ]
        );
        return response(
            $pusher->presence_auth($channel_name, $socket_id, $user->id)
        );
    }

    public function token(Request $request)
    {

        $appID = env('AGORA_APP_ID');
        $appCertificate = env('AGORA_APP_CERTIFICATE');
        $channelName = $request->channelName;
        $user = Auth::user()->name;
        $role = RtcTokenBuilder::RoleAttendee;
        $expireTimeInSeconds = 3600;
        $currentTimestamp = now()->getTimestamp();
        $privilegeExpiredTs = $currentTimestamp + $expireTimeInSeconds;

        $token = RtcTokenBuilder::buildTokenWithUserAccount($appID, $appCertificate, $channelName, $user, $role, $privilegeExpiredTs);

        return $token;
    }

    public function callUser(Request $request)
    {
        $data['userToCall'] = $request->user_to_call;
        $data['channelName'] = $request->channel_name;
        $data['from'] = Auth::id();

        broadcast(new MakeAgoraCall($data))->toOthers();
    }

    public function call_end(Request $request)
    {
        $data['userToCall'] = $request->user_to_call;
        $data['channelName'] = $request->channel_name;
        $data['from'] = Auth::id();

        broadcast(new EndAgoraCall($data))->toOthers();
    }

    public function call_decline(Request $request)
    {
        $data['userToCall'] = $request->user_to_call;
        $data['channelName'] = $request->channel_name;
        $data['from'] = Auth::id();

        broadcast(new DeclineAgoraCall($data))->toOthers();
    }
}
