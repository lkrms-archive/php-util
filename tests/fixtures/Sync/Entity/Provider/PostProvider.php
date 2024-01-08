<?php declare(strict_types=1);

namespace Lkrms\Tests\Sync\Entity\Provider;

use Lkrms\Sync\Contract\ISyncContext;
use Lkrms\Sync\Contract\ISyncProvider;
use Lkrms\Tests\Sync\Entity\Post;

/**
 * Syncs Post objects with a backend
 *
 * @method Post createPost(ISyncContext $ctx, Post $post)
 * @method Post getPost(ISyncContext $ctx, int|string|null $id)
 * @method Post updatePost(ISyncContext $ctx, Post $post)
 * @method Post deletePost(ISyncContext $ctx, Post $post)
 * @method iterable<Post> getPosts(ISyncContext $ctx)
 *
 * @generated
 */
interface PostProvider extends ISyncProvider {}
