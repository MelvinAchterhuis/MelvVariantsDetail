<?php declare(strict_types=1);

namespace Melv\VariantsDetail\Controller;

use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\Error\Error;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Checkout\Cart\SalesChannel\CartService;
use Shopware\Core\Content\Product\Exception\ProductNotFoundException;
use Shopware\Core\Framework\Routing\Annotation\RouteScope;
use Shopware\Core\Framework\Routing\Exception\MissingRequestParameterException;
use Shopware\Core\Framework\Validation\DataBag\RequestDataBag;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @RouteScope(scopes={"storefront"})
 */
class VariantLineItemController extends StorefrontController
{
    private $cartService;

    public function __construct(
        CartService $cartService
    ) {
        $this->cartService = $cartService;
    }
    /**
     * @Route ("/checkout/line-item/add-variants", name="frontend.checkout.variants.add", methods={"POST"}, defaults={"XmlHttpRequest"=true}))
     * @param Cart $cart
     * @param RequestDataBag $requestDataBag
     * @param Request $request
     * @param SalesChannelContext $salesChannelContext
     * @return Response
     */
    public function addVariantsToCart(Cart $cart, RequestDataBag $requestDataBag, Request $request, SalesChannelContext $salesChannelContext): Response
    {
        $lineItems = $requestDataBag->get('lineItems');
        if (!$lineItems) {
            throw new MissingRequestParameterException('lineItems');
        }

        $count = 0;

        try {
            $items = [];
            /** @var RequestDataBag $lineItemData */
            foreach ($lineItems as $lineItemData) {
                //Only items with larger than zero quantity
                if($lineItemData->getInt('quantity') > 0) {
                    $lineItem = new LineItem(
                        $lineItemData->getAlnum('id'),
                        $lineItemData->getAlnum('type'),
                        $lineItemData->get('referencedId'),
                        $lineItemData->getInt('quantity', 1)
                    );

                    $lineItem->setStackable($lineItemData->getBoolean('stackable', true));
                    $lineItem->setRemovable($lineItemData->getBoolean('removable', true));

                    $count += $lineItem->getQuantity();

                    $items[] = $lineItem;
                }
            }

            //If array has least 1 lineItem, add to cart, otherwise show warning
            if(count($items) > 0) {
                $cart = $this->cartService->add($cart, $items, $salesChannelContext);
                if (!$this->traceErrors($cart)) {
                    $this->addFlash(self::SUCCESS, $this->trans('checkout.addToCartSuccess', ['%count%' => $count]));
                }
            } else {
                $this->addFlash(self::WARNING, $this->trans('melvVariantsDetail.checkout.addToCartEmpty'));
            }

        } catch (ProductNotFoundException $exception) {
            $this->addFlash(self::DANGER, $this->trans('error.addToCartError'));
        }

        return $this->createActionResponse($request);
    }

    private function traceErrors(Cart $cart): bool
    {
        if ($cart->getErrors()->count() <= 0) {
            return false;
        }

        $this->addCartErrors($cart, function (Error $error) {
            return $error->isPersistent();
        });

        return true;
    }
}
