<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Card;
use App\Entity\User;
use Psr\Log\LoggerInterface;
use App\Service\HearthstoneApiService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class CardController extends AbstractController
{
    //Selection de carte par son ID. Les données sont récupérées depuis l'API Hearthstone originale mais seulement si la carte est dans notre base de données.
    /**
     * @Route("/card/select/{id}", name="card")
     */
    public function getCardAction($id)
    {
        $card = $this->getDoctrine()
            ->getRepository(Card::class)
            ->find($id);
        
        if (!$card) {
            throw $this->createNotFoundException(
                'No card found for id '.$id
            );
        }
        
        $hearthstoneApiService = new HearthstoneApiService();
        $cardJson = $hearthstoneApiService->getCard($card->getHsId());
        $cardJson[0]->id = $id;
        
        return $this->json($cardJson[0]);
    }
    
    /*
    * Retourne une liste de cartes depuis l'API Hearthstone originale
    * 24 cartes = 30 secondes d'appel API ! Il faut changer et enegistrer les cartes sur l'app....
    */

    /** 
     * @Route("/card/select-list")
     */
    public function getCardListAction(Request $request, LoggerInterface $logger) 
    {
        $logger->info('REQUEST JSON: '.$request->request->get("json"));
        $json = json_decode($request->request->get("json"), true);
        $jsonValues = $json["json"];
        $hearthstoneApiService = new HearthstoneApiService();
        
        $imgArray = [];
        $html = "";
        for ($i=0; $i<count($jsonValues); $i++) {
            $hsId = $jsonValues[$i]["hsId"];
            $card = $hearthstoneApiService->getCard($hsId);
            $html = $html . "<img src='".$card[0]->img."'><br>";
        }
        return new Response($html);
    }
    
    //Retourne toutes les cartesde l'utilisateur (peu ou pas utilisé car cartes dans l'user)
    /**
     * @Route("/card/select-by-user/{id}")
     */
    public function getCardsByUser($id, Container $container)
    {
        $serializer = $container->get('jms_serializer');

        $user = $this->getDoctrine()
            ->getRepository(User::class)
            ->find($id);
        
        if (!$user) {
            throw $this->createNotFoundException(
                'No user found for id '.$id
            );
        }
        
        return $this->json(json_decode($serializer->serialize($user->getCards(), 'json')));
    }
    
    //Get un array avec tous les HS ids de cartes en base
    public function getAllCardHsIds() {
        $card = $this->getDoctrine()
        ->getRepository(Card::class)
        ->findAll();

        $cardIds = array();

        for ($i=0; $i < count($card); $i++) {
            array_push($cardIds, $card[$i]->getHsId()); //1 est l'emplacement du HS id
        }

        return $cardIds;
    }

    //Permet d'importer en BDD une carte de l'API Hearthstone originale, en reseignant le "Hearthstone ID" de la carte
    /**
     * @Route("/card/import/{hsId}")
     **/
    public function importCardAction($hsId)
    {
        $em = $this->getDoctrine()->getManager();
        $hearthstoneApiService = new HearthstoneApiService();
        $cardJson = $hearthstoneApiService->getCard($hsId);

        $cardArray = $this->getAllCardHsIds();

        if (in_array($cardJson[0]->cardId, $cardArray)) {
            return $this->json([
                'status' => 'ERROR',
                'message' => 'Erreur lors de l\'enregistrement de la carte '.$cardJson[0]->cardId." - Cette carte existe déjà",
                'devMessage' => 'Already Exist',
            ]);
        } else {
            // $cardJson[0]->img
            $newCard = new Card();
            
            $newCard->setHsId($cardJson[0]->cardId);
            $newCard->setCost(isset($cardJson[0]->cost) ? $cardJson[0]->cost * 15 : 50);
            $newCard->setName(isset($cardJson[0]->name) ? $cardJson[0]->name : "");
            $newCard->setCardSet(isset($cardJson[0]->cardSet) ? $cardJson[0]->cardSet : "");
            $newCard->setType(isset($cardJson[0]->type) ? $cardJson[0]->type : "");
            $newCard->setFaction(isset($cardJson[0]->faction) ? $cardJson[0]->faction : "");
            $newCard->setRarity(isset($cardJson[0]->rarity) ? $cardJson[0]->rarity : "");
            $newCard->setText(isset($cardJson[0]->text) ? $cardJson[0]->text : "");
            $newCard->setFlavor(isset($cardJson[0]->flavor) ? $cardJson[0]->flavor : "");
            $newCard->setImg(isset($cardJson[0]->img) ? $cardJson[0]->img : "");
            $newCard->setImgGold(isset($cardJson[0]->imgGold) ? $cardJson[0]->imgGold : "");
            
            $em->persist($newCard);
            
            try {
                // actually executes the queries (i.e. the INSERT query)
                $em->flush();
                
                return $this->json([
                    'status' => 'SUCCESS',
                    'message' => 'Carte '.$newCard->getHsId().' enregistrée',
                    'devMessage' => "Success : nothing to show here",
                ]);
            } catch (Exception $e) {
                return $this->json([
                    'status' => 'ERROR',
                    'message' => 'Erreur lors de l\'enregistrement de la carte '.$newCard->getHsId(),
                    'devMessage' => $e->getMessage(),
                ]);
            }
        }


        
    }
    
    //permet de créer une nouvelle carte en bdd (peu utilisé on utilise plutôt l'import directement)
    /**
     * @Route("/card/new")
     */
    public function newCardAction(Request $request)
    {
        // you can fetch the EntityManager via $this->getDoctrine()
        // or you can add an argument to your action: index(EntityManagerInterface $entityManager)
        $entityManager = $this->getDoctrine()->getManager();
        $card = new Card();
        $card->setHsId($request->request->get('hsId'));
        $card->setCost($request->request->get('cost'));

        // tell Doctrine you want to (eventually) save the User (no queries yet)
        $entityManager->persist($card);

        // actually executes the queries (i.e. the INSERT query)
        $entityManager->flush();

        return $this->json([
            'message' => 'Successfully saved card',
            'id' => $card->getId(),
            'devMessage' => "Success : nothing to show here",
        ]);
    }
}
