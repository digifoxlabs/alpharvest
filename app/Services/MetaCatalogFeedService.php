<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Support\Facades\Storage;

class MetaCatalogFeedService
{
    public const FEED_PATH = 'feeds/meta-products.csv';

    protected array $headers = [
        'id',
        'title',
        'description',
        'availability',
        'condition',
        'price',
        'link',
        'image_link',
        'brand',
        'google_product_category',
        'fb_product_category',
        'quantity_to_sell_on_facebook',
        'sale_price',
        'sale_price_effective_date',
        'item_group_id',
        'gender',
        'color',
        'size',
        'age_group',
        'material',
        'pattern',
        'shipping',
        'shipping_weight',
        'video[0].url',
        'video[0].tag[0]',
        'gtin',
        'product_tags[0]',
        'product_tags[1]',
        'style[0]',
    ];

    public function generateCsvString(): string
    {
        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, $this->headers);

        foreach ($this->feedRows() as $row) {
            fputcsv($stream, $row);
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);

        fclose($stream);

        return $csv;
    }

    public function writeFeed(): string
    {
        $csv = $this->generateCsvString();

        Storage::disk('public')->put(self::FEED_PATH, $csv);

        return $csv;
    }

    public function batchItemData(Product $product): array
    {
        $store = $product->store;
        $category = $product->category;
        $price = $this->priceValue($product);

        return array_filter([
            'retailer_id' => $this->catalogProductId($product),
            'name' => $product->name,
            'description' => $product->description ?: $product->name,
            'availability' => $this->availability($product),
            'condition' => 'new',
            'price' => (string) $price,
            'currency' => $store->currency,
            'url' => $this->productLink($product),
            'image_url' => $this->imageLink($product),
            'brand' => $store->whatsapp_brand_name ?: $store->name,
            'category' => $category?->name,
            'inventory' => $product->inventory_quantity,
        ], fn ($value) => $value !== null && $value !== '');
    }

    protected function feedRows(): array
    {
        return Product::query()
            ->with(['store', 'category'])
            ->where('is_active', true)
            ->whereHas('store', fn ($query) => $query->where('is_active', true))
            ->orderBy('store_id')
            ->orderBy('name')
            ->get()
            ->map(fn (Product $product) => $this->feedRow($product))
            ->all();
    }

    protected function feedRow(Product $product): array
    {
        $store = $product->store;
        $category = $product->category;
        $metadata = $product->metadata ?? [];
        $price = $this->priceValue($product);
        $salePrice = $this->salePriceValue($product);

        return [
            'id' => $this->catalogProductId($product),
            'title' => $product->name,
            'description' => $product->description ?: $product->name,
            'availability' => $this->availability($product),
            'condition' => 'new',
            'price' => number_format($price, 2, '.', '').' '.$store->currency,
            'link' => $this->productLink($product),
            'image_link' => $this->imageLink($product),
            'brand' => $store->whatsapp_brand_name ?: $store->name,
            'google_product_category' => $metadata['google_product_category'] ?? $category?->name ?? '',
            'fb_product_category' => $metadata['fb_product_category'] ?? $category?->name ?? '',
            'quantity_to_sell_on_facebook' => max($product->inventory_quantity, 0),
            'sale_price' => $salePrice ? number_format($salePrice, 2, '.', '').' '.$store->currency : '',
            'sale_price_effective_date' => $metadata['sale_price_effective_date'] ?? '',
            'item_group_id' => $metadata['item_group_id'] ?? '',
            'gender' => $metadata['gender'] ?? '',
            'color' => $product->color ?: ($metadata['color'] ?? ''),
            'size' => $product->size ?: ($metadata['size'] ?? ''),
            'age_group' => $metadata['age_group'] ?? '',
            'material' => $metadata['material'] ?? '',
            'pattern' => $metadata['pattern'] ?? '',
            'shipping' => $metadata['shipping'] ?? '',
            'shipping_weight' => $product->shipping_weight ? number_format((float) $product->shipping_weight, 2, '.', '').' kg' : ($metadata['shipping_weight'] ?? ''),
            'video[0].url' => $metadata['video_url'] ?? '',
            'video[0].tag[0]' => $metadata['video_tag'] ?? '',
            'gtin' => $metadata['gtin'] ?? '',
            'product_tags[0]' => $category?->name ?? '',
            'product_tags[1]' => $store->slug,
            'style[0]' => $metadata['style'] ?? '',
        ];
    }

    protected function catalogProductId(Product $product): string
    {
        return $product->meta_retailer_id ?: $product->sku;
    }

    protected function availability(Product $product): string
    {
        return $product->inventory_quantity > 0 ? 'in stock' : 'out of stock';
    }

    protected function priceValue(Product $product): float
    {
        return (float) $product->price;
    }

    protected function salePriceValue(Product $product): ?float
    {
        if ($product->sale_price && $product->sale_price > 0 && $product->sale_price <= $product->price) {
            return (float) $product->sale_price;
        }

        return null;
    }

    protected function productLink(Product $product): string
    {
        return route('platform.products.show', [
            'store' => $product->store,
            'product' => $product,
        ]);
    }

    protected function imageLink(Product $product): string
    {
        return $product->image_url
            ?: $product->store->whatsapp_store_image_url
            ?: route('feeds.meta-placeholder');
    }
}
