# Installation Guide for `stupid-pixel-statamic-automation`

This guide will walk you through the steps to install and set up the `stupid-pixel-statamic-automation` package in your Statamic project.

## Step 1: Add Package Repository to `composer.json`

First, you need to tell Composer where to find the package. Open your project's main `composer.json` file (in the root directory) and add the following `repositories` entry. If you already have a `repositories` section, add this new entry to it.

```json
{
    "type": "path",
    "url": "packages/stupid-pixel-statamic-automation"
}
```

Your `composer.json` might look something like this after the change:

```json
{
    "name": "statamic/statamic",
    "type": "project",
    "repositories": [
        {
            "type": "path",
            "url": "packages/stupid-pixel-statamic-automation"
        }
    ],
    "require": {
        // ... existing requirements
        "stupid-pixel/stupid-pixel-statamic-automation": "*"
    },
    // ... rest of your composer.json
}
```

## Step 2: Require the Package

Next, require the package using Composer. This will symlink the package into your `vendor` directory and update the autoloader.

```bash
composer require stupid-pixel/stupid-pixel-statamic-automation:"*"
```

Alternatively, you can manually add it to your `require` section in the root `composer.json`:

```json
"require": {
    // ... existing requirements
    "stupid-pixel/stupid-pixel-statamic-automation": "*"
},
```

And then run:

```bash
composer update
```

## Step 3: Register the Service Provider

For Laravel 11+ (which this project uses), service providers are typically registered in `bootstrap/providers.php`. Open this file and add the package's service provider to the array:

```php
<?php

return [
    App\Providers\AppServiceProvider::class,
    StupidPixel\StatamicAutomation\StatamicAutomationServiceProvider::class,
];
```

## Step 4: API Routes

The package includes API routes for managing Statamic entries, collections, blueprints, and assets. These routes are automatically loaded by the service provider and are prefixed with `/api/autoblogger`.

Example API Endpoints:

-   **Entries:**
    -   `GET /api/autoblogger/entries`
    -   `POST /api/autoblogger/entries`
    -   `GET /api/autoblogger/entries/{slug}`
    -   `PUT /api/autoblogger/entries/{slug}`
    -   `DELETE /api/autoblogger/entries/{slug}`
    -   `POST /api/autoblogger/entries/bulk`

-   **Collections:**
    -   `GET /api/autoblogger/collections`
    -   `POST /api/autoblogger/collections`
    -   `GET /api/autoblogger/collections/{handle}`
    -   `PUT /api/autoblogger/collections/{handle}`
    -   `DELETE /api/autoblogger/collections/{handle}`

-   **Blueprints:**
    -   `GET /api/autoblogger/blueprints`
    -   `POST /api/autoblogger/blueprints`
    -   `GET /api/autoblogger/blueprints/{handle}`
    -   `PUT /api/autoblogger/blueprints/{handle}`
    -   `DELETE /api/autoblogger/blueprints/{handle}`

-   **Assets:**
    -   `GET /api/autoblogger/assets`
    -   `POST /api/autoblogger/assets`
    -   `POST /api/autoblogger/assets/from-url`
    -   `GET /api/autoblogger/assets/{container}/{path}`
    -   `PUT /api/autoblogger/assets/{container}/{path}`
    -   `DELETE /api/autoblogger/assets/{container}/{path}`

These routes are protected by the `web` middleware and require a valid CSRF token for POST, PUT, and DELETE requests. You can obtain a CSRF token from the `/csrf-token` endpoint of your Statamic application.

```
GET /csrf-token
```

This will return a JSON response like: `{"token":"YOUR_CSRF_TOKEN"}`. Include this token in the `X-CSRF-TOKEN` header for your API requests.

## Step 5: Run Migrations (if applicable)

If the package introduces any database migrations (currently, this package does not), you would run them using:

```bash
php artisan migrate
```

## Step 5: Add Webhook Environment Variables

To add the necessary webhook environment variables to your `.env` file, run the following Artisan command:

```bash
php artisan statamic-automation:add-webhook-env
```

This command will add `WEBHOOK_URL` and `WEBHOOK_SECRET` to your `.env` file if they don't already exist. You should then update these values with your actual webhook endpoint and secret key.

## Usage

Once installed, you can use the `StupidPixel\StatamicAutomation\StatamicAutomation` class in your application to interact with the Statamic API programmatically.

```php
<?php

use StupidPixel\StatamicAutomation\StatamicAutomation;

$automation = new StatamicAutomation('http://localhost:8000'); // Replace with your Statamic URL

// Example: Create a new collection
$result = $automation->createCollection([
    'title' => 'My New Collection',
    'handle' => 'my_new_collection',
]);

if ($result['success']) {
    echo "Collection created successfully!";
} else {
    echo "Error: " . $result['message'];
}

// Example: Get a CSRF token
$csrfToken = $automation->getCsrfToken();
if ($csrfToken) {
    echo "CSRF Token: " . $csrfToken;
}

// ... and so on for other methods

```
