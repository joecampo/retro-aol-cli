<?php

namespace App\Actions;

use App\Enums\ChatPacket;
use App\Enums\PacketToken;
use App\Traits\RemoveListener;
use App\ValueObjects\Packet;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\Concerns\WithAttributes;
use Rahul900Day\LaravelConsoleSpinner\Spinner;
use React\EventLoop\Loop;
use React\EventLoop\TimerInterface;
use React\Socket\ConnectionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\ConsoleOutput;

class FetchChatRooms
{
    use AsAction;
    use RemoveListener;
    use WithAttributes;

    protected Spinner $spinner;

    protected TimerInterface $spinnerTimer;

    public function handle(ConnectionInterface $connection): void
    {
        $this->set('packets', collect());
        $this->set('connection', $connection);
        $this->set('console', new ConsoleOutput());

        $this->initializeSpinner();

        $connection->write(Packet::make(ChatPacket::CJ_PACKET->value)->prepare());

        $connection->on('data', function (string $data) {
            with(Packet::make($data), function (Packet $packet) {
                if ($packet->token()?->name === PacketToken::AT->name) {
                    $this->packets->push($packet);
                    $this->startTimer();
                }
            });
        });
    }

    private function parseChatrooms(): Collection
    {
        return $this->packets
            ->filter(fn (Packet $packet) => str_contains($packet->toHex(), '0001000109032000620f13020102010a010101'))
            ->map(fn (Packet $packet) => str($packet->toHex())->after('010101000a'))
            ->flatMap(function ($hex) {
                preg_match_all('/06(\d{2,4})09(.*?)0202010201020001/', $hex, $output);

                return collect($output[1])->zip($output[2])->toArray();
            })
            ->map(fn ($value) => ['people' => intval(hex2binary($value[0])), 'name' => hex2binary($value[1])])
            ->unique();
    }

    private function startTimer(): void
    {
        once(function () {
            Loop::addTimer(5, function () {
                Loop::cancelTimer($this->spinnerTimer);
                $this->removeListener('data', $this->connection);
                $this->console->write("\033[?25h");

                DisplayChatRooms::run($this->connection, $this->parseChatrooms());
            });
        });
    }

    private function initializeSpinner(): void
    {
        $this->spinner = new Spinner($this->outputStyle(), 1000);

        $this->console->write("\033[?25l");
        $this->spinner->setMessage('Fetching chatrooms ????');
        $this->spinner->start();

        $this->spinnerTimer = Loop::addPeriodicTimer(0.003, function () {
            $this->spinner->advance();
        });
    }

    private function outputStyle(): OutputStyle
    {
        return resolve('Illuminate\Console\OutputStyle', [new ArrayInput([]), $this->console]);
    }
}
