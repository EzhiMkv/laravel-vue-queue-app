<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\QueueController;
use App\Models\Client;
use App\Models\QueuePosition;
use App\Models\User;
use App\Services\QueueService;
use Tests\TestCase;

class QueueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
    }
    /**
     * A basic test example.
     *
     * @return void
     */
    public function test_queue_is_moving()
    {
        $user = User::where('email', 'admin')->firstOrFail();
        $second_in_queue_client = QueuePosition::where('position', 2)->with('client')->firstOrFail()->client;

        $this->actingAs($user)->get('/api/queue/proceed');
        $second_in_queue_client = $second_in_queue_client->fresh();
        $this->assertTrue($second_in_queue_client->position->position == 1);
    }
}
