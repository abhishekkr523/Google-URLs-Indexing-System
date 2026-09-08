<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UrlSubmissionController;
use Illuminate\Support\Facades\Route;
use App\Models\UrlSubmission;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.attempt');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [UrlSubmissionController::class, 'index'])->name('dashboard');
    Route::post('/urls', [UrlSubmissionController::class, 'store'])->name('urls.store');
    Route::get('/urls/{urlSubmission}', [UrlSubmissionController::class, 'show'])->name('urls.show');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', [AdminController::class, 'index'])->name('index');
        Route::get('/urls/{urlSubmission}', [AdminController::class, 'show'])->name('urls.show');
    });
});



Route::get('/crawl-bridge', function () {
    // Expose the latest accepted submissions for Googlebot traversal
    $urls = UrlSubmission::whereNotNull('processed_at')
        ->latest('updated_at')
        ->take(100)
        ->pluck('url');

    return view('crawl-bridge', ['urls' => $urls]);
})->name('crawl.bridge');

// Dynamic sitemap containing all submitted URLs — helps Googlebot
// discover these URLs faster via XML sitemap (standard discovery channel).
Route::get('/sitemap.xml', function () {
    $urls = UrlSubmission::latest()->pluck('url');

    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

    foreach ($urls as $url) {
        $xml .= "  <url>\n";
        $xml .= '    <loc>' . htmlspecialchars($url, ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</loc>\n";
        $xml .= "    <changefreq>weekly</changefreq>\n";
        $xml .= "  </url>\n";
    }

    $xml .= '</urlset>';

    return response($xml)->header('Content-Type', 'application/xml');
})->name('sitemap');

// robots.txt — tell Googlebot about our sitemap and crawl-bridge
Route::get('/robots.txt', function () {
    $content = "User-agent: *\n";
    $content .= "Allow: /crawl-bridge\n";
    $content .= "Allow: /sitemap.xml\n";
    $content .= "\n";
    $content .= "Sitemap: " . url('/sitemap.xml') . "\n";

    return response($content)->header('Content-Type', 'text/plain');
})->name('robots');
