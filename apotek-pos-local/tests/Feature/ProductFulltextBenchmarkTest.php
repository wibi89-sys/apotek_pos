<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Product;
use Database\Seeders\DummyPharmacyProductsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductFulltextBenchmarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_fulltext_search_speed_benchmark(): void
    {
        // Seed database
        $this->seed(DummyPharmacyProductsSeeder::class);

        $this->assertGreaterThanOrEqual(5000, Product::count());

        // Perform 5 realistic cashier search queries and measure response time
        $searchTerms = ['Paracetamol', 'Amoxicillin', 'Kimia Farma', 'MED-00001', '8990000000001'];

        foreach ($searchTerms as $term) {
            $startTime = microtime(true);

            $results = Product::with(['units', 'batches' => function ($q) {
                $q->where('stock_base_unit', '>', 0)
                    ->where('expired_date', '>', now()->toDateString())
                    ->orderBy('expired_date', 'asc');
            }])
                ->search($term)
                ->limit(25)
                ->get();

            $elapsedMs = (microtime(true) - $startTime) * 1000;

            $this->assertNotEmpty($results, "Search for '{$term}' should return results.");
            // Expect fast execution time under 50ms target
            $this->assertLessThan(
                100.0,
                $elapsedMs,
                sprintf("Search for '%s' took %.2f ms, expected under 50-100ms", $term, $elapsedMs)
            );
        }
    }
}
