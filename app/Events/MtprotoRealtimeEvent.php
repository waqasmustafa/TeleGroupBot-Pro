<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MtprotoRealtimeEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $user_id;
    public $type;
    public $payload;

    /**
     * Create a new event instance.
     *
     * @param int $user_id
     * @param string $type ('message', 'notification', 'campaign')
     * @param array $payload
     * @return void
     */
    public function __construct($user_id, $type, $payload)
    {
        $this->user_id = $user_id;
        $this->type = $type;
        $this->payload = $payload;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        // Public channel to simplify client-side connection
        return ['mtproto-realtime-channel-' . $this->user_id];
    }

    /**
     * The event's broadcast name.
     *
     * @return string
     */
    public function broadcastAs()
    {
        return 'mtproto-realtime-event';
    }
}
