# Notes App

A personal productivity application built with Laravel 12. Strong inspriation from the Final Project "Pixel Positions" from the Laracasts "30-days-to-learn-laravel" Course.

## Overview

Notes App lets users manage notes, projects, and learning concepts. Content can be organized with tech stack tags. The app includes user authentication and background job processing.

## Requirements

- PHP 8.2+
- Composer
- Node.js and npm
- SQLite (default), MySQL, or PostgreSQL

## Tech Stack

- Laravel 12 (Backend)
- Tailwind CSS 4 (Styling)
- Vite 7 (Asset bundling)
- Pest 4 (Testing)

## Installation

```bash
git clone <repository-url>
cd testing_project

composer install
npm install

cp .env.example .env
php artisan key:generate

touch database/database.sqlite
php artisan migrate --seed

npm run build
```

## Running the Application

Start all services at once:

```bash
composer dev
```

Or run services individually:

```bash
php artisan serve      # Web server
php artisan queue:work # Queue worker
npm run dev            # Vite dev server
```

## Database Models

**User** - Has many notes, projects, and concepts

**Note** - Title, body. Belongs to user. Has many tags.

**Project** - Title, description, status. Belongs to user. Has many tags.

**Concept** - Title, description. Belongs to user. Has many tags.

**Tag** - Name, slug. Used to categorize notes, projects, and concepts.

## Main Routes

| Method | URI                 | Description                   |
| ------ | ------------------- | ----------------------------- |
| GET    | /                   | Dashboard                     |
| GET    | /notes              | List notes                    |
| GET    | /notes/create       | Create note form              |
| POST   | /notes              | Store note                    |
| GET    | /notes/{note}       | View note                     |
| GET    | /notes/{note}/edit  | Edit note form                |
| PUT    | /notes/{note}       | Update note                   |
| DELETE | /notes/{note}       | Delete note                   |
| GET    | /projects           | List projects                 |
| POST   | /projects           | Store project                 |
| GET    | /projects/{project} | View project                  |
| PUT    | /projects/{project} | Update project                |
| DELETE | /projects/{project} | Delete project                |
| GET    | /concepts           | List concepts                 |
| POST   | /concepts           | Store concept                 |
| GET    | /concepts/{concept} | View concept                  |
| PUT    | /concepts/{concept} | Update concept                |
| DELETE | /concepts/{concept} | Delete concept                |
| GET    | /tags               | List all tags                 |
| GET    | /tags/{tag:slug}    | View tag with related content |
| GET    | /register           | Registration form             |
| POST   | /register           | Create account                |
| GET    | /login              | Login form                    |
| POST   | /login              | Authenticate                  |
| POST   | /logout             | Log out                       |

Note: Create, edit, update, and delete routes require authentication.

## Background Jobs

- **SendWelcomeEmail** - Dispatched when a user registers
- **LogActivity** - Dispatched when content is created, updated, or deleted

Jobs are queued using the database driver by default. Run `php artisan queue:work` to process them.

Can also be set to Redis for Horizon use.

## Testing

```bash
php artisan test
```

## Code Formatting

```bash
./vendor/bin/pint
```

## License

MIT
