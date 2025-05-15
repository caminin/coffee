<?php

namespace App\Domain\Queue;

use App\Domain\Entity\CommandeCafe;

interface CommandeCafeQueueProducerInterface
{
    public function publish(CommandeCafe $commande): void;
} 