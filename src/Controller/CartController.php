<?php

namespace App\Controller;

use App\DTO\CartDTO;
use App\{Entity\Cart,
    Entity\Model,
    Entity\Purchase,
    Entity\Texture,
    Entity\TexturePurchase,
    Entity\User
};
use App\Repository\CartRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Stripe\{Charge, Stripe};
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\{HttpFoundation\JsonResponse, HttpFoundation\Request, HttpFoundation\Response};
use Symfony\Component\Routing\Annotation\Route;

/**
 * @Route("/api/cart")
 */
class CartController extends AbstractController
{
    public function getUserFromToken(Request $request, JWTTokenManagerInterface $tokenManager): User
    {
        $em = $this->getDoctrine();
        $token = preg_split("/ /", $request->headers->get("authorization"))[1];
        $decodedToken = $tokenManager->parse($token);
        $ownerEmail = $decodedToken["username"];
        return $em->getRepository(User::class)->findOneBy(['email' => $ownerEmail]);
    }

    /**
     * @Route("/", methods={"GET"})
     */
    public function index(
        Request $request,
        JWTTokenManagerInterface $tokenManager,
        CartRepository $cartRepository
    ): Response {
        $user = $this->getUserFromToken($request, $tokenManager);
        $cartItems = $cartRepository->findBy(['user' => $user->getId()]);
        $DTOItems = [];
        foreach ($cartItems as $item) {
            $DTOItems[] = new CartDTO(
                $user->getEmail(),
                $item->getName(),
                $item->getType(),
                $item->getPrice(),
                $item->getObjectId(),
                $item->getId()
            );
        }
        return $this->json(['code' => 200, 'message' => $DTOItems]);
    }


    /**
     * @Route("/{id}", methods={"DELETE"})
     */
    public function removeFromCart(
        int $id,
        Request $request,
        CartRepository $cartRepository
    ): JsonResponse {
        $em = $this->getDoctrine();
        $type = $request->query->get("type");

        $cartItem = $cartRepository->findOneBy(['objectId' => $id, 'type' => $type]);
        if ($cartItem == null) {
            return $this->json(['code' => '404', 'message' => 'Object not found!']);
        }
        $manager = $em->getManager();
        $manager->remove($cartItem);
        $manager->flush();
        return $this->json(['code' => 200, 'message' => 'Success!']);
    }

    /**
     * @Route ("/count", methods={"GET"})
     */
    public function countCartItems(
        Request $request,
        JWTTokenManagerInterface $tokenManager,
        CartRepository $cartRepository
    ): JsonResponse {
        $user = $this->getUserFromToken($request, $tokenManager);
        $count = $cartRepository->createQueryBuilder("cart")->select("count(cart.id)")->where(
            "cart.user = :user"
        )->setParameter('user', $user->getId())->getQuery()->getSingleScalarResult();
        return $this->json(['code' => 200, 'message' => $count]);
    }

    /**
     * @Route ("/checkout", methods={"POST"})
     * @throws \Stripe\Exception\ApiErrorException
     */
    public function checkout(
        Request $request,
        CartRepository $cartRepository,
        JWTTokenManagerInterface $tokenManager
    ): JsonResponse {
        $user = $this->getUserFromToken($request, $tokenManager);
        $cartItems = $cartRepository->findBy(['user' => $user->getId()]);
        $manager = $this->getDoctrine()->getManager();
        foreach ($cartItems as $item) {
            if ($item->getType() == "texture") {
                $texture = $this->getDoctrine()->getRepository(Texture::class)->findOneBy(["id" => $item->getObjectId()]
                );
                $texturePurchase = new TexturePurchase($user, $texture);
                $isTexturePurchase = $this->getDoctrine()->getRepository(TexturePurchase::class)->findOneBy(
                    ['texture' => $item->getObjectId(), 'user' => $user->getId()]
                );
                if ($isTexturePurchase == null) {
                    $texture->setPurchaseCount();
                    $manager->persist($texturePurchase);
                }
            } else {
                $model = $this->getDoctrine()->getRepository(Model::class)->findOneBy(["id" => $item->getObjectId()]);
                $purchase = new Purchase($user, $model);
                $modelPurchase = $this->getDoctrine()->getRepository(Purchase::class)->findOneBy(
                    ['model' => $item->getObjectId(), 'user' => $user->getId()]
                );
                if ($modelPurchase == null) {
                    $model->setPurchaseCount();
                    $manager->persist($purchase);
                }
            }
            $manager->remove($item);
        }
        $manager->flush();
        return $this->json(['code' => 200, 'message' => "Success!"]);
    }

    /**
     * @Route("/{id}", methods={"POST"})
     */
    public function addToCart(
        int $id,
        Request $request,
        JWTTokenManagerInterface $tokenManager,
        CartRepository $cartRepository
    ): JsonResponse {
        $em = $this->getDoctrine();
        $user = $this->getUserFromToken($request, $tokenManager);
        $type = $request->query->get("type");

        if ($cartRepository->findBy(['user' => $user->getId(), 'type' => $type, 'objectId' => $id]) != null) {
            return $this->json(['code' => 400, 'message' => 'Item is already in your cart!']);
        }

        $cart = new Cart();

        if ($type == "texture") {
            $texture = $em->getRepository(Texture::class)->find($id);
            $cart->setName($texture->getName())->setObjectId($texture->getId())->setPrice(
                $texture->getPrice()
            )->setUser(
                $user
            );
            $cart->setType("texture");
        } else {
            $model = $em->getRepository(Model::class)->find($id);
            $cart->setName($model->getName())->setObjectId($model->getId())->setPrice($model->getPrice())->setUser(
                $user
            );
            $cart->setType("model");
        }

        $manager = $em->getManager();
        $manager->persist($cart);
        $manager->flush();
        return $this->json(['code' => 200, 'message' => "Added to cart!"]);
    }
}