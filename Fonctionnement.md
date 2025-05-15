# Fonctionnement Détaillé de l'Application Machine à Café Connectée
[<- Retour au README Principal](./README.MD) 
## Introduction

Ce document décrit le fonctionnement interne de l'application "Machine à Café Connectée", en détaillant le flux des commandes et les intéractions entre les composants

## 1. Flux Principal d'une Commande de Café

Voici les étapes typiques lorsqu'un utilisateur passe une commande de café :

1.  **Interface Utilisateur (Frontend React)**:
    *   L'utilisateur sélectionne le type de café, l'intensité et la taille via l'interface.

2.  **Soumission de la Commande (Frontend -> API)**:
    *   Au clic sur "Commander", le frontend envoie une mutation GraphQL `commandeCafeCreate` à l'API Symfony. Les données de la commande sont incluses en arguments.

3.  **Traitement par l'API (Symfony Backend)**:
    *   Le résolveur GraphQL (`CommandeCafeMutationResolver`) prend en charge la mutation.
    *   Il valide les données de la commande.
    *   Une nouvelle entité `CommandeCafe` est créée avec le statut initial `EN_ATTENTE`.
    *   Cette entité est persistée en base de données (PostgreSQL) via Doctrine.
    *   Un message contenant les informations essentielles de la commande (ID, type, taille, intensité) est publié dans une file d'attente RabbitMQ (`coffee_orders_queue`).
    *   Simultanément, un événement est publié sur le hub Mercure (via `CommandeUpdatePublisherInterface`) pour notifier en temps réel les clients qu'une nouvelle commande a été créée (`EVENT_COMMANDE_CREEE`).
    *   L'API retourne une confirmation de la création de la commande au frontend via un JSON

4.  **Mise à Jour du Frontend (via Mercure)**:
    *   Le hook `useMercureEvents.js` dans le frontend, abonné au topic Mercure des commandes, reçoit l'événement `commande_creee`.
    *   L'interface est mise à jour dynamiquement pour afficher la nouvelle commande dans la section "Commandes en attente".

5.  **Prise en Charge par le Worker (Processus Continu)**:
    *   Le `CoffeeWorkerCommand.php` (tournant en continu, géré par Supervisor) est un consommateur RabbitMQ (`RabbitMqConsumer.php`). Il écoute la file `coffee_orders_queue`.
    *   Lorsqu'il reçoit un message de commande :
        *   Il décode les données du message.
        *   Il publie un nouvel événement Mercure (`EVENT_COMMANDE_EN_COURS`) pour informer le frontend du changement de statut.
        *   Il invoque le `PreparerCafeService.php` en lui passant les détails de la commande.

6.  **Mise à Jour du Frontend (via Mercure)**:
    *   Le FrontEnd reçoit l'événement `commande_en_cours`.
    *   L'interface déplace la commande de la section "En attente" vers la section "En cours de préparation"

7.  **Simulation de la Préparation (PreparerCafeService)**:
    *   Le `PreparerCafeService.php` simule les différentes étapes de préparation (mouture, infusion, distribution) en utilisant des `usleep()`.
    *   La durée de chaque étape peut dépendre du type de café, de la taille et de l'intensité (voir constantes de temps dans le service).
    *   **Gestion de l'annulation** : Pendant ces étapes, le service vérifie périodiquement (après chaque `usleep`) si un flag d'annulation a été positionné dans Redis (ex: clé `annulation_commande_{id_commande}`).

8.  **Fin de la Préparation (Cas Normal - Non Annulé)**:
    *   Une fois toutes les étapes simulées terminées, le `PreparerCafeService.php` met à jour le statut de la commande en base de données à `TERMINEE`.
    *   Il enregistre la date de fin de préparation.
    *   Il publie un événement Mercure (`EVENT_COMMANDE_TERMINEE`).

9.  **Mise à Jour Finale du Frontend (via Mercure)**:
    *   Le FrontEnd reçoit l'événement `commande_terminee`.
    *   La commande est retirée des "Commandes en cours" et ajoutée à l'historique (`OrderHistoryPanel`).

