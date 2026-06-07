<?php

namespace App\Services;

use App\Models\AiInstruction;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\BlogTag;
use RuntimeException;

class BlogPostMetadataAiService
{
    public function __construct(
        protected GeminiService $gemini,
        protected BlogImageStorage $images,
    ) {}

    /**
     * @return array{
     *   excerpt: string,
     *   meta_description: string,
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

        if ($result['image_prompt'] === '') {
            return $result;
        }

        try {
            $generated = $this->gemini->generateImage($result['image_prompt']);
            $storagePostId = ($postId !== null && $postId > 0) ? $postId : 0;

            if ($storagePostId > 0) {
                $post = BlogPost::query()->find($storagePostId);
                if ($post?->featured_image_path) {
                    $this->images->delete($post->featured_image_path);
                }
            }

            $path = $this->images->storeFeaturedFromBinary(
                $generated['bytes'],
                $generated['mime'],
                $storagePostId,
            );

            $result['featured_image_path'] = $path;
            $result['featured_image_url'] = $this->images->publicUrl($path);

            if ($storagePostId > 0) {
                BlogPost::query()
                    ->where('id', $storagePostId)
                    ->update(['featured_image_path' => $path]);
            }
        } catch (\Throwable $e) {
            $result['image_generation_error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * @return array{
     *   excerpt: string,
     *   meta_description: string,
     *   tags: string,
     *   image_prompt: string,
     *   category_ids: list<int>,
     *   category_names: list<string>
     * }
     */
    public function suggest(string $title, string $htmlContent): array
    {
        $instruction = AiInstruction::forKey(AiInstruction::KEY_BLOG_POST);
        if (!$instruction) {
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
        $meta = $this->stringField($raw, 'meta_description');
        if (strlen($meta) > 160) {
            $meta = $this->trimToLength($meta, 160);
        }

        $returnedCategories = $raw['categories'] ?? [];
        if (!is_array($returnedCategories)) {
            $returnedCategories = [];
        }

        $nameToId = $categories->keyBy('name');
        $matchedIds = [];
        $matchedNames = [];
        foreach ($returnedCategories as $name) {
            if (!is_string($name)) {
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
        if (!is_array($tags)) {
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
            'excerpt' => $this->trimToLength($excerpt, 500),
            'meta_description' => $meta,
            'tags' => implode(', ', $tagStrings),
            'image_prompt' => $this->trimToLength($imagePrompt, 2000),
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
                    'description' => 'A high-conversion search engine optimization snippet under 160 characters, tailored strictly for Google rankings.',
                ],
                'image_prompt' => [
                    'type' => 'STRING',
                    'description' => 'A detailed text-to-image prompt for generating a 16:9 featured blog header image that visually represents the article. Write as a clear instruction to an image AI (e.g. "Create an image to feature a blog post where we compare Pressure-Treated, Cedar, Composite, and PVC decking"). Focus on the main topic, professional home renovation or construction context, realistic photography style, no text or logos in the image.',
                ],
            ],
            'required' => ['excerpt', 'categories', 'tags', 'meta_description', 'image_prompt'],
        ];
    }

    private function stringField(array $raw, string $key): string
    {
        $value = $raw[$key] ?? '';
        if (!is_string($value)) {
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

    private function trimToLength(string $text, int $max): string
    {
        if (strlen($text) <= $max) {
            return $text;
        }

        $cut = substr($text, 0, $max);
        $lastSpace = strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace > (int) ($max * 0.6)) {
            return rtrim(substr($cut, 0, $lastSpace));
        }

        return rtrim($cut);
    }
}
