<?php

namespace App\Command;

use App\Application\Service\PreparerCafeService;
use App\Domain\Entity\CommandeCafe;
use App\Domain\Queue\MessageConsumerInterface;
use App\Domain\Realtime\WorkerStatusUpdatePublisherInterface;
use App\Domain\Repository\CommandeCafeRepositoryInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Throwable;

#[AsCommand(
    name: 'app:coffee-worker',
    description: 'Lance le worker pour consommer les commandes de café depuis RabbitMQ.'
)]
class CoffeeWorkerCommand extends Command
{

    private MessageConsumerInterface $messageConsumer;
    private LoggerInterface $logger;
    private PreparerCafeService $preparerCafeService;
    private WorkerStatusUpdatePublisherInterface $statusPublisher;
    private CommandeCafeRepositoryInterface $commandeRepository;
    private SymfonyStyle $io;

    public function __construct(
        MessageConsumerInterface $messageConsumer,
        LoggerInterface $logger,
        PreparerCafeService $preparerCafeService,
        WorkerStatusUpdatePublisherInterface $statusPublisher,
        CommandeCafeRepositoryInterface $commandeRepository
    ) {
        $this->messageConsumer = $messageConsumer;
        $this->logger = $logger;
        $this->preparerCafeService = $preparerCafeService;
        $this->statusPublisher = $statusPublisher;
        $this->commandeRepository = $commandeRepository;
        parent::__construct();
        $this->log("CoffeeWorkerCommand instancié.");
    }

    protected function configure(): void {}

    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->io = new SymfonyStyle($input, $output);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->log("Début de execute() CoffeeWorkerCommand.");
        $this->statusPublisher->publishWorkerStatus(WorkerStatusUpdatePublisherInterface::STATUS_STARTED);
        $this->log("CoffeeWorker démarré. En attente de messages...");

