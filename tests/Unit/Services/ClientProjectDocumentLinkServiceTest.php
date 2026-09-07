<?php

namespace Tests\Unit\Services;

use App\Models\ClientProjectDocumentLink;
use App\Services\ClientProjectDocumentLinkService;
use PHPUnit\Framework\TestCase;

class ClientProjectDocumentLinkServiceTest extends TestCase
{
    private ClientProjectDocumentLinkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ClientProjectDocumentLinkService;
    }

    public function test_detects_office_extensions(): void
    {
        $this->assertSame(
            ClientProjectDocumentLink::KIND_WORD,
            $this->service->detectKind('https://files.example.com/scope.docx')
        );
        $this->assertSame(
            ClientProjectDocumentLink::KIND_EXCEL,
            $this->service->detectKind('https://files.example.com/takeoff.xlsx?dl=1')
        );
        $this->assertSame(
            ClientProjectDocumentLink::KIND_PPT,
            $this->service->detectKind('https://files.example.com/walkthrough.pptx')
        );
    }

    public function test_detects_google_workspace_urls(): void
    {
        $this->assertSame(
            ClientProjectDocumentLink::KIND_WORD,
            $this->service->detectKind('https://docs.google.com/document/d/abc/edit')
        );
        $this->assertSame(
            ClientProjectDocumentLink::KIND_EXCEL,
            $this->service->detectKind('https://docs.google.com/spreadsheets/d/abc/edit#gid=0')
        );
        $this->assertSame(
            ClientProjectDocumentLink::KIND_PPT,
            $this->service->detectKind('https://docs.google.com/presentation/d/abc/edit')
        );
    }

    public function test_detects_microsoft_share_links(): void
    {
        $this->assertSame(
            ClientProjectDocumentLink::KIND_WORD,
            $this->service->detectKind('https://1drv.ms/w/s!Abcdef')
        );
        $this->assertSame(
            ClientProjectDocumentLink::KIND_EXCEL,
            $this->service->detectKind('https://contoso.sharepoint.com/:x:/g/personal/pat/file')
        );
        $this->assertSame(
            ClientProjectDocumentLink::KIND_PPT,
            $this->service->detectKind('https://contoso.sharepoint.com/:p:/s/Jobs/abc')
        );
    }

    public function test_detects_onedrive_doc_aspx_file_query(): void
    {
        $url = 'https://onedrive.live.com/personal/8acdd0dd7736f10f/_layouts/15/Doc.aspx?sourcedoc=%7BCEBFC3D6-C8B1-414C-941B-654C2E04044C%7D&file=Patricia%20bathroom.pptx&action=edit&mobileredirect=true';

        $this->assertSame(ClientProjectDocumentLink::KIND_PPT, $this->service->detectKind($url));
        $this->assertSame($url, ClientProjectDocumentLinkService::normalizeUrl($url));
        $this->assertSame('Patricia bathroom.pptx', ClientProjectDocumentLinkService::suggestTitle($url));
    }

    public function test_override_wins_over_auto_detect(): void
    {
        $this->assertSame(
            ClientProjectDocumentLink::KIND_EXCEL,
            $this->service->detectKind('https://drive.google.com/file/d/abc/view', 'excel')
        );
    }

    public function test_unknown_url_is_other(): void
    {
        $this->assertSame(
            ClientProjectDocumentLink::KIND_OTHER,
            $this->service->detectKind('https://drive.google.com/file/d/abc/view')
        );
    }

    public function test_normalize_url_adds_https_and_rejects_javascript(): void
    {
        $this->assertSame(
            'https://1drv.ms/x/s!abc',
            ClientProjectDocumentLinkService::normalizeUrl('1drv.ms/x/s!abc')
        );
        $this->assertNull(ClientProjectDocumentLinkService::normalizeUrl('javascript:alert(1)'));
        $this->assertNull(ClientProjectDocumentLinkService::normalizeUrl(''));
    }
}
