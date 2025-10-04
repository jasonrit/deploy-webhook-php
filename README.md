# deploy-webhook-php

A GitHub webhook handler for automated deployment. This receives webhook notifications from GitHub when code is pushed to repositories and automatically deploys the changes to the appropriate server directories.

## Usage

Test the webhook signature logic locally:

```bash
php test-webhook.php
```

## Key Features

- Listens for GitHub webhook events
- Verifies webhook signatures using a secret for security
- Maps repository names to deployment paths on the server (supports multiple projects)
- Only deploys commits to the `main` branch
- Executes `git pull` to update the deployed code
- Performs health checks after deployment
- Sends email notifications on success/failure
- Logs all deployment events to a log file
- Validates directory permissions before deploying

## File Structure

```
.
├── .env.example          # Example environment configuration
├── .gitignore           # Git ignore rules
├── README.md            # This file
├── index.htm            # Root index page
├── tryit.htm            # Test page
├── test-webhook.php     # Webhook testing utility
├── health/
│   └── index.htm        # Health check endpoint
└── webhook/
    └── index.php        # Main webhook handler
```

## Safe-add commands

`sudo git config --system --add safe.directory /var/www/jr-webhook`
