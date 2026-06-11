<?php

namespace Tests\Support;

use App\Models\AiGeneratedImage;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Models\Client;
use App\Models\ClientProject;
use App\Models\ClientProjectInvoice;
use App\Models\ClientProjectQuotation;
use App\Models\FenceQuotation;
use App\Models\Helper;
use App\Models\Material;
use App\Models\PavingQuotation;
use App\Models\Project;
use App\Models\ProjectCategory;
use App\Models\StairsMaterialType;
use App\Models\StairsQuotation;
use App\Models\StandardQuotation;
use App\Models\Vehicle;
use Illuminate\Routing\Route;

/**
 * Minimal records so GET route smoke tests can resolve route parameters.
 */
final class SmokeTestFixtures
{
    private ?ClientProject $clientProject = null;

    private ?ClientProject $clientProjectWithQuotation = null;

    public function resolve(string $name, Route $route): mixed
    {
        return match ($name) {
            'material' => $this->material()->id,
            'client' => $this->client()->id,
            'client_project' => $this->clientProject()->id,
            'invoice' => $this->invoice()->id,
            'quotation' => $this->projectQuotation()->id,
            'project' => $this->portfolioProject()->id,
            'category' => $this->categoryForRoute($route)->id,
            'post' => $this->postForRoute($route),
            'tag' => $this->blogTag()->id,
            'helper' => $this->helper()->id,
            'vehicle' => $this->vehicle()->id,
            'fence_quotation' => $this->fenceQuotation()->id,
            'standard_quotation' => $this->standardQuotation()->id,
            'paving_quotation' => $this->pavingQuotation()->id,
            'stairs_quotation' => $this->stairsQuotation()->id,
            'ai_instruction' => $this->aiInstructionKey(),
            'ai_generated_image' => $this->aiGeneratedImage()->id,
            'stairs_material_type' => $this->stairsMaterialType()->id,
            'format' => 'webp',
            'key' => 'luxury_vinyl_flush',
            'sessionId' => 'smoke-test-session',
            default => throw new \InvalidArgumentException("No smoke fixture for route parameter [{$name}] on [{$route->getName()}]."),
        };
    }

    public function material(): Material
    {
        static $material;

        return $material ??= Material::query()->create([
            'name' => 'Smoke Test Material',
            'is_active' => true,
        ]);
    }

    public function client(): Client
    {
        static $client;

        return $client ??= Client::query()->create([
            'name' => 'Smoke Test Client',
            'email' => 'smoke-client@example.test',
        ]);
    }

    public function clientProject(): ClientProject
    {
        return $this->clientProject ??= ClientProject::factory()->create([
            'client_name' => 'Smoke Project Client',
            'client_email' => 'smoke-project@example.test',
        ]);
    }

    public function invoice(): ClientProjectInvoice
    {
        static $invoice;

        return $invoice ??= ClientProjectInvoice::factory()
            ->for($this->clientProject())
            ->create();
    }

    public function projectQuotation(): ClientProjectQuotation
    {
        static $quotation;

        return $quotation ??= ClientProjectQuotation::query()->create([
            'client_project_id' => $this->clientProjectWithQuotation()->id,
            'type' => ClientProjectQuotation::TYPE_QUOTATION,
            'quotation_number' => 'QUO-SMOKE-'.uniqid(),
            'items' => [
                ['description' => 'Smoke test line', 'amount' => 100],
            ],
        ]);
    }

    public function portfolioProject(): Project
    {
        static $project;

        return $project ??= Project::query()->create([
            'title' => 'Smoke Portfolio Project',
            'description' => 'Smoke test portfolio entry.',
        ]);
    }

    public function categoryForRoute(Route $route): ProjectCategory|BlogCategory
    {
        $routeName = (string) $route->getName();

        if (str_contains($routeName, 'blog.categories')) {
            return $this->blogCategory();
        }

        return $this->portfolioCategory();
    }

    public function portfolioCategory(): ProjectCategory
    {
        static $category;

        return $category ??= ProjectCategory::query()->create([
            'name' => 'Smoke Portfolio Category',
            'slug' => 'smoke-portfolio-category',
        ]);
    }

    public function blogCategory(): BlogCategory
    {
        static $category;

        return $category ??= BlogCategory::query()->create([
            'name' => 'Smoke Blog Category',
            'slug' => 'smoke-blog-category',
        ]);
    }

    public function blogPost(): BlogPost
    {
        static $post;

        return $post ??= BlogPost::query()->create([
            'title' => 'Smoke Blog Post',
            'slug' => 'smoke-blog-post',
            'html_content' => '<p>Smoke test post.</p>',
            'status' => BlogPost::STATUS_PUBLISHED,
            'published_at' => now(),
        ]);
    }

    public function postForRoute(Route $route): int|string
    {
        $post = $this->blogPost();

        if ($route->getName() === 'blog.show') {
            return $post->slug;
        }

        return $post->id;
    }

    public function blogTag(): BlogTag
    {
        static $tag;

        return $tag ??= BlogTag::query()->create([
            'name' => 'Smoke Tag',
            'slug' => 'smoke-tag',
        ]);
    }

    public function helper(): Helper
    {
        static $helper;

        return $helper ??= Helper::query()->create([
            'name' => 'Smoke Helper',
            'email' => 'smoke-helper@example.test',
            'hourly_rate' => 25,
        ]);
    }

    public function vehicle(): Vehicle
    {
        static $vehicle;

        return $vehicle ??= Vehicle::query()->create([
            'name' => 'Smoke Van',
        ]);
    }

    public function fenceQuotation(): FenceQuotation
    {
        static $quote;

        return $quote ??= FenceQuotation::query()->create([
            'quote_number' => 'FENCE-SMOKE-'.uniqid(),
            'client_name' => 'Smoke Fence Client',
            'sides' => [['length_ft' => 20]],
            'calculation' => ['materials' => [], 'totals' => []],
        ]);
    }

    public function standardQuotation(): StandardQuotation
    {
        static $quote;

        return $quote ??= StandardQuotation::query()->create([
            'quote_number' => 'STD-SMOKE-'.uniqid(),
            'client_name' => 'Smoke Standard Client',
            'materials' => [],
        ]);
    }

    public function pavingQuotation(): PavingQuotation
    {
        static $quote;

        return $quote ??= PavingQuotation::query()->create([
            'quote_number' => 'PAV-SMOKE-'.uniqid(),
            'client_name' => 'Smoke Paving Client',
            'inputs' => [],
            'calculation' => [],
        ]);
    }

    public function stairsQuotation(): StairsQuotation
    {
        static $quote;

        return $quote ??= StairsQuotation::query()->create([
            'quote_number' => 'STAIR-SMOKE-'.uniqid(),
            'client_name' => 'Smoke Stairs Client',
            'inputs' => [],
            'calculation' => [],
        ]);
    }

    public function aiInstructionKey(): string
    {
        return 'contract';
    }

    public function aiGeneratedImage(): AiGeneratedImage
    {
        static $image;

        return $image ??= AiGeneratedImage::query()->create([
            'mode' => AiGeneratedImage::MODE_CREATE,
            'prompt' => 'Smoke test image',
        ]);
    }

    public function stairsMaterialType(): StairsMaterialType
    {
        return StairsMaterialType::query()->orderBy('id')->firstOrFail();
    }

    private function clientProjectWithQuotation(): ClientProject
    {
        return $this->clientProjectWithQuotation ??= ClientProject::factory()->create([
            'client_name' => 'Smoke Quotation Client',
            'client_email' => 'smoke-quotation@example.test',
        ]);
    }
}
