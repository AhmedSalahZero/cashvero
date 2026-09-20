<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Messaging / "Super Message" support chat.
 * A user may listen to a conversation's channel only if they're allowed
 * to see that conversation at all — same rule as Conversation::isVisibleTo().
 */
Broadcast::channel('conversation.{id}', function ($user, $id) {
    $conversation = \App\Models\Conversation::find($id);

    return $conversation ? $conversation->isVisibleTo($user) : false;
});
