<?php

namespace App\Infrastructure\Queue;

use App\Domain\Queue\MessageConsumerInterface;
use App\Domain\Realtime\WorkerStatusUpdatePublisherInterface;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Exception\AMQPTimeoutException;
use PhpAmqpLib\Channel\AMQPChannel;
use Psr\Log\LoggerInterface;
use Throwable;

class RabbitMqConsumer implements MessageConsumerInterface
{
    private AMQPStreamConnection $connection;
    private AMQPChannel $channel;
    private string $queueName;
    private LoggerInterface $logger;
    private bool $consuming = false;
    private string $consumerTag = '';

    public function __construct(
        string $host,
        int $port,
        string $user,
        string $password,
        string $queueName,
        LoggerInterface $logger,
        string $vhost = '/'
    ) {
        $this->queueName = $queueName;
        $this->logger = $logger;
        $this->consumerTag = 'consumer-' . substr(md5(uniqid((string)mt_rand(), true)), 0, 8);
        $this->logger->info("[{$this->consumerTag}] Initialisation RabbitMqConsumer pour la file '{$this->queueName}'.");
        try {
            $this->connection = new AMQPStreamConnection($host, $port, $user, $password, $vhost);
            $this->channel = $this->connection->channel();
            $this->channel->queue_declare($this->queueName, false, true, false, false);
            $this->logger->info("[{$this->consumerTag}] Connecté à RabbitMQ, canal ouvert et file '{$this->queueName}' déclarée.");
        } catch (Throwable $e) {
            $this->logger->error("[{$this->consumerTag}] ERREUR CRITIQUE lors de l'initialisation RabbitMqConsumer: " . $e->getMessage(), [
                'exception_class' => get_class($e),
                'trace_snippet' => substr($e->getTraceAsString(), 0, 1000)
            ]);
            throw $e;
        }
    }

    public function consume(callable $callback, int $timeout = 0): void
    {
        if (!isset($this->channel) || !$this->channel->is_open() || !isset($this->connection) || !$this->connection->isConnected()) {
            $this->logger->error("[{$this->consumerTag}] Tentative de consommation alors que la connexion/canal n'est pas (ou plus) active.");
            return;
        }

        $this->consuming = true;
        $this->logger->info("[{$this->consumerTag}] Début de la consommation sur '{$this->queueName}'. $this->consuming mis à true.");

        try {
            $this->channel->basic_qos(null, 1, null);
            $this->channel->basic_consume(
                $this->queueName,
                $this->consumerTag,
                false,
                false,
                false,
                false,
                function (AMQPMessage $message) use ($callback) {
                    $this->logger->debug("[{$this->consumerTag}] Message [{$message->getDeliveryTag()}] reçu par callback interne de basic_consume.");
                    try {
                        call_user_func($callback, $message);
                    } catch (Throwable $e) {
                        $this->logger->error("[{$this->consumerTag}] Erreur dans callback externe pour message [{$message->getDeliveryTag()}]: " . $e->getMessage(), [
                            'exception_class' => get_class($e),
                            'message_body_snippet' => substr($message->getBody(), 0, 100),
                            'trace_snippet' => substr($e->getTraceAsString(), 0, 500)
                        ]);
                    }
                }
            );
        } catch (Throwable $e) {
            $this->logger->error("[{$this->consumerTag}] Erreur lors de la configuration de basic_qos ou basic_consume: " . $e->getMessage(), [
                'exception_class' => get_class($e),
                'trace_snippet' => substr($e->getTraceAsString(), 0, 500)
            ]);
            $this->consuming = false;
        }

        if ($this->consuming) {
            $this->logger->info("[{$this->consumerTag}] basic_consume configuré. Entrée dans la boucle d'attente (channel->wait).");
            while ($this->consuming && isset($this->channel) && $this->channel->is_open() && $this->channel->is_consuming() && isset($this->connection) && $this->connection->isConnected()) {
                try {
                    $this->logger->debug("[{$this->consumerTag}] Boucle: attente msg (timeout {$timeout}s). Status: consuming=" . ($this->consuming ? 'vrai' : 'faux') . ", channelOpen=" . ($this->channel->is_open() ? 'vrai' : 'faux') . ", channelConsuming=" . ($this->channel->is_consuming() ? 'vrai' : 'faux') . ", connectionUp=" . ($this->connection->isConnected() ? 'vrai' : 'faux') . ".");
                    $this->channel->wait(null, false, $timeout);
                    $this->logger->debug("[{$this->consumerTag}] channel->wait() retourné (message traité ou autre activité). $this->consuming=" . ($this->consuming ? 'true' : 'false') . ".");
                } catch (AMQPTimeoutException $e) {
                    $this->logger->debug("[{$this->consumerTag}] AMQPTimeoutException (timeout: {$timeout}s) dans la boucle de wait. $this->consuming=" . ($this->consuming ? 'true' : 'false') . ". La boucle continue si $this->consuming est true.");
                } catch (Throwable $e) {
                    $this->logger->error("[{$this->consumerTag}] Erreur Throwable dans boucle de wait: " . $e->getMessage(), [
                        'exception_class' => get_class($e),
                        'trace_snippet' => substr($e->getTraceAsString(), 0, 500)
                    ]);
                    $this->consuming = false;
                    break;
                }
            }
        }

        $statusChannelOpen = isset($this->channel) && $this->channel->is_open();
        $statusChannelConsuming = $statusChannelOpen && $this->channel->is_consuming();
        $statusConnectionConnected = isset($this->connection) && $this->connection->isConnected();

        $this->logger->info("[{$this->consumerTag}] Sortie de la boucle de consommation. Status: consuming=" . ($this->consuming ? 'vrai' : 'faux') . ", channelOpen=" . ($statusChannelOpen ? 'vrai' : 'faux') . ", channelConsuming=" . ($statusChannelConsuming ? 'vrai' : 'faux') . ", connectionUp=" . ($statusConnectionConnected ? 'vrai' : 'faux') . ".");
        $this->logger->info("[{$this->consumerTag}] Fin de la méthode consume().");
    }

