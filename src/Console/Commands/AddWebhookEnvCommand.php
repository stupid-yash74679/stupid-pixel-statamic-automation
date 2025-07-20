<?php

namespace StupidPixel\StatamicAutomation\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AddWebhookEnvCommand extends Command
{
    protected $signature = 'statamic-automation:add-webhook-env';
    protected $description = 'Adds WEBHOOK_URL and WEBHOOK_SECRET to the .env file if they do not exist.';

    public function handle()
    {
        $envPath = base_path('.env');

        if (!File::exists($envPath)) {
            $this->error('.env file not found!');
            return Command::FAILURE;
        }

        $content = File::get($envPath);
        $updated = false;

        if (!str_contains($content, 'WEBHOOK_URL=')) {
            $content .= "\nWEBHOOK_URL=\"http://your-webhook-receiver.com/endpoint\"";
            $updated = true;
        }

        if (!str_contains($content, 'WEBHOOK_SECRET=')) {
            $content .= "\nWEBHOOK_SECRET=\"your_secret_key_for_verification\"";
            $updated = true;
        }

        if ($updated) {
            File::put($envPath, $content);
            $this->info('WEBHOOK_URL and WEBHOOK_SECRET added to .env file.');
        } else {
            $this->info('WEBHOOK_URL and WEBHOOK_SECRET already exist in .env file.');
        }

        return Command::SUCCESS;
    }
}
