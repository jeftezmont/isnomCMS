<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Database;
use App\Core\ErrorHandler;
use App\Core\Gate;
use App\Models\Media;
use App\Models\NavLink;
use App\Models\Post;
use App\Models\Role;
use App\Models\Setting;
use App\Models\Taxonomy;
use App\Models\User;
use App\Models\WebAuthnCredential;
use App\Services\HealthCheckService;
use App\Services\BackupService;
use App\Services\DeployCheckService;
use App\Services\SetupService;
use App\Services\PublicCache;

final class AdminController extends Controller
{
    private function services(): array
    {
        $pdo = Database::connect($this->config);
        return [new Post($pdo), new Taxonomy($pdo), new Media($pdo, $this->config)];
    }

    private function servicesOrSetup(): ?array
    {
        try {
            return $this->services();
        } catch (\Throwable $exception) {
            ErrorHandler::report($exception, 'admin-services');
            $this->redirect('/admin/setup');
        }
    }

    public function dashboard(): void
    {
        $this->requirePermission('dashboard.view');
        $healthService = new HealthCheckService($this->config);
        $healthReport = $healthService->run();
        $deployReport = (new DeployCheckService($this->config))->run();
        $stats = ['total' => 0, 'published' => 0, 'drafts' => 0, 'categories' => 0];
        $recent = [];
        $contentError = null;

        try {
            [$posts] = $this->services();
            $ownContentOnly = !Gate::allows($this->config, 'posts.edit');
            $stats = $posts->stats($ownContentOnly ? Auth::id() : null);
            $recent = array_slice($posts->allAdmin($ownContentOnly ? ['author_id' => Auth::id()] : []), 0, 6);
        } catch (\Throwable $exception) {
            $contentError = 'El módulo de artículos necesita atención. Revisa Salud del sitio para ver qué tabla o configuración falta.';
            ErrorHandler::report($exception, 'admin-dashboard');
        }

        $healthHighlights = array_values(array_filter([
            $healthService->find($healthReport, 'database_connection'),
            $healthService->find($healthReport, 'uploads_directory'),
            $healthService->find($healthReport, 'php_version'),
            $healthService->find($healthReport, 'turnstile'),
            $healthService->find($healthReport, 'production_mode'),
        ]));

        $this->view('admin/dashboard', [
            'title' => 'Dashboard',
            'stats' => $stats,
            'recent' => $recent,
            'contentError' => $contentError,
            'healthReport' => $healthReport,
            'healthHighlights' => $healthHighlights,
            'deployReport' => $deployReport,
        ], 'admin');
    }

    public function health(): void
    {
        $this->requirePermission('tools.health');
        $this->view('admin/health', [
            'title' => 'Salud del sitio',
            'report' => (new HealthCheckService($this->config))->run(),
        ], 'admin');
    }

    public function deploy(): void
    {
        $this->requirePermission('tools.deploy');
        $this->view('admin/deploy', [
            'title' => 'Listo para Hostinger',
            'report' => (new DeployCheckService($this->config))->run(),
        ], 'admin');
    }

