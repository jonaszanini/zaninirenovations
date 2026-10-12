<?php

namespace App\Services;

use App\Models\AiInstruction;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use App\Support\PlainTextLimiter;
use RuntimeException;

class BlogPostMetadataAiService
{
    public function __construct(
        protected GeminiService $gemini,
        protected BlogImageStorage $images,
    ) {
    }

    /**
     * @return array{
     *   excerpt: string,
     *   meta_description: string,
     *   seo_title: string,
     *   slug: string,
     *   featured_image_alt: string,
     *   tags: string,
     *   image_prompt: string,
     *   featured_image_path?: string,
     *   featured_image_url?: string,
     *   image_generation_error?: string,
     *   category_ids: list<int>,
     *   category_names: list<string>
     * }
     */
    public function suggestWithFeaturedImage(string $title, string $htmlContent, ?int $postId = null): array
    {
        $result = $this->suggest($title, $htmlContent);

        return $this->attachGeneratedImage($result, $result['image_prompt']);
    }

    /**
     * @return array{featured_image_path?: string, featured_image_url?: string, image_prompt: string, image_generation_error?: string}
     */
    public function generateFeaturedImage(string $imagePrompt): array
    {
        $prompt = PlainTextLimiter::trimToLength(trim($imagePrompt), 2000);
        $result = ['image_prompt' => $prompt];

        return $this->attachGeneratedImage($result, $prompt);
    }

    /**
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function attachGeneratedImage(array $result, string $prompt): array
    {
        if ($prompt === '') {
            $result['image_generation_error'] = 'Add an AI image prompt before generating a featured image.';

            return $result;
        }

        try {
            $generated = $this->gemini->generateImage($prompt);

            // Always write to the pending folder (blog-featured/0). The saved
            // featured image is only replaced when the user clicks Save.
            $path = $this->images->storeFeaturedFromBinary(
                $generated['bytes'],
                $generated['mime'],
                0,
            );

            $result['featured_image_path'] = $path;
            $result['featured_image_url'] = $this->images->publicUrl($path);
            $result['image_prompt'] = $prompt;
        } catch (\Throwable $e) {
            $result['image_generation_error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * @return array{
     *   excerpt: string,
     *   meta_description: string,
     *   seo_title: string,
     *   slug: string,
     *   featured_image_alt: string,
     *   tags: string,
     *   image_prompt: string,
     *   category_ids: list<int>,
     *   category_names: list<string>
     * }
     */
    public function suggest(string $title, string $htmlContent): array
    {
        $instruction = AiInstruction::forKey(AiInstruction::KEY_BLOG_POST);
        if (! $instruction) {
            throw new RuntimeException('Blog post AI instruction is not configured. Add it under Tools → AI Instructions.');
        }

        $title = trim($title);
        $body = $this->plainTextFromHtml($htmlContent);
        if ($title === '' || $body === '') {
            throw new RuntimeException('Enter a title and blog content before generating metadata.');
        }

        $categories = BlogCategory::query()->orderBy('name')->get(['id', 'name']);
        if ($categories->isEmpty()) {
            throw new RuntimeException('Create at least one blog category before generating metadata.');
        }

        $categoryNames = $categories->pluck('name')->all();
        $existingTags = BlogTag::query()->orderBy('name')->pluck('name')->all();
        $existingTagsList = $existingTags !== [] ? implode(', ', $existingTags) : '(none yet)';

        $userText = "### PROVIDED BLOG TITLE ###\n{$title}\n\n### PROVIDED BLOG CONTENT ###\n{$body}";

        $raw = $this->gemini->generateStructuredJson(
            $instruction->instruction,
            $userText,
            $this->responseSchema($categoryNames, $existingTagsList),
        );

        $excerpt = $this->stringField($raw, 'excerpt');
        $meta = PlainTextLimiter::trimToLength($this->stringField($raw, 'meta_description'), 155);
        $seoTitle = PlainTextLimiter::trimToLength($this->stringField($raw, 'seo_title'), 70);
        $slug = BlogPost::slugify($this->stringField($raw, 'slug') ?: $title);
        $featuredAlt = PlainTextLimiter::trimToLength($this->stringField($raw, 'featured_image_alt'), 255);

        $returnedCategories = $raw['categories'] ?? [];
        if (! is_array($returnedCategories)) {
            $returnedCategories = [];
        }

        $nameToId = $categories->keyBy('name');
        $matchedIds = [];
        $matchedNames = [];
        foreach ($returnedCategories as $name) {
            if (! is_string($name)) {
                continue;
            }
            $cat = $nameToId->get($name);
            if ($cat) {
                $matchedIds[] = (int) $cat->id;
                $matchedNames[] = $name;
            }
        }
        $matchedIds = array_values(array_unique($matchedIds));

        $tags = $raw['tags'] ?? [];
        if (! is_array($tags)) {
            $tags = [];
        }
        $tagStrings = [];
        foreach ($tags as $tag) {
            if (is_string($tag) && trim($tag) !== '') {
                $tagStrings[] = trim($tag);
            }
        }
        $tagStrings = array_slice(array_values(array_unique($tagStrings)), 0, 8);

        $imagePrompt = $this->stringField($raw, 'image_prompt');

        return [
            'excerpt' => PlainTextLimiter::trimToLength($excerpt, 500),
            'meta_description' => $meta,
            'seo_title' => $seoTitle,
            'slug' => $slug,
            'featured_image_alt' => $featuredAlt,
            'tags' => implode(', ', $tagStrings),
            'image_prompt' => PlainTextLimiter::trimToLength($imagePrompt, 2000),
            'category_ids' => $matchedIds,
            'category_names' => $matchedNames,
        ];
    }

