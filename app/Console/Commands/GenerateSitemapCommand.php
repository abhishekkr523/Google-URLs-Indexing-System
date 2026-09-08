<?php

namespace App\Console\Commands;

use App\Models\UrlSubmission;
use Illuminate\Console\Command;

class GenerateSitemapCommand extends Command
{
    protected $signature   = 'sitemap:generate';
    protected $description = 'Generate a static public/sitemap.xml from all submitted URLs';

    public function handle(): int
    {
        $urls = UrlSubmission::latest()->pluck('url');

        $xml  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . htmlspecialchars($url, ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</loc>\n";
            $xml .= "    <changefreq>weekly</changefreq>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';

        $path = public_path('sitemap.xml');
        file_put_contents($path, $xml);

        $this->info("sitemap.xml generated with {$urls->count()} URLs → {$path}");
        $this->line('Upload this file to your htdocs root: https://powerhousethegym.site.je/sitemap.xml');

        return self::SUCCESS;
    }
}
