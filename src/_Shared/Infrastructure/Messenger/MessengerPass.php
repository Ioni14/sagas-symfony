<?php

namespace Shared\Infrastructure\Messenger;

use Shared\Application\Saga;
use Shared\Application\SagaManager;
use Shipping\Application\SagaInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class MessengerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        /*
         * We don't want SagaManager handling all messages
         * Here we aggregate all messages types handled by all Saga handlers to specify only them to SagaManager
         */
        $defs = $container->findTaggedServiceIds('saga_handler');

        $handledMessages = [];
        foreach ($defs as $serviceId => $tags) {
            $def = $container->getDefinition($serviceId);
            /** @var class-string<Saga> $class */
            $class = $def->getClass();
            if (!is_a($class, SagaInterface::class, true)) {
                throw new \InvalidArgumentException($class . ' should implement '.SagaInterface::class . ' because it has been tagged "saga_handler".');
            }
            $handledMessages = array_merge($handledMessages, $class::getHandledMessages());
        }

        $def = new Definition(SagaManager::class);
        foreach ($handledMessages as $handledMessage) {
            $def->addTag('messenger.message_handler', ['handles' => $handledMessage]);
        }
        $container->setDefinition(SagaManager::class, $def);
    }
}
