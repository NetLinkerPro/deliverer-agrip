<?php

namespace NetLinker\DelivererAgrip\Tests\Sections\Sources\Repositories;

use Illuminate\Support\Facades\Artisan;
use NetLinker\DelivererAgrip\Sections\Sources\Repositories\CategoryRepository;
use NetLinker\DelivererAgrip\Tests\TestCase;

class CategoryRepositoryTest extends TestCase
{
    public function testGetCategories()
    {
        /** @var CategoryRepository $repository */
        $repository = app(CategoryRepository::class);
        $categories = $repository->get(env('XML_URL'), env('LOGIN'), env('PASS'));
        $this->assertNotEmpty($categories);
    }
}
