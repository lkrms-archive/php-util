<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Support;

use Lkrms\Sync\Catalog\HydrationFlag;
use Lkrms\Sync\Support\DeferredRelationship;
use Lkrms\Tests\Sync\Entity\Provider\AlbumProvider;
use Lkrms\Tests\Sync\Entity\Provider\UserProvider;
use Lkrms\Tests\Sync\Entity\Album;
use Lkrms\Tests\Sync\Entity\Photo;
use Lkrms\Tests\Sync\Entity\Post;
use Lkrms\Tests\Sync\Entity\Task;
use Lkrms\Tests\Sync\Entity\User;

final class DeferredRelationshipTest extends \Lkrms\Tests\Sync\SyncTestCase
{
    public function testLazyHydration(): void
    {
        $provider = $this->App->get(UserProvider::class);
        $context = $provider
            ->getContext()
            ->withHydrationFlags(HydrationFlag::LAZY);

        $user = $provider->with(User::class, $context)->get(1);
        $this->assertInstanceOf(DeferredRelationship::class, $user->Posts);

        foreach ($user->Posts as $post) {
            break;
        }
        $this->assertIsArray($user->Posts);
        $this->assertCount(10, $user->Posts);
        $this->assertContainsOnlyInstancesOf(Post::class, $user->Posts);
        // @phpstan-ignore-next-line
        $this->assertSame($post, $user->Posts[0]);
        $this->assertSame($user, $user->Posts[0]->User);
        // @phpstan-ignore-next-line
        $this->assertSame('sunt aut facere repellat provident occaecati excepturi optio reprehenderit', $post->Title);

        $this->assertHttpRequestCounts([
            '/users/1' => 1,
            '/users/1/posts' => 1,
        ]);
    }

    public function testEagerHydration(): void
    {
        $provider = $this->App->get(UserProvider::class);
        $context = $provider
            ->getContext()
            ->withHydrationFlags(HydrationFlag::EAGER);

        $user = $provider->with(User::class, $context)->get(1);
        $this->assertIsArray($user->Posts);
        $this->assertCount(10, $user->Posts);
        $this->assertContainsOnlyInstancesOf(Post::class, $user->Posts);
        $this->assertSame($user, $user->Posts[0]->User);
        $this->assertSame('sunt aut facere repellat provident occaecati excepturi optio reprehenderit', $user->Posts[0]->Title);

        $this->assertHttpRequestCounts([
            '/users/1' => 1,
            '/users/1/todos' => 1,
            '/users/1/posts' => 1,
            '/posts/1/comments' => 1,
            '/posts/2/comments' => 1,
            '/posts/3/comments' => 1,
            '/posts/4/comments' => 1,
            '/posts/5/comments' => 1,
            '/posts/6/comments' => 1,
            '/posts/7/comments' => 1,
            '/posts/8/comments' => 1,
            '/posts/9/comments' => 1,
            '/posts/10/comments' => 1,
            '/users/1/albums' => 1,
            '/albums/1/photos' => 1,
            '/albums/2/photos' => 1,
            '/albums/3/photos' => 1,
            '/albums/4/photos' => 1,
            '/albums/5/photos' => 1,
            '/albums/6/photos' => 1,
            '/albums/7/photos' => 1,
            '/albums/8/photos' => 1,
            '/albums/9/photos' => 1,
            '/albums/10/photos' => 1,
        ]);
    }

    public function testHydrationFlagDepth(): void
    {
        $provider = $this->App->get(AlbumProvider::class);
        $context = $provider
            ->getContext()
            ->withHydrationFlags(HydrationFlag::SUPPRESS)
            ->withHydrationFlags(HydrationFlag::EAGER, true, null, 1);

        $album = $provider->with(Album::class, $context)->get(1);
        $this->assertIsArray($album->Photos);
        $this->assertCount(50, $album->Photos);
        $this->assertContainsOnlyInstancesOf(Photo::class, $album->Photos);
        $this->assertInstanceOf(User::class, $album->User);
        $this->assertNull($album->User->Posts);
        $this->assertNull($album->User->Albums);
        $this->assertNull($album->User->Tasks);

        $this->assertHttpRequestCounts([
            '/albums/1' => 1,
            '/albums/1/photos' => 1,
            '/users/1' => 1,
        ]);
    }

    public function testHydrationFlagEntity(): void
    {
        $provider = $this->App->get(AlbumProvider::class);
        $context = $provider
            ->getContext()
            ->withHydrationFlags(HydrationFlag::SUPPRESS)
            ->withHydrationFlags(HydrationFlag::EAGER, true, Task::class);

        $album = $provider->with(Album::class, $context)->get(1);
        $this->assertNull($album->Photos);
        $this->assertNull($album->User->Posts);
        $this->assertNull($album->User->Albums);
        $this->assertInstanceOf(User::class, $album->User);
        $this->assertIsArray($album->User->Tasks);
        $this->assertContainsOnlyInstancesOf(Task::class, $album->User->Tasks);
        $this->assertCount(20, $album->User->Tasks);

        foreach ($album->User->Tasks as $task) {
            $this->assertSame($album->User, $task->User);
        }

        $this->assertHttpRequestCounts([
            '/albums/1' => 1,
            '/users/1' => 1,
            '/users/1/todos' => 1,
        ]);
    }
}