        if (extension_loaded('pcntl')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, [$this, 'handleSignal']);
            pcntl_signal(SIGINT, [$this, 'handleSignal']);
            $this->log("Gestionnaires de signaux SIGTERM et SIGINT installés.");
        } else {
            $this->log("L'extension PCNTL n'est pas chargée.", \Psr\Log\LogLevel::WARNING);
        }

        $callback = function (AMQPMessage $message) {
            $body = $message->getBody();
            $this->log("Message [{$message->getDeliveryTag()}] reçu. Body: " . substr($body, 0, 100) . "...");
            
            $commandData = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->log("Message non JSON: " . substr($body, 0, 100) . "...", \Psr\Log\LogLevel::ERROR);
                $this->messageConsumer->nack($message->getDeliveryTag(), false);
                return;
            }

            $commandeIdFromMessage = $commandData['id'] ?? null;
            if ($commandeIdFromMessage === null) {
                $this->log("ID de commande manquant dans le message. Contenu: " . $body, \Psr\Log\LogLevel::ERROR);
                $this->messageConsumer->nack($message->getDeliveryTag(), false); // ou ack si l'erreur est non récupérable
                return;
            }
            $commandeIdContext = ['commande_id' => $commandeIdFromMessage];

            // Charger la commande depuis la BDD pour vérifier son statut réel
            $commandeEntity = $this->commandeRepository->findById((int)$commandeIdFromMessage);

            if (!$commandeEntity) {
                $this->log("Commande ID {$commandeIdFromMessage} non trouvée en BDD avant préparation (peut-être déjà supprimée/annulée). Ack.", \Psr\Log\LogLevel::WARNING, $commandeIdContext);
                $this->messageConsumer->ack($message->getDeliveryTag()); // Acquitter car rien à faire
                return;
            }

            // Vérifier si la commande est annulée
            if ($commandeEntity->getStatut() === CommandeCafe::STATUT_ANNULEE) {
                $this->log("Commande ID {$commandeIdFromMessage} est marquée ANNULEE. Préparation ignorée. Ack.", \Psr\Log\LogLevel::INFO, $commandeIdContext);
                $this->messageConsumer->ack($message->getDeliveryTag()); // Acquitter car rien à faire
                return;
            }

            // Vérifier si la commande n'est pas déjà dans un état final ou en cours par un autre worker (si statut EN_COURS est exclusif)
            if (!in_array($commandeEntity->getStatut(), [CommandeCafe::STATUT_EN_ATTENTE])) { // Ajoutez d'autres statuts valides si nécessaire
                $this->log("Commande ID {$commandeIdFromMessage} n'est pas EN_ATTENTE (statut: {$commandeEntity->getStatut()}). Préparation ignorée. Ack.", \Psr\Log\LogLevel::WARNING, $commandeIdContext);
                $this->messageConsumer->ack($message->getDeliveryTag()); // Acquitter
                return;
            }
            
            // Si on arrive ici, on peut tenter la préparation
            $this->statusPublisher->publishWorkerStatus(WorkerStatusUpdatePublisherInterface::STATUS_PROCESSING, $commandeIdContext);

            $type = $commandData['type'] ?? $commandeEntity->getType(); // Utiliser les données du message ou de l'entité
            $size = $commandData['size'] ?? $commandeEntity->getTaille();
            $intensity = $commandData['intensity'] ?? $commandeEntity->getIntensite();

            $this->log("Début préparation pour ID {$commandeIdFromMessage}: Type={$type}, Taille={$size}, Intensité={$intensity}", \Psr\Log\LogLevel::INFO, $commandeIdContext);

            try {
                // On passe l'ID de l'entité chargée, car c'est notre source de vérité maintenant
                $preparedCommande = $this->preparerCafeService->preparer($commandeEntity->getId(), $type, $size, $intensity);
                $this->log("Café préparé: ID={$preparedCommande->getId()}, Statut={$preparedCommande->getStatut()}", \Psr\Log\LogLevel::INFO, $commandeIdContext);
                $this->statusPublisher->publishWorkerStatus(WorkerStatusUpdatePublisherInterface::STATUS_STARTED, $commandeIdContext); // Retour à "en attente de messages"
                $this->messageConsumer->ack($message->getDeliveryTag());
            } catch (\RuntimeException $e) { 
                // Cas où preparerCafeService throw une RuntimeException (ex: commande non trouvée à ce stade, ce qui serait surprenant ici mais possible)
                $this->log("Erreur (RuntimeException) dans preparerCafeService pour ID {$commandeIdFromMessage}: " . $e->getMessage(), \Psr\Log\LogLevel::ERROR, $commandeIdContext);
                $this->statusPublisher->publishWorkerStatus(WorkerStatusUpdatePublisherInterface::STATUS_ERROR, array_merge($commandeIdContext, ['error' => $e->getMessage()]));
                $this->messageConsumer->ack($message->getDeliveryTag()); // Acquitter pour ne pas retenter si l'erreur est "logique" (commande disparue)
            } catch (Throwable $e) {
                $this->log("Erreur (Throwable) lors de la préparation pour ID {$commandeIdFromMessage}: " . $e->getMessage(), \Psr\Log\LogLevel::ERROR, $commandeIdContext);
                $this->statusPublisher->publishWorkerStatus(WorkerStatusUpdatePublisherInterface::STATUS_ERROR, array_merge($commandeIdContext, ['error' => $e->getMessage()]));
                $this->messageConsumer->nack($message->getDeliveryTag(), false); // Nack pour potentiellement retenter si c'est une erreur transitoire
            }
            $this->log("Fin traitement pour ID: {$commandeIdFromMessage}.", \Psr\Log\LogLevel::INFO, $commandeIdContext);
        };

        try {
            $this->log("Appel à messageConsumer->consume()...");
            $this->messageConsumer->consume($callback, 5);
        } catch (Throwable $e) {
            $this->log("Erreur fatale dans le worker: " . $e->getMessage(), \Psr\Log\LogLevel::CRITICAL);
        } finally {
            $this->log("Entrée dans le bloc finally de execute(). Fermeture du consommateur et publication STATUS_STOPPED.");
            $this->messageConsumer->close();
            $this->statusPublisher->publishWorkerStatus(WorkerStatusUpdatePublisherInterface::STATUS_STOPPED);
            $this->log("CoffeeWorkerCommand terminé. Fin de execute().");
            $this->log("CoffeeWorker arrêté.");
        }

        return Command::SUCCESS;
    }

    /**
     * Log a message to both the console output (via SymfonyStyle) and the standard logger.
     *
     * @param string|\Stringable $message The message to log.
     * @param string $level The log level for the logger (defaults to INFO).
     * @param array<mixed> $context Context data for the logger.
     * @param bool $consoleOnly Set to true to only output to console.
     * @param bool $logOnly Set to true to only log to the standard logger.
     */
    public function log(
        string|\Stringable $message, 
        string $level = \Psr\Log\LogLevel::INFO, 
        array $context = [],
        bool $consoleOnly = false, 
        bool $logOnly = false
    ): void
    {
        $prefixedMessage = $message;

        if (!isset($this->io)) {
            if (!$logOnly) {
                $this->logger->warning("SymfonyStyle (io) not initialized in CoffeeWorkerCommand when trying to log to console.");
            }
        }

        if (isset($this->io) && !$logOnly) {
            match ($level) {
                \Psr\Log\LogLevel::ERROR, \Psr\Log\LogLevel::CRITICAL, \Psr\Log\LogLevel::ALERT, \Psr\Log\LogLevel::EMERGENCY => $this->io->error($prefixedMessage),
                \Psr\Log\LogLevel::WARNING => $this->io->warning($prefixedMessage),
                \Psr\Log\LogLevel::NOTICE => $this->io->note($prefixedMessage),
                \Psr\Log\LogLevel::INFO => $this->io->info($prefixedMessage),
                default => $this->io->text($prefixedMessage), // Default for DEBUG, etc.
            };
        }

        if (!$consoleOnly) {
            $this->logger->log($level, (string) $prefixedMessage, $context);
        }
    }

    public function handleSignal(int $signal): void
    {
        $this->log("Signal {$signal} reçu. Demande d'arrêt du consommateur...");
        // S'assurer que messageConsumer est bien initialisé
        if (isset($this->messageConsumer) && method_exists($this->messageConsumer, 'stopConsuming')) {
            $this->messageConsumer->stopConsuming();
        } else {
            $this->log("messageConsumer non disponible ou stopConsuming n'existe pas lors de la gestion du signal.", \Psr\Log\LogLevel::WARNING);
        }
    }
} 