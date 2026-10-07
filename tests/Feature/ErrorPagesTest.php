<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Tests\TestCase;

class ErrorPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_unknown_url_renders_the_branded_404(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee('We could not pinpoint that page')
            ->assertSee('Error 404');
    }

    public function test_403_shows_a_custom_policy_message_but_hides_the_default_one(): void
    {
        Route::get('/_test/denied-custom', fn () => abort(403, 'Only the owner can edit this address.'));
        Route::get('/_test/denied-default', fn () => abort(403, 'This action is unauthorized.'));

        $this->get('/_test/denied-custom')
            ->assertForbidden()
            ->assertSee('Only the owner can edit this address.');

        $this->get('/_test/denied-default')
            ->assertForbidden()
            ->assertSee('Your role does not allow this action.')
            ->assertDontSee('This action is unauthorized.');
    }

    public function test_expired_csrf_token_renders_the_419_page(): void
    {
        // VerifyCsrfToken always passes under unit tests, so raise the status directly.
        Route::post('/_test/form', fn () => abort(419));

        $this->post('/_test/form')
            ->assertStatus(419)
            ->assertSee('Your session expired');
    }

    public function test_500_never_leaks_the_exception_message(): void
    {
        config(['app.debug' => false]);
        Route::get('/_test/boom', fn () => throw new RuntimeException('secret-internal-detail'));

        $this->get('/_test/boom')
            ->assertStatus(500)
            ->assertSee('Something went wrong on our side')
            ->assertDontSee('secret-internal-detail');
    }

    public function test_maintenance_renders_the_503_page(): void
    {
        Route::get('/_test/down', fn () => abort(503));

        $this->get('/_test/down')
            ->assertStatus(503)
            ->assertSee('Down for maintenance');
    }

    public function test_unlisted_status_falls_back_to_the_catch_all_with_its_code(): void
    {
        Route::get('/_test/gone', fn () => abort(410));

        $this->get('/_test/gone')
            ->assertStatus(410)
            ->assertSee('That request could not be completed')
            ->assertSee('Error 410');
    }

    public function test_json_requests_still_get_json(): void
    {
        $this->getJson('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }
}
