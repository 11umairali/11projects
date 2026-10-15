<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $urls = [
            ['loc' => url('/'), 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => url('/login'), 'changefreq' => 'monthly', 'priority' => '0.5'],
            ['loc' => url('/register'), 'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => url('/legal/terms'), 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => url('/legal/privacy'), 'changefreq' => 'yearly', 'priority' => '0.3'],
            ['loc' => url('/legal/refund'), 'changefreq' => 'yearly', 'priority' => '0.3'],
        ];

        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $url) {
            $xml .= '<url>';
            $xml .= '<loc>' . htmlspecialchars($url['loc']) . '</loc>';
            $xml .= '<changefreq>' . $url['changefreq'] . '</changefreq>';
            $xml .= '<priority>' . $url['priority'] . '</priority>';
            $xml .= '</url>';
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
