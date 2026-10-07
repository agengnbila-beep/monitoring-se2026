<?php

namespace Tests\Feature;

use App\Models\Dataset;
use App\Models\SavedQuery;
use App\Models\User;
use App\Models\Widget;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\Route;
use Tests\Concerns\CreatesImportedDatasets;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use CreatesImportedDatasets;
    use RefreshDatabase;

    /**
     * Matriks hak akses dibangun dari daftar route, sehingga route admin
     * baru otomatis ikut diuji tanpa perlu menulis test baru.
     */
    public function test_viewer_is_forbidden_on_every_admin_route(): void
    {
        $parameters = $this->routeParameters();
        $viewer = User::factory()->create();

        $adminRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RouteDefinition $route) => in_array('can:admin', $route->gatherMiddleware(), true));

        $this->assertGreaterThan(20, $adminRoutes->count());

        foreach ($adminRoutes as $route) {
            $method = collect($route->methods())->reject(fn (string $m) => $m === 'HEAD')->first();
            $uri = $this->fill($route->uri(), $parameters);

            $this->actingAs($viewer)
                ->json($method, $uri)
                ->assertForbidden();
        }
    }

    /**
     * Route yang bisa dibuka viewer dikunci di sini. Route baru yang lupa
     * dimasukkan ke grup admin akan membuat test ini gagal.
     */
    public function test_only_known_routes_are_open_to_viewers(): void
    {
        $viewerRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RouteDefinition $route) => in_array('auth', $route->gatherMiddleware(), true))
            ->reject(fn (RouteDefinition $route) => in_array('can:admin', $route->gatherMiddleware(), true))
            ->map(fn (RouteDefinition $route) => $route->getName())
            ->sort()
            ->values()
            ->all();

        $this->assertSame([
            'api.dashboards.filter-options',
            'api.dashboards.index',
            'api.dashboards.show',
            'api.widgets.data',
            'dashboard',
            'dashboards.show',
            'logout',
        ], $viewerRoutes);
    }

    public function test_guest_cannot_reach_any_authenticated_route(): void
    {
        $parameters = $this->routeParameters();

        $protected = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RouteDefinition $route) => in_array('auth', $route->gatherMiddleware(), true));

        foreach ($protected as $route) {
            $method = collect($route->methods())->reject(fn (string $m) => $m === 'HEAD')->first();

            $this->json($method, $this->fill($route->uri(), $parameters))->assertUnauthorized();
        }
    }

    public function test_query_runs_are_rate_limited_per_user(): void
    {
        $dataset = $this->importedDataset(['kecamatan' => 'text'], [['kecamatan' => 'Bangli']]);
        $this->actingAs(User::factory()->admin()->create());

        for ($i = 0; $i < 60; $i++) {
            $this->postJson('/api/queries/run', ['dataset_id' => $dataset->id, 'config' => []])->assertOk();
        }

        $this->postJson('/api/queries/run', ['dataset_id' => $dataset->id, 'config' => []])
            ->assertTooManyRequests()
            ->assertJsonPath('message', 'Terlalu banyak permintaan. Tunggu sebentar lalu coba lagi.');
    }

    public function test_responses_carry_security_headers(): void
    {
        $this->get('/login')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_sqlite_database_lives_outside_the_public_folder(): void
    {
        $this->assertStringStartsNotWith(
            public_path(),
            config('database.connections.sqlite.database') === ':memory:'
                ? database_path('database.sqlite')
                : config('database.connections.sqlite.database'),
        );
        $this->assertSame([], glob(public_path('*.sqlite*')));
    }

    /**
     * @return array<string, int|string>
     */
    private function routeParameters(): array
    {
        $widget = Widget::factory()->create();

        return [
            'dataset' => Dataset::factory()->create()->id,
            'savedQuery' => SavedQuery::factory()->create()->id,
            'dashboard' => $widget->dashboard_id,
            'widget' => $widget->id,
            'key' => 'kecamatan',
        ];
    }

    /**
     * @param  array<string, int|string>  $parameters
     */
    private function fill(string $uri, array $parameters): string
    {
        return '/'.preg_replace_callback('/\{(\w+)(?::\w+)?\}/', fn (array $m) => (string) $parameters[$m[1]], $uri);
    }
}
