<?php

namespace StupidPixel\StatamicAutomation\Http\Controllers;

use Illuminate\Http\Request;
use Statamic\Facades\Asset;
use Statamic\Facades\Blueprint;
use Statamic\Facades\Collection;
use Statamic\Facades\Entry;
use Statamic\Facades\AssetContainer;
use Illuminate\Support\Facades\Validator;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Log;
use Illuminate\Routing\Controller;

class StatamicAutoBloggerController extends Controller
{
    protected function dispatchWebhook(string $event, array $payload)
    {
        $webhookUrl = env('WEBHOOK_URL');

        if (! $webhookUrl) {
            // Log that webhook URL is not configured, but don't throw an error
            return;
        }

        try {
            $client = new Client();
            $client->post($webhookUrl, [
                'json' => [
                    'event' => $event,
                    'payload' => $payload,
                ],
                'headers' => [
                    'X-Webhook-Secret' => env('WEBHOOK_SECRET'), // Optional: for basic security
                ],
                'timeout' => 5, // seconds
            ]);
        } catch (\Exception $e) {
            // Log the error, but don't prevent the main request from succeeding
            Log::error("Webhook dispatch failed for event {$event}: " . $e->getMessage());
        }
    }

    /**
     * Standardized error response.
     *
     * @param string $message
     * @param string $code
     * @param int $status
     * @param array $details
     * @return \Illuminate\Http\JsonResponse
     */
    protected function errorResponse(string $message, string $code, int $status, array $details = [])
    {
        return response()->json([
            'error' => $message,
            'code' => $code,
            'details' => $details,
        ], $status);
    }

    // Entries CRUD
    public function listEntries(Request $request)
    {
        $query = Entry::query();

        if ($collectionHandle = $request->query('collection')) {
            $collection = Collection::find($collectionHandle);
            if (! $collection) {
                return $this->errorResponse('Collection not found.', 'COLLECTION_NOT_FOUND', 404);
            }
            $query->where('collection', $collection->handle());
        }

        if ($blueprintHandle = $request->query('blueprint')) {
            $query->where('blueprint', $blueprintHandle);
        }

        if ($request->has('published')) {
            $published = filter_var($request->query('published'), FILTER_VALIDATE_BOOLEAN);
            $query->where('published', $published);
        }

        // Advanced filtering for data fields (example: ?data[author]=John Doe)
        if ($dataFilters = $request->query('data')) {
            foreach ($dataFilters as $key => $value) {
                $query->where("data->{$key}", $value);
            }
        }

        // Sorting
        $sortBy = $request->query('sort_by', 'updated_at');
        $sortOrder = $request->query('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $limit = $request->query('limit', 100);
        $offset = $request->query('offset', 0);
        $entries = $query->skip($offset)->take($limit)->get()->map(function ($entry) {
            return [
                'id' => $entry->id(),
                'collection' => $entry->collectionHandle(),
                'blueprint' => $entry->blueprint()->handle(),
                'title' => $entry->get('title'),
                'slug' => $entry->slug(),
                'url' => $entry->absoluteUrl(),
                'data' => $entry->data()->all(),
            ];
        });

        return response()->json($entries, 200);
    }

    public function showEntry(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'collection' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Failed', 'VALIDATION_FAILED', 422, $validator->errors()->toArray());
        }

        $collection = Collection::find($request->collection);
        if (! $collection) {
            return $this->errorResponse('Collection not found.', 'COLLECTION_NOT_FOUND', 404);
        }

        $entry = Entry::query()
            ->where('slug', $slug)
            ->where('collection', $collection->handle())
            ->first();

        if (! $entry) {
            return $this->errorResponse('Entry not found.', 'ENTRY_NOT_FOUND', 404);
        }

        return response()->json([
            'id' => $entry->id(),
            'collection' => $entry->collectionHandle(),
            'blueprint' => $entry->blueprint()->handle(),
            'title' => $entry->get('title'),
            'slug' => $entry->slug(),
            'url' => $entry->absoluteUrl(),
            'data' => $entry->data()->all(),
        ], 200);
    }

    public function storeEntry(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'collection' => 'required|string',
            'blueprint' => 'required|string',
            'title' => 'required|string',
            'slug' => 'required|string',
            'data' => 'array',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Failed', 'VALIDATION_FAILED', 422, $validator->errors()->toArray());
        }

