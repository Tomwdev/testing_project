<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TagSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $tags = [
            // Languages
            'PHP',
            'JavaScript',
            'TypeScript',
            'Python',
            'Go',
            'Rust',
            'Java',
            'C#',
            'Ruby',
            'Swift',

            // Frameworks
            'Laravel',
            'Vue.js',
            'React',
            'Angular',
            'Next.js',

            // Databases
            'MySQL',
            'PostgreSQL',
            'SQLite',
            'MongoDB',
            'Redis',

            // Tools & DevOps
            'Docker',
            'Git',
            'GitHub',
            'GitLab',

            // Frontend
            'Tailwind CSS',
            'Bootstrap',

            // Testing
            'PHPUnit',
            'Pest',

            // Concepts
            'API',
            'REST',
            'GraphQL',
        ];

        foreach ($tags as $tagName) {
            Tag::create([
                'name' => $tagName,
                'slug' => Str::slug($tagName),
            ]);
        }
    }
}
