<?php

namespace App\Controller;

use FOS\RestBundle\Controller\Annotations\View;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use Symfony\Component\Routing\Annotation\Route;
use App\Entity\Card;
use App\Entity\User;
use App\Entity\Friendship;
use Psr\Log\LoggerInterface;
use JMS\Serializer\SerializationContext;
use App\Service\HearthstoneApiService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;


/*
* @View(serializerEnableMaxDepthChecks=true)
*/
class FriendshipController extends AbstractController
{
    //Créé une nouvelle amitié entre deux users
    /**
     * @Route("/friendship/new")
     */
    public function addFriendshipAction(Request $request, Container $container)
    {
        $serializer = $container->get('jms_serializer');
        $json = json_decode($request->getContent(), true);
        if (isset($json["user1"]) && isset($json["user2"])) {
            $user1 = $this->getDoctrine()
            ->getRepository(User::class)
            ->find($json["user1"]);

            if(is_string($json["user2"])) {
                $user2 = $this->getDoctrine()
                    ->getRepository(User::class)
                    ->findBy(array('pseudo' => $json["user2"]));
            } else {
                $user2 = $this->getDoctrine()
                    ->getRepository(User::class)
                    ->find($json["user2"]);
            }

            if (isset($user2[0])) {
                if ($user2[0]->getPseudo() != $user1->getPseudo()) {
                    $friendship = new Friendship($user1, $user2[0], $json["isAccepted"], $json["whoDemanding"]);
                } else {
                    return $this->json([
                        'exit_code' => 500,
                        'message' => 'Hello darkness my old friend...',
                        'devMessage' => 'SAME_USER',
                    ]);
                }
                
            } else {
                return $this->json([
                    'exit_code' => 500,
                    'message' => 'Erreur: utilisateur introuvable',
                    'devMessage' => 'CANT_FIND_SECOND_USER',
                ]);
            }

            if ($this->friendshipAlreadyExists($friendship)) {
                return $this->json([
                    'exit_code' => 500,
                    'message' => 'Vous êtez déjà ami',
                    'devMessage' => 'FRIENDSHIP_ALREADY_EXISTS',
                ]);
            }
            
            $em = $this->getDoctrine()->getManager();
            $em->persist($friendship);
            $em->flush();
            
            return $this->json([
                'exit_code' => 200,
                'message' => 'Ami ajouté, en attente de son acceptation',
                'devMessage' => 'SUCCESS',
            ]);
        } else {
            return $this->json([
                'exit_code' => 500,
                'message' => 'Impossible d\'ajouter cet ami: il est peut-être introuvable',
                'devMessage' => 'CANT_FIND_IDS_IN_JSON',
            ]);
        }
    }

    //permet de savoir si l'amitié existe déjà d'uncôté ou de l'autre
    public function friendshipAlreadyExists(Friendship $friendship) {
        $test1Passed = false;
        $test2Passed = false;

        $testFriendship1 = $this->getDoctrine()
            ->getRepository(Friendship::class)
            ->findBy(array('user1' => $friendship->getUser1(), 'user2' => $friendship->getUser2()));

        if ($testFriendship1 == null) {
            $test1Passed = false;
        } else {
            $test1Passed = true;
        }

        $testFriendship2 = $this->getDoctrine()
            ->getRepository(Friendship::class)
            ->findBy(array('user1' => $friendship->getUser2(), 'user2' => $friendship->getUser1()));
        
        if ($testFriendship2 == null) {
            $test2Passed = false;
        } else {
            $test2Passed = true;
        }
        if ($test1Passed) {
            return true;
        } else if ($test2Passed) {
            return true;
        } else {
            return false;
        }
    }

