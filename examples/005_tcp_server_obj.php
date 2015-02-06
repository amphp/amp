<?php

require __DIR__ . '/../vendor/autoload.php';

date_default_timezone_set('UTC'); // F U Derick.
define('SERVER_ADDRESS', '127.0.0.1:1337');


/**
 * A simple struct to hold a client's state while it's connected to our server
 */
class Client {
    public $id;
    public $socket;
    public $readWatcher;
    public $writeWatcher;
    public $outputBuffer;
}


/**
 * A simple TCP server that broadcasts the current time once per 3 seconds to all connected clients
 */
class Server {
    private $reactor;
    private $clients = [];
    private $ioGranularity = 8192;

    public function __construct(Amp\Reactor $reactor = null) {
        $this->reactor = $reactor ?: Amp\getReactor();
    }

    public function start($address) {
        if (!$server = @stream_socket_server($address, $errno, $errstr)) {
            throw new RuntimeException(
                sprintf('Failed binding server on %s; [%d] %s', $address, $errno, $errstr)
            );
        }

        printf("Server socket listening on %s\n", $address);

        stream_set_blocking($server, false);

        $this->reactor->onReadable($server, function() use ($server) {
            $this->acceptClients($server);
        });

        // Let's schedule a broadcast of the current time to all connected sockets every three seconds
        $this->reactor->repeat(function() {
            $this->broadcastTime();
        }, $msInterval = 3000);

        // Release the hounds!
        $this->reactor->run();
    }

    private function acceptClients($server) {
        while ($socket = @stream_socket_accept($server, $timeout=0, $name)) {
            $client = new Client;
            $client->id = (int) $socket;
            $client->socket = $socket;

            // What to do when the client socket is readable
            $client->readWatcher = $this->reactor->onReadable($socket, function() use ($client) {
                $this->readFromClient($client);
            });

            // What to do when the socket is writable
            $client->writeWatcher = $this->reactor->onWritable($socket, function() use ($client) {
                $this->writeToClient($client);
            });

            // Buffer something to send to the client. The writability watcher we just enabled
            // above will take care of sending this data automatically.
            $client->outputBuffer = "--- Welcome to the example server! ---\n\n";

            printf("Client socket accepted: %s\n", $name);

            // Store the client using its integer ID
            $this->clients[$client->id] = $client;
        }
    }

    private function broadcastTime() {
        $data = date('r') . "\n";
        foreach ($this->clients as $client) {
            $client->outputBuffer .= $data;
            $this->reactor->enable($client->writeWatcher);
        }
    }

    private function readFromClient(Client $client) {
        $data = @fread($client->socket, $this->ioGranularity);

        // This only happens on EOF. If the socket has died we need to unload it.
        if ($data == '' && $this->isSocketDead($client->socket)) {
            $this->unloadClient($client);
        } else {
            printf("Data received from client %d: %s\n", $client->id, $data);
        }
    }

    private function isSocketDead($socket) {
        return (!is_resource($socket) || feof($socket));
    }

    private function writeToClient(Client $client) {
        $bytesWritten = @fwrite($client->socket, $client->outputBuffer);

        if ($bytesWritten === strlen($client->outputBuffer)) {
            // All data written. Disable the writability watcher. Sockets are essentially "always"
            // writable, so it's important to disable write watchers when you don't have any data
            // remaining to write. Otherwise you'll just hammer your CPU.
            $client->outputBuffer = '';
            $this->reactor->disable($client->writeWatcher);
        } elseif ($bytesWritten > 0) {
            // Data was partially written -- truncate the buffer
            $client->outputBuffer = substr($client->outputBuffer, $bytesWritten);
        } elseif ($this->isSocketDead($client->socket)) {
            // Otherwise the client is dead and we just unload it
            $this->unloadClient($client);
        }
    }

    /**
     * We have to clean up after ourselves or we'll create memory leaks. Always be sure to cancel
     * any stream IO watchers or repeating timer events once they're no longer needed!
     */
    private function unloadClient(Client $client) {
        $this->reactor->cancel($client->readWatcher);
        $this->reactor->cancel($client->writeWatcher);
        if (is_resource($client->socket)) {
            @fclose($client->socket);
        }
        unset($this->clients[$client->id]);

        printf("Client %d disconnected\n", $client->id);
    }
}

(new Server)->start(SERVER_ADDRESS);