    /**
     * @param  list<string>  $categoryNames
     */
    private function responseSchema(array $categoryNames, string $existingTagsList): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'excerpt' => [
                    'type' => 'STRING',
                    'description' => 'A concise, engaging summary text derived directly from the provided blog content.',
                ],
                'categories' => [
                    'type' => 'ARRAY',
                    'description' => 'Array containing one or more categories that best match the content from the predefined checklist.',
                    'items' => [
                        'type' => 'STRING',
                        'enum' => $categoryNames,
                    ],
                ],
                'tags' => [
                    'type' => 'ARRAY',
                    'description' => 'An array of 4 to 6 highly relevant search keywords. Include contextually accurate terms from the user prompt and generate additional high-performing industry terms that fit better. Here is an already existing list, please feel free to use them: '.$existingTagsList,
                    'items' => [
                        'type' => 'STRING',
                    ],
                ],
                'meta_description' => [
                    'type' => 'STRING',
                    'description' => 'A high-conversion search engine optimization snippet of at most 155 characters, ending on a complete sentence. Never cut off mid-word and never leave a trailing comma.',
                ],
                'seo_title' => [
                    'type' => 'STRING',
                    'description' => 'A search-result title of at most 60 characters. Include the topic and Ottawa or Carleton Place when it fits naturally. No em-dashes.',
                ],
                'slug' => [
                    'type' => 'STRING',
                    'description' => 'A lowercase URL slug using hyphens only, derived from the title, with no stop-word stuffing.',
                ],
                'featured_image_alt' => [
                    'type' => 'STRING',
                    'description' => 'Concise alt text for the 16:9 featured header image, describing the scene without starting with "image of".',
                ],
                'image_prompt' => [
                    'type' => 'STRING',
                    'description' => 'A detailed text-to-image prompt for generating a 16:9 featured blog header image that visually represents the article. Write as a clear instruction to an image AI (e.g. "Create an image to feature a blog post where we compare Pressure-Treated, Cedar, Composite, and PVC decking"). Focus on the main topic, professional home renovation or construction context, realistic photography style, no text or logos in the image.',
                ],
            ],
            'required' => ['excerpt', 'categories', 'tags', 'meta_description', 'seo_title', 'slug', 'featured_image_alt', 'image_prompt'],
        ];
    }

    private function stringField(array $raw, string $key): string
    {
        $value = $raw[$key] ?? '';
        if (! is_string($value)) {
            return '';
        }

        return trim($value);
    }

    private function plainTextFromHtml(string $html): string
    {
        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace("/[ \t]+\n/", "\n", $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