    public function setup(): void
    {
        $setup = new SetupService($this->config);
        $state = $setup->state();
        $authenticated = Auth::check($this->config);

        if ($authenticated && ($state['admin_count'] ?? 0) > 0 && !empty($state['schema']['tables']['roles']['exists'])) {
            $this->requirePermission('tools.setup');
        }

        if (($state['admin_count'] ?? 0) > 0 && !$authenticated) {
            $this->redirect('/admin/login');
        }

        $notice = $_SESSION['_setup_notice'] ?? null;
        $result = $_SESSION['_setup_result'] ?? null;
        unset($_SESSION['_setup_notice'], $_SESSION['_setup_result']);
        $error = null;
        $setupAuthorized = $authenticated
            || !$setup->isProduction()
            || !empty($_SESSION['_setup_authorized']);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            $action = (string) ($_POST['action'] ?? '');

            if ($action === 'unlock') {
                if ($setup->unlock((string) ($_POST['setup_token'] ?? ''))) {
                    $_SESSION['_setup_authorized'] = true;
                    $_SESSION['_setup_notice'] = 'Acceso de instalación habilitado para esta sesión.';
                    $this->redirect('/admin/setup');
                }
                $error = 'La clave de instalación no es válida.';
            } elseif (!$setupAuthorized) {
                $error = 'Autoriza primero esta sesión con SETUP_TOKEN.';
            } else {
                try {
                    if ($action === 'repair_schema') {
                        $_SESSION['_setup_result'] = $setup->repairSchema();
                        $_SESSION['_setup_notice'] = 'La actualización segura del esquema terminó.';
                        $this->redirect('/admin/setup');
                    }
                    if ($action === 'create_admin') {
                        $admin = $setup->createFirstAdmin($_POST);
                        Auth::loginUser((int) $admin['id'], (string) $admin['name']);
                        unset($_SESSION['_setup_authorized']);
                        $_SESSION['_setup_notice'] = 'El primer administrador fue creado correctamente.';
                        $this->redirect('/admin/setup');
                    }
                    $error = 'Acción de instalación no reconocida.';
                } catch (\InvalidArgumentException $exception) {
                    $error = $exception->getMessage();
                } catch (\Throwable $exception) {
                    $errorId = ErrorHandler::report($exception, 'admin-setup');
                    $error = 'No se pudo completar la operación. Error ID: ' . $errorId;
                }
            }

            $state = $setup->state();
            $setupAuthorized = $authenticated || !$setup->isProduction() || !empty($_SESSION['_setup_authorized']);
        }

