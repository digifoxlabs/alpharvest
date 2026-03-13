<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\MetaCatalogFeedService;
use Illuminate\Http\Response;

class FeedController extends Controller
{
    public function metaProducts(MetaCatalogFeedService $feedService): Response
    {
        $csv = $feedService->writeFeed();

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="meta-products.csv"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function metaPlaceholder(): Response
    {
        $svg = <<<'SVG'
<svg xmlns="http://www.w3.org/2000/svg" width="1200" height="1200" viewBox="0 0 1200 1200" role="img" aria-labelledby="title desc">
  <title id="title">Catalog product placeholder</title>
  <desc id="desc">Fallback artwork for products without uploaded images.</desc>
  <defs>
    <linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#f5ecd8" />
      <stop offset="100%" stop-color="#d6efe3" />
    </linearGradient>
  </defs>
  <rect width="1200" height="1200" fill="url(#bg)" rx="80" />
  <circle cx="600" cy="440" r="180" fill="#0b6b58" opacity="0.12" />
  <rect x="260" y="690" width="680" height="72" rx="36" fill="#0b6b58" opacity="0.18" />
  <rect x="340" y="820" width="520" height="42" rx="21" fill="#0b6b58" opacity="0.12" />
  <path d="M470 380h260c17 0 30 13 30 30v220c0 17-13 30-30 30H470c-17 0-30-13-30-30V410c0-17 13-30 30-30zm50 70a52 52 0 1 0 0 104 52 52 0 0 0 0-104zm-20 150-40 40h280l-90-110-90 90-60-20z" fill="#0b6b58" opacity="0.75" />
</svg>
SVG;

        return response($svg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
