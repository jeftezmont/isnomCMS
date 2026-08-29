<?php

namespace App\Core;

abstract class Controller
{
    public function __construct(protected array $config)
    {
    }

    protected function view(string $view, array $data = [], string $layout = 'site'): void
    {
        $config = $this->config;
        extract($data, EXTR_SKIP);
        ob_start();
        require APP_PATH . "/Views/{$view}.php";
        $content = ob_get_clean();
        require APP_PATH . "/Views/layouts/{$layout}.php";
    }

    protected function redirect(string $path): never
    {
        header('Location: ' . url($path));
        exit;
    }

    protected function requireAuth(): void
    {
        if (!Auth::check($this->config)) {
            $this->redirect('/admin/login');
        }
    }

    protected function requirePermission(string $permission): void
    {
        $this->requireAuth();
        if (!Gate::allows($this->config, $permission)) {
            http_response_code(403);
            (new \App\Controllers\SiteController($this->config))->forbidden();
            exit;
        }
    }
}
