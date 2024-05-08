<?php
public function customPaginate(int|null $paginate = 20, int|null $page = 1): LengthAwarePaginator
{
    $countQuery = $this->postQuery->toBase()->cloneWithout(['select', 'orders', 'groups']);
    $postsLimitSql = $countQuery->select('posts.id')->limit(Post::LISTING_LIMIT)->toSql();

    $total = DB::table(DB::raw("($postsLimitSql) as sub"))
        ->mergeBindings($countQuery)
        ->count();

    $offset = ($page - 1) * $paginate;
    $items = $this->postQuery->skip($offset)->take($paginate)->get(['id']);

    return new LengthAwarePaginator(
        $items,
        $total,
        $paginate,
        $page, [
            'path' => Paginator::resolveCurrentPath(),
            'query' => request()->query(),
        ]
    );
}

public
function customPaginateWithParentPosts(int|null $paginate = 20, int|null $page = 1, int $amount = 40): LengthAwarePaginator
{
    $paginator = $this->customPaginate($paginate, $page);

    if (!$this->locationSlug || $paginator->total() > self::MIN_POSTS) return $paginator;

    $location = $this->categoryRepository->getLocationBySlug($this->locationSlug);
    $parent = $location->parent()->first();

    if (!$parent) return $paginator;

    $postsIds = $this->getIds();

    $this->limit = $amount;
    $this->locationSlug = $parent->slug;

    if ($this->queryType == self::QUERY_TYPE_DRIVERS) {
        $this->postQuery = $this->getDriversPostsQuery();
    } else {
        $this->postQuery = $this->getDefaultPostsQuery();
        $this->filterByPrice();
    }

    $parentPosts = $this->postQuery->whereNotIn('posts.id', $postsIds->toArray())->get();

    if ($parentPosts->isEmpty()) return $paginator;

    $originalPosts = $paginator->getCollection();
    $postsCount = $paginator->total() + $parentPosts->count();

    $parentPosts[0]->parent_posts = ['name' => $parent->name, 'slug' => $parent->slug, 'type' => $this->postType];

    if ($originalPosts->count() >= $paginate) {
        $posts = $originalPosts;
    } else {
        if ($paginator->total() == 0) {
            $posts = $parentPosts->forPage($page, $paginate);
        } else {
            $parentPage = $page - ceil($paginator->total() / $paginate);
            $parentPage = $parentPage > 0 ? $parentPage : 1;
            $parentSlice = $paginator->total() > $paginate ? $paginator->total() % $paginate : $paginate - $paginator->total();

            $posts = $originalPosts->isNotEmpty() ? $originalPosts->merge($parentPosts->take($parentSlice)) : $parentPosts->slice($parentSlice)->forPage($parentPage, $paginate);
        }
    }

    $newPaginator = new LengthAwarePaginator(
        $posts,
        $postsCount,
        $paginator->perPage(),
        $paginator->currentPage(), [
            'path' => $paginator->resolveCurrentPath(),
            'query' => request()->query(),
        ]
    );

    return $newPaginator;
}

public
function getIds()
{
    $ids = $this->postQuery->toBase()->cloneWithout(['select', 'orders', 'groups', 'limit', 'offset']);
    return $ids->distinct('posts.id')->pluck('id');
}

public
function getPositionIds()
{
    return $this->postQuery->select('posts.id')->pluck('id');
}

public
function defaultGet()
{
    return $this->postQuery->get();
}