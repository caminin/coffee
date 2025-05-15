# Choix d'Architecture du Projet Machine à Café Connectée
[<- Retour au README Principal](./README.MD) 
## Introduction

Ce document détaille les choix d'architecture effectués pour le développement de l'application "Machine à Café Connectée". 
Il explique les différents choix d'Architecture du projet.

Le projet en lui-même est plus "complexe" que nécessaire dans le sens de beaucoup de processus lancé pour une interface simple, mais cela a été fait pour mettre en avant les différentes solutions technologiques possibles en fonction des soucis rencontrés


## Architecture Backend

### 1. Approche Hexagonale (Ports & Adapters)

Le cœur du backend est structuré selon les principes de l'Architecture Hexagonale. 
Cela permet une séparation entre le domaine métier et les détails d'infrastructure.

*   **Domaine et Ports (`API/src/Domain`)**: Contient la logique métier pure, les entités (ex: `CommandeCafe`). Il est indépendant de tout framework ou technologie d'infrastructure et peut techniquement se connecter à n'importe quel Infrastructure (Changer RabbitMQ par Kafka)
*   **Infrastructure (`API/src/Infrastructure`)**: Fournit les implémentations concrètes des interfaces définies dans le domaine (ex: `RabbitMqConsumer.php`, `Doctrine` repositories, services Mercure). C'est ici que se trouvent les adaptateurs pour les bases de données, les files d'attente, les bus d'événements, etc. Ce sont les choix finaux de technologies concrètes

Ce découplage facilite les tests, la maintenabilité et l'évolution de l'application.
Il apporte malgré tout une certaine complexité dans la gestion du code, car nécessité d'avoir une connaissance approfondie de l'architecture Hexagonale.

### 2. API (GraphQL & REST)

L'API contient deux manières de communiquer, une en GraphQL pour la gestion des entités (Ici les Commandes), et une en REST pour la gestion de la Machine

Les deux méthodes ont leur utilisation spécifique : 

*   **GraphQL (`API/config/graphql/types`)**: Utilisé pour les mutations (création, annulation de commandes de café). Les résolveurs GraphQL (ex: `CommandeCafeMutationResolver`) font le lien avec la partie Service qui est dans la couche Application
*   **REST**: Le pilotage du processus (démarrage, arrêt), correspond bien à des endpoints REST simples. (`/api/machine/{action}`).

Les informations des commandes sont sauvegardées dans la BDD afin d'avoir un historique

### 3. Gestion des Tâches Asynchrones avec RabbitMQ

Pour répondre à l'exigence d'un "système de gestion de file d'attente", j'ai choisi RabbitMQ qui répond parfaitement aux exigences de performances.

*   Les nouvelles commandes de café sont envoyées à une file RabbitMQ.
*   Un processus "worker" (`CoffeeWorkerCommand.php`) consomme les messages de cette file (`RabbitMqConsumer.php`) pour traiter les commandes de manière asynchrone.
*   Il est ainsi possible d'avoir X workers qui travaillent en parallèle sur la file et qui se synchronisent grâce à RabbitMQ

### 4. Mises à Jour en Temps Réel avec Mercure

Afin de respecter la partie temps réel de l'énoncé, j'ai configuré un serveur Mercure.
J'ai préféré Mercure aux websockets pour plusieurs raisons : 
* Meilleures implémentation des sécurité via les JWT et la gestion des topics
* Meilleures gestion des déconnexion via la récupération d'event avec le Last-event-id et reconnexion native
* Gestion des topics simplifie grandement la maintenance
* Scalabilité bien plus simple


Le fonctionnement de la partie temps réel est le suivant : 

*   Le service `PreparerCafeService.php` publie des mises à jour sur l'état des commandes (créée, en cours, terminée, annulée) via des interfaces comme `CommandeUpdatePublisherInterface`.
*   Le statut du worker (démarré, arrêté, en traitement, erreur) est également publié via `WorkerStatusUpdatePublisherInterface`.
*   Le frontend (`FRONT/src/hooks/useMercureEvents.js`) s'abonne à ces mises à jour pour refléter l'état du système.

### 5. Processus Continu (Worker) et sa Gestion (Redis)

Le processus continu pour simuler la préparation du café est implémenté par le `CoffeeWorkerCommand.php`.
Afin d'éviter trop d'appels sur la BDD pour l'annulation d'une commande en cours (Vérif que la commande est annulée), un cache Redis est mis pour indiquer aux workers qu'une commande en cours est annulée.

*   Ce worker est une commande Symfony qui écoute la file RabbitMQ.
*   Pour assurer qu'il tourne en permanence, il est géré par Supervisor dans l'image Docker.
*   Le `WorkerManager.php` (utilisant `supervisorctl`) permet de contrôler ce processus (démarrer, arrêter, redémarrer) via l'API

## Architecture Frontend

Le frontend a été conçu pour être très intéractif et offrir le plus d'information avec une latence minime
Pour cela j'ai choisi ReactJS et Vite, qui est un très bon framework pour des projets d'affichages simple

### 1. Technologie : React et Vite

*   **React**: Bibliothèque JavaScript choisie pour construire une interface utilisateur interactive et modulaire.
*   **Vite**: Outil de build rapide et efficace

### 2. Communication avec le Backend

*   **GraphQL**: Utilisé pour la majorité des interactions initiées par l'utilisateur, comme la création de commandes de café (`FRONT/src/services/graphqlService.js`).
*   **REST**: Utilisé pour les actions de contrôle de la machine (start, stop, restart) via `FRONT/src/services/machineService.js`.
*   **Mercure**: Pour la réception des mises à jour en temps réel sur l'état des commandes et de la machine, géré par le hook `useMercureEvents.js`.

### 3. Interface Utilisateur et Fonctionnalités Clés

Le frontend (`HomePage.jsx`) implémente le "Dashboard interactif" requis :

*   Affichage de l'état de la machine (traduit via `machineStatusTranslations`).
*   Panneaux pour :
    *   L'historique des commandes (`OrderHistoryPanel`).
    *   Les commandes en attente et en cours de traitement (`PendingOrdersPanel`, `MainControlPanel`).
*   Formulaire pour passer de nouvelles commandes (`MainControlPanel`).
*   Actions pour :
    *   Commander un café.
    *   Annuler une commande en attente.
    *   Arrêter/annuler une commande en cours de traitement.
    *   Piloter la machine (démarrer, arrêter, redémarrer).

## Dockerisation

L'ensemble des éléments fonctionnement via une seule commande docker qui lance tous les éléments, fait la migration BDD si nécessaire et lance tous les processus avec un simple `docker compose up --build`

---
[<- Retour au README Principal](./README.MD) 