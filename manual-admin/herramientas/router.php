<?php

// Router del servidor embebido de PHP para las capturas. El cifrado TLS lo
// termina socat delante de este servidor, así que aquí se le indica a Laravel
// que la petición llegó por HTTPS, como ocurrirá en el dominio final.
$_SERVER['HTTPS'] = 'on';
$_SERVER['SERVER_PORT'] = '443';
$_SERVER['REQUEST_SCHEME'] = 'https';

return require __DIR__.'/../../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php';
