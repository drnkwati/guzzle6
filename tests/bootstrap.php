<?php
namespace GuzzleHttp\Guzzle\Test {
    require __DIR__ . '/../vendor/autoload.php';
    require __DIR__ . '/Server.php';
    use GuzzleHttp\Guzzle\Test\Server;
    Server::start();
    register_shutdown_function(function () { Server::stop(); });
}

// Override curl_setopt_array() to get the last set curl options
namespace GuzzleHttp\Guzzle\Handler {
    function curl_setopt_array($handle, array $options) {
        if (!empty($_SERVER['curl_test'])) {
            $_SERVER['_curl'] = $options;
        } else {
            unset($_SERVER['_curl']);
        }
        \curl_setopt_array($handle, $options);
    }
}