    //Récupérer les amitiés d'un utilisateur
    /**
     * @Route("/friendship/selectByUser/{id}")
     */
    public function getFriendshipAction(Request $request, Container $container, $id)
    {
        $serializer = $container->get('jms_serializer');

        $user = $this->getDoctrine()
            ->getRepository(User::class)
            ->findById($id);
        
        $firstList = $this->getDoctrine()
            ->getRepository(Friendship::class)
            ->findBy(array('user1' => $user));
        
        /*
        for($i=0; $i<count($firstList); $i++) {
            echo $firstList[$i]->getUser1()->getPseudo() ." => ". $firstList[$i]->getUser2()->getPseudo() . " \n , ";
        }
        */

        $secondList = $this->getDoctrine()
            ->getRepository(Friendship::class)
            ->findBy(array('user2' => $user));

        for($i=0; $i<count($secondList); $i++) {
            $secondList[$i] = $this->reverseUsers($secondList[$i]);
        }

        $finalArray = array_merge($firstList, $secondList);

        /*
        for($i=0; $i<count($finalArray); $i++) {
            echo $finalArray[$i]->getUser1()->getPseudo() ." => ". $finalArray[$i]->getUser2()->getPseudo() . " \n , ";
        }
        */

        return $this->json(json_decode($serializer->serialize($finalArray, 'json')));
    }

    //Récupérer toutes les amitiés "en cours" pour un utilisateur
    /**
     * @Route("/friendship/selectByUserPending/{id}")
     */
    public function getPendingFriendshipAction(Request $request, Container $container, $id)
    {
        $serializer = $container->get('jms_serializer');

        $user = $this->getDoctrine()
            ->getRepository(User::class)
            ->findById($id);
        
        $firstList = $this->getDoctrine()
            ->getRepository(Friendship::class)
            ->findBy(array('user1' => $user, 'isAccepted' => false));
        
        /*
        for($i=0; $i<count($firstList); $i++) {
            echo $firstList[$i]->getUser1()->getPseudo() ." => ". $firstList[$i]->getUser2()->getPseudo() . " \n , ";
        }
        */

        $secondList = $this->getDoctrine()
            ->getRepository(Friendship::class)
            ->findBy(array('user2' => $user, 'isAccepted' => false));

        for($i=0; $i<count($secondList); $i++) {
            $secondList[$i] = $this->reverseUsers($secondList[$i]);
        }

        $finalArray = array_merge($firstList, $secondList);

        /*
        for($i=0; $i<count($finalArray); $i++) {
            echo $finalArray[$i]->getUser1()->getPseudo() ." => ". $finalArray[$i]->getUser2()->getPseudo() . " \n , ";
        }
        */

        return $this->json(json_decode($serializer->serialize($finalArray, 'json')));
    }
    
    //permet d'inverser les users afin de toujours être dans la même position pour Android
    public function reverseUsers(Friendship $friendship) {
        $usr1 = $friendship->getUser1();
        $usr2 = $friendship->getUser2();
        $friendship->setUser1($usr2);
        $friendship->setUser2($usr1);
        return $friendship;
    }

    //Supprime une amitié
    /**
     * @Route("/friendship/delete/{id}")
     */
    public function deleteFriendshipAction(Request $request, Container $container, $id)
    {
        $friendship = $this->getDoctrine()
            ->getRepository(Friendship::class)
            ->findById($id);

        if ($friendship[0] != null) {
            $em = $this->getDoctrine()->getManager();
            $em->remove($friendship[0]);
            $em->flush();

            return $this->json([
                'exit_code' => 200,
                'message' => $friendship[0]->getUser2()->getPseudo() . ' a bien été supprimé',
                'devMessage' => 'SUCCESS',
            ]);
        } else {
            return $this->json([
                'exit_code' => 500,
                'message' => 'Erreur: Cet ami n\'existe pas',
                'devMessage' => 'ERROR_FRIEND_NOT_FOUND',
            ]);
        }
    }

    //Accepte une amitié
    /**
     * @Route("/friendship/accept/{id}")
     */
    public function acceptFriendshipAction(Request $request, Container $container, $id)
    {
        $friendship = $this->getDoctrine()
            ->getRepository(Friendship::class)
            ->findById($id);

        if ($friendship[0] != null) {
            $friendship[0]->setIsAccepted(true);
            $em = $this->getDoctrine()->getManager();
            $em->merge($friendship[0]);
            $em->flush();

            return $this->json([
                'exit_code' => 200,
                'message' => 'Ami ajouté !',
                'devMessage' => 'SUCCESS',
            ]);
        } else {
            return $this->json([
                'exit_code' => 500,
                'message' => 'Erreur: Cet ami n\'existe pas',
                'devMessage' => 'ERROR_FRIEND_NOT_FOUND',
            ]);
        }
    }
}