<?php
declare(strict_types=1);

namespace App\Support;

use App\Security\Csrf;

final class Router
{
    /** @var array<int, array{0:string,1:string,2:array{0:class-string,1:string},3?:array<string,mixed>}> */
    private array $routes = [];

    /**
     * @param array<int, array{0:string,1:string,2:array{0:class-string,1:string},3?:array<string,mixed>}> $routes
     */
    public function __construct(array $routes)
    {
        $this->routes = $routes;
    }

    public function dispatch(Request $request): never
    {
        foreach ($this->routes as $route) {
            [$method, $path, $handler] = $route;
            $options = $route[3] ?? [];

            if ($method !== $request->method || $path !== $request->path) {
                continue;
            }

            if (($options['csrf'] ?? false) === true && !Csrf::validateRequest($request)) {
                Response::json(['ok' => false, 'message' => 'CSRF inválido.'], 419);
            }

            [$controllerClass, $action] = $handler;
            $controller = new $controllerClass();

            $result = $controller->{$action}($request);

            if (is_string($result)) {
                Response::html($result);
            }

            if (is_array($result)) {
                Response::json($result);
            }

            Response::serverError(new \RuntimeException('Resposta inválida do controller.'));
        }

        Response::notFound();
    }
}
