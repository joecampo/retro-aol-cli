<?php

use App\Actions\FetchChatRooms;
use function Clue\React\Block\sleep;
use Illuminate\Console\OutputStyle;
use React\Socket\ConnectionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

it('it can fetch public chatrooms', function () {
    $fetchChatRooms = FetchChatRooms::make();

    app()->bind('Illuminate\Console\OutputStyle', function () {
        return new OutputStyle(new ArrayInput([]), new BufferedOutput());
    });

    $this->client->connect(function (ConnectionInterface $connection) use ($fetchChatRooms) {
        $fetchChatRooms->handle($connection);
    });

    sleep(.1);

    expect(invade($fetchChatRooms)->parseChatrooms()->toArray())->toBe([
        ['people' => 0, 'name' => 'deadend'],
        ['people' => 7, 'name' => 'Welcome'],
        ['people' => 0, 'name' => 'The 8-bit Guy'],
        ['people' => 0, 'name' => 'Tech Linked'],
        ['people' => 0, 'name' => 'Nostalgia Nerd'],
        ['people' => 0, 'name' => 'News'],
    ]);
});