        $healthReport = (new HealthCheckService($this->config))->run();
        $this->view('admin/setup', [
            'title' => 'Configurar isnomCMS',
            'state' => $state,
            'healthReport' => $healthReport,
            'authenticated' => $authenticated,
            'locked' => !$setupAuthorized,
            'setupTokenConfigured' => $setup->setupTokenConfigured(),
            'notice' => $notice,
            'result' => $result,
            'error' => $error,
        ], $authenticated ? 'admin' : 'admin-auth');
    }

    public function posts(): void
    {
        $this->requirePermission('posts.view');
        $services = $this->servicesOrSetup();
        if (!$services) {
            return;
        }
        [$posts, $tax] = $services;
        $this->view('admin/posts', [
            'title' => 'Artículos',
            'posts' => $posts->allAdmin(array_merge($_GET, Gate::allows($this->config, 'posts.edit') ? [] : ['author_id' => Auth::id()])),
            'categories' => $tax->categories(),
        ], 'admin');
    }

    public function postForm(array $params = []): void
    {
        $id = isset($params['id']) ? (int) $params['id'] : null;
        $this->requirePermission($id ? 'posts.view' : 'posts.create');
        $services = $this->servicesOrSetup();
        if (!$services) {
            return;
        }
        [$posts, $tax, $media] = $services;
        $post = $id ? $posts->find($id) : null;
        if ($id && !$post) {
            http_response_code(404);
            (new SiteController($this->config))->notFound();
            return;
        }
        if ($id && !Gate::allows($this->config, 'posts.edit') && (!Gate::allows($this->config, 'posts.edit_own') || (int) ($post['author_id'] ?? 0) !== Auth::id())) {
            $this->forbidden();
        }
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            $image = $post['featured_image'] ?? '';
            if (!empty($_FILES['featured_upload']['name'])) {
                $image = $media->store($_FILES['featured_upload'], Auth::id() ?? 0) ?: $image;
            }
            $data = [
                'title' => trim($_POST['title'] ?? ''),
                'slug' => $this->slug($_POST['slug'] ?: ($_POST['title'] ?? '')),
                'excerpt' => trim($_POST['excerpt'] ?? ''),
                'content' => trim($_POST['content'] ?? ''),
                'featured_image' => trim($_POST['featured_image'] ?? '') ?: $image,
                'category_id' => $_POST['category_id'] ?? null,
                'status' => Gate::allows($this->config, 'posts.publish') && in_array($_POST['status'] ?? 'draft', ['draft', 'published', 'private'], true) ? $_POST['status'] : 'draft',
                'published_at' => $_POST['published_at'] ? str_replace('T', ' ', $_POST['published_at']) . ':00' : date('Y-m-d H:i:s'),
                'seo_title' => trim($_POST['seo_title'] ?? ''),
                'seo_description' => trim($_POST['seo_description'] ?? ''),
                'og_image' => trim($_POST['og_image'] ?? ''),
            ];
            $savedId = $posts->save($data, Auth::id() ?? 0, $id);
            $posts->syncTags($savedId, $_POST['tags'] ?? '');
            PublicCache::invalidate($this->config);
            $this->redirect('/admin/posts');
        }
        $tagNames = $id ? implode(', ', array_column($posts->tagsFor($id), 'name')) : '';
        $this->view('admin/post-form', [
            'title' => $id ? 'Editar artículo' : 'Nuevo artículo',
            'post' => $post,
            'categories' => $tax->categories(),
            'mediaItems' => $media->all(),
            'tagNames' => $tagNames,
            'canPublish' => Gate::allows($this->config, 'posts.publish'),
        ], 'admin');
    }

    public function deletePost(array $params): void
    {
        $this->requirePermission('posts.delete');
        Csrf::verify();
        $services = $this->servicesOrSetup();
        if (!$services) {
            return;
        }
        [$posts] = $services;
        $posts->delete((int) $params['id']);
        PublicCache::invalidate($this->config);
        $this->redirect('/admin/posts');
    }

    public function media(): void
    {
        $this->requirePermission($_SERVER['REQUEST_METHOD'] === 'POST' ? 'media.create' : 'media.view');
        $services = $this->servicesOrSetup();
        if (!$services) {
            return;
        }
        [, , $media] = $services;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            $media->store($_FILES['image'] ?? [], Auth::id() ?? 0);
            $this->redirect('/admin/media');
        }
        $this->view('admin/media', ['title' => 'Medios', 'items' => $media->all()], 'admin');
    }

    public function users(): void
    {
        $this->requirePermission($_SERVER['REQUEST_METHOD'] === 'POST' ? 'users.create' : 'users.view');
        try {
            $users = new User(Database::connect($this->config));
        } catch (\Throwable) {
            $this->redirect('/admin/setup');
        }

        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            try {
                $canAssignRoles = Gate::allows($this->config, 'users.assign_roles');
                $requestedRole = $canAssignRoles ? (string) ($_POST['role'] ?? 'author') : 'author';
                if ($requestedRole === 'super_admin' && !Gate::allows($this->config, 'roles.manage')) {
                    throw new \InvalidArgumentException('No tienes permiso para asignar Super Admin.');
                }
                $users->create($_POST, $requestedRole, Auth::id());
                $this->redirect('/admin/users');
            } catch (\Throwable $exception) {
                $error = $exception instanceof \PDOException
                    ? 'No se pudo crear el usuario. Revisa que el correo no exista ya.'
                    : $exception->getMessage();
            }
        }

        $this->view('admin/users', [
            'title' => 'Usuarios',
            'users' => $users->all(),
            'roles' => $users->roles(Gate::allows($this->config, 'roles.manage')),
            'canAssignRoles' => Gate::allows($this->config, 'users.assign_roles'),
            'error' => $error,
            'currentUserId' => Auth::id(),
        ], 'admin');
    }

    public function deleteUser(array $params): void
    {
        $this->requirePermission('users.delete');
        Csrf::verify();
        $id = (int) $params['id'];
        if ($id === Auth::id()) {
            $this->redirect('/admin/users');
        }
        try {
            $users = new User(Database::connect($this->config));
            if ($users->roleFor($id) === 'super_admin' && !Gate::allows($this->config, 'roles.manage')) $this->forbidden();
            $users->delete($id);
        } catch (\InvalidArgumentException $exception) {
            $_SESSION['_users_error'] = $exception->getMessage();
        }
        $this->redirect('/admin/users');
    }

    public function updateUserRole(array $params): void
    {
        $this->requirePermission('users.assign_roles');
        Csrf::verify();
        $role = (string) ($_POST['role'] ?? 'author');
        if ($role === 'super_admin' && !Gate::allows($this->config, 'roles.manage')) $this->forbidden();
        try {
            (new User(Database::connect($this->config)))->assignRole((int) $params['id'], $role, Auth::id());
            Gate::clear((int) $params['id']);
        } catch (\InvalidArgumentException $exception) {
            $_SESSION['_users_error'] = $exception->getMessage();
        }
        $this->redirect('/admin/users');
    }

    public function roles(): void
    {
        $this->requirePermission('roles.manage');
        $model = new Role(Database::connect($this->config));
        $message = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            $model->replacePermissions((string) ($_POST['role'] ?? ''), is_array($_POST['permissions'] ?? null) ? $_POST['permissions'] : []);
            Gate::clear();
            $message = 'Permisos actualizados.';
        }
        $this->view('admin/roles', ['title' => 'Roles y permisos', 'roles' => $model->all(), 'permissions' => $model->permissions(), 'message' => $message], 'admin');
    }

    public function passkeys(): void
    {
        $this->redirect('/admin/security');
    }

    public function deletePasskey(array $params): void
    {
        $this->requirePermission('security.view');
        Csrf::verify();
        (new WebAuthnCredential(Database::connect($this->config)))->deleteForUser((int) $params['id'], Auth::id() ?? 0);
        $this->redirect('/admin/security');
    }

    public function settings(): void
    {
        $this->requirePermission($_SERVER['REQUEST_METHOD'] === 'POST' ? 'settings.edit' : 'settings.view');
        try {
            $pdo = Database::connect($this->config);
            $settings = new Setting($pdo);
            $nav = new NavLink($pdo);
        } catch (\Throwable) {
            $this->redirect('/admin/setup');
        }

        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            try {
                $socialLinks = $this->pairedLinks($_POST['social_label'] ?? [], $_POST['social_url'] ?? []);
                $settings->saveMany([
                    'accent' => preg_match('/^#[0-9a-f]{6}$/i', $_POST['accent'] ?? '') ? $_POST['accent'] : '#ff4f9a',
                    'accent_soft' => preg_match('/^#[0-9a-f]{6}$/i', $_POST['accent_soft'] ?? '') ? $_POST['accent_soft'] : '#ffe5f0',
                    'coming_soon_mode' => !empty($_POST['coming_soon_mode']) ? '1' : '0',
                    'home_bio_1' => trim($_POST['home_bio_1'] ?? ''),
                    'home_bio_2' => trim($_POST['home_bio_2'] ?? ''),
                    'home_bio_3' => trim($_POST['home_bio_3'] ?? ''),
                    'social_links' => json_encode($socialLinks, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'discord_url' => trim($_POST['discord_url'] ?? ''),
                    'instagram_url' => trim($_POST['instagram_url'] ?? ''),
                    'soundcloud_url' => trim($_POST['soundcloud_url'] ?? ''),
                    'threads_url' => trim($_POST['threads_url'] ?? ''),
                ]);
                $nav->replaceBlog($_POST['nav_label'] ?? [], $_POST['nav_url'] ?? []);
                PublicCache::invalidate($this->config);
                $this->redirect('/admin/settings');
            } catch (\Throwable $exception) {
                ErrorHandler::report($exception, 'admin-settings');
                $error = 'No se pudieron guardar los ajustes. Revisa que la base de datos tenga las tablas site_settings y nav_links.';
            }
        }

        $this->view('admin/settings', [
            'title' => 'Ajustes',
            'settings' => $settings->all(),
            'blogNav' => $nav->forBlog(),
            'error' => $error,
        ], 'admin');
    }

    private function pairedLinks(array $labels, array $urls): array
    {
        $links = [];
        $total = max(count($labels), count($urls));
        for ($i = 0; $i < $total; $i++) {
            $label = trim((string) ($labels[$i] ?? ''));
            $url = trim((string) ($urls[$i] ?? ''));
            if ($label === '' || $url === '') {
                continue;
            }
            $links[] = ['label' => $label, 'url' => $url];
        }
        return $links;
    }

    public function exportPosts(): void
    {
        $this->requirePermission('tools.backups');
        $services = $this->servicesOrSetup();
        if (!$services) {
            return;
        }
        [$posts] = $services;
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="jeftezmont-posts-' . date('Y-m-d') . '.json"');
        echo json_encode($posts->allAdmin(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function previewPostMarkdown(): void
    {
        $this->requirePermission('posts.create');
        if (!Csrf::valid((string) ($_POST['_csrf'] ?? ''))) {
            $this->json(['error' => 'CSRF token inválido.'], 419);
        }

        $content = (string) ($_POST['content'] ?? '');
        $this->json(['html' => markdownish($content)]);
    }

    public function backups(): void
    {
        $this->requirePermission('tools.backups');
        try {
            $backup = new BackupService(Database::connect($this->config), $this->config);
        } catch (\Throwable) {
            $this->redirect('/admin/setup');
        }

        $this->view('admin/backups', [
            'title' => 'Backups',
            'summary' => $backup->summary(),
        ], 'admin');
    }

    public function downloadBackup(): void
    {
        $this->requirePermission('tools.backups');
        Csrf::verify();
        $format = (string) ($_GET['format'] ?? 'json');
        $backup = new BackupService(Database::connect($this->config), $this->config);
        $date = date('Y-m-d-His');

        if ($format === 'zip') {
            $zipPath = $backup->zip();
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="isnomcms-backup-' . $date . '.zip"');
            header('Content-Length: ' . filesize($zipPath));
            readfile($zipPath);
            unlink($zipPath);
            return;
        }

        if ($format === 'sql') {
            header('Content-Type: application/sql; charset=utf-8');
            header('Content-Disposition: attachment; filename="isnomcms-backup-' . $date . '.sql"');
            echo $backup->sql();
            return;
        }

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="isnomcms-backup-' . $date . '.json"');
        echo json_encode($backup->json(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function deleteMedia(array $params): void
    {
        $this->requirePermission('media.delete');
        Csrf::verify();
        $services = $this->servicesOrSetup();
        if (!$services) {
            return;
        }
        [, , $media] = $services;
        $media->delete((int) $params['id']);
        $this->redirect('/admin/media');
    }

    public function categories(): void
    {
        $this->requirePermission('taxonomy.manage');
        $services = $this->servicesOrSetup();
        if (!$services) {
            return;
        }
        [, $tax] = $services;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            $tax->saveCategory($_POST);
            PublicCache::invalidate($this->config);
            $this->redirect('/admin/categories');
        }
        $this->view('admin/taxonomy', ['title' => 'Categorías', 'type' => 'categories', 'items' => $tax->categories()], 'admin');
    }

    public function deleteCategory(array $params): void
    {
        $this->requirePermission('taxonomy.manage');
        Csrf::verify();
        $services = $this->servicesOrSetup();
        if (!$services) {
            return;
        }
        [, $tax] = $services;
        $tax->deleteCategory((int) $params['id']);
        PublicCache::invalidate($this->config);
        $this->redirect('/admin/categories');
    }

    public function tags(): void
    {
        $this->requirePermission('taxonomy.manage');
        $services = $this->servicesOrSetup();
        if (!$services) {
            return;
        }
        [, $tax] = $services;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            Csrf::verify();
            $tax->saveTag($_POST);
            PublicCache::invalidate($this->config);
            $this->redirect('/admin/tags');
        }
        $this->view('admin/taxonomy', ['title' => 'Etiquetas', 'type' => 'tags', 'items' => $tax->tags()], 'admin');
    }

    public function deleteTag(array $params): void
    {
        $this->requirePermission('taxonomy.manage');
        Csrf::verify();
        $services = $this->servicesOrSetup();
        if (!$services) {
            return;
        }
        [, $tax] = $services;
        $tax->deleteTag((int) $params['id']);
        PublicCache::invalidate($this->config);
        $this->redirect('/admin/tags');
    }

    private function slug(string $value): string
    {
        return strtolower(trim(preg_replace('/[^a-z0-9]+/i', '-', iconv('UTF-8', 'ASCII//TRANSLIT', $value)), '-'));
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function forbidden(): never
    {
        http_response_code(403);
        (new SiteController($this->config))->forbidden();
        exit;
    }
}
