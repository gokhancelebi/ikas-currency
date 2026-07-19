<?php

namespace App\Http\Controllers;

use App\Lib\IkasSync\RateService;
use App\Models\Product;
use App\Models\Variation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function __construct(private RateService $rates)
    {
    }

    public function index(Request $request)
    {
        $filters = [
            'search' => $request->string('search')->trim()->toString(),
            'cost' => $request->input('cost', 'all'),
            'sync' => $request->input('sync', 'all'),
            'type' => $request->input('type', 'all'),
            'multiple_price' => $request->input('multiple_price', 'all'),
            'price_type' => $request->input('price_type', 'all'),
            'ikas_status' => $request->input('ikas_status', 'all'),
            'attention' => $request->boolean('attention'),
        ];

        $products = Product::query()
            ->withCount([
                'variations',
                'variations as variations_missing_cost_count' => function ($q) {
                    $q->whereNull('price')->orWhere('price', '');
                },
                'variations as variations_sync_off_count' => function ($q) {
                    $q->where('sync_enabled', false);
                },
            ])
            ->filterSearch($filters['search'])
            ->when($filters['attention'], fn ($q) => $q->needsAttention())
            ->filterCost($filters['cost'])
            ->filterSync($filters['sync'])
            ->filterType($filters['type'])
            ->filterMultiplePrice($filters['multiple_price'])
            ->filterPriceType($filters['price_type'])
            ->filterIkasStatus($filters['ikas_status'])
            ->orderBy('id', 'desc');

        $ratesStatus = $this->rates->inspectRatesForUi();

        $last_update_start = 0;
        $last_update = 0;
        $last_update_file = public_path('last_update.txt');
        if (is_readable($last_update_file)) {
            $parts = explode('-', trim((string) file_get_contents($last_update_file)), 2);
            $last_update_start = (int) ($parts[0] ?? 0);
            $last_update = (int) ($parts[1] ?? 0);
        }

        $products = $products->paginate(20)->withQueryString();

        return view('products.index', [
            'products' => $products,
            'filters' => $filters,
            'filtersActive' => $this->filtersAreActive($filters),
            'ratesReady' => $ratesStatus['ready'],
            'ratesMessage' => $ratesStatus['message'],
            'kurlar' => $ratesStatus['kurlar'],
            'altin' => $ratesStatus['altin'],
            'last_update' => $last_update,
            'last_update_start' => $last_update_start,
        ]);
    }

    public function show(int $id): View
    {
        $product = Product::withCount('variations')
            ->with(['variations' => fn ($q) => $q->orderBy('name')])
            ->findOrFail($id);

        return view('products.show', compact('product'));
    }

    public function edit(int $id): View
    {
        $product = Product::withCount('variations')->findOrFail($id);

        return view('products.edit', compact('product'));
    }

    public function editVariation(int $product, int $variation): View
    {
        $product = Product::withCount('variations')->findOrFail($product);
        $variation = Variation::where('id', $variation)
            ->where('ikas_product_id', $product->ikas_product_id)
            ->firstOrFail();

        return view('products.variations.edit', compact('product', 'variation'));
    }

    public function update(Request $request, int $id): RedirectResponse|\Illuminate\Http\JsonResponse
    {
        if ($request->filled('variation_id')) {
            $product = Product::findOrFail($id);
            $variation = Variation::where('id', $request->input('variation_id'))
                ->where('ikas_product_id', $product->ikas_product_id)
                ->firstOrFail();
            $variation->update($this->variationFieldsFromRequest($request));

            if ($request->expectsJson()) {
                return response()->json([
                    'status' => 'success',
                    'msg' => __('products.messages.variation_updated_json', ['name' => $variation->name]),
                ]);
            }

            return redirect()
                ->route('products.variations.edit', [$product->id, $variation->id])
                ->with('status', __('products.messages.variation_updated'));
        }

        $product = Product::findOrFail($id);
        $product->update($this->productFieldsFromRequest($request));

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'msg' => __('products.messages.product_updated_json', ['sku' => $product->sku]),
            ]);
        }

        return redirect()
            ->route('products.show', $product->id)
            ->with('status', __('products.messages.product_updated'));
    }

    /** @return array<string, mixed> */
    private function productFieldsFromRequest(Request $request): array
    {
        $fields = $request->only([
            'price_type', 'discount', 'profit', 'commission',
            'total_price', 'comparison_price', 'multiple_price',
        ]);

        $fields['price'] = $this->normalizeCost($request->input('price'));
        $fields['sync_enabled'] = $request->boolean('sync_enabled');

        return $fields;
    }

    /** @return array<string, mixed> */
    private function variationFieldsFromRequest(Request $request): array
    {
        $fields = $request->only(['price_type', 'discount', 'profit', 'commission', 'total_price', 'comparison_price']);

        $fields['price'] = $this->normalizeCost($request->input('price'));
        $fields['sync_enabled'] = $request->boolean('sync_enabled');

        return $fields;
    }

    private function normalizeCost(mixed $value): ?string
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }

        return (string) $value;
    }

    /** Herhangi bir liste filtresi uygulanmış mı */
    private function filtersAreActive(array $filters): bool
    {
        return $filters['search'] !== ''
            || $filters['cost'] !== 'all'
            || $filters['sync'] !== 'all'
            || $filters['type'] !== 'all'
            || $filters['multiple_price'] !== 'all'
            || $filters['price_type'] !== 'all'
            || $filters['ikas_status'] !== 'all'
            || $filters['attention'];
    }

    public function bulk_update(Request $request)
    {
        $fields = [];

        if ($request->filled('price_type')) {
            $fields['price_type'] = $request->input('price_type');
        }
        if ($request->filled('multiple_price')) {
            $fields['multiple_price'] = $request->input('multiple_price');
        }
        if ($request->has('sync_enabled') && $request->input('sync_enabled') !== '') {
            $fields['sync_enabled'] = $request->boolean('sync_enabled');
        }
        if ($request->has('price')) {
            $fields['price'] = $this->normalizeCost($request->input('price'));
        }
        if ($request->filled('discount')) {
            $fields['discount'] = $request->input('discount');
        }
        if ($request->filled('profit')) {
            $fields['profit'] = $request->input('profit');
        }

        if (! $request->has('product_ids')) {
            return response()->json(['status' => 'error', 'msg' => __('products.messages.no_products_selected')]);
        }

        if ($fields === []) {
            return response()->json(['status' => 'error', 'msg' => __('products.messages.no_fields_to_update')]);
        }

        $index = 0;
        foreach ($request->post('product_ids') as $pid) {
            $product = Product::find($pid);
            if ($product) {
                $product->update($fields);
                $index++;
            }
        }

        return response()->json([
            'status' => 'success',
            'msg' => __('products.messages.bulk_updated', ['count' => $index]),
        ]);
    }
}
