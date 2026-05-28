<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Core\Config;

$host = (string) Config::get('WS_HOST', '0.0.0.0');
$port = (int) Config::get('WS_PORT', '8090');
$server = stream_socket_server("tcp://{$host}:{$port}", $errno, $errstr);
if (!$server) {
    fwrite(STDERR, "WebSocket error: {$errstr} ({$errno})" . PHP_EOL);
    exit(1);
}

stream_set_blocking($server, false);
$clients = [];
$lastOffset = 0;
$eventsFile = BASE_PATH . '/storage/cache/realtime.jsonl';
echo "Evasion ERP WebSocket listening on {$host}:{$port}" . PHP_EOL;

while (true) {
    $read = array_merge([$server], $clients);
    $write = $except = [];
    @stream_select($read, $write, $except, 1);

    foreach ($read as $socket) {
        if ($socket === $server) {
            $client = @stream_socket_accept($server, 0);
            if ($client) {
                stream_set_blocking($client, false);
                $headers = fread($client, 2048) ?: '';
                if (preg_match('/Sec-WebSocket-Key:\s*(.*)\r\n/i', $headers, $matches)) {
                    $accept = base64_encode(sha1(trim($matches[1]) . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
                    fwrite($client, "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: {$accept}\r\n\r\n");
                    $clients[(int) $client] = $client;
                    fwrite($client, frame(json_encode(['event' => 'connected', 'message' => 'Evasion ERP live'])));
                } else {
                    fclose($client);
                }
            }
            continue;
        }

        $data = fread($socket, 2048);
        if ($data === '' || $data === false) {
            unset($clients[(int) $socket]);
            fclose($socket);
        }
    }

    if (is_file($eventsFile)) {
        clearstatcache(true, $eventsFile);
        $size = filesize($eventsFile) ?: 0;
        if ($size < $lastOffset) {
            $lastOffset = 0;
        }
        if ($size > $lastOffset) {
            $handle = fopen($eventsFile, 'r');
            if ($handle) {
                fseek($handle, $lastOffset);
                while (($line = fgets($handle)) !== false) {
                    broadcast($clients, trim($line));
                }
                $lastOffset = ftell($handle) ?: $size;
                fclose($handle);
            }
        }
    }
}

function broadcast(array &$clients, string $message): void
{
    if ($message === '') {
        return;
    }
    $frame = frame($message);
    foreach ($clients as $key => $client) {
        if (@fwrite($client, $frame) === false) {
            unset($clients[$key]);
            fclose($client);
        }
    }
}

function frame(string $payload): string
{
    $length = strlen($payload);
    if ($length <= 125) {
        return chr(129) . chr($length) . $payload;
    }
    if ($length <= 65535) {
        return chr(129) . chr(126) . pack('n', $length) . $payload;
    }

    return chr(129) . chr(127) . pack('J', $length) . $payload;
}
