<?php
/**
 * Copyright © Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Elasticsearch\Model\ResourceModel\Fulltext\Collection;

use Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection\SearchResultApplierInterface;
use Magento\Framework\Data\Collection;
use Magento\Framework\Api\Search\SearchResultInterface;

/**
 * Resolve specific attributes for search criteria.
 */
class SearchResultApplier implements SearchResultApplierInterface
{
    /**
     * @var Collection|\Magento\CatalogSearch\Model\ResourceModel\Fulltext\Collection
     */
    private $collection;

    /**
     * @var SearchResultInterface
     */
    private $searchResult;

    /**
     * @var int
     */
    private $size;

    /**
     * @var int
     */
    private $currentPage;

    /**
     * @param Collection $collection
     * @param SearchResultInterface $searchResult
     * @param int $size
     * @param int $currentPage
     */
    public function __construct(
        Collection $collection,
        SearchResultInterface $searchResult,
        int $size,
        int $currentPage
    ) {
        $this->collection = $collection;
        $this->searchResult = $searchResult;
        $this->size = $size;
        $this->currentPage = $currentPage;
    }

    /**
     * @inheritdoc
     */
    public function apply()
    {
        if (empty($this->searchResult->getItems())) {
            $this->collection->getSelect()->where('NULL');

            return;
        }

        $items = $this->sliceItems($this->searchResult->getItems(), $this->size, $this->currentPage);
        $ids = [];
        foreach ($items as $item) {
            $ids[] = (int)$item->getId();
        }
        $this->collection->getSelect()->where('e.entity_id IN (?)', $ids);
        $orderList = join(',', $ids);
        $this->collection->getSelect()->reset(\Magento\Framework\DB\Select::ORDER);
        $this->collection->getSelect()->order("FIELD(e.entity_id,$orderList)");
    }

    /**
     * Slice current items
     *
     * @param array $items
     * @param int $size
     * @param int $currentPage
     * @return array
     */
    private function sliceItems(array $items, int $size, int $currentPage): array
    {
        if ($size !== 0) {
            $offset = $this->getOffset($currentPage, $size);
            $itemsCount = count($items);
            if ($this->isOffsetOutOfRange($offset, $size, $itemsCount)) {
                $offset = 0;
            }
            $maxAllowedPageNumber = ceil($itemsCount/$size);
            if ($currentPage > $maxAllowedPageNumber) {
                $offset = $this->getOffset($maxAllowedPageNumber, $size);
            }
            $items = array_slice($items, $offset, $this->size);
        }

        return $items;
    }

    /**
     * Check that offset could be applied for search result items.
     *
     * @param int $offset
     * @param int $size
     * @param int $itemsCount
     * @return bool
     */
    private function isOffsetOutOfRange(int $offset, int $size, int $itemsCount): bool
    {
        return $offset < 0 || $itemsCount <= $size;
    }

    /**
     * Check that given page is available in search results.
     *
     * @param int $pageNumber
     * @param int $pageSize
     * @return int
     */
    private function getOffset(int $pageNumber, int $pageSize): int
    {
        return ($pageNumber - 1) * $pageSize;
    }
}
