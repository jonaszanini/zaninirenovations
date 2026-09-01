<?php

namespace Tests\Unit\Support;

use App\Support\QuotationAfterSaveRedirect;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class QuotationAfterSaveRedirectTest extends TestCase
{
    private function request(): Request
    {
        return Request::create('http://zanini.test/admin/quotations/shed/1/edit', 'POST');
    }

    public function test_accepts_admin_relative_paths(): void
    {
        $this->assertSame(
            '/admin/quotations/shed/price-configuration',
            QuotationAfterSaveRedirect::safeUrl('/admin/quotations/shed/price-configuration', $this->request())
        );
    }

    public function test_accepts_same_host_absolute_urls(): void
    {
        $this->assertSame(
            '/admin/client-projects/76#quotation',
            QuotationAfterSaveRedirect::safeUrl('http://zanini.test/admin/client-projects/76#quotation', $this->request())
        );
    }

    public function test_rejects_external_hosts(): void
    {
        $this->assertNull(
            QuotationAfterSaveRedirect::safeUrl('https://evil.example/admin/quotations', $this->request())
        );
    }

    public function test_rejects_non_admin_paths(): void
    {
        $this->assertNull(
            QuotationAfterSaveRedirect::safeUrl('/login', $this->request())
        );
    }
}
