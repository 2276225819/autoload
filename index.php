<?php 
ini_set('display_errors','On'); 
ini_set('log_errors','On');
require  __DIR__.'/vendor/autoload.php';

$app = new Illuminate\Foundation\Application(
    realpath(__DIR__ .'/vendor/laravel/laravel/')
); 
$app->singleton(
    Illuminate\Contracts\Http\Kernel::class,
    App\Http\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    App\Console\Kernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    App\Exceptions\Handler::class
); 

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
 
$response = $kernel->handle(
    $request = Illuminate\Http\Request::capture()
);

$response->send();

$kernel->terminate($request, $response);