        try {
            $collection = Collection::find($request->collection);

            if (! $collection) {
                return $this->errorResponse('Collection not found.', 'COLLECTION_NOT_FOUND', 404);
            }

            $entry = Entry::make()
                ->collection($collection)
                ->blueprint($request->blueprint)
                ->slug($request->slug)
                ->data(array_merge(['title' => $request->title], $request->data ?? []))
                ->published(true);

            $entry->save();

            $this->dispatchWebhook('entry.created', [
                'id' => $entry->id(),
                'collection' => $entry->collectionHandle(),
                'slug' => $entry->slug(),
                'url' => $entry->absoluteUrl(),
            ]);

            return response()->json(['message' => 'Entry created successfully.', 'url' => $entry->absoluteUrl()], 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create entry.', 'ENTRY_CREATION_FAILED', 500, ['details' => $e->getMessage()]);
        }
    }

    public function updateEntry(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'collection' => 'required|string',
            'title' => 'sometimes|required|string',
            'blueprint' => 'sometimes|required|string',
            'data' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Failed', 'VALIDATION_FAILED', 422, $validator->errors()->toArray());
        }

        $collection = Collection::find($request->collection);
        if (! $collection) {
            return $this->errorResponse('Collection not found.', 'COLLECTION_NOT_FOUND', 404);
        }

        $entry = Entry::query()
            ->where('slug', $slug)
            ->where('collection', $collection->handle())
            ->first();
        if (! $entry) {
            return $this->errorResponse('Entry not found.', 'ENTRY_NOT_FOUND', 404);
        }

        try {
            if ($request->has('title')) {
                $entry->set('title', $request->title);
            }
            if ($request->has('blueprint')) {
                $entry->blueprint($request->blueprint);
            }
            if ($request->has('data')) {
                $entry->data(array_merge($entry->data()->all(), $request->data));
            }

            $entry->save();

            $this->dispatchWebhook('entry.updated', [
                'id' => $entry->id(),
                'collection' => $entry->collectionHandle(),
                'slug' => $entry->slug(),
                'url' => $entry->absoluteUrl(),
            ]);

            return response()->json(['message' => 'Entry updated successfully.', 'url' => $entry->absoluteUrl()], 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update entry.', 'ENTRY_UPDATE_FAILED', 500, ['details' => $e->getMessage()]);
        }
    }

    public function deleteEntry(Request $request, $slug)
    {
        $validator = Validator::make($request->all(), [
            'collection' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Failed', 'VALIDATION_FAILED', 422, $validator->errors()->toArray());
        }

        $collection = Collection::find($request->collection);
        if (! $collection) {
            return $this->errorResponse('Collection not found.', 'COLLECTION_NOT_FOUND', 404);
        }

        $entry = Entry::query()
            ->where('slug', $slug)
            ->where('collection', $collection->handle())
            ->first();
        if (! $entry) {
            return $this->errorResponse('Entry not found.', 'ENTRY_NOT_FOUND', 404);
        }

        try {
            $entry->delete();

            $this->dispatchWebhook('entry.deleted', [
                'id' => $entry->id(),
                'collection' => $entry->collectionHandle(),
                'slug' => $entry->slug(),
            ]);

            return response()->json(['message' => 'Entry deleted successfully.'], 204);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete entry.', 'ENTRY_DELETION_FAILED', 500, ['details' => $e->getMessage()]);
        }
    }

    public function bulkStoreEntries(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'entries' => 'required|array',
            'entries.*.collection' => 'required|string',
            'entries.*.blueprint' => 'required|string',
            'entries.*.title' => 'required|string',
            'entries.*.slug' => 'required|string',
            'entries.*.data' => 'array',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Failed', 'VALIDATION_FAILED', 422, $validator->errors()->toArray());
        }

        $results = [];
        foreach ($request->entries as $entryData) {
            try {
                $collection = Collection::find($entryData['collection']);

                if (! $collection) {
                    $results[] = ['status' => 'failed', 'slug' => $entryData['slug'] ?? null, 'error' => 'Collection not found.', 'code' => 'COLLECTION_NOT_FOUND'];
                    continue;
                }

                $entry = Entry::make()
                    ->collection($collection)
                    ->blueprint($entryData['blueprint'])
                    ->slug($entryData['slug'])
                    ->data(array_merge(['title' => $entryData['title']], $entryData['data'] ?? []))
                    ->published(true);

                $entry->save();
                $results[] = ['status' => 'success', 'slug' => $entry->slug(), 'url' => $entry->absoluteUrl()];

                $this->dispatchWebhook('entry.created', [
                    'id' => $entry->id(),
                    'collection' => $entry->collectionHandle(),
                    'slug' => $entry->slug(),
                    'url' => $entry->absoluteUrl(),
                ]);

            } catch (\Exception $e) {
                $results[] = ['status' => 'failed', 'slug' => $entryData['slug'] ?? null, 'error' => 'Failed to create entry.', 'code' => 'ENTRY_CREATION_FAILED', 'details' => $e->getMessage()];
            }
        }

        return response()->json($results, 200);
    }

