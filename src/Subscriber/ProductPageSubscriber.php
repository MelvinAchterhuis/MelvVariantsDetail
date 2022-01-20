<?php declare(strict_types=1);

namespace Melv\VariantsDetail\Subscriber;

use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepositoryInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;

class ProductPageSubscriber implements EventSubscriberInterface
{
    private $productRepository;

    public function __construct(
        SalesChannelRepositoryInterface $productRepository
    ) {
        $this->productRepository = $productRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'loadVariants',
        ];
    }

    public function loadVariants(ProductPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $product = $page->getProduct();
        $parentId = $product->getParentId();

        //If no parentId -> no variant, early return
        if(!$parentId) {
            return;
        }

        $sorting = new FieldSorting('productNumber', 'ASC');
        $criteria = new Criteria();
        $criteria->addFilter(new EqualsFilter('parentId', $parentId));
        $criteria->addAssociation('options.group');
        $criteria->addSorting($sorting);
        $variantProducts = $this->productRepository->search($criteria, $event->getSalesChannelContext());

        //Only add extension when there is more than 1 variant
        if($variantProducts->getTotal() > 1) {
            $page->addExtension('MelvVariantsDetail', $variantProducts->getEntities());
        }
    }
}
