<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Database;
use App\Models\NavLink;
use App\Models\Setting;

final class SiteController extends Controller
{
    public function home(): void
    {
        $settings = $this->settings();
        $bio = array_values(array_filter([
            $settings['home_bio_1'] ?? '',
            $settings['home_bio_2'] ?? '',
            $settings['home_bio_3'] ?? '',
        ])) ?: [
            'Ingeniero en Sistemas, desarrollador web y creador digital con base en la Ciudad de México. Trabajo de manera independiente diseñando y construyendo proyectos donde convergen **tecnología, diseño y creatividad**. Me interesa crear experiencias digitales que no solo funcionen, sino que tengan identidad, intención y una razón de ser.',
            'Mi trabajo explora el encuentro entre **código y diseño, ingeniería y creatividad, música e imagen, tecnología y significado**. Fuera de lo digital, encuentro en la fotografía, el podcasting, la lectura y la escritura creativa otras formas de explorar y comunicar ideas. También escribo y reflexiono sobre tecnología, música, cultura, diseño y teología.',
            'Creo en las ideas que nacen de la intuición, pero se sostienen con estructura, estrategia y atención al detalle. Ya sea una interfaz, una identidad, una fotografía, un texto o una experiencia completa, busco convertir ideas en algo que **funcione, comunique y signifique**.',
        ];
        $projects = [
            ['Starlight Studios', 'https://isnom.org'],
            ['Radio Michi', 'https://radiomichi.net'],
            ['Worldwide Radio', 'https://isnom.org'],
            ['ISNOM Records', 'https://isnom.org'],
            ['ISNOM Studios', 'https://isnom.org'],
            ['IDEEN', 'https://isnom.org'],
        ];
        $discordUrl = $settings['discord_url'] ?? 'https://discord.gg/nCRrSAwVph';
        $this->view('public/home', compact('bio', 'projects', 'discordUrl'), 'site');
    }

    private function settings(): array
    {
        try {
            return (new Setting(Database::connect($this->config)))->all();
        } catch (\Throwable) {
            return [];
        }
    }

    public function notFound(): void
    {
        $this->errorPage(404, 'Página no encontrada', 'No pudimos encontrar la página que buscas.', 'Puede que el enlace esté roto o que la página haya sido movida.');
    }

    public function forbidden(): void
    {
        http_response_code(403);
        $this->errorPage(403, 'Acceso no autorizado', 'No tienes permisos para acceder a esta página.', 'Si crees que esto es un error, intenta iniciar sesión.');
    }

    public function serverError(?string $errorId = null, ?string $technicalDetail = null): void
    {
        http_response_code(500);
        $this->errorPage(
            500,
            'Error del servidor',
            'El CMS encontró un problema al realizar esta operación.',
            $technicalDetail ?: 'Estamos trabajando para solucionarlo. Inténtalo de nuevo más tarde.',
            null,
            $errorId
        );
    }

    public function comingSoon(): void
    {
        $this->errorPage(null, 'Algo increíble se está cocinando', 'Estamos trabajando en esta sección del sitio.', 'Vuelve pronto para descubrirlo.', 'Próximamente');
    }

    private function errorPage(?int $code, string $heading, string $body, string $detail, ?string $eyebrow = null, ?string $errorId = null): void
    {
        $this->view('public/error', [
            'title' => $heading,
            'code' => $code,
            'heading' => $heading,
            'body' => $body,
            'detail' => $detail,
            'eyebrow' => $eyebrow,
            'errorId' => $errorId,
            'blogNav' => $this->blogNav(),
        ], 'site');
    }

    private function blogNav(): array
    {
        try {
            $items = (new NavLink(Database::connect($this->config)))->forBlog();
        } catch (\Throwable) {
            $items = [];
        }
        return $items ?: [
            ['label' => 'Inicio', 'url' => '/blog'],
            ['label' => 'Tecnología', 'url' => '/blog?category=tecnologia'],
            ['label' => 'Teología', 'url' => '/blog?category=teologia'],
            ['label' => 'Música', 'url' => '/blog?category=musica'],
        ];
    }
}
