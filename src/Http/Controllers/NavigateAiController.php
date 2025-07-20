<?php

namespace StupidPixel\StatamicAutomation\Http\Controllers;

use Illuminate\Http\Request;
use Statamic\Http\Controllers\Controller;
use StupidPixel\StatamicAutomation\Http\Controllers\AiServerController;
use Illuminate\Support\Facades\File;
use Statamic\Facades\Collection;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Entry;
use Statamic\Facades\Stache;

class NavigateAiController extends Controller
{
    public function generatePages(Request $request)
    {
        $kbContent = File::get(base_path('vendor/stupid-yash74679/stupid-pixel-statamic-automation/kb.md'));
        $prompt = "Based on the following brand knowledge base:\n\n" . $kbContent . "\n\nGenerate a list of essential website pages for a modern website, focusing on excellent user experience (UX). Include standard pages like Home, About Us, Contact Us, Careers, FAQs, Blogs, Services. For each page, suggest its primary purpose and key content elements. Provide the response in JSON format, where each page is an object with 'title', 'slug', 'purpose', and 'content_elements' keys.";

        $aiServer = new AiServerController();
        $aiRequest = new Request(['prompt' => $prompt]);

        try {
            $aiResponse = $aiServer->chat($aiRequest);
            $pages = json_decode($aiResponse->getData()->response, true); // Assuming AI response is in 'response' key

            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception('Failed to decode AI response JSON: ' . json_last_error_msg());
            }

            $results = [];

            // Ensure 'pages' collection exists
            $this->ensureCollectionExists('pages');

            // Ensure 'page' blueprint exists
            $this->ensureBlueprintExists('page');

            foreach ($pages as $page) {
                $title = $page['title'] ?? 'Untitled Page';
                $slug = $page['slug'] ?? \Str::slug($title);
                $content = $page['content_elements'] ?? 'Default content.';

                try {
                    $this->createEntry('pages', 'page', $slug, $title, $content);
                    $results[] = ['page' => $title, 'status' => 'created'];
                } catch (\Exception $e) {
                    $results[] = ['page' => $title, 'status' => 'failed', 'error' => $e->getMessage()];
                }
            }

            return response()->json(['message' => 'Page generation complete.', 'results' => $results]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to get AI response or process pages: ' . $e->getMessage()], 500);
        }
    }

    protected function ensureCollectionExists(string $handle)
    {
        if (! Collection::find($handle)) {
            Collection::make($handle)
                ->title(ucfirst($handle))
                ->routes('{parent}/{slug}')
                ->save();
            Stache::clear(); // Clear Stache to ensure new collection is recognized
        }
    }

    protected function ensureBlueprintExists(string $handle)
    {
        if (! Blueprint::find('collections.' . $handle)) {
            Blueprint::make() 
                ->setHandle($handle)
                ->setContents([
                    'title' => ucfirst($handle),
                    'fields' => [
                        [
                            'handle' => 'title',
                            'field' => [
                                'type' => 'text',
                                'display' => 'Title',
                                'required' => true,
                            ],
                        ],
                        [
                            'handle' => 'content',
                            'field' => [
                                'type' => 'markdown',
                                'display' => 'Content',
                            ],
                        ],
                    ],
                ])->save();
            Stache::clear(); // Clear Stache to ensure new blueprint is recognized
        }
    }

    protected function createEntry(string $collectionHandle, string $blueprintHandle, string $slug, string $title, string $content)
    {
        $collection = Collection::find($collectionHandle);
        if (! $collection) {
            throw new \Exception('Collection ' . $collectionHandle . ' not found.');
        }

        Entry::make()
            ->collection($collection)
            ->blueprint($blueprintHandle)
            ->slug($slug)
            ->data([
                'title' => $title,
                'content' => $content,
            ])
            ->save();
        Stache::clear(); // Clear Stache to ensure new entry is recognized
    }
}