    public function ack(mixed $messageDeliveryTag): void
    {
        if (!isset($this->channel) || !$this->channel->is_open()) {
            $this->logger->warning("[{$this->consumerTag}] Tentative d'ack sur un canal non disponible ou fermé. DeliveryTag: {$messageDeliveryTag}");
            return;
        }
        try {
            $this->channel->basic_ack($messageDeliveryTag);
            $this->logger->debug("[{$this->consumerTag}] Message [{$messageDeliveryTag}] acquitté.");
        } catch (Throwable $e) {
            $this->logger->error("[{$this->consumerTag}] Erreur lors de l'acquittement du message [{$messageDeliveryTag}]: " . $e->getMessage(), [
                'exception_class' => get_class($e)
            ]);
        }
    }

    public function nack(mixed $messageDeliveryTag, bool $requeue = false): void
    {
        if (!isset($this->channel) || !$this->channel->is_open()) {
            $this->logger->warning("[{$this->consumerTag}] Tentative de nack sur un canal non disponible ou fermé. DeliveryTag: {$messageDeliveryTag}");
            return;
        }
        try {
            $this->channel->basic_nack($messageDeliveryTag, false, $requeue);
            $this->logger->debug("[{$this->consumerTag}] Message [{$messageDeliveryTag}] nack. Requeue: " . ($requeue ? 'true' : 'false') . ".");
        } catch (Throwable $e) {
            $this->logger->error("[{$this->consumerTag}] Erreur lors du nack du message [{$messageDeliveryTag}]: " . $e->getMessage(), [
                'exception_class' => get_class($e)
            ]);
        }
    }

    public function stopConsuming(): void
    {
        $this->logger->info("[{$this->consumerTag}] Appel de stopConsuming(). Passage de $this->consuming à false.");
        $this->consuming = false;
    }

    public function close(): void
    {
        $this->logger->info("[{$this->consumerTag}] Appel de close(). État actuel: $this->consuming=" . ($this->consuming ? 'true' : 'false') . ".");
        $this->consuming = false;

        try {
            if (isset($this->channel) && $this->channel->is_open()) {
                if (!empty($this->consumerTag) && $this->channel->is_consuming()) {
                    $this->logger->info("[{$this->consumerTag}] Annulation de basic_cancel ({$this->consumerTag}) sur le canal avant fermeture.");
                    $this->channel->basic_cancel($this->consumerTag, false, true);
                }
                $this->logger->info("[{$this->consumerTag}] Fermeture du canal.");
                $this->channel->close();
            }
        } catch (Throwable $e) {
            $this->logger->error("[{$this->consumerTag}] Erreur lors de l'annulation ou de la fermeture du canal: " . $e->getMessage(), [
                'exception_class' => get_class($e),
                'trace_snippet' => substr($e->getTraceAsString(), 0, 200)
            ]);
        }

        try {
            if (isset($this->connection) && $this->connection->isConnected()) {
                $this->logger->info("[{$this->consumerTag}] Fermeture de la connexion.");
                $this->connection->close();
            }
        } catch (Throwable $e) {
            $this->logger->error("[{$this->consumerTag}] Erreur lors de la fermeture de la connexion: " . $e->getMessage(), [
                'exception_class' => get_class($e),
                'trace_snippet' => substr($e->getTraceAsString(), 0, 200)
            ]);
        }
        $this->logger->info("[{$this->consumerTag}] Tentatives de fermeture du canal et de la connexion effectuées.");
    }

    public function __destruct()
    {
        $this->logger->info("[{$this->consumerTag}] Destructeur de RabbitMqConsumer appelé. Tentative de fermeture propre.");
        $this->close();
    }
} 