## 2. Annulation d'une Commande

Deux scénarios principaux pour l'annulation :

### a) Annulation d'une Commande "EN_ATTENTE"

1.  **Frontend**: L'utilisateur clique sur "Annuler" pour une commande qui est encore dans la file d'attente.
2.  **Frontend -> API**: Une mutation GraphQL `annulerCommande` est envoyée
3.  **API**:
    *   Le résolveur vérifie que la commande est bien `EN_ATTENTE`.
    *   Publie un événement Mercure `EVENT_COMMANDE_ANNULEE` (ou `EVENT_COMMANDE_TERMINEE` avec le statut annulé, selon l'implémentation de `handleInterruptionParAnnulation`).
4.  **Frontend (via Mercure)**: La commande est retirée de la liste des commandes en attente et ajoutée à l'historique comme "Annulée".
5.  **Worker**: Si le worker récupère une commande déjà marquée `ANNULEE` de la file, il l'ignore et acquitte le message (comme vu dans `CoffeeWorkerCommand.php`).

### b) Annulation/Arrêt d'une Commande "EN_COURS" (Interruption)

1.  **Frontend**: L'utilisateur clique sur "Arrêter la préparation" pour une commande en cours.
2.  **Frontend -> API**: Une mutation GraphQL (ex: `stopProcessingOrder` ) est envoyée.
3.  **API**:
    *   Le résolveur positionne un flag dans Redis (ex: `annulation_commande_{id_commande} = true`).
4.  **Worker (PreparerCafeService)**:
    *   Le service vérifie le flag Redis entre les étapes de simulation, si c'est le cas il interrompt la préparation.
    *   Il publie l'événement `EVENT_COMMANDE_TERMINEE` (avec la commande ayant le statut `ANNULEE`).
5.  **Frontend (via Mercure)**: La commande est retirée des "Commandes en cours" et ajoutée à l'historique comme "Annulée".

## 3. Gestion du Worker (CoffeeWorkerCommand)

*   **Exécution Continue**: Le worker est une commande Symfony (`app:coffee-worker`) conçue pour tourner en permanence. Elle est gérée par **Supervisor** (configuré dans `API/docker/supervisor/conf.d/`), qui assure son démarrage automatique et son redémarrage en cas d'erreur.
*   **Contrôle via API**:
    *   Le `WorkerManager.php` (`API/src/Infrastructure/Queue/WorkerManager.php`) fournit une interface pour interagir avec `supervisorctl` (start, stop, restart, status).
    *   Ces actions sont exposées via des endpoints REST de l'API (ex: `/api/machine/start`, `/api/machine/stop`), appelés par le frontend.
*   **Statut du Worker**:
    *   Lorsqu'une action est effectuée via le `WorkerManager`, ou lorsque le worker lui-même change d'état (ex: démarre, traite un message, s'arrête), des événements de statut sont publiés sur Mercure via `WorkerStatusUpdatePublisherInterface`.
    *   Le frontend s'abonne à ces événements pour afficher l'état actuel de la machine (En marche, Arrêtée, En traitement, Erreur).

## 4. Mises à Jour en Temps Réel (Mercure)

Mercure est central pour la réactivité du frontend :

*   **Topics**: Des topics spécifiques sont utilisés pour différents types d'événements (ex: un topic général pour les commandes, un autre pour le statut de la machine).
*   **Événements Clés**:
    *   `commande_creee`: Nouvelle commande soumise.
    *   `commande_en_cours`: Le worker a commencé la préparation.
    *   `commande_terminee`: La préparation est finie (avec succès ou suite à une annulation).
    *   `commande_annulee`: Confirmation spécifique d'une annulation (peut se chevaucher ou compléter `commande_terminee`).
    *   `commande_deleted`: (Si implémenté) Une commande est supprimée de la base.
    *   `worker_status_update`: Changement d'état du worker (started, stopped, processing, error).
*   **Frontend**: Le hook `useMercureEvents.js` gère la connexion au hub Mercure et la dispatch des événements aux bons gestionnaires d'état dans `HomePage.jsx`.

---
[<- Retour au README Principal](./README.MD) 