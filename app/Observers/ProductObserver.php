<?php

namespace App\Observers;

use App\Models\Product;
use App\Services\MetaCatalogFeedService;
use App\Services\MetaCatalogSyncService;

class ProductObserver
{
    public function __construct(
        protected MetaCatalogFeedService $feedService,
        protected MetaCatalogSyncService $syncService
    ) {
    }

    public function saved(Product $product): void
    {
        $this->feedService->writeFeed();
        $this->syncService->syncSavedProduct($product);
    }

    public function deleted(Product $product): void
    {
        $this->feedService->writeFeed();
        $this->syncService->deleteProduct($product);
    }
}
