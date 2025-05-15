# Pistes d'Amélioration du Projet Machine à Café Connectée
[<- Retour au README Principal](./README.MD) 

Ce document liste des pistes d'amélioration potentielles pour aller plus loin avec l'application "Machine à Café Connectée"

## 1. Gestion de plusieurs machines et plusieurs clients

Ici le système reçoit des informations de commandes effectuées ou annulées, mais il manque encore quelques éléments de communication pour vraiment pouvoir mettre plusieurs workers en même temps (ex : interface n'a qu'un seul slot pour la commande en cours).

Il serait plus logique d'avoir un historique, et une gestion des commandes par machine et donc avoir des topics séparés en fonction des machines.

## 2. Tests Approfondis

Bien que fait en archi héxagonale (et donc facilement testable via les Mocks), le projet ne contient pas de tests.

*   **Tests Unitaires**: Tester les parties services (par ex: que les temps du worker soient logiques)
*   **Tests End-to-End (E2E)**: Utiliser des outils comme Cypress ou Selenium

## 3. Sécurité Renforcée

Actuellement, les aspects de sécurité sont basiques. Pour une application en production, il faudrait :

*   **Authentification/Autorisation**: Mettre en place un système d'authentification via des JWT, mieux sécuriser et configurer les serveur de messages (Mercure) pour éviter que l'extérieur puisse publish par exemple.
*   **Validation des Entrées**: Renforcer la validation des données reçues par l'API (payloads GraphQL, paramètres de requêtes REST) pour prévenir les injections et les erreurs.
*   **Gestion des Secrets**: S'assurer que les clés d'API, mots de passe, etc., sont gérés de manière sécurisée (variables d'environnement, solutions de gestion de secrets).

## 4. Observabilité et Monitoring

Pour mieux comprendre le comportement de l'application et diagnostiquer les problèmes :

*   **Logging Amélioré**: Les logs sont actuellements dans le /var/log et le /srv/app/var/log. Il faudrait les réunir dans un service de Log spécialisé comme Loki
*   **Monitoring**: Mettre en place Prometheus pour permettre de monitorer les services présents.

## 5. Documentation API Complète

J'ai documenté le projets dans des .MD comme `ChoixArchi.md` mais les projets devraient avoir leur documentation spécifique pour permettre une meilleure tracabilité des évolutions et des choix techno.

Pour cela on peut utiliser des outils tel que Swagger ou Graphiql

## 6. Framework Front

Le projet Front est actuellement en ReactJS. Pour moi un bon projet ReactJS demande : 
* Soit d'être configuré et structurer avant, avec une idée de maintenabilité optimale
* Soit d'utiliser **NextJS**

En effet ReactJS est aussi puissant qu'il est permissif, et sans NextJS un projet peut très rapidement devenir difficile maintenable.

## 7. Interface et objectifs UX/UI

L'interface ici est développée dans deux objectifs : 
*   **Efficace** Etre simple d'accès pour une personne voulant tester rapidement
*   **Démonstrative** Permettre de mettre en avant facilement toutes les fonctionnalités techniques de l'application

L'objectif final est donc différent d'un produit "fini" qui aurait comme objectif une maximisation de l'expérience utilisateur, même si cela demandait de cacher certaines informations pour simplifier l'interface (utilité de voir l'historique par exemple)

On a ici plus affaire à un dashboard de contrôle qu'une interface utilisateur

## 8. Fonctionnalités Supplémentaires (Bonus)

L'énoncé mentionnait des "bonus". Voici quelques idées :

*   **Gestion Utilisateurs**: Profils utilisateurs, préférences de café, historique personnel
*   **Personnalisation Avancée des Commandes**: Plus d'options (Sucre et quantité, )
*   **Statistiques et Rapports**: Statistiques sur les cafés commandés (commandes les plus courantes, heure de pointe). Par exemple pour anticiper des livraisons de café. 
*   **Notifications Utilisateur**: Notifications push (via Mercure ou autre) lorsque le café d'un utilisateur spécifique est prêt.
*   **Internationalisation (i18n)**: Traduire l'interface frontend en plusieurs langues.

## 9. Optimisations et Scalabilité

*   **Optimisation des Requêtes BDD**: Pour l'instant la simplicité des Entités ne le nécessite pas, mais l'intégration d'Index
*   **Mise en Cache Stratégique**: Utiliser Redis ou Varnish pour mettre en cache les réponses d'API fréquemment consultées et peu changeantes comme l'historique.
*   **Scalabilité des Workers**: Configurer Supervisor pour lancer plusieurs instances du `CoffeeWorkerCommand` si la charge de commandes augmente significativement.

## 10. Qualité de Code et Outillage Développeur

*   **Linters et Formatteurs**: Intégrer de Prettiers et Linters de manière plus stricte et automatisée (ex: hooks pre-commit).
*   **Analyse Statique**: Utiliser des outils comme PHPStan et Psalm pour détecter les erreurs potentielles dans le code PHP.

---
[<- Retour au README Principal](./README.MD) 