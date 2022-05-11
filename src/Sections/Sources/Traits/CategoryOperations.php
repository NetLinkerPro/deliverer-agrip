<?php


namespace NetLinker\DelivererAgrip\Sections\Sources\Traits;


use NetLinker\DelivererAgrip\Sections\Sources\Classes\CategorySource;

trait CategoryOperations
{

    /**
     * @param CategorySource $category
     * @return CategorySource
     */
    private function getDeepestCategory(CategorySource $category): CategorySource
    {
        $categoryDeepest = $category;
        while ($categoryDeepest) {
            $categoryChild = $categoryDeepest->getChildren()[0] ?? null;
            if ($categoryChild) {
                $categoryDeepest = $categoryChild;
            } else {
                break;
            }
        }
        return $categoryDeepest;
    }
}