    // Collections CRUD
    public function listCollections(Request $request)
    {
        $query = Collection::query();

        // Sorting
        $sortBy = $request->query('sort_by', 'title');
        $sortOrder = $request->query('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $collections = $query->get()->map(function ($collection) {
            return [
                'handle' => $collection->handle(),
                'title' => $collection->title(),
            ];
        });
        return response()->json($collections, 200);
    }

    public function showCollection($handle)
    {
        $collection = Collection::find($handle);
        if (! $collection) {
            return $this->errorResponse('Collection not found.', 'COLLECTION_NOT_FOUND', 404);
        }
        return response()->json([
            'handle' => $collection->handle(),
            'title' => $collection->title(),
            'structure' => $collection->structure() ? $collection->structure()->toArray() : [],
            'routes' => $collection->routes() ? $collection->routes()->toArray() : [],
        ], 200);
    }

    public function storeCollection(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'handle' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (Collection::find($value)) {
                        $fail('A collection with this handle already exists.');
                    }
                },
            ],
            'title' => 'required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Failed', 'VALIDATION_FAILED', 422, $validator->errors()->toArray());
        }

        try {
            $collection = Collection::make($request->handle)
                ->title($request->title)
                ->save();

            $this->dispatchWebhook('collection.created', [
                'handle' => $collection->handle(),
                'title' => $collection->title(),
            ]);

            return response()->json(['message' => 'Collection created successfully.'], 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create collection.', 'COLLECTION_CREATION_FAILED', 500, ['details' => $e->getMessage()]);
        }
    }

    public function updateCollection(Request $request, $handle)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Failed', 'VALIDATION_FAILED', 422, $validator->errors()->toArray());
        }

        $collection = Collection::find($handle);
        if (! $collection) {
            return $this->errorResponse('Collection not found.', 'COLLECTION_NOT_FOUND', 404);
        }

        try {
            if ($request->has('title')) {
                $collection->title($request->title);
            }
            $collection->save();

            $this->dispatchWebhook('collection.updated', [
                'handle' => $collection->handle(),
                'title' => $collection->title(),
            ]);

            return response()->json(['message' => 'Collection updated successfully.'], 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update collection.', 'COLLECTION_UPDATE_FAILED', 500, ['details' => $e->getMessage()]);
        }
    }

    public function deleteCollection($handle)
    {
        $collection = Collection::find($handle);
        if (! $collection) {
            return $this->errorResponse('Collection not found.', 'COLLECTION_NOT_FOUND', 404);
        }

        try {
            $collection->delete();

            $this->dispatchWebhook('collection.deleted', [
                'handle' => $collection->handle(),
            ]);

            return response()->json(['message' => 'Collection deleted successfully.'], 204);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete collection.', 'COLLECTION_DELETION_FAILED', 500, ['details' => $e->getMessage()]);
        }
    }

    // Blueprints CRUD
    public function listBlueprints(Request $request)
    {
        $query = Blueprint::query();

        // Sorting
        $sortBy = $request->query('sort_by', 'title');
        $sortOrder = $request->query('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        $blueprints = $query->get()->map(function ($blueprint) {
            return [
                'handle' => $blueprint->handle(),
                'title' => $blueprint->title(),
            ];
        });
        return response()->json($blueprints, 200);
    }

    public function showBlueprint($handle)
    {
        $blueprint = Blueprint::find($handle);
        if (! $blueprint) {
            return $this->errorResponse('Blueprint not found.', 'BLUEPRINT_NOT_FOUND', 404);
        }
        return response()->json([
            'handle' => $blueprint->handle(),
            'title' => $blueprint->title(),
            'fields' => $blueprint->fields()->all(),
        ], 200);
    }

    public function storeBlueprint(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'handle' => [
                'required',
                'string',
                function ($attribute, $value, $fail) {
                    if (Blueprint::find($value)) {
                        $fail('A blueprint with this handle already exists.');
                    }
                },
            ],
            'title' => 'required|string',
            'fields' => 'required|array',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Failed', 'VALIDATION_FAILED', 422, $validator->errors()->toArray());
        }

        try {
            $blueprint = Blueprint::make($request->handle);
            $blueprint->setContents([
                'title' => $request->title,
                'fields' => $request->fields,
            ]);
            $blueprint->save();

            $this->dispatchWebhook('blueprint.created', [
                'handle' => $blueprint->handle(),
                'title' => $blueprint->title(),
            ]);

            return response()->json(['message' => 'Blueprint created successfully.'], 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to create blueprint.', 'BLUEPRINT_CREATION_FAILED', 500, ['details' => $e->getMessage()]);
        }
    }

    public function updateBlueprint(Request $request, $handle)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string',
            'fields' => 'sometimes|required|array',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Failed', 'VALIDATION_FAILED', 422, $validator->errors()->toArray());
        }

        $blueprint = Blueprint::find($handle);
        if (! $blueprint) {
            return $this->errorResponse('Blueprint not found.', 'BLUEPRINT_NOT_FOUND', 404);
        }

        try {
            if ($request->has('title')) {
                $blueprint->set('title', $request->title);
            }
            if ($request->has('fields')) {
                $blueprint->set('fields', $request->fields);
            }
            $blueprint->save();

            $this->dispatchWebhook('blueprint.updated', [
                'handle' => $blueprint->handle(),
                'title' => $blueprint->title(),
            ]);

            return response()->json(['message' => 'Blueprint updated successfully.'], 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update blueprint.', 'BLUEPRINT_UPDATE_FAILED', 500, ['details' => $e->getMessage()]);
        }
    }

    public function deleteBlueprint($handle)
    {
        $blueprint = Blueprint::find($handle);
        if (! $blueprint) {
            return $this->errorResponse('Blueprint not found.', 'BLUEPRINT_NOT_FOUND', 404);
        }

        try {
            $blueprint->delete();

            $this->dispatchWebhook('blueprint.deleted', [
                'handle' => $blueprint->handle(),
            ]);

            return response()->json(['message' => 'Blueprint deleted successfully.'], 204);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete blueprint.', 'BLUEPRINT_DELETION_FAILED', 500, ['details' => $e->getMessage()]);
        }
    }

    // Assets CRUD
    public function listAssets(Request $request)
    {
        $query = Asset::query();

        if ($containerHandle = $request->query('container')) {
            $container = AssetContainer::find($containerHandle);
            if (! $container) {
                return $this->errorResponse('Asset container not found.', 'ASSET_CONTAINER_NOT_FOUND', 404);
            }
            $query->where('container', $container->handle());
        }

        // Sorting
        $sortBy = $request->query('sort_by', 'updated_at');
        $sortOrder = $request->query('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $limit = $request->query('limit', 100);
        $offset = $request->query('offset', 0);
        $assets = $query->skip($offset)->take($limit)->get()->map(function ($asset) {
            return [
                'id' => $asset->id(),
                'container' => $asset->containerHandle(),
                'path' => $asset->path(),
                'url' => $asset->url(),
                'title' => $asset->title(),
                'data' => $asset->data()->all(),
            ];
        });

        return response()->json($assets, 200);
    }

    public function showAsset($containerHandle, $path)
    {
        $container = AssetContainer::find($containerHandle);
        if (! $container) {
            return $this->errorResponse('Asset container not found.', 'ASSET_CONTAINER_NOT_FOUND', 404);
        }

        $asset = $container->asset($path);
        if (! $asset) {
            return $this->errorResponse('Asset not found.', 'ASSET_NOT_FOUND', 404);
        }

        return response()->json([
            'id' => $asset->id(),
            'container' => $asset->containerHandle(),
            'path' => $asset->path(),
            'url' => $asset->url(),
            'title' => $asset->title(),
            'data' => $asset->data()->all(),
        ], 200);
    }

    public function storeAsset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'container' => 'required|string',
            'file' => 'required|file',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Failed', 'VALIDATION_FAILED', 422, $validator->errors()->toArray());
        }

        try {
            $container = AssetContainer::find($request->container);

            if (! $container) {
                return $this->errorResponse('Asset container not found.', 'ASSET_CONTAINER_NOT_FOUND', 404);
            }

            $file = $request->file('file');
            $path = $file->store($container->handle(), $container->disk()->name());

            $asset = $container->makeAsset($path);
            $asset->save();

            $this->dispatchWebhook('asset.created', [
                'id' => $asset->id(),
                'container' => $asset->containerHandle(),
                'path' => $asset->path(),
                'url' => $asset->url(),
            ]);

            return response()->json(['message' => 'Asset uploaded successfully.', 'url' => $asset->url()], 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to upload asset.', 'ASSET_UPLOAD_FAILED', 500, ['details' => $e->getMessage()]);
        }
    }

    public function storeAssetFromUrl(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'container' => 'required|string',
            'url' => 'required|url',
            'filename' => 'sometimes|string',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Failed', 'VALIDATION_FAILED', 422, $validator->errors()->toArray());
        }

        try {
            $container = AssetContainer::find($request->container);

            if (! $container) {
                return $this->errorResponse('Asset container not found.', 'ASSET_CONTAINER_NOT_FOUND', 404);
            }

            $contents = file_get_contents($request->url);
            if ($contents === false) {
                return $this->errorResponse('Failed to fetch asset from URL.', 'ASSET_FETCH_FAILED', 500);
            }

            $filename = $request->filename ?? basename(parse_url($request->url, PHP_URL_PATH));
            $tempPath = temp_path($filename);
            file_put_contents($tempPath, $contents);

            $path = $container->disk()->putFileAs($container->handle(), $tempPath, $filename);

            $asset = $container->makeAsset($path);
            $asset->save();

            unlink($tempPath); // Clean up temp file

            $this->dispatchWebhook('asset.created_from_url', [
                'id' => $asset->id(),
                'container' => $asset->containerHandle(),
                'path' => $asset->path(),
                'url' => $asset->url(),
            ]);

            return response()->json(['message' => 'Asset uploaded successfully from URL.', 'url' => $asset->url()], 201);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to upload asset from URL.', 'ASSET_UPLOAD_FROM_URL_FAILED', 500, ['details' => $e->getMessage()]);
        }
    }

    public function updateAsset(Request $request, $containerHandle, $path)
    {
        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string',
            'data' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return $this->errorResponse('Validation Failed', 'VALIDATION_FAILED', 422, $validator->errors()->toArray());
        }

        $container = AssetContainer::find($containerHandle);
        if (! $container) {
            return $this->errorResponse('Asset container not found.', 'ASSET_CONTAINER_NOT_FOUND', 404);
        }

        $asset = $container->asset($path);
        if (! $asset) {
            return $this->errorResponse('Asset not found.', 'ASSET_NOT_FOUND', 404);
        }

        try {
            if ($request->has('title')) {
                $asset->title($request->title);
            }
            if ($request->has('data')) {
                $asset->data(array_merge($asset->data()->all(), $request->data));
            }
            $asset->save();

            $this->dispatchWebhook('asset.updated', [
                'id' => $asset->id(),
                'container' => $asset->containerHandle(),
                'path' => $asset->path(),
                'url' => $asset->url(),
            ]);

            return response()->json(['message' => 'Asset updated successfully.', 'url' => $asset->url()], 200);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to update asset.', 'ASSET_UPDATE_FAILED', 500, ['details' => $e->getMessage()]);
        }
    }

    public function deleteAsset($containerHandle, $path)
    {
        $container = AssetContainer::find($containerHandle);
        if (! $container) {
            return $this->errorResponse('Asset container not found.', 'ASSET_CONTAINER_NOT_FOUND', 404);
        }

        $asset = $container->asset($path);
        if (! $asset) {
            return $this->errorResponse('Asset not found.', 'ASSET_NOT_FOUND', 404);
        }

        try {
            $asset->delete();

            $this->dispatchWebhook('asset.deleted', [
                'id' => $asset->id(),
                'container' => $asset->containerHandle(),
                'path' => $asset->path(),
            ]);

            return response()->json(['message' => 'Asset deleted successfully.'], 204);
        } catch (\Exception $e) {
            return $this->errorResponse('Failed to delete asset.', 'ASSET_DELETION_FAILED', 500, ['details' => $e->getMessage()]);
        }
    }
}
