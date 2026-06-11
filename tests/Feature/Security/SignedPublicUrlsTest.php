<?php

namespace Tests\Feature\Security;

use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use Illuminate\Support\Facades\URL;
use Tests\IntegrationTestCase;

class SignedPublicUrlsTest extends IntegrationTestCase
{
    public function test_unsigned_invoice_pdf_url_is_rejected(): void
    {
        $project = ClientProject::factory()->create();
        $invoice = ClientProjectInvoice::factory()->for($project)->create();

        $this->get(route('public.client-project.invoice-pdf', [$project, $invoice]))
            ->assertForbidden();
    }

    public function test_valid_signed_invoice_pdf_url_is_allowed(): void
    {
        $project = ClientProject::factory()->create();
        $invoice = ClientProjectInvoice::factory()->for($project)->create();

        $url = URL::temporarySignedRoute(
            'public.client-project.invoice-pdf',
            now()->addHour(),
            ['client_project' => $project, 'invoice' => $invoice],
        );

        $this->get($url)->assertOk();
    }
}
