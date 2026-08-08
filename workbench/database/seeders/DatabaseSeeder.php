<?php

namespace Workbench\Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Workbench\App\Models\Post;
use Workbench\Database\Factories\UserFactory;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // UserFactory::new()->times(10)->create();

        $user = UserFactory::new()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        // 45 posts → 5 pages at the default 10 per page, so pagination is
        // actually observable in the demo table.
        Post::factory()->count(45)->create();

        // A few notifications so the database-notifications bell (slice B3)
        // has something to show for the seeded user.
        $user->notifications()->createMany([
            [
                'id' => (string) Str::uuid(),
                'type' => 'demo',
                'data' => [
                    'title' => 'Welcome to Refilament',
                    'body' => 'The panel shell now hosts a notifications bell.',
                ],
                'read_at' => null,
            ],
            [
                'id' => (string) Str::uuid(),
                'type' => 'demo',
                'data' => [
                    'title' => 'A post was published',
                    'body' => 'Someone published a new post in the listing.',
                    'url' => '/refilament/posts',
                ],
                'read_at' => null,
            ],
        ]);
    }
}
