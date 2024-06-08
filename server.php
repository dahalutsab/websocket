<?php
require __DIR__ . '/vendor/autoload.php';

use Ratchet\Http\HttpServer;
use Ratchet\Server\IoServer;
use Ratchet\WebSocket\WsServer;
use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use Ratchet\Http\HttpServerInterface;
use Psr\Http\Message\RequestInterface;
use React\Http\Message\Response;

class Chat implements MessageComponentInterface {
    public function onOpen(ConnectionInterface $conn) {
        echo "New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        echo "Message received: $msg\n";
    }

    public function onClose(ConnectionInterface $conn) {
        echo "Connection {$conn->resourceId} has disconnected\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "An error has occurred: {$e->getMessage()}\n";
        $conn->close();
    }
}

class AuthHttpServer implements HttpServerInterface {
    private $httpServer;

    public function __construct(HttpServerInterface $httpServer) {
        $this->httpServer = $httpServer;
    }

    public function onOpen(ConnectionInterface $conn, RequestInterface $request = null) {
        $authHeader = $request->getHeaderLine('Authorization');

        if ($authHeader === 'Basic ' . base64_encode('username:password')) {
            $this->httpServer->onOpen($conn, $request);
        } else {
            $response = new Response(
                401,
                ['WWW-Authenticate' => 'Basic realm="Protected Area"'],
                'Unauthorized'
            );
            $conn->send((string)$response);
            $conn->close();
        }
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $this->httpServer->onMessage($from, $msg);
    }

    public function onClose(ConnectionInterface $conn) {
        $this->httpServer->onClose($conn);
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        $this->httpServer->onError($conn, $e);
    }
}

$server = IoServer::factory(
    new HttpServer(
        new AuthHttpServer(
            new WsServer(
                new Chat()
            )
        )
    ),
    8080
);

$server->run();
