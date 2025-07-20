# stupid-pixel-statamic-automation

A Laravel/Statamic package to automate common Statamic CMS tasks via a programmatic API.

## Features

This package provides a convenient way to interact with your Statamic CMS programmatically, offering endpoints for:

-   **Entries:** List, create, show, update, delete, and bulk store entries.
-   **Collections:** List, create, show, update, and delete collections.
-   **Blueprints:** List, create, show, update, and delete blueprints.
-   **Assets:** List, store (from file or URL), show, update, and delete assets.

## Installation

To install this package, follow these steps:

1.  **Add Package Repository to `composer.json`**

    Open your project's main `composer.json` file and add the following `repositories` entry. This tells Composer where to find your local package.

    ```json
    {
        "type": "path",
        "url": "packages/stupid-pixel-statamic-automation"
    }
    ```

2.  **Require the Package**

    Add the package to your project's `require` section in `composer.json`:

    ```json
    "require": {
        // ... existing requirements
        "stupid-pixel/stupid-pixel-statamic-automation": "*"
    },
    ```

    Then, run `composer update` to install the package and update your autoloader:

    ```bash
    composer update
    ```

3.  **Register the Service Provider**

    For Laravel 11+ (which this project uses), open `bootstrap/providers.php` and add the package's service provider to the array:

    ```php
    <?php

    return [
        App\Providers\AppServiceProvider::class,
        StupidPixel\StatamicAutomation\StatamicAutomationServiceProvider::class,
    ];
    ```

## Usage

### API Endpoints

The package exposes API endpoints under the `/api/autoblogger` prefix. These routes are protected by the `web` middleware and require a valid CSRF token for POST, PUT, and DELETE requests.

**Example: Getting a CSRF Token**

To obtain a CSRF token, make a GET request to:

```
GET /csrf-token
```

This will return a JSON response like: `{"token":"YOUR_CSRF_TOKEN"}`. Include this token in the `X-CSRF-TOKEN` header for your subsequent API requests.

**Example API Endpoints:**

-   **Entries:**
    -   `GET /api/autoblogger/entries`
    -   `POST /api/autoblogger/entries`
    -   `POST /api/autoblogger/entries/bulk`
    -   `GET /api/autoblogger/entries/{slug}`
    -   `PUT /api/autoblogger/entries/{slug}`
    -   `DELETE /api/autoblogger/entries/{slug}`

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

### Programmatic Usage

You can also use the `StatamicAutomation` class directly within your Laravel/Statamic application:

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

// ... and so on for other methods like createBlueprint, createAsset, createEntry

```

## Contributing

Feel free to contribute to this package by submitting issues or pull requests.

## License

This package is open-sourced software licensed under the [MIT license](LICENSE